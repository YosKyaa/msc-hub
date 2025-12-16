# Inventory & Room Booking - Dokumentasi

## Overview

Modul Inventory & Room Booking adalah sistem untuk mengelola peminjaman peralatan dan ruangan di MSC JGU. Sistem ini mendukung:

- ✅ Manajemen inventory items (kamera, mic, tripod, dll)
- ✅ Booking peralatan dengan validasi ketersediaan
- ✅ Booking ruangan dengan validasi jadwal
- ✅ 2-stage approval (Staff MSC → Head MSC)
- ✅ Tracking check-out dan return untuk inventory
- ✅ Validasi jam operasional (08:00 - 16:00)
- ✅ Pencegahan booking overlap
- ✅ **Public Booking Portal** dengan Google Sign-In
- ✅ Domain restriction (@jgu.ac.id, @student.jgu.ac.id)
- ✅ Privacy: user hanya lihat booking miliknya sendiri

---

## Database Structure

### Entity Relationship

```
┌─────────────────┐
│ inventory_items │
└────────┬────────┘
         │ N:M
         ▼
┌─────────────────────────┐     ┌────────────────┐
│   inventory_bookings    │────▶│ inventory_logs │
└─────────────────────────┘     └────────────────┘
         │
         │ pivot
         ▼
┌─────────────────────────┐
│ inventory_booking_items │
└─────────────────────────┘

┌─────────┐      1:N      ┌───────────────┐
│  rooms  │──────────────▶│ room_bookings │
└─────────┘               └───────────────┘

┌────────────────────┐
│ booking_sequences  │  (untuk generate kode unik)
└────────────────────┘
```

### Tabel: inventory_items

| Kolom | Tipe | Nullable | Keterangan |
|-------|------|----------|------------|
| id | bigint | No | Primary key |
| code | varchar(50) | No | Kode unik item (CAM-001) |
| name | varchar(255) | No | Nama item |
| category | enum | Yes | camera, lens, microphone, dll |
| condition_status | enum | No | good, minor_issue, broken, maintenance |
| notes | text | Yes | Catatan kondisi |
| is_active | boolean | No | Status aktif |
| created_by | FK users | Yes | User pembuat |
| timestamps, softDeletes | | |

### Tabel: inventory_bookings

| Kolom | Tipe | Nullable | Keterangan |
|-------|------|----------|------------|
| id | bigint | No | Primary key |
| booking_code | varchar(20) | No | Kode unik (INV-2025-0001) |
| requester_name | varchar(255) | No | Nama pemohon |
| requester_email | varchar(255) | No | Email pemohon |
| requester_google_id | varchar(255) | Yes | Google ID pemohon |
| unit | varchar(255) | Yes | Unit/Fakultas |
| purpose | text | Yes | Tujuan peminjaman |
| start_at | datetime | No | Waktu mulai |
| end_at | datetime | No | Waktu selesai |
| status | enum | No | Status booking |
| staff_approved_at | timestamp | Yes | Waktu approve staff |
| staff_approved_by | FK users | Yes | User staff |
| head_approved_at | timestamp | Yes | Waktu approve head |
| head_approved_by | FK users | Yes | User head |
| rejected_at | timestamp | Yes | Waktu ditolak |
| rejected_by | FK users | Yes | User penolak |
| reject_reason | text | Yes | Alasan tolak |
| checked_out_at | timestamp | Yes | Waktu check-out |
| checked_out_by | FK users | Yes | User check-out |
| checkout_note | text | Yes | Catatan check-out |
| returned_at | timestamp | Yes | Waktu return |
| returned_by | FK users | Yes | User return |
| return_note | text | Yes | Catatan return |
| cancelled_at | timestamp | Yes | Waktu dibatalkan |
| timestamps | | |

### Tabel: inventory_booking_items (Pivot)

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| id | bigint | Primary key |
| inventory_booking_id | FK | Relasi ke booking |
| inventory_item_id | FK | Relasi ke item |
| quantity | smallint | Default 1 |
| timestamps | |

### Tabel: inventory_logs

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| id | bigint | Primary key |
| inventory_booking_id | FK | Relasi ke booking |
| type | enum | check_out, return, condition_update, status_change |
| note | text | Catatan |
| created_by | FK users | User pembuat |
| timestamps | |

### Tabel: rooms

| Kolom | Tipe | Nullable | Keterangan |
|-------|------|----------|------------|
| id | bigint | No | Primary key |
| name | varchar(255) | No | Nama ruangan |
| location | varchar(255) | Yes | Lokasi |
| open_time | time | No | Jam buka (default 08:00) |
| close_time | time | No | Jam tutup (default 16:00) |
| capacity | smallint | Yes | Kapasitas |
| facilities | text | Yes | Fasilitas |
| is_active | boolean | No | Status aktif |
| timestamps | |

### Tabel: room_bookings

| Kolom | Tipe | Nullable | Keterangan |
|-------|------|----------|------------|
| id | bigint | No | Primary key |
| booking_code | varchar(20) | No | Kode unik (ROOM-2025-0001) |
| room_id | FK rooms | No | Relasi ke room |
| requester_name | varchar(255) | No | Nama pemohon |
| requester_email | varchar(255) | No | Email pemohon |
| requester_google_id | varchar(255) | Yes | Google ID pemohon |
| unit | varchar(255) | Yes | Unit/Fakultas |
| purpose | text | Yes | Keperluan |
| attendees | smallint | Yes | Jumlah peserta |
| start_at | datetime | No | Waktu mulai |
| end_at | datetime | No | Waktu selesai |
| status | enum | No | Status booking |
| staff_approved_at | timestamp | Yes | Waktu approve staff |
| staff_approved_by | FK users | Yes | User staff |
| head_approved_at | timestamp | Yes | Waktu approve head |
| head_approved_by | FK users | Yes | User head |
| rejected_at | timestamp | Yes | Waktu ditolak |
| rejected_by | FK users | Yes | User penolak |
| reject_reason | text | Yes | Alasan tolak |
| cancelled_at | timestamp | Yes | Waktu dibatalkan |
| completed_at | timestamp | Yes | Waktu selesai |
| timestamps | |

### Tabel: booking_sequences

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| id | bigint | Primary key |
| type | varchar(20) | INV atau ROOM |
| year | year | Tahun |
| last_number | int | Nomor terakhir |
| timestamps | |

---

## Enums

### BookingStatus

```php
enum BookingStatus: string
{
    case PENDING = 'pending';           // Menunggu approval
    case APPROVED_STAFF = 'approved_staff'; // Disetujui staff
    case APPROVED_HEAD = 'approved_head';   // Disetujui head
    case REJECTED = 'rejected';         // Ditolak
    case CHECKED_OUT = 'checked_out';   // Sedang dipinjam (inventory)
    case RETURNED = 'returned';         // Sudah dikembalikan (inventory)
    case CANCELLED = 'cancelled';       // Dibatalkan
    case COMPLETED = 'completed';       // Selesai (room)
}
```

### InventoryCondition

```php
enum InventoryCondition: string
{
    case GOOD = 'good';               // Baik
    case MINOR_ISSUE = 'minor_issue'; // Ada masalah kecil
    case BROKEN = 'broken';           // Rusak
    case MAINTENANCE = 'maintenance'; // Sedang diperbaiki
}
```

### InventoryCategory

```php
enum InventoryCategory: string
{
    case CAMERA = 'camera';
    case LENS = 'lens';
    case MICROPHONE = 'microphone';
    case TRIPOD = 'tripod';
    case LIGHTING = 'lighting';
    case AUDIO = 'audio';
    case VIDEO = 'video';
    case COMPUTER = 'computer';
    case PROJECTOR = 'projector';
    case OTHER = 'other';
}
```

### InventoryLogType

```php
enum InventoryLogType: string
{
    case CHECK_OUT = 'check_out';
    case RETURN = 'return';
    case CONDITION_UPDATE = 'condition_update';
    case STATUS_CHANGE = 'status_change';
}
```

---

## Business Rules

### 1. Operating Hours Validation

```php
// Jam operasional: 08:00 - 16:00
$openTime = '08:00';
$closeTime = '16:00';

// Validasi:
// - start_at tidak boleh sebelum 08:00
// - end_at tidak boleh setelah 16:00
// - end_at harus setelah start_at
```

### 2. Overlap Prevention (Inventory)

```php
// Query untuk cek overlap per item
$hasOverlap = $item->bookings()
    ->whereIn('status', ['pending', 'approved_staff', 'approved_head', 'checked_out'])
    ->where(function ($q) use ($startAt, $endAt) {
        $q->where('start_at', '<', $endAt)
          ->where('end_at', '>', $startAt);
    })
    ->exists();
```

### 3. Overlap Prevention (Room)

```php
// Query untuk cek overlap per ruangan
$hasOverlap = $room->bookings()
    ->whereIn('status', ['pending', 'approved_staff', 'approved_head'])
    ->where(function ($q) use ($startAt, $endAt) {
        $q->where('start_at', '<', $endAt)
          ->where('end_at', '>', $startAt);
    })
    ->exists();
```

### 4. 2-Stage Approval

```
┌─────────┐     Staff      ┌────────────────┐     Head       ┌───────────────┐
│ PENDING │────Approve────▶│ APPROVED_STAFF │────Approve────▶│ APPROVED_HEAD │
└─────────┘                └────────────────┘                └───────────────┘
     │                            │
     │ Reject                     │ Reject
     ▼                            ▼
┌──────────┐               ┌──────────┐
│ REJECTED │               │ REJECTED │
└──────────┘               └──────────┘
```

**Rules:**
- Staff dapat approve jika status = `pending`
- Head dapat approve jika status = `approved_staff` DAN `staff_approved_at` tidak null
- Reject dapat dilakukan jika status = `pending` atau `approved_staff`

### 5. Inventory Lifecycle

```
┌─────────┐     ┌───────────────┐     ┌─────────────┐     ┌──────────┐
│ PENDING │────▶│ APPROVED_HEAD │────▶│ CHECKED_OUT │────▶│ RETURNED │
└─────────┘     └───────────────┘     └─────────────┘     └──────────┘
                        │
                        │ Check-out         Return
```

### 6. Room Lifecycle

```
┌─────────┐     ┌───────────────┐     ┌───────────┐
│ PENDING │────▶│ APPROVED_HEAD │────▶│ COMPLETED │
└─────────┘     └───────────────┘     └───────────┘
```

---

## URL Endpoints

### Filament Admin

| URL | Keterangan |
|-----|------------|
| `/admin/inventory-items` | Daftar inventory items |
| `/admin/inventory-items/create` | Tambah item baru |
| `/admin/inventory-items/{id}/edit` | Edit item |
| `/admin/inventory-bookings` | Daftar booking inventory |
| `/admin/inventory-bookings/create` | Buat booking baru |
| `/admin/inventory-bookings/{id}` | Detail booking |
| `/admin/inventory-bookings/{id}/edit` | Edit booking |
| `/admin/room-bookings` | Daftar booking ruangan |
| `/admin/room-bookings/create` | Buat booking baru |
| `/admin/room-bookings/{id}` | Detail booking |
| `/admin/room-bookings/{id}/edit` | Edit booking |

---

## Filament Actions

### Inventory Booking Actions

| Action | Role | Kondisi |
|--------|------|---------|
| View | all | Selalu |
| Edit | all | status = pending |
| Approve (Staff) | admin, staff_msc, head_msc | status = pending |
| Approve (Head) | admin, head_msc | status = approved_staff |
| Reject | all | status = pending/approved_staff |
| Check-out | all | status = approved_head |
| Return | all | status = checked_out |
| Cancel | all | status = pending |
| Delete | admin | Selalu |

### Room Booking Actions

| Action | Role | Kondisi |
|--------|------|---------|
| View | all | Selalu |
| Edit | all | status = pending |
| Approve (Staff) | admin, staff_msc, head_msc | status = pending |
| Approve (Head) | admin, head_msc | status = approved_staff |
| Reject | all | status = pending/approved_staff |
| Complete | all | status = approved_head & end_at <= now |
| Cancel | all | status = pending |
| Delete | admin | Selalu |

---

## Seeded Data

### Room (1 room)

| Name | Location | Hours | Capacity |
|------|----------|-------|----------|
| Ruang Multimedia MSC | Gedung MSC Lt. 2 | 08:00-16:00 | 30 |

### Inventory Items (21 items)

| Category | Items |
|----------|-------|
| Camera | Canon EOS 80D, Sony A7III, Canon EOS R |
| Lens | Canon 50mm f/1.8, Canon 24-70mm f/2.8, Sony 85mm f/1.4 |
| Tripod | Manfrotto MT055, Sirui T-2205X |
| Microphone | Rode VideoMic Pro+, Sennheiser MKE 400, Blue Yeti USB |
| Lighting | Godox SL-60W, Aputure AL-MC, Ring Light 18" |
| Video | DJI Pocket 2, GoPro Hero 10 |
| Audio | Zoom H6 Recorder, Tascam DR-40X |
| Projector | Epson EB-X51 |
| Computer | MacBook Pro 14" M1 |

---

## File Structure

```
app/
├── Enums/
│   ├── BookingStatus.php
│   ├── InventoryCategory.php
│   ├── InventoryCondition.php
│   └── InventoryLogType.php
├── Models/
│   ├── InventoryItem.php
│   ├── InventoryBooking.php
│   ├── InventoryLog.php
│   ├── Room.php
│   └── RoomBooking.php
├── Http/Controllers/
│   ├── Auth/
│   │   └── GoogleAuthController.php      # Google OAuth
│   └── PublicBookingController.php       # Public booking forms
└── Filament/Resources/
    ├── InventoryItemResource.php
    │   └── Pages/
    │       ├── ListInventoryItems.php
    │       ├── CreateInventoryItem.php
    │       └── EditInventoryItem.php
    ├── InventoryBookingResource.php
    │   ├── Pages/
    │   │   ├── ListInventoryBookings.php
    │   │   ├── CreateInventoryBooking.php
    │   │   ├── EditInventoryBooking.php
    │   │   └── ViewInventoryBooking.php
    │   └── RelationManagers/
    │       └── ItemsRelationManager.php
    └── RoomBookingResource.php
        └── Pages/
            ├── ListRoomBookings.php
            ├── CreateRoomBooking.php
            ├── EditRoomBooking.php
            └── ViewRoomBooking.php

database/
├── migrations/
│   ├── 2024_01_03_000001_create_inventory_items_table.php
│   ├── 2024_01_03_000002_create_inventory_bookings_table.php
│   ├── 2024_01_03_000003_create_inventory_booking_items_table.php
│   ├── 2024_01_03_000004_create_inventory_logs_table.php
│   ├── 2024_01_03_000005_create_rooms_table.php
│   ├── 2024_01_03_000006_create_room_bookings_table.php
│   ├── 2024_01_03_000007_create_booking_sequences_table.php
│   └── 2024_01_04_000001_add_requester_google_id_to_bookings.php
└── seeders/
    ├── RoomSeeder.php
    └── InventoryItemSeeder.php

resources/views/
├── layouts/
│   └── booking.blade.php                 # Layout untuk public booking
└── booking/
    ├── inventory-form.blade.php          # Form pinjam alat
    ├── room-form.blade.php               # Form booking ruangan
    ├── success.blade.php                 # Halaman sukses
    ├── my-bookings.blade.php             # Daftar booking saya
    ├── detail.blade.php                  # Detail booking
    └── room-unavailable.blade.php        # Error page

routes/
└── web.php                               # Public routes
```

---

## Manual Testing Checklist

### Inventory Items

- [ ] Create item dengan kode unik
- [ ] Edit kondisi item (good → maintenance)
- [ ] Toggle is_active
- [ ] Filter by category, condition
- [ ] Delete item

### Inventory Booking

- [ ] Buat booking dengan waktu valid (08:00-16:00) ✓
- [ ] Buat booking diluar jam operasional → ERROR
- [ ] Buat booking dengan item yang sudah dibooking → ERROR
- [ ] Staff approve booking
- [ ] Head approve booking (setelah staff)
- [ ] Coba head approve sebelum staff → tombol hidden
- [ ] Check-out booking (setelah head approve)
- [ ] Return booking (setelah checkout)
- [ ] Reject booking dengan alasan
- [ ] Cancel booking (status pending)

### Room Booking

- [ ] Buat booking ruangan dengan waktu valid
- [ ] Buat booking diluar jam operasional → ERROR
- [ ] Buat booking waktu overlap → ERROR
- [ ] Staff approve booking
- [ ] Head approve booking
- [ ] Mark as completed
- [ ] Reject booking
- [ ] Cancel booking

### Overlap Test

```
Booking A: 10:00 - 12:00 (Item X) → OK
Booking B: 11:00 - 13:00 (Item X) → ERROR (overlap)
Booking C: 12:00 - 14:00 (Item X) → OK (no overlap)
Booking D: 09:00 - 11:00 (Item Y) → OK (different item)
```

### Operating Hours Test

```
Booking: 07:00 - 09:00 → ERROR (starts before 08:00)
Booking: 15:00 - 17:00 → ERROR (ends after 16:00)
Booking: 08:00 - 16:00 → OK
Booking: 10:00 - 14:00 → OK
```

---

## Common Errors & Solutions

### 1. "Jam mulai tidak boleh sebelum 08:00"

**Penyebab:** Booking diluar jam operasional

**Solusi:** Pilih waktu antara 08:00 - 16:00

### 2. "Item tidak tersedia pada waktu yang dipilih"

**Penyebab:** Item sudah dibooking di waktu yang sama

**Solusi:** 
- Pilih waktu lain
- Pilih item lain
- Cek booking yang overlap dan tunggu hingga selesai

### 3. "Ruangan tidak tersedia"

**Penyebab:** Ruangan sudah dibooking di waktu yang sama

**Solusi:** Pilih waktu lain

### 4. "Head approve button tidak muncul"

**Penyebab:** Staff belum approve

**Solusi:** Minta staff approve terlebih dahulu

### 5. Cache Issues

```bash
php artisan optimize:clear
php artisan view:clear
```

---

## Future Improvements

1. ~~**Public Booking Form**~~ - ✅ DONE (v1.0.0)
2. **Email Notifications** - Notifikasi email saat status berubah
3. **Calendar View** - Tampilan kalender untuk room bookings
4. **QR Code** - Generate QR untuk check-in/check-out
5. **Reports** - Laporan penggunaan inventory dan ruangan
6. **Recurring Bookings** - Booking berulang (mingguan/bulanan)
7. **Mobile App** - Aplikasi mobile untuk booking
8. **WhatsApp Notifications** - Integrasi notifikasi WhatsApp

---

## API Reference (Future)

### Endpoints (Planned)

```
GET    /api/v1/inventory-items
GET    /api/v1/inventory-items/{id}
GET    /api/v1/inventory-items/{id}/availability

POST   /api/v1/inventory-bookings
GET    /api/v1/inventory-bookings/{code}

GET    /api/v1/rooms
GET    /api/v1/rooms/{id}/availability

POST   /api/v1/room-bookings
GET    /api/v1/room-bookings/{code}
```

---

## Changelog

### v1.0.0 (Initial Release)

- ✅ CRUD Inventory Items dengan kategori dan kondisi
- ✅ Booking Inventory dengan multi-item selection
- ✅ Booking Room dengan validasi kapasitas
- ✅ 2-stage approval (Staff → Head)
- ✅ Operating hours validation (08:00-16:00)
- ✅ Overlap prevention untuk items dan rooms
- ✅ Check-out / Return workflow untuk inventory
- ✅ Logging untuk inventory transactions
- ✅ Badge count untuk pending bookings
- ✅ Filters: status, date range, category, condition
- ✅ Public Booking Portal dengan Google Sign-In

---

## Public Booking Portal

### Overview

Portal publik memungkinkan pengguna dengan email JGU untuk mengajukan booking alat dan ruangan tanpa perlu login ke admin panel.

### Features

- Google Sign-In dengan domain restriction (@jgu.ac.id, @student.jgu.ac.id)
- Form booking inventory (pilih multiple items)
- Form booking ruangan
- Halaman "Booking Saya" untuk melihat status
- Privacy: user hanya bisa melihat booking miliknya sendiri
- Rate limiting pada form submission

### URL Endpoints (Public)

| URL | Method | Keterangan |
|-----|--------|------------|
| `/borrow/inventory` | GET | Form peminjaman alat |
| `/borrow/inventory` | POST | Submit peminjaman alat |
| `/book/room` | GET | Form booking ruangan |
| `/book/room` | POST | Submit booking ruangan |
| `/my-bookings` | GET | Daftar booking saya |
| `/my-bookings/{type}/{code}` | GET | Detail booking |
| `/booking/success/{type}/{code}` | GET | Halaman sukses |
| `/google/redirect` | GET | Google OAuth redirect |

### Authentication Flow

```
User mengakses /borrow/inventory
        │
        ▼
Session 'requester' exists?
        │
    NO  │  YES
        │    │
        ▼    ▼
Redirect ke    Tampilkan
Google OAuth   Form Booking
        │
        ▼
User login dengan Google
        │
        ▼
Validasi domain email
(@jgu.ac.id / @student.jgu.ac.id)
        │
    VALID   INVALID
        │       │
        ▼       ▼
Store session   Redirect dengan
'requester'     error message
        │
        ▼
Redirect ke
halaman awal
```

### Session Data

```php
Session::get('requester') = [
    'google_id' => '1234567890',
    'name' => 'John Doe',
    'email' => 'john.doe@jgu.ac.id',
    'avatar' => 'https://...',
    'type' => 'lecturer', // or 'student'
    'authenticated_at' => '2024-01-01T00:00:00+00:00',
];
```

### Privacy Rule

User hanya dapat melihat booking dengan `requester_email` == `session('requester.email')`.

```php
// Controller
$bookings = InventoryBooking::where('requester_email', $requester['email'])->get();
```

### Validation Rules

#### 1. Operating Hours (08:00 - 16:00)

```php
// Validasi di controller
$hourErrors = InventoryBooking::validateOperatingHours($startAt, $endAt);
if (!empty($hourErrors)) {
    return back()->withErrors(['time' => implode(' ', $hourErrors)]);
}
```

#### 2. Item Overlap Prevention

```php
// Cek conflict per item
$conflicts = InventoryBooking::checkItemOverlaps($itemIds, $startAt, $endAt);
if (!empty($conflicts)) {
    return back()->withErrors(['items' => 'Item tidak tersedia: ' . implode(', ', $conflicts)]);
}
```

#### 3. Room Overlap Prevention

```php
if ($room->hasOverlappingBookings($startAt, $endAt)) {
    return back()->withErrors(['time' => 'Ruangan sudah dibooking.']);
}
```

### Rate Limiting

POST routes dilindungi dengan throttle middleware:

```php
Route::post('/inventory', [PublicBookingController::class, 'submitInventoryBooking'])
    ->middleware('throttle:10,1'); // 10 requests per minute
```

### File Structure

```
app/Http/Controllers/
├── Auth/
│   └── GoogleAuthController.php    # Google OAuth
└── PublicBookingController.php     # Public booking forms

resources/views/
├── layouts/
│   └── booking.blade.php           # Layout untuk booking
└── booking/
    ├── inventory-form.blade.php    # Form pinjam alat
    ├── room-form.blade.php         # Form booking ruangan
    ├── success.blade.php           # Halaman sukses
    ├── my-bookings.blade.php       # Daftar booking saya
    ├── detail.blade.php            # Detail booking
    └── room-unavailable.blade.php  # Error page

routes/web.php                      # Public routes
```

### Manual Testing Checklist (Public Portal)

- [ ] Akses /borrow/inventory tanpa login → redirect ke Google
- [ ] Login dengan email @jgu.ac.id → sukses
- [ ] Login dengan email @gmail.com → ditolak
- [ ] Submit booking dalam jam operasi (08:00-16:00) → sukses
- [ ] Submit booking diluar jam operasi → error
- [ ] Submit booking dengan item yang overlap → error
- [ ] Submit booking ruangan yang overlap → error
- [ ] Cek halaman "Booking Saya" → hanya lihat milik sendiri
- [ ] User A tidak bisa lihat booking User B
- [ ] Logout → session hilang

---

## Credits

**Inventory & Room Booking** dikembangkan untuk MSC (Media & Strategic Communications) JGU.

- **Framework:** Laravel 12
- **Admin Panel:** Filament v4
- **Roles/Permissions:** Spatie Laravel Permission
