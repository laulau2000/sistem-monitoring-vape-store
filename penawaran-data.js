/**
 * =====================================================================
 *  KONFIGURASI HALAMAN PENAWARAN  (dipakai oleh penawaran.html)
 * =====================================================================
 *  Satu halaman penawaran.html dapat dipakai untuk BANYAK klien.
 *  Anda cukup mengubah file ini — tidak perlu menyentuh penawaran.html.
 *
 *  Cara memakai:
 *    penawaran.html                 -> klien default (PENAWARAN_DEFAULT)
 *    penawaran.html?klien=retail    -> memakai preset "retail"
 *    penawaran.html?klien=apotek    -> memakai preset "apotek"
 *
 *  Menambah klien baru:
 *    1. Salin salah satu preset di PENAWARAN_CLIENTS.
 *    2. Ganti key-nya (mis. "bengkel") lalu sesuaikan isinya.
 *    3. Selesai — halaman langsung siap dibuka.
 *
 *  Catatan: field "judul" / "sub" / "*Html" boleh memuat tag sederhana
 *  seperti <em>...</em> atau <strong>...</strong>.
 * =====================================================================
 */

/* Klien yang dipakai bila URL tidak menyebut ?klien=... */
window.PENAWARAN_DEFAULT = 'vape';

/* Data penyedia (Anda). Dipakai semua klien; tiap klien boleh menimpanya. */
window.PENAWARAN_PENYEDIA = {
    nama: 'Lumen Website',
    wa: '+62 81274096898',
    waLink: 'https://wa.me/6281274096898',
    email: 'weekendlau13@gmail.com'
};

/* ============ IKON UNTUK BAGIAN "FITUR" =============================
 * dashboard | cart | print | chart | warehouse | history | product |
 * branch | lock | tag | truck | users | clock | percent | bag
 * Warna: red | blue | amber | purple
 * ===================================================================
 */

window.PENAWARAN_CLIENTS = {

    /* =================================================================
     * 1) PRESET: VAPE STORE  (default)
     * ================================================================= */
    vape: {
        meta: {
            title: 'Penawaran — Sistem Monitoring & Kasir Vape Store "Lumen Website"',
            description: 'Penawaran implementasi sistem monitoring penjualan, kasir (POS), dan manajemen stok untuk jaringan vape store multi-cabang.'
        },
        brand: {
            logoText: 'LW',
            nama: 'Lumen Website',
            sub: 'Sistem Monitoring & Kasir Vape Store'
        },
        topbarCta: 'Hubungi Kami',
        hero: {
            badge: 'Penawaran Implementasi Sistem',
            judul: 'Satu sistem untuk memantau <em>penjualan, kasir,</em> dan <em>stok</em> seluruh cabang vape store Anda.',
            sub: 'Dashboard monitoring penjualan multi-cabang, kasir (POS) cepat dengan cetak struk, laporan laba berbasis HPP, hingga pantauan ketersediaan stok gudang — semuanya dalam satu aplikasi berbasis web yang ringan dan mudah dipakai.',
            ctaUtama: 'Lihat Paket',
            ctaSekunder: 'Jelajahi Fitur',
            stats: [
                { num: 'Multi-Cabang', label: 'Data tiap cabang terisolasi & terkonsolidasi' },
                { num: '1 Klik', label: 'Catat transaksi & cetak struk' },
                { num: 'HPP', label: 'Laba & margin terhitung otomatis' },
                { num: 'Stok', label: 'Pantauan gudang & peringatan restock' }
            ]
        },
        masalah: {
            kicker: 'Mengapa Sistem Ini',
            judul: 'Dari catatan manual menjadi data yang siap diambil keputusan',
            sub: 'Kelola beberapa cabang tanpa harus menunggu rekap manual di akhir bulan.',
            judulBox: 'Tantangan umum',
            list: [
                'Rekap penjualan tiap cabang dicatat terpisah & rawan selisih.',
                'Sulit mengetahui laba asli karena harga beli (HPP) tidak terlacak.',
                'Stok gudang tidak terpantau — barang habis atau menumpuk tanpa disadari.',
                'Laporan bulanan lambat karena harus digabung manual.'
            ]
        },
        solusi: {
            judulBox: 'Dengan sistem ini',
            list: [
                'Omset terkonsolidasi otomatis di dashboard toko induk.',
                'Laba & margin dihitung otomatis dari HPP tiap transaksi.',
                'Ketersediaan stok gudang terpantau lengkap dengan peringatan restock.',
                'Riwayat penjualan rapi, dapat difilter & dicetak ulang struknya.'
            ]
        },
        fitur: {
            kicker: 'Fitur Unggulan',
            judul: 'Semua yang dibutuhkan operasional toko',
            sub: 'Fitur dirancang untuk pengguna kasir maupun pemilik yang memantau dari jauh.',
            items: [
                { icon: 'dashboard', warna: 'red', judul: 'Dashboard Monitoring', desc: 'Ringkasan omset, jumlah transaksi, produk terlaris, dan grafik tren 14 hari serta kontribusi tiap cabang.' },
                { icon: 'cart', warna: 'blue', judul: 'Kasir (POS) Cepat', desc: 'Pilih produk, atur jumlah & diskon, pilih metode bayar (Tunai/QRIS/Transfer), dan hitung kembalian otomatis.' },
                { icon: 'print', warna: 'purple', judul: 'Cetak Struk', desc: 'Struk otomatis muncul setelah transaksi dan dapat dicetak ulang kapan saja dari riwayat penjualan.' },
                { icon: 'chart', warna: 'amber', judul: 'Laporan Laba & Margin', desc: 'Laba kotor dan persentase margin dihitung dari harga beli (HPP), tersaji per periode dan per cabang.' },
                { icon: 'warehouse', warna: 'red', judul: 'Ketersediaan Stok Gudang', desc: 'Pantau jumlah unit, nilai persediaan, serta peringatan otomatis untuk barang menipis dan habis.' },
                { icon: 'history', warna: 'blue', judul: 'Riwayat Penjualan', desc: 'Telusuri transaksi berdasarkan cabang, rentang tanggal, atau nomor nota, lengkap dengan detail item.' },
                { icon: 'product', warna: 'purple', judul: 'Manajemen Produk', desc: 'Kelola kode, nama, kategori, harga jual, dan harga beli (HPP) tiap produk dengan mudah.' },
                { icon: 'branch', warna: 'amber', judul: 'Multi-Cabang & Isolasi Data', desc: 'Staf tiap cabang hanya melihat data cabangnya, sementara pemilik (superadmin) memantau seluruh cabang.' },
                { icon: 'lock', warna: 'blue', judul: 'Hak Akses & Keamanan', desc: 'Peran pengguna (superadmin/cabang) dengan otentikasi login dan penyimpanan kata sandi ter-enkripsi.' }
            ]
        },
        preview: {
            kicker: 'Tampilan Aplikasi',
            judul: 'Lihat sekilas tampilan sistem yang akan Anda pakai',
            sub: 'Capture langsung dari aplikasi — dashboard, kasir, stok, dan manajemen produk yang siap pakai.',
            items: [
                { img: 'img/penawaran/capture-dashboard.png', badge: 'Dashboard', judul: 'Dashboard Monitoring', desc: 'Ringkasan omset, laba kotor, dan kontribusi tiap cabang dalam satu layar.' },
                { img: 'img/penawaran/capture-pos.png', badge: 'Kasir', judul: 'Kasir (POS) Cepat', desc: 'Pilih produk, atur diskon & metode bayar, lalu cetak struk dalam hitungan detik.' },
                { img: 'img/penawaran/capture-stok.png', badge: 'Stok', judul: 'Ketersediaan Stok Gudang', desc: 'Pantau jumlah unit, nilai persediaan, dan peringatan barang menipis secara real-time.' },
                { img: 'img/penawaran/capture-owner.png', badge: 'Owner', judul: 'Ringkasan Owner', desc: 'Pantau cepat omzet harian, estimasi laba, dan metode pembayaran dalam tab khusus.' },
                { img: 'img/penawaran/capture-produk.png', badge: 'Produk', judul: 'Manajemen Produk', desc: 'Kelola katalog produk lengkap dengan foto, harga jual, HPP, dan status stok.' }
            ]
        },
        paket: {
            kicker: 'Paket & Investasi',
            judul: 'Pilih paket sesuai skala bisnis Anda',
            sub: 'Semua paket sudah mencakup aplikasi siap pakai, konfigurasi awal, dan pendampingan penggunaan.',
            items: [
                {
                    nama: 'Basic', desc: 'Untuk toko tunggal yang baru beralih ke sistem digital.',
                    harga: 'Rp 9.500.000', satuan: 'sekali bayar',
                    fitur: ['1 cabang', 'Dashboard & kasir (POS)', 'Riwayat penjualan & cetak struk', 'Manajemen produk']
                },
                {
                    nama: 'Business', desc: 'Untuk jaringan beberapa cabang yang ingin terpantau terpusat.',
                    harga: 'Rp 18.500.000', satuan: 'sekali bayar',
                    tag: 'Paling Populer', featured: true,
                    fitur: ['Hingga 5 cabang', 'Semua fitur Basic', 'Ketersediaan stok gudang', 'Laporan laba & margin (HPP)', 'Isolasi data per cabang & hak akses']
                },
                {
                    nama: 'Enterprise', desc: 'Untuk bisnis dengan cabang banyak & kebutuhan khusus.',
                    harga: 'Rp 26.500.000', satuan: 'sekali bayar / sesuai kebutuhan',
                    fitur: ['Cabang tak terbatas', 'Semua fitur Business', 'Penyesuaian fitur & branding', 'Prioritas dukungan & pendampingan']
                }
            ],
            catatan: '* Harga sekali bayar (one-time). Termasuk instalasi, konfigurasi awal, pelatihan staf, dan garansi perbaikan bug 3 bulan; belum termasuk perangkat (PC/printer struk). Maintenance & update opsional Rp 1.500.000/tahun.'
        },
        alur: {
            kicker: 'Alur Implementasi',
            judul: 'Dari kesepakatan sampai siap dipakai',
            items: [
                { judul: 'Konsultasi', desc: 'Kami pelajari kebutuhan cabang, produk, dan alur kerja toko Anda.' },
                { judul: 'Setup & Konfigurasi', desc: 'Sistem dipasang, cabang & pengguna didaftarkan, data awal dimuat.' },
                { judul: 'Pelatihan', desc: 'Staf kasir dan pemilik dibimbing menggunakan dashboard & POS.' },
                { judul: 'Go-Live', desc: 'Aplikasi digunakan penuh dengan pendampingan pada masa awal.' }
            ]
        },
        kontak: {
            judul: 'Siap memodernisasi penjualan vape store Anda?',
            sub: 'Mari diskusikan kebutuhan toko Anda. Kami siap membantu dari pemasangan hingga pelatihan staf.',
            ctaUtama: 'Chat WhatsApp',
            ctaSekunder: 'Kirim Email'
        },
        footer: {
            nama: 'Lumen Website',
            sub: 'Sistem Monitoring & Kasir Vape Store',
            catatan: 'Halaman penawaran ini dapat diakses bebas tanpa login.'
        }
    },

    /* =================================================================
     * 2) PRESET: TOKO RETAIL / SEMBAKO  (contoh)
     * ================================================================= */
    retail: {
        meta: {
            title: 'Penawaran — Sistem Kasir & Stok Toko Retail',
            description: 'Penawaran implementasi sistem kasir (POS), stok, dan laporan penjualan untuk toko retail, sembako, dan minimarket.'
        },
        brand: {
            logoText: 'TS',
            nama: 'Toko Sembako Sejahtera',
            sub: 'Sistem Kasir, Stok & Laporan'
        },
        topbarCta: 'Hubungi Kami',
        hero: {
            badge: 'Penawaran Implementasi Sistem',
            judul: 'Kelola <em>penjualan, stok,</em> dan <em>laba</em> toko Anda dalam satu aplikasi.',
            sub: 'Kasir cepat dengan barcode, stok otomatis berkurang tiap transaksi, peringatan barang menipis, dan laporan penjualan harian yang bisa dicetak — tanpa perlu perangkat mahal.',
            ctaUtama: 'Lihat Paket',
            ctaSekunder: 'Jelajahi Fitur',
            stats: [
                { num: 'Cepat', label: 'Transaksi kilat dengan pencarian produk' },
                { num: 'Otomatis', label: 'Stok berkurang sendiri tiap penjualan' },
                { num: 'Harian', label: 'Laporan penjualan siap dicetak' },
                { num: 'Hemat', label: 'Tanpa biaya langganan bulanan' }
            ]
        },
        masalah: {
            kicker: 'Mengapa Sistem Ini',
            judul: 'Berhenti mencatat penjualan di buku, mulai pakai data',
            sub: 'Cocok untuk toko retail, sembako, kelontong, dan minimarket.',
            judulBox: 'Tantangan umum',
            list: [
                'Harga barang sering ditanya pelanggan dan harus dicek satu per satu.',
                'Stok fisik tidak cocok dengan catatan — barang hilang tanpa jejak.',
                'Tidak tahu produk mana yang paling laku dan paling menguntungkan.',
                'Hitung uang di akhir hari sering selisih.'
            ]
        },
        solusi: {
            judulBox: 'Dengan sistem ini',
            list: [
                'Harga dan stok muncul otomatis saat produk dipilih.',
                'Stok berkurang otomatis dan peringatan muncul saat barang menipis.',
                'Laporan produk terlaris membantu menentukan stok berikutnya.',
                'Total transaksi & kembalian dihitung sistem, uang lebih mudah dicocokkan.'
            ]
        },
        fitur: {
            kicker: 'Fitur Unggulan',
            judul: 'Peralatan lengkap untuk toko sehari-hari',
            sub: 'Dirancang sederhana agar staf baru pun bisa langsung memakai.',
            items: [
                { icon: 'cart', warna: 'blue', judul: 'Kasir (POS) Kilat', desc: 'Cari produk dengan nama atau kode, masukkan keranjang, dan simpan transaksi dalam hitungan detik.' },
                { icon: 'print', warna: 'purple', judul: 'Cetak Struk', desc: 'Struk belanja tercetak otomatis dan bisa dicetak ulang kapan saja dari riwayat penjualan.' },
                { icon: 'warehouse', warna: 'red', judul: 'Stok Otomatis', desc: 'Stok berkurang sendiri setiap penjualan dengan peringatan barang menipis dan habis.' },
                { icon: 'chart', warna: 'amber', judul: 'Laporan Laba', desc: 'Laba kotor dan margin dihitung dari harga beli, tersaji per hari, per periode, dan per produk.' },
                { icon: 'product', warna: 'purple', judul: 'Manajemen Produk', desc: 'Atur kode, nama, kategori, harga jual, dan harga beli tiap barang dengan mudah.' },
                { icon: 'history', warna: 'blue', judul: 'Riwayat Penjualan', desc: 'Telusuri transaksi berdasarkan tanggal atau nomor nota, lengkap dengan rincian barang.' },
                { icon: 'truck', warna: 'amber', judul: 'Barang Masuk', desc: 'Catat barang datang dari supplier; stok dan harga beli diperbarui otomatis.' },
                { icon: 'users', warna: 'blue', judul: 'Multi-Pengguna', desc: 'Pisahkan akun pemilik dan kasir dengan hak akses masing-masing.' },
                { icon: 'lock', warna: 'red', judul: 'Aman & Terkunci', desc: 'Akses dilindungi login dengan kata sandi ter-enkripsi.' }
            ]
        },
        preview: {
            kicker: 'Tampilan Aplikasi',
            judul: 'Lihat sekilas tampilan sistem yang akan Anda pakai',
            sub: 'Capture langsung dari aplikasi — kasir, stok, laporan, dan manajemen produk yang siap pakai.',
            items: [
                { img: 'img/penawaran/capture-dashboard.png', badge: 'Dashboard', judul: 'Dashboard Monitoring', desc: 'Ringkasan omset, jumlah transaksi, dan produk terlaris dalam satu layar.' },
                { img: 'img/penawaran/capture-pos.png', badge: 'Kasir', judul: 'Kasir (POS) Kilat', desc: 'Cari produk, masukkan keranjang, pilih metode bayar, dan cetak struk dalam hitungan detik.' },
                { img: 'img/penawaran/capture-stok.png', badge: 'Stok', judul: 'Stok Otomatis', desc: 'Stok berkurang sendiri tiap penjualan dengan peringatan barang menipis dan habis.' },
                { img: 'img/penawaran/capture-owner.png', badge: 'Owner', judul: 'Ringkasan Owner', desc: 'Pantau cepat omzet harian, estimasi laba, dan metode pembayaran.' },
                { img: 'img/penawaran/capture-produk.png', badge: 'Produk', judul: 'Manajemen Produk', desc: 'Kelola katalog produk lengkap dengan foto, harga jual, HPP, dan status stok.' }
            ]
        },
        paket: {
            kicker: 'Paket & Investasi',
            judul: 'Pilih paket sesuai ukuran toko Anda',
            sub: 'Semua paket sudah mencakup aplikasi siap pakai, konfigurasi awal, dan pendampingan penggunaan.',
            items: [
                {
                    nama: 'Basic', desc: 'Untuk satu toko dengan satu kasir.',
                    harga: 'Rp 6.500.000', satuan: 'sekali bayar',
                    fitur: ['1 toko & 1 kasir', 'Kasir (POS) & cetak struk', 'Manajemen produk & stok', 'Laporan penjualan harian']
                },
                {
                    nama: 'Business', desc: 'Untuk toko dengan beberapa kasir dan gudang.',
                    harga: 'Rp 12.500.000', satuan: 'sekali bayar',
                    tag: 'Paling Populer', featured: true,
                    fitur: ['Sampai 5 pengguna', 'Semua fitur Basic', 'Pencatatan barang masuk', 'Laporan laba & margin', 'Peringatan stok menipis']
                },
                {
                    nama: 'Enterprise', desc: 'Untuk jaringan toko atau minimarket banyak cabang.',
                    harga: 'Rp 22.500.000', satuan: 'sekali bayar / sesuai kebutuhan',
                    fitur: ['Cabang tak terbatas', 'Semua fitur Business', 'Transfer barang antar cabang', 'Surat jalan otomatis', 'Prioritas dukungan']
                }
            ],
            catatan: '* Harga sekali bayar (one-time). Termasuk instalasi, konfigurasi awal, pelatihan staf, dan garansi perbaikan bug 3 bulan; belum termasuk perangkat (PC/printer struk). Maintenance & update opsional Rp 1.500.000/tahun.'
        },
        alur: {
            kicker: 'Alur Implementasi',
            judul: 'Dari kesepakatan sampai toko jalan dengan sistem',
            items: [
                { judul: 'Konsultasi', desc: 'Kami pelajari jenis barang, jumlah kasir, dan alur kerja toko Anda.' },
                { judul: 'Input Data Barang', desc: 'Data produk, harga, dan stok awal dimasukkan ke sistem.' },
                { judul: 'Pelatihan Kasir', desc: 'Staf dilatih melakukan transaksi, cetak struk, dan cek stok.' },
                { judul: 'Go-Live', desc: 'Toko mulai berjalan penuh dengan pendampingan masa awal.' }
            ]
        },
        kontak: {
            judul: 'Siap membuat toko Anda lebih rapi?',
            sub: 'Mari diskusikan kebutuhan toko Anda. Kami bantu dari pemasangan sampai pelatihan staf.',
            ctaUtama: 'Chat WhatsApp',
            ctaSekunder: 'Kirim Email'
        },
        footer: {
            nama: 'Toko Sembako Sejahtera',
            sub: 'Sistem Kasir, Stok & Laporan',
            catatan: 'Halaman penawaran ini dapat diakses bebas tanpa login.'
        }
    },

    /* =================================================================
     * 3) PRESET: APOTEK / TOKO OBAT  (contoh)
     * ================================================================= */
    apotek: {
        meta: {
            title: 'Penawaran — Sistem Kasir & Stok Apotek',
            description: 'Penawaran implementasi sistem kasir, stok obat, dan laporan penjualan untuk apotek dan toko obat.'
        },
        brand: {
            logoText: 'AS',
            nama: 'Apotek Sehat Sentosa',
            sub: 'Sistem Kasir, Stok & Laporan Apotek'
        },
        topbarCta: 'Hubungi Kami',
        hero: {
            badge: 'Penawaran Implementasi Sistem',
            judul: 'Kelola <em>penjualan, stok obat,</em> dan <em>kedaluwarsa</em> dengan tertib.',
            sub: 'Kasir apotek yang cepat, stok obat terpantau per batch, peringatan stok menipis, dan laporan penjualan yang siap diperiksa kapan saja.',
            ctaUtama: 'Lihat Paket',
            ctaSekunder: 'Jelajahi Fitur',
            stats: [
                { num: 'Tertib', label: 'Stok obat tercatat rapi per item' },
                { num: 'Cepat', label: 'Pelayanan resep & bebas tanpa antre lama' },
                { num: 'Pantau', label: 'Peringatan stok menipis otomatis' },
                { num: 'Laporan', label: 'Rekap harian & bulanan siap cetak' }
            ]
        },
        masalah: {
            kicker: 'Mengapa Sistem Ini',
            judul: 'Pelayanan cepat dengan catatan stok yang bisa dipercaya',
            sub: 'Membantu apotek menjaga ketersediaan obat dan ketertiban administrasi.',
            judulBox: 'Tantangan umum',
            list: [
                'Stok obat di rak tidak cocok dengan catatan kartu stok.',
                'Obat menipis atau habis baru diketahui saat dicari pelanggan.',
                'Sulit merekap penjualan harian dan memisahkan obat generik atau merek.',
                'Laporan penjualan bulanan disusun lambat dan rawan salah.'
            ]
        },
        solusi: {
            judulBox: 'Dengan sistem ini',
            list: [
                'Stok berkurang otomatis tiap penjualan sehingga catatan selalu sinkron.',
                'Peringatan stok menipis muncul lebih awal untuk rencana pemesanan.',
                'Rekap penjualan per item dan per kategori tersedia otomatis.',
                'Laporan siap dicetak kapan saja untuk pemeriksaan internal.'
            ]
        },
        fitur: {
            kicker: 'Fitur Unggulan',
            judul: 'Fitur yang mendukung pelayanan apotek',
            sub: 'Dirancang agar penyerahan obat dan pencatatan berjalan cepat.',
            items: [
                { icon: 'cart', warna: 'blue', judul: 'Kasir (POS) Cepat', desc: 'Pencarian obat berdasarkan nama atau kode, langsung masuk keranjang dan siap dibayar.' },
                { icon: 'print', warna: 'purple', judul: 'Cetak Struk', desc: 'Bukti pembelian tercetak otomatis dan dapat dicetak ulang dari riwayat penjualan.' },
                { icon: 'warehouse', warna: 'red', judul: 'Stok Obat Terpantau', desc: 'Jumlah unit dan nilai persediaan terlihat jelas, dengan peringatan stok menipis dan habis.' },
                { icon: 'product', warna: 'purple', judul: 'Katalog Obat & Kategori', desc: 'Kelompokkan produk per kategori agar pencarian dan pelaporan lebih mudah.' },
                { icon: 'history', warna: 'blue', judul: 'Riwayat Penjualan', desc: 'Telusuri transaksi per tanggal atau nomor nota lengkap dengan rincian item.' },
                { icon: 'chart', warna: 'amber', judul: 'Laporan Laba & Margin', desc: 'Laba kotor dan margin dihitung dari harga beli tiap item yang terjual.' },
                { icon: 'truck', warna: 'amber', judul: 'Penerimaan Barang', desc: 'Catat obat datang dari distributor; stok dan harga beli diperbarui otomatis.' },
                { icon: 'users', warna: 'blue', judul: 'Multi-Pengguna', desc: 'Pisahkan akun apoteker, kasir, dan pemilik dengan hak akses masing-masing.' },
                { icon: 'lock', warna: 'red', judul: 'Akses Terlindungi', desc: 'Setiap pengguna masuk dengan akun sendiri berkata sandi ter-enkripsi.' }
            ]
        },
        preview: {
            kicker: 'Tampilan Aplikasi',
            judul: 'Lihat sekilas tampilan sistem yang akan Anda pakai',
            sub: 'Capture langsung dari aplikasi — kasir, stok obat, laporan, dan manajemen produk yang siap pakai.',
            items: [
                { img: 'img/penawaran/capture-dashboard.png', badge: 'Dashboard', judul: 'Dashboard Monitoring', desc: 'Ringkasan omset, jumlah transaksi, dan produk terlaris dalam satu layar.' },
                { img: 'img/penawaran/capture-pos.png', badge: 'Kasir', judul: 'Kasir (POS) Cepat', desc: 'Cari obat berdasarkan nama atau kode, masukkan keranjang, dan cetak struk.' },
                { img: 'img/penawaran/capture-stok.png', badge: 'Stok', judul: 'Stok Obat Terpantau', desc: 'Jumlah unit dan nilai persediaan terlihat jelas dengan peringatan stok menipis.' },
                { img: 'img/penawaran/capture-owner.png', badge: 'Owner', judul: 'Ringkasan Owner', desc: 'Pantau cepat omzet harian, estimasi laba, dan metode pembayaran.' },
                { img: 'img/penawaran/capture-produk.png', badge: 'Produk', judul: 'Katalog Obat & Kategori', desc: 'Kelompokkan produk per kategori agar pencarian dan pelaporan lebih mudah.' }
            ]
        },
        paket: {
            kicker: 'Paket & Investasi',
            judul: 'Pilih paket sesuai skala apotek Anda',
            sub: 'Semua paket sudah mencakup aplikasi siap pakai, konfigurasi awal, dan pendampingan penggunaan.',
            items: [
                {
                    nama: 'Basic', desc: 'Untuk apotek dengan satu meja pelayanan.',
                    harga: 'Rp 9.500.000', satuan: 'sekali bayar',
                    fitur: ['1 apotek & 1 kasir', 'Kasir (POS) & cetak struk', 'Katalog obat & stok', 'Laporan penjualan harian']
                },
                {
                    nama: 'Business', desc: 'Untuk apotek dengan beberapa petugas dan gudang obat.',
                    harga: 'Rp 18.500.000', satuan: 'sekali bayar',
                    tag: 'Paling Populer', featured: true,
                    fitur: ['Sampai 5 pengguna', 'Semua fitur Basic', 'Penerimaan barang dari distributor', 'Laporan laba & margin', 'Peringatan stok menipis']
                },
                {
                    nama: 'Enterprise', desc: 'Untuk jaringan apotek dengan beberapa cabang.',
                    harga: 'Rp 26.500.000', satuan: 'sekali bayar / sesuai kebutuhan',
                    fitur: ['Cabang tak terbatas', 'Semua fitur Business', 'Transfer barang antar cabang', 'Surat jalan otomatis', 'Prioritas dukungan']
                }
            ],
            catatan: '* Harga sekali bayar (one-time). Termasuk instalasi, konfigurasi awal, pelatihan staf, dan garansi perbaikan bug 3 bulan; belum termasuk perangkat (PC/printer struk). Maintenance & update opsional Rp 1.500.000/tahun.'
        },
        alur: {
            kicker: 'Alur Implementasi',
            judul: 'Dari kesepakatan sampai apotek siap memakai sistem',
            items: [
                { judul: 'Konsultasi', desc: 'Kami pelajari jenis obat, kategori, dan alur pelayanan apotek Anda.' },
                { judul: 'Input Data Obat', desc: 'Data obat, kategori, harga, dan stok awal dimasukkan ke sistem.' },
                { judul: 'Pelatihan Petugas', desc: 'Petugas dilatih melayani penjualan, cetak struk, dan cek stok.' },
                { judul: 'Go-Live', desc: 'Apotek mulai memakai sistem secara penuh dengan pendampingan.' }
            ]
        },
        kontak: {
            judul: 'Siap menertibkan administrasi apotek Anda?',
            sub: 'Mari diskusikan kebutuhan apotek Anda. Kami bantu dari pemasangan sampai pelatihan petugas.',
            ctaUtama: 'Chat WhatsApp',
            ctaSekunder: 'Kirim Email'
        },
        footer: {
            nama: 'Apotek Sehat Sentosa',
            sub: 'Sistem Kasir, Stok & Laporan Apotek',
            catatan: 'Halaman penawaran ini dapat diakses bebas tanpa login.'
        }
    }

};
