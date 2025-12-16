# Content Request Inbox - Dokumentasi

## Overview

Content Request Inbox adalah sistem untuk menerima dan mengelola permintaan pembuatan konten dari civitas JGU ke tim MSC (Media & Strategic Communications).

### Fitur Utama
- ✅ Login Google OAuth (hanya @jgu.ac.id dan @student.jgu.ac.id)
- ✅ Form request konten publik
- ✅ Cek status request
- ✅ 2-stage approval (Staff MSC → Head MSC)
- ✅ Auto-create Asset di Asset Vault saat publish
- ✅ Comment thread untuk komunikasi

---

## Setup Google OAuth

### 1. Buat Project di Google Cloud Console

1. Buka https://console.cloud.google.com
2. Buat project baru atau pilih existing
3. Enable **Google+ API** dan **Google OAuth 2.0**

### 2. Buat OAuth Credentials

1. Go to **APIs & Services** → **Credentials**
2. Click **Create Credentials** → **OAuth client ID**
3. Application type: **Web application**
4. Name: MSC Hub
5. Authorized redirect URIs:
   - Development: `http://localhost:8000/auth/google/callback`
   - Production: `https://your-domain.com/auth/google/callback`
6. Copy **Client ID** dan **Client Secret**

### 3. Update .env

```env
GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-client-secret
GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"
```

### 4. (Optional) Restrict ke Domain JGU

Di Google Cloud Console:
1. Go to **OAuth consent screen**
2. User type: **Internal** (jika menggunakan Google Workspace JGU)
3. Atau biarkan External dan validasi di code (sudah dihandle)

---

## Database Structure

### Tabel: content_request_sequences
Untuk generate request code yang aman dari race condition.

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| id | bigint | Primary key |
| year | year | Tahun |
| last_number | int | Nomor terakhir |

### Tabel: content_requests

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| id | bigint | Primary key |
| request_code | string(20) | Unique, format: CR-YYYY-NNNN |
| requester_name | string | Nama pemohon |
| requester_email | string | Email pemohon |
| requester_google_id | string | Google ID (nullable) |
| requester_type | enum | student, lecturer, staff, other |
| unit | string | Fakultas/Unit (nullable) |
| phone | string | Telepon (nullable) |
| content_type | enum | Jenis konten |
| platform_target | string | Platform target (nullable) |
| purpose | text | Tujuan (nullable) |
| audience | text | Target audience (nullable) |
| event_date | date | Tanggal event (nullable) |
| location | string | Lokasi (nullable) |
| deadline | date | Deadline (nullable) |
| materials_link | text | Link materi (nullable) |
| notes | longtext | Catatan (nullable) |
| status | enum | Status workflow |
| assigned_to_user_id | FK users | PIC (nullable) |
| staff_approved_at | timestamp | Waktu approve staff |
| staff_approved_by | FK users | Staff yang approve |
| head_approved_at | timestamp | Waktu approve head |
| head_approved_by | FK users | Head yang approve |
| reject_reason | text | Alasan tolak (nullable) |
| rejected_at | timestamp | Waktu ditolak |
| rejected_by | FK users | User yang menolak |
| published_link | text | Link hasil publikasi |
| source_link | text | Link source file |
| published_at | timestamp | Waktu publish |
| archived_at | timestamp | Waktu diarsipkan |
| linked_project_id | FK projects | Project terkait |
| created_asset_id | FK assets | Asset yang dibuat |

### Tabel: content_request_comments

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| id | bigint | Primary key |
| content_request_id | FK | Relasi ke request |
| author_type | enum | requester, staff, head |
| author_name | string | Nama author |
| author_email | string | Email author |
| user_id | FK users | User ID (untuk staff/head) |
| message | longtext | Isi komentar |

---

## Status Workflow

```
┌──────────────┐
│   INCOMING   │  Request baru masuk
└──────┬───────┘
       │ Assign PIC
       ▼
┌──────────────┐
│   ASSIGNED   │  Sudah ada PIC
└──────┬───────┘
       │ Mulai Kerjakan
       ▼
┌──────────────┐     ┌──────────────┐
│ IN_PROGRESS  │◄────│NEED_REVISION │
└──────┬───────┘     └──────────────┘
       │                    ▲
       │ Staff Approve      │ Revisi
       ▼                    │
┌──────────────────────┐    │
│WAITING_HEAD_APPROVAL │────┘
└──────────┬───────────┘
           │ Head Approve
           ▼
    ┌──────────────┐
    │   APPROVED   │
    └──────┬───────┘
           │ Publish
           ▼
    ┌──────────────┐
    │  PUBLISHED   │ → Auto-create Asset Vault
    └──────┬───────┘
           │ Archive
           ▼
    ┌──────────────┐
    │   ARCHIVED   │
    └──────────────┘

    ┌──────────────┐
    │   REJECTED   │  Bisa dari status manapun
    └──────────────┘
```

---

## URL Endpoints

### Public (Requester)

| URL | Method | Keterangan |
|-----|--------|------------|
| `/request/content` | GET | Form request konten |
| `/request/content` | POST | Submit request |
| `/request/success` | GET | Halaman sukses |
| `/request/status` | GET | Form cek status |
| `/request/status` | POST | Cek status |
| `/request/status/{code}` | GET | Detail status |
| `/request/status/{id}/comment` | POST | Tambah komentar |

### Google OAuth

| URL | Method | Keterangan |
|-----|--------|------------|
| `/auth/google/redirect` | GET | Redirect ke Google |
| `/auth/google/callback` | GET | Callback dari Google |
| `/auth/google/logout` | POST | Logout requester |

### Admin (Filament)

| URL | Keterangan |
|-----|------------|
| `/admin/content-requests` | List semua request |
| `/admin/content-requests/create` | Buat request manual |
| `/admin/content-requests/{id}` | View detail |
| `/admin/content-requests/{id}/edit` | Edit request |

---

## Enums

### RequestStatus
```
incoming              → Baru masuk
assigned              → Sudah ada PIC
in_progress           → Sedang dikerjakan
need_revision         → Perlu revisi
waiting_head_approval → Menunggu Head MSC
approved              → Disetujui
rejected              → Ditolak
published             → Dipublikasi
archived              → Diarsipkan
```

### ContentType
```
photo_documentation   → Dokumentasi Foto
video_documentation   → Dokumentasi Video
design_poster         → Desain Poster
design_banner         → Desain Banner
design_flyer          → Desain Flyer
social_media_post     → Post Social Media
video_profile         → Video Profil
video_teaser          → Video Teaser/Promo
live_streaming        → Live Streaming
website_news          → Berita Website
other                 → Lainnya
```

### RequesterType
```
student   → Mahasiswa
lecturer  → Dosen
staff     → Staff
other     → Lainnya
```

---

## Filament Actions

### Table Actions

| Action | Role | Kondisi |
|--------|------|---------|
| Assign PIC | all | status = incoming |
| Mulai Kerjakan | all | status = assigned/need_revision |
| Minta Revisi | all | status = in_progress |
| Approve (Staff) | all | status = in_progress |
| Approve (Head) | admin, head_msc | status = waiting_head_approval |
| Tolak | all | status bukan published/archived |
| Publish | all | status = approved |
| Arsipkan | all | status = published |
| Tambah Komentar | all | selalu |
| Delete | admin | selalu |

---

## Integrasi Asset Vault

Saat request di-publish, sistem otomatis:

1. Membuat record di tabel `assets`
2. Mapping:
   - `title` = "{ContentType} - {Requester} ({Date})"
   - `asset_type` = mapping dari content_type
   - `platform` = mapping dari platform_target
   - `source_link` = dari form publish
   - `output_link` = published_link
   - `happened_at` = event_date atau today
   - `status` = published
   - `project_id` = linked_project_id (jika dipilih)
   - `pic_user_id` = assigned_to_user_id
   - `created_by` = current user
3. Menambahkan tags:
   - "Content Request"
   - Platform tag (jika ada)
4. Menyimpan `created_asset_id` di content_request

---

## Manual Testing Checklist

### Public Side
- [ ] Buka `/request/content` - harus redirect ke login Google
- [ ] Login dengan email JGU - berhasil
- [ ] Login dengan email non-JGU - ditolak
- [ ] Isi form dan submit - dapat kode request
- [ ] Buka `/request/status` - masukkan kode
- [ ] Lihat detail status - tampil dengan benar
- [ ] Tambah komentar sebagai requester

### Admin Side
- [ ] List content requests dengan badge count
- [ ] Filter by status, content_type, PIC
- [ ] Assign PIC ke request incoming
- [ ] Mulai kerjakan (status → in_progress)
- [ ] Request revisi dengan catatan
- [ ] Staff approve (status → waiting_head_approval)
- [ ] Head approve (status → approved)
- [ ] Publish dengan link - asset ter-create di Vault
- [ ] Arsipkan request yang sudah publish
- [ ] Tolak request dengan alasan

### Edge Cases
- [ ] Request code generator under concurrency
- [ ] Email domain validation
- [ ] Deadline validation (tidak boleh masa lalu)
- [ ] Link URL validation

---

## Common Errors & Fixes (Windows/Laragon)

### 1. Google OAuth Error: redirect_uri_mismatch

**Penyebab:** URI di Google Console tidak match dengan .env

**Solusi:**
```env
# Pastikan APP_URL benar
APP_URL=http://localhost:8000

# Atau untuk Laragon dengan virtual host
APP_URL=http://msc-hub.test

# Update GOOGLE_REDIRECT_URI
GOOGLE_REDIRECT_URI=http://localhost:8000/auth/google/callback
```

### 2. Session not persisting after Google callback

**Penyebab:** Session domain mismatch

**Solusi:**
```env
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=false  # untuk development
```

### 3. CSRF token mismatch

**Penyebab:** Session expired

**Solusi:**
```bash
php artisan config:clear
php artisan cache:clear
```

### 4. Class not found setelah edit

**Solusi:**
```bash
composer dump-autoload
php artisan optimize:clear
```

---

## File Structure

```
app/
├── Enums/
│   ├── CommentAuthorType.php
│   ├── ContentType.php
│   ├── RequestStatus.php
│   └── RequesterType.php
├── Http/Controllers/
│   ├── Auth/
│   │   └── GoogleAuthController.php
│   └── ContentRequestController.php
├── Models/
│   ├── ContentRequest.php
│   └── ContentRequestComment.php
├── Policies/
│   └── ContentRequestPolicy.php
└── Filament/Resources/
    └── ContentRequestResource.php
        └── Pages/
            ├── ListContentRequests.php
            ├── CreateContentRequest.php
            ├── EditContentRequest.php
            └── ViewContentRequest.php

config/
└── services.php (Google OAuth config)

database/migrations/
├── 2024_01_02_000001_create_content_request_sequences_table.php
├── 2024_01_02_000002_create_content_requests_table.php
└── 2024_01_02_000003_create_content_request_comments_table.php

resources/views/
├── layouts/
│   └── public.blade.php
└── content-request/
    ├── form.blade.php
    ├── success.blade.php
    ├── status.blade.php
    └── status-detail.blade.php

routes/
└── web.php
```

---

## Future Improvements

1. **Campus SSO Integration** - Mengganti Google OAuth dengan SSO kampus
2. **Email Notifications** - Notifikasi email saat status berubah
3. **File Upload** - Upload materi langsung (bukan link)
4. **Dashboard Analytics** - Statistik request per periode
5. **SLA Tracking** - Tracking waktu penyelesaian
6. **API Endpoints** - REST API untuk integrasi mobile app
