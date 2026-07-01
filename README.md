# Cacti Monitoring — Instalasi Multi-Site

Cacti **v1.2.31** — Network Monitoring Framework berbasis PHP, MySQL/MariaDB, dan RRDtool.

Proyek ini digunakan untuk memonitor **84 perangkat jaringan** yang tersebar di 19 kota/region
seluruh Indonesia (Balikpapan, Semarang, Makassar, Palu, Batam, dan lainnya) menggunakan
Mikrotik RouterOS, Ubiquiti AP, dan perangkat Linux/Windows.

---

## Prasyarat

### PHP

| Requirement | Minimal |
|-------------|---------|
| **PHP** | >= 8.0 |
| Ekstensi | `PDO`, `Phar`, `dom`, `gd`, `gmp`, `intl`, `json`, `ldap`, `mbstring`, `mysqlnd`, `openssl`, `pcntl`, `pdo_mysql`, `posix`, `sockets`, `sqlite3`, `xml` |

### Database

| Requirement | Keterangan |
|-------------|------------|
| **Database** | MySQL 5.7+ / MariaDB 10.3+ |
| **Koneksi** | `mysqli` / `pdo_mysql` |
| **Charset** | `utf8mb4` |

### Software Pendukung

| Software | Kegunaan |
|----------|----------|
| **RRDtool** 1.4+ | Penyimpanan dan rendering grafik time-series |
| **Net-SNMP** 5.7+ | Pengumpulan data SNMP dari perangkat |
| **Spine / cactid** | Poller berkinerja tinggi (alternatif cmd.php) |
| **Web Server** | Apache / Nginx + PHP-FPM |
| **Composer** | Manajemen dependensi PHP |

### Dependensi PHP (Composer)

Dependensi sudah termasuk dalam `include/vendor/` dan tidak perlu diinstal ulang
kecuali ada pembaruan:

| Paket | Versi | Fungsi |
|-------|-------|--------|
| `ezyang/htmlpurifier` | ^4.19 | Sanitasi HTML |
| `paragonie/constant_time_encoding` | ^2.0 | Encoding constant-time |
| `paragonie/random_compat` | * | Fallback random untuk PHP |
| `phpseclib/phpseclib` | ^3.0 | SSH/SFTP |

---

## Hal Penting Sebelum Instalasi

### Permission Folder

Folder berikut harus **writable** oleh user web server (www-data/apache):

| Folder | Fungsi |
|--------|--------|
| `log/` | File log Cacti (`cacti.log`) |
| `cache/` | Cache boost, mibcache, realtime, spikekill |
| `rra/` | File RRD (ratusan file .rrd) |
| `resource/` | Resource CSS/JS (beberapa file di-generate runtime) |

Jika baru deploy, pastikan permission di-set:

```bash
chmod -R a+w log/ cache/ rra/ resource/
```

### Config File

File `include/config.php` berisi kredensial database dan **tidak boleh di-commit**
(sudah masuk `.gitignore`). Gunakan `include/config.php.dist` sebagai template.

### Database Tuning

Beberapa tabel poller menggunakan engine `MEMORY` (poller_output, automation_ips,
automation_processes, poller_output_boost_local_data_ids,
poller_output_boost_processes). Pastikan `max_heap_table_size` di MySQL/MariaDB
cukup besar (minimal 64M, disarankan 256M+).

### Timezone

Set timezone PHP dan MySQL/Waktu server harus **sinkron**. Cacti menyimpan waktu
dalam `TIMESTAMP` (UTC) dan menampilkan sesuai timezone user.

---

## Langkah Instalasi

### Opsi A: Instalasi Manual (Linux)

1.  **Clone repositori**

    ```bash
    git clone <repo-url> /var/www/html/cacti-monitoring
    cd /var/www/html/cacti-monitoring
    ```

2.  **Buat database dan user**

    ```sql
    CREATE DATABASE cacti CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    CREATE USER 'cactiuser'@'localhost' IDENTIFIED BY 'your-password-here';
    GRANT ALL PRIVILEGES ON cacti.* TO 'cactiuser'@'localhost';
    FLUSH PRIVILEGES;
    ```

3.  **Import skema database**

    ```bash
    mysql -u cactiuser -p cacti < cacti.sql
    ```

4.  **Konfigurasi Cacti**

    ```bash
    cp include/config.php.dist include/config.php
    ```

    Edit `include/config.php`:

    ```php
    $database_default  = 'cacti';
    $database_hostname = 'localhost';
    $database_username = 'cactiuser';
    $database_password = 'your-password-here';
    $database_port     = '3306';
    $poller_id         = 1;
    $url_path          = '/cacti-monitoring/';
    ```

5.  **Set permission folder**

    ```bash
    chmod -R a+w log/ cache/ rra/ resource/
    ```

6.  **Konfigurasi web server**

    **Apache**: Gunakan `.htaccess.dist` sebagai referensi. Pastikan
    `mod_rewrite` aktif dan `AllowOverride All` untuk direktori Cacti.

    **Nginx**: Contoh konfigurasi ada di `tests/e2e/nginx.conf`.

7.  **Selesaikan instalasi melalui web**

    Buka `http://server-anda/cacti-monitoring/` di browser. Jika skema database
    sudah di-import (langkah 3), instalasi akan mendeteksi bahwa Cacti sudah
    terinstal dan langsung menampilkan halaman login.

    **Login default:** `admin` / `admin`

8.  **Konfigurasi Poller (Cron)**

    Buka **Console > Configuration > Settings > Poller** dan pilih poller type:

    - **cmd.php** (bawaan, cocok untuk < 100 device)
    - **Spine / cactid** (direkomendasikan untuk > 100 device)

    Tambahkan cron job untuk menjalankan poller setiap 5 menit:

    ```cron
    */5 * * * * apache php /var/www/html/cacti-monitoring/poller.php > /dev/null 2>&1
    ```

    Atau jika menggunakan Cacti Daemon (systemd):

    ```bash
    cp service/cactid.service /etc/systemd/system/
    systemctl daemon-reload
    systemctl enable --now cactid
    ```

    Edit `/etc/sysconfig/cactid` sesuai environment Anda.

9.  **Verifikasi**

    - Login ke Cacti
    - Buka **Console > Management > Devices** — 84 perangkat akan terdaftar
    - Buka **Console > Graph Trees** — tree per-kota/region sudah terbentuk
    - Periksa `log/cacti.log` untuk memastikan poller berjalan

### Opsi B: Instalasi Development (Docker Compose)

> **Catatan:** Docker stack ini dirancang untuk **E2E testing**, bukan produksi.

```bash
cd tests/e2e
docker compose up -d
```

Stack terdiri dari:
- **MariaDB 10.11** — Database dengan seed `cacti.sql`
- **PHP 7.4 FPM** — Runtime Cacti
- **Nginx Alpine** — Web server (port 8080)

Akses di `http://localhost:8080/` (login: `admin` / `admin`).

Detail konfigurasi ada di `tests/e2e/docker-compose.yml` dan
`tests/e2e/entrypoint.sh`. Variabel lingkungan yang digunakan:

| Variabel | Default | Fungsi |
|----------|---------|--------|
| `CACTI_DB_HOST` | mariadb | Host database |
| `CACTI_DB_NAME` | cacti | Nama database |
| `CACTI_DB_USER` | cactiuser | User database |
| `CACTI_DB_PASS` | cactipass | Password database |
| `CACTI_DB_PORT` | 3306 | Port database |
| `CACTI_CSP_MODE` | nonce-report | Mode CSP header |
| `HOST_PORT` | 8080 | Port host untuk Nginx |

---

## Konfigurasi Awal

### `include/config.php`

File ini adalah satu-satunya konfigurasi yang perlu diedit manual.
Semua pengaturan lainnya dikelola melalui UI Cacti.

```php
// === Koneksi Database ===
$database_type     = 'mysql';           // Jenis database
$database_default  = 'cacti';           // Nama database
$database_hostname = 'localhost';       // Host database
$database_username = 'cactiuser';       // User database
$database_password = '********';        // Password database
$database_port     = '3306';            // Port database
$database_retries  = 5;                 // Retry koneksi
$database_ssl      = false;             // SSL database
$database_persist  = false;             // Persistent connection

// === Remote Poller (hanya untuk remote poller) ===
#$rdatabase_type     = 'mysql';
#$rdatabase_default  = 'cacti';
#$rdatabase_hostname = 'remote-server';
#$rdatabase_username = 'cactiuser';
#$rdatabase_password = '********';
#$rdatabase_port     = '3306';

// === Poller ===
$poller_id = 1;  // 1 = main server, >1 = remote poller

// === URL Path ===
$url_path = '/cacti-monitoring/';

// === Session ===
$cacti_session_name = 'Cacti';
$cacti_db_session   = false;  // Simpan session di DB (untuk load balancing)

// === Opsional ===
// $scripts_path    = '/var/www/html/cacti/scripts';
// $resource_path   = '/var/www/html/cacti/resource/';
// $php_path        = '/bin/php';
// $php_snmp_support = false;
// $input_whitelist = '/usr/local/etc/cacti/input_whitelist.json';
// $path_csrf_secret = '/usr/share/cacti/resource/csrf-secret.php';
```

### Database

Setelah import `cacti.sql`, ada 109+ tabel. Beberapa tabel penting:

| Tabel | Fungsi |
|-------|--------|
| `host` | Definisi perangkat (84 device) |
| `graph_tree` | Definisi tree (Default Tree) |
| `graph_tree_items` | Item tree (branch + device) |
| `poller_item` | Item polling per device |
| `poller_output` | Buffer hasil polling (MEMORY) |
| `user_auth` | User accounts |
| `settings` | Key-value global settings |
| `plugin_config` | Registrasi plugin |

### Poller / Cron

**Cacti Daemon (cactid):** Jalankan sebagai systemd service:

```bash
# Edit path di service/cactid.service sesuai instalasi Anda
# Default: /var/www/html/cacti/cactid.php
cp service/cactid.service /etc/systemd/system/
systemctl enable --now cactid
```

**Cron-based poller (cmd.php):**

```bash
# Poller utama — jalankan setiap 5 menit
*/5 * * * * php /path/to/cacti/poller.php > /dev/null 2>&1

# Poller automation — untuk network discovery
*/5 * * * * php /path/to/cacti/poller_automation.php > /dev/null 2>&1

# Poller boost — untuk performa (jika diaktifkan)
*/5 * * * * php /path/to/cacti/poller_boost.php > /dev/null 2>&1

# Poller dsstats — statistik data source (setiap jam)
00 * * * * php /path/to/cacti/poller_dsstats.php > /dev/null 2>&1

# Poller reports — laporan terjadwal
*/5 * * * * php /path/to/cacti/poller_reports.php > /dev/null 2>&1

# Poller spikekill — deteksi anomali (setiap 15 menit)
*/15 * * * * php /path/to/cacti/poller_spikekill.php > /dev/null 2>&1

# Poller maintenance — tugas maintenance (setiap jam)
15 * * * * php /path/to/cacti/poller_maintenance.php > /dev/null 2>&1

# Poller rrdcheck — verifikasi RRD file
15 */6 * * * php /path/to/cacti/poller_rrdcheck.php > /dev/null 2>&1
```

Lihat juga CLI scripts di `cli/` untuk berbagai tugas maintenance otomatis.

---

## Struktur Tree Perangkat

Tree telah direkonstruksi berdasarkan data produksi dengan branch per region:

```
Default Tree
├── R225-MKA           │ Mikrotik R225
├── P3                 │ Pangkalan 3
├── _GATEWAY_          │ Gateway, Azure, Web server, DNS
├── P5                 │ Pangkalan 5 — Bekasi
├── P6                 │ Pangkalan 6 — Bekasi
├── P12                │ Pangkalan 12 — Cileungsi
├── PEKANBARU          │ R.202 Pekanbaru
├── PALU               │ Distribusi, Sw Core, R.211
├── BALI               │ R.243 Bali
├── BANJARMASIN        │ R.210 Banjarmasin
├── BATAM              │ R.201 Batam
├── SEMARANG           │ Bacbone, R.205, Ubiquiti AP
├── MAKASSAR           │ ASTINET, R.237, R.239
├── MUARABARU          │ Quantum RAS, R.240
├── PALEMBANG          │ R.204 Palembang
├── MEDAN              │ R.212 Medan
├── MANADO             │ R.209 Manado
├── SURABAYA           │ Krian, Tambaksawah
└── BALIKPAPAN         │ Bacbone, CS, Distribusi, SW Core
```

Perangkat ditempatkan ke branch yang sesuai dengan kota/lokasi masing-masing.
Grafik dikelompokkan berdasarkan **Graph Template** (`host_grouping_type = 1`).

---

## Menu Utama Cacti

### Console

Halaman utama setelah login. Menampilkan:

- **Peringatan system** — log error, poller tidak jalan, dsb
- **Availability** — ringkasan persentase uptime perangkat
- **Tree Preview** — navigasi cepat ke branch tree
- **User terakhir login**

### Management

| Submenu | Fungsi |
|---------|--------|
| **Devices** | Tambah/edit/hapus perangkat (saat ini 84 device) |
| **Device Templates** | Template perangkat (Mikrotik, Linux, Generic SNMP) |
| **Graphs** | Tambah/hapus grafik untuk perangkat |
| **Graph Trees** | Kelola tree branch per region |
| **Data Sources** | Lihat data source perangkat (.rrd files) |
| **Sites** | Definisi situs/lokasi fisik |

### Create

| Submenu | Fungsi |
|---------|--------|
| **New Graphs** | Tambah grafik baru ke perangkat yang sudah ada |
| **Devices** | Tambah perangkat baru (import atau manual) |

### Templates

| Submenu | Fungsi |
|---------|--------|
| **Graph Templates** | Template grafik (Interface Traffic, CPU, Memory, dll) |
| **Host Templates** | Template perangkat SNMP |
| **Data Templates** | Template data input untuk RRD |
| **Data Queries** | XML-based SNMP data queries |
| **CDEFs** | Custom math functions untuk grafik |
| **VDEFs** | Vertical label definitions |
| **Colors** | Manajemen warna grafik |
| **Color Templates** | Kumpulan warna untuk grafik |
| **GPRINT Presets** | Format tampilan angka di grafik |

### Configuration

| Submenu | Fungsi |
|---------|--------|
| **Settings** | Konfigurasi global Cacti (poller, path, auth, dll) |
| **Collection** | Poller, Data Source Profiles, Availability |
| **Authentication** | Authorization realms, user/group management |
| **Visual** | Themes, graph settings, tree defaults |
| **Weather** | Integrasi data cuaca (jika plugin weatherbug aktif) |

### Users

| Submenu | Fungsi |
|---------|--------|
| **User Management** | Tambah/edit user, grup, permission |
| **User Domains** | Autentikasi via LDAP/AD (terintegrasi) |
| **User Groups** | Grup permission untuk user |

### Utilities

| Submenu | Fungsi |
|---------|--------|
| **System Log** | View `log/cacti.log` |
| **System Utilities** | Maintenance, audit database |
| **Clear Filter** | Reset filter halaman |
| **View Log** | Log user login/logout |
| **Rebuild Poller Cache** | Rebuild cache setelah perubahan massal |
| **Audit Database** | Verifikasi integritas database |
| **Repair Database** | Perbaiki database jika ada masalah |

---

## Plugin

Project ini menggunakan **integrated plugins** (bawaan Cacti, tidak perlu instalasi
terpisah):

| Plugin | Fungsi |
|--------|--------|
| `snmpagent` | SNMP Agent bawaan Cacti |
| `clog` | View log real-time di console |
| `settings` | Settings tambahan |
| `boost` | Performa boost untuk RRD update |
| `dsstats` | Statistik data source |
| `watermark` | Watermark pada grafik |
| `ssl` | Manajemen SSL |
| `ugroup` | User group management |
| `domains` | Autentikasi domain/LDAP |
| `jqueryskin` | Skin jQuery UI |
| `secpass` | Kebijakan password |
| `logrotate` | Rotasi log otomatis |
| `realtime` | Grafik realtime |
| `rrdclean` | Pembersihan RRD file |
| `nectar` | Report engine (email reports) |
| `aggregate` | Aggregate graphs |
| `autom8` | Automation rules |
| `discovery` | Network discovery (auto-add device) |
| `spikekill` | Deteksi dan pembersihan spike data |
| `superlinks` | Super links di grafik |
| `debug` | Debugging tools |

Plugin eksternal (`thold`, `monitor`) dapat diinstal melalui Plugin Management
(`plugins.php`) atau secara manual di folder `plugins/`.

---

## CLI Scripts

Terdapat **47 script CLI** di folder `cli/` untuk otomatisasi:

| Script | Fungsi |
|--------|--------|
| `add_device.php` | Tambah perangkat via CLI |
| `add_graphs.php` | Tambah grafik ke perangkat |
| `add_tree.php` | Kelola tree structure |
| `import_package.php` | Import package template |
| `import_template.php` | Import template XML |
| `upgrade_database.php` | Upgrade database ke versi terbaru |
| `install_cacti.php` | Instalasi CLI (tanpa web wizard) |
| `repair_database.php` | Repair database |
| `plugin_manage.php` | Install/uninstall plugin |
| `rebuild_poller_cache.php` | Rebuild poller cache |
| `poller_replicate.php` | Replikasi poller data |
| `remove_device.php` | Hapus perangkat |
| `reconstruct_tree.php` | Rekonstruksi tree per region |

---

## Troubleshooting

### 1. Poller Tidak Berjalan

**Gejala:** Grafik tidak update, semua data terlihat flat/datar.

**Cek:**
```bash
tail -f log/cacti.log | grep POLLER
```

Pastikan cron job atau systemd service berjalan:
```bash
systemctl status cactid
# atau lihat cron:
crontab -l | grep poller
```

### 2. Permission Error

**Gejala:** Pesan "Log file is not available for writing" atau grafik putih.

**Solusi:**
```bash
chmod -R a+w log/ cache/ rra/
```

### 3. Database Connection Error

**Gejala:** Halaman putih atau error "Database connection failed".

**Cek:**
- Kredensial di `include/config.php`
- MySQL/MariaDB service berjalan
- User database memiliki akses dari host yang sesuai

### 4. SNMP Timeout

**Gejala:** Perangkat muncul dengan "SNMP not performed due to setting or ping result"
atau "Device did not respond to SNMP".

**Cek:**
- SNMP community/credentials benar
- Perangkat dapat di-ping dari server Cacti
- Port 161/162 UDP terbuka di firewall
- SNMP agent berjalan di perangkat

### 5. Grafik Tidak Muncul / Putih

**Cek:**
- RRDtool terinstal: `which rrdtool`
- Path RRDtool di **Settings > Paths** sudah benar
- File .rrd ada di folder `rra/`

### 6. Boost Cache Bermasalah

Jika boost diaktifkan tapi data tidak muncul:

```bash
php cli/poller_output_empty.php
```

### 7. Rebuild Cache Setelah Perubahan Massal

```bash
php cli/rebuild_poller_cache.php
```

### 8. Log Cacti

Semua log ada di `log/`:

| File | Isi |
|------|-----|
| `cacti.log` | Log utama Cacti |
| `install-*.log` | Log instalasi |

Aktifkan debug logging di **Console > Configuration > Settings > General** >
**Log Level** → `DEBUG` untuk troubleshooting detail.

---

## File Penting Referensi

| File | Deskripsi |
|------|-----------|
| `include/config.php.dist` | Template konfigurasi |
| `include/config.php` | Konfigurasi aktual (jangan di-commit) |
| `include/cacti_version` | Versi Cacti (1.2.31) |
| `include/global.php` | Bootstrap utama Cacti |
| `include/plugins.php` | Daftar integrated plugins |
| `cacti.sql` | Full database schema + seed data |
| `lib/api_tree.php` | API untuk manajemen tree |
| `cli/reconstruct_tree.php` | Script rekonstruksi tree per region |
| `service/cactid.service` | Systemd unit untuk Cacti Daemon |
| `template_monitoring/` | Data export/import device |

---

## Lisensi

Project ini menggunakan Cacti yang dirilis di bawah lisensi **GPL-2.0-only**.
Lihat file `LICENSE` untuk informasi lengkap.

Cacti — Copyright (C) 2004-2026 The Cacti Group — https://www.cacti.net/
