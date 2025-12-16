# Asset Vault - Modul Arsip Digital MSC JGU

## Daftar Isi
- [Overview](#overview)
- [Instalasi & Setup](#instalasi--setup)
- [Struktur Database](#struktur-database)
- [Fitur Utama](#fitur-utama)
- [Panduan Penggunaan](#panduan-penggunaan)
- [Roles & Permissions](#roles--permissions)
- [API & Enums](#api--enums)
- [File Structure](#file-structure)

---

## Overview

**Asset Vault** adalah sistem arsip digital untuk menyimpan metadata dan link dari semua karya MSC (Media & Strategic Communications) JGU. Sistem ini membantu tim untuk:

- ✅ Menyimpan referensi foto dokumentasi, video, desain, banner, dan konten lainnya
- ✅ Mengelompokkan aset berdasarkan project/event
- ✅ Mencari aset dengan cepat menggunakan filter dan tags
- ✅ Melacak status konten (draft, final, published)
- ✅ Menyimpan link source (file kerja) dan output (hasil publikasi)

### Konsep Utama

Asset Vault **tidak menyimpan file langsung**, melainkan menyimpan:
- **Metadata**: judul, tipe, platform, tanggal, status, PIC
- **Link Source**: URL ke file kerja di Google Drive, Figma, Canva, dll
- **Link Output**: URL ke hasil publikasi di Instagram, YouTube, Website, dll

---

## Instalasi & Setup

### Prerequisites
- PHP 8.2+
- Laravel 12
- Filament v4
- MySQL/PostgreSQL

### Langkah Instalasi

```bash
# 1. Install package yang dibutuhkan (sudah terinstall)
composer require spatie/laravel-permission

# 2. Jalankan migrations
php artisan migrate

# 3. Seed roles dan permissions
php artisan db:seed --class=RoleSeeder

# 4. Seed user default
php artisan db:seed --class=UserSeeder

# 5. Seed tags default
php artisan db:seed --class=TagSeeder

# 6. (Optional) Seed data dummy untuk testing
php artisan db:seed --class=ProjectSeeder
php artisan db:seed --class=AssetSeeder

# 7. Clear cache
php artisan optimize:clear
```

### User Default

| Email | Password | Role |
|-------|----------|------|
| admin@msc.jgu.ac.id | password | admin |
| head@msc.jgu.ac.id | password | head_msc |
| staff@msc.jgu.ac.id | password | staff_msc |

---

## Struktur Database

### Entity Relationship

```
┌─────────────┐       ┌─────────────┐       ┌─────────────┐
│   projects  │───────│   assets    │───────│    tags     │
└─────────────┘  1:N  └─────────────┘  N:M  └─────────────┘
       │                     │                     │
       └─────────────────────┴─────────────────────┘
                             │
                      ┌──────┴──────┐
                      │  taggables  │ (pivot)
                      └─────────────┘
```

### Tabel: projects

| Kolom | Tipe | Nullable | Keterangan |
|-------|------|----------|------------|
| id | bigint | No | Primary key |
| title | varchar(255) | No | Judul project |
| description | text | Yes | Deskripsi |
| unit | varchar(255) | Yes | Fakultas/Prodi/Unit |
| event_date | date | Yes | Tanggal event |
| location | varchar(255) | Yes | Lokasi event |
| status | enum | No | `active`, `archived` |
| created_by | bigint FK | Yes | User pembuat |
| created_at | timestamp | No | |
| updated_at | timestamp | No | |
| deleted_at | timestamp | Yes | Soft delete |

### Tabel: assets

| Kolom | Tipe | Nullable | Keterangan |
|-------|------|----------|------------|
| id | bigint | No | Primary key |
| project_id | bigint FK | Yes | Relasi ke project |
| title | varchar(255) | No | Judul aset |
| asset_type | enum | No | Tipe aset |
| platform | enum | Yes | Platform asal/tujuan |
| source_link | varchar(2048) | Yes | Link file kerja |
| output_link | varchar(2048) | Yes | Link hasil publikasi |
| happened_at | date | Yes | Tanggal konten |
| year | smallint | Yes | Tahun (auto dari happened_at) |
| status | enum | No | `draft`, `final`, `published` |
| pic_user_id | bigint FK | Yes | Person in Charge |
| created_by | bigint FK | Yes | User pembuat |
| notes | text | Yes | Catatan tambahan |
| is_featured | boolean | No | Featured/highlight |
| created_at | timestamp | No | |
| updated_at | timestamp | No | |
| deleted_at | timestamp | Yes | Soft delete |

### Tabel: tags

| Kolom | Tipe | Nullable | Keterangan |
|-------|------|----------|------------|
| id | bigint | No | Primary key |
| name | varchar(255) | No | Nama tag (unique) |
| slug | varchar(255) | No | Slug (unique) |
| created_at | timestamp | No | |
| updated_at | timestamp | No | |

### Tabel: taggables (Pivot)

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| tag_id | bigint FK | ID tag |
| taggable_id | bigint | ID record |
| taggable_type | varchar | Model class (Asset/Project) |

---

## Fitur Utama

### 1. Projects (Kelola Event/Kegiatan)

**Fungsi:** Mengelompokkan aset berdasarkan event atau kegiatan

**Fitur:**
- CRUD project dengan soft delete
- Relation manager untuk aset (kelola aset dari halaman project)
- Filter: status, unit, tahun, trashed
- Sorting: title, event_date, created_at
- Counter: jumlah aset per project

**Fields:**
- Title (required)
- Description
- Unit (Fakultas/Prodi/Organisasi)
- Event Date
- Location
- Tags (multi-select, create on-the-fly)
- Status (Active/Archived)

### 2. Assets (Arsip Konten)

**Fungsi:** Menyimpan metadata dan link dari setiap karya

**Fitur:**
- CRUD dengan soft delete
- Filter lengkap: tipe, platform, status, project, tahun, tags, PIC, featured
- Sorting: title, happened_at, created_at
- Actions:
  - **Buka Link** - Buka output_link (atau source_link jika tidak ada)
  - **Buka Source** - Buka source_link
  - **Toggle Featured** - Tandai/hapus featured
  - **Duplikat** - Clone aset beserta tags
- Bulk Actions:
  - **Ubah Status** - Ubah status banyak aset sekaligus
  - **Set Project** - Assign project ke banyak aset
  - **Tambah Tags** - Tambah tags ke banyak aset
  - Delete, Restore, Force Delete

**Fields:**
- Project (optional, select)
- Title (required)
- Asset Type (required, select)
- Platform (select)
- Source Link (URL)
- Output Link (URL)
- Happened At (date, auto-fill year)
- Status (Draft/Final/Published)
- PIC (select user)
- Tags (multi-select, create on-the-fly)
- Notes (textarea)
- Is Featured (toggle)

### 3. Tags (Kategorisasi)

**Fungsi:** Label untuk kategorisasi aset dan project

**Fitur:**
- CRUD sederhana
- Auto-generate slug dari name
- Counter: jumlah aset dan project per tag
- Create on-the-fly dari form asset/project

**Tags Default:**
- Event: Wisuda, Dies Natalis, Seminar, Workshop, Webinar, dll
- Unit: Rektorat, Fakultas, Prodi, UKM, Himpunan
- Content: Instagram, YouTube, TikTok, Website

### 4. Dashboard Widgets

| Widget | Fungsi |
|--------|--------|
| WelcomeWidget | Welcome message, statistik singkat, quick links |
| AssetStatsWidget | Counter: total, published, foto, video, desain |
| RecentAssetsWidget | Tabel 5 aset terbaru |

### 5. Halaman Dokumentasi

Route: `/admin/dokumentasi`

Berisi:
- Quick Start guide
- Penjelasan menu (Projects, Assets, Tags)
- Workflow arsip konten
- Tips & keyboard shortcuts
- Daftar tipe aset dan platform
- Tabel hak akses

---

## Panduan Penggunaan

### Workflow Arsip Konten

```
┌─────────────────┐
│ 1. Buat Project │  Event/kegiatan baru
└────────┬────────┘
         ▼
┌─────────────────┐
│ 2. Upload File  │  Ke Google Drive / Figma / Canva
└────────┬────────┘
         ▼
┌─────────────────┐
│ 3. Buat Asset   │  Isi metadata + paste link source
└────────┬────────┘
         ▼
┌─────────────────┐
│ 4. Publish      │  Publish ke IG / YouTube / Website
└────────┬────────┘
         ▼
┌─────────────────┐
│ 5. Update Asset │  Tambah link output + status Published
└─────────────────┘
```

### Keyboard Shortcuts

| Shortcut | Fungsi |
|----------|--------|
| `Ctrl+K` atau `⌘+K` | Global search |
| `Esc` | Tutup modal/dropdown |

### Tips Penggunaan

1. **Pencarian Cepat:** Tekan `Ctrl+K` untuk mencari aset/project dari mana saja
2. **Bulk Edit:** Pilih beberapa aset dengan checkbox, lalu gunakan bulk actions
3. **Duplikat:** Klik menu ⋮ → Duplikat untuk copy aset dengan cepat
4. **Featured:** Tandai karya terbaik dengan toggle Featured untuk highlight
5. **Filter Kombinasi:** Gunakan multiple filter untuk pencarian spesifik

---

## Roles & Permissions

### Role Definitions

| Role | Keterangan |
|------|------------|
| `admin` | Administrator dengan akses penuh |
| `head_msc` | Kepala MSC |
| `staff_msc` | Staff MSC |

### Permission Matrix

| Permission | admin | head_msc | staff_msc |
|------------|-------|----------|-----------|
| View Assets/Projects/Tags | ✅ | ✅ | ✅ |
| Create Assets/Projects/Tags | ✅ | ✅ | ✅ |
| Edit Assets/Projects/Tags | ✅ | ✅ | ✅ |
| Delete Assets/Projects/Tags | ✅ | ❌ | ❌ |
| Restore Deleted | ✅ | ❌ | ❌ |
| Force Delete | ✅ | ❌ | ❌ |

### Policy Implementation

Setiap model memiliki policy yang mengatur akses:
- `AssetPolicy`
- `ProjectPolicy`
- `TagPolicy`

---

## API & Enums

### AssetType Enum

```php
enum AssetType: string
{
    case PHOTO = 'photo';       // Foto dokumentasi
    case VIDEO = 'video';       // Video
    case DESIGN = 'design';     // Desain grafis
    case BANNER = 'banner';     // Banner/poster
    case DOCUMENT = 'document'; // Dokumen
    case POST = 'post';         // Post sosmed
    case OTHER = 'other';       // Lainnya
}
```

### AssetStatus Enum

```php
enum AssetStatus: string
{
    case DRAFT = 'draft';         // Masih dikerjakan
    case FINAL = 'final';         // Selesai, belum dipublish
    case PUBLISHED = 'published'; // Sudah dipublish
}
```

### Platform Enum

```php
enum Platform: string
{
    case INSTAGRAM = 'instagram';
    case TIKTOK = 'tiktok';
    case FACEBOOK = 'facebook';
    case WEBSITE = 'website';
    case YOUTUBE = 'youtube';
    case DRIVE = 'drive';
    case FIGMA = 'figma';
    case CANVA = 'canva';
    case OTHER = 'other';
}
```

### ProjectStatus Enum

```php
enum ProjectStatus: string
{
    case ACTIVE = 'active';     // Project aktif
    case ARCHIVED = 'archived'; // Project diarsipkan
}
```

---

## File Structure

```
app/
├── Enums/
│   ├── AssetType.php
│   ├── AssetStatus.php
│   ├── Platform.php
│   └── ProjectStatus.php
├── Models/
│   ├── Asset.php
│   ├── Project.php
│   ├── Tag.php
│   └── Traits/
│       └── HasTags.php
├── Policies/
│   ├── AssetPolicy.php
│   ├── ProjectPolicy.php
│   └── TagPolicy.php
└── Filament/
    ├── Pages/
    │   └── Dokumentasi.php
    ├── Resources/
    │   ├── ProjectResource.php
    │   ├── ProjectResource/
    │   │   ├── Pages/
    │   │   │   ├── ListProjects.php
    │   │   │   ├── CreateProject.php
    │   │   │   ├── EditProject.php
    │   │   │   └── ViewProject.php
    │   │   └── RelationManagers/
    │   │       └── AssetsRelationManager.php
    │   ├── AssetResource.php
    │   ├── AssetResource/
    │   │   └── Pages/
    │   │       ├── ListAssets.php
    │   │       ├── CreateAsset.php
    │   │       ├── EditAsset.php
    │   │       └── ViewAsset.php
    │   ├── TagResource.php
    │   └── TagResource/
    │       └── Pages/
    │           ├── ListTags.php
    │           ├── CreateTag.php
    │           └── EditTag.php
    └── Widgets/
        ├── WelcomeWidget.php
        ├── AssetStatsWidget.php
        └── RecentAssetsWidget.php

database/
├── migrations/
│   ├── xxxx_create_projects_table.php
│   ├── xxxx_create_tags_table.php
│   └── xxxx_create_assets_table.php
├── factories/
│   ├── ProjectFactory.php
│   └── AssetFactory.php
└── seeders/
    ├── RoleSeeder.php
    ├── UserSeeder.php
    ├── TagSeeder.php
    ├── ProjectSeeder.php
    └── AssetSeeder.php

resources/views/filament/
├── pages/
│   └── dokumentasi.blade.php
└── widgets/
    └── welcome-widget.blade.php
```

---

## URL Endpoints

| URL | Keterangan |
|-----|------------|
| `/admin` | Dashboard |
| `/admin/login` | Login page |
| `/admin/dokumentasi` | Halaman dokumentasi |
| `/admin/projects` | Daftar projects |
| `/admin/projects/create` | Buat project baru |
| `/admin/projects/{id}` | Lihat detail project |
| `/admin/projects/{id}/edit` | Edit project |
| `/admin/assets` | Daftar assets |
| `/admin/assets/create` | Buat asset baru |
| `/admin/assets/{id}` | Lihat detail asset |
| `/admin/assets/{id}/edit` | Edit asset |
| `/admin/tags` | Daftar tags |
| `/admin/tags/create` | Buat tag baru |
| `/admin/tags/{id}/edit` | Edit tag |

---

## Troubleshooting

### Cache Issues
```bash
php artisan optimize:clear
php artisan view:clear
php artisan filament:clear-cached-components
```

### Permission Issues
```bash
php artisan db:seed --class=RoleSeeder --force
```

### Migration Issues
```bash
php artisan migrate:fresh --seed
```

---

## Changelog

### v1.0.0 (Initial Release)
- ✅ CRUD Projects dengan tags dan soft delete
- ✅ CRUD Assets dengan filter lengkap
- ✅ CRUD Tags dengan polymorphic relation
- ✅ Role-based access control (admin, head_msc, staff_msc)
- ✅ Dashboard widgets (stats, recent assets)
- ✅ Halaman dokumentasi
- ✅ Bulk actions untuk assets
- ✅ Duplikat asset
- ✅ Toggle featured
- ✅ Global search (Ctrl+K)

---

## Credits

**Asset Vault** dikembangkan untuk MSC (Media & Strategic Communications) JGU.

- **Framework:** Laravel 12
- **Admin Panel:** Filament v4
- **Roles/Permissions:** Spatie Laravel Permission
