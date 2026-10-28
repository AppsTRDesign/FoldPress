<div align="center">

<img src="banners/github-social-1280x640-tr.png" alt="FoldPress — WordPress'in eksik dosya yöneticisi" width="100%">

# FoldPress

**WordPress'in eksik olan dosya yöneticisi.**
Klasörler, sürükle bırak, toplu işlemler, ZIP / UNZIP, WebP / AVIF, tam donanımlı kod düzenleyici, Google Drive tarzı önizlemeler ve bulut içe aktarma — tek, hızlı, güvenli ve tamamen AJAX bir arayüzde.

[English](README.md) · **Türkçe**

![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759b?logo=wordpress&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php&logoColor=white)
![Lisans](https://img.shields.io/badge/Lisans-GPLv2%2B-16a34a)
![Diller](https://img.shields.io/badge/i18n-EN%20%7C%20TR-4f46e5)

</div>

---

## İçindekiler

- [Neden FoldPress](#neden-foldpress)
- [Özellikler](#özellikler)
- [Ekran görüntüleri](#ekran-görüntüleri)
- [Kurulum](#kurulum)
- [Klavye kısayolları](#klavye-kısayolları)
- [Bulut içe aktarma kurulumu](#bulut-içe-aktarma-kurulumu)
- [Güvenlik](#güvenlik)
- [Harici servisler](#harici-servisler)
- [Sık sorulan sorular](#sık-sorulan-sorular)
- [Değişiklik günlüğü](#değişiklik-günlüğü)
- [Lisans](#lisans)

## Neden FoldPress

WordPress ortam kütüphanesi düz bir listedir. Site birkaç yüz dosyaya ulaştığında bir şey bulmak zorlaşır, düzenlemek imkânsız hale gelir ve her rutin iş (yeniden adlandırma, dönüştürme, temizlik) için ayrı bir eklenti gerekir. FoldPress; **Ortam Kütüphanesi**, **Yeni Ortam Ekle**, **ortam düzenleme ekranı** ve **Tema / Eklenti Dosya Düzenleyicileri** yerine tek, modern, tutarlı ve mobil uyumlu bir arayüz getirir.

## Özellikler

### Dosya yöneticisi
- Bağlantı çizgileri ve canlı dosya sayılarıyla **sınırsız iç içe klasör** ağacı. Klasörler sanaldır; dosya adresleri asla değişmez.
- Dosyaları klasörlere, klasörleri klasörlere ve bilgisayarınızdan dosyaları doğrudan herhangi bir klasöre **sürükle bırak**.
- Tıklama, `Ctrl` + tık, `Shift` + tık ve `Ctrl + A` ile **çoklu seçim**. Izgara ve liste görünümünde üç durumlu onay kutuları.
- **Toplu işlemler**: taşı, kes & yapıştır, ZIP'e sıkıştır, indir, sil.
- **Klasör seçimi**: birden fazla klasörü (veya tümünü) seçip taşıyın, ZIP'leyin (yapı korunur) veya silin.
- Her yerde **sağ tık menüsü**: önizle, ayrıntılar, yeniden adlandır, taşı, kes, ZIP, ZIP aç, URL kopyala, indir, sil, yeni klasör, **yeni dosya**.
- Menüden **dosya oluşturma** (txt, md, css, js, json, csv…). Uzantı, oluşturulmadan önce izinli türlerle karşılaştırılır.
- **Izgara / liste görünümü**, **türe göre gruplama**, arama, sıralama, "daha fazla yükle" veya numaralı sayfalama.
- Tüm çekirdek alanları içeren **ayrıntılar paneli**: yüklenme tarihi / yükleyen, yüklendiği yer, dosya adı, tür, boyut, ölçüler, alternatif metin, başlık, altyazı, açıklama, URL.
- Animasyonlu ilerleme çubuğu, yüzde sayacı, yükleme hızı, kalan süre ve dosya bazlı durumla **satır içi yükleme kartı**.
- Sunucuda **ZIP / UNZIP**. Arşivler yapısını koruyan klasörlere çıkarılır; yalnızca izinli türler alınır.

### Önizlemeler (Google Drive tarzı)
- Küçük resim şeridi, yakınlaştırma, klavye ve kaydırma ile görseller.
- **Word, Excel, PowerPoint, OpenDocument** dosyaları kendi sunucunuzda işlenir (üçüncü taraf yok): sayfalar, tablolar, tüm sayfalar (sheet), tüm slaytlar.
- Markdown, satır numaralı metin / kod, tablo olarak CSV, ZIP dosyalarının içerik listesi.
- Herkese açık siteler için isteğe bağlı **Microsoft Office tam düzen** görünümü.
- **Özel video ve ses oynatıcı**: önizlemeli ilerleme çubuğu, hız, döngü, resim içinde resim, tam ekran, klavye kısayolları ve canlı ses spektrum görselleştirici.

### Gelişmiş düzenleyici
- Ortam düzenleme ekranının yerini temiz, iki sütunlu bir düzenleyici alır.
- **Fiziksel yeniden adlandırma**: dosyayı ve tüm boyutlarını diskte yeniden adlandırır, **veritabanındaki tüm referansları günceller** — yazılar, sayfalar, bloklar, Elementor JSON, WooCommerce galerileri, bileşenler, tema ayarları, seçenekler, terim ve kullanıcı meta verileri (serileştirme güvenli).
- **Değişiklik geçmişi** ve tek tıkla eski adlara dönüş.
- **Kullanıldığı yerler**: dosyayı ortam ID'si ve adresiyle içeriklerde, galerilerde, özel alanlarda, Elementor'da, WooCommerce'te, kullanıcılarda, kategorilerde ve site ayarlarında bulur — filtreler, durum rozetleri ve düzenle / görüntüle bağlantılarıyla.
- Oluşturulan boyutlar listesi, kırp & döndür, neyin iptal edileceğini listeleyen kaydedilmemiş değişiklik koruması.

### Kod düzenleyici (Tema ve Eklenti Dosya Düzenleyicilerinin yerine)
- Dosya ağacı, sekmeler, yol çubuğu, durum çubuğu, açık / koyu tema, tam ekran modu.
- **PHP, HTML, CSS / SCSS / LESS, JavaScript / TypeScript / JSX, JSON, XML / SVG, Markdown, SQL, YAML, Shell, Diff, Java, C#, C / C++** ve daha fazlası için sözdizimi renklendirme.
- Bul & değiştir, satıra git, yorum satırı, parantez ve etiket eşleştirme, CSS / JS / JSON / HTML denetimi.
- Dosyadaki her WordPress işlevi için **belge arama** ("Tanıma bak").
- **Güvenli kaydetme**: hiçbir şey yazılmadan önce tam PHP sözdizimi denetimi yapılır; sunucu izin verdiğinde WordPress'in kendi ölümcül hata koruması da kullanılır. Gerçek ölümcül hatada dosya geri alınır ve hatalı satır gösterilir; loopback'i engelleyen sunucularda geçerli kod yine kaydedilir. Her sonuç modal ile gösterilir.
- Ortam kütüphanesindeki metin dosyalarını da düzenleyin.

### Optimizasyon
- Yüklemede ve mevcut ortam dosyalarında **WebP / AVIF dönüştürme**; Düşük / Orta / Yüksek / Özel kalite.
- Her orijinal **yedeklenir**; tek tıkla tüm orijinaller geri yüklenir, dönüştürülen dosyalar silinir ve yedek klasörü temizlenir.
- Büyük görsel eşiği, JPEG kalitesi, görsel boyutları, ek boyutları açma / kapatma.

### Yüklemeler ve dosya türleri
- **Dosya Türleri listesi tek kuraldır**: açık türler yüklenir, kapalı türler her yerde reddedilir. MIME türü ve aç / kapat anahtarıyla özel türler ekleyin.
- Çalıştırılabilir ve betik sayfası türleri (PHP, HTML, SVG…) her zaman engellidir. Sunucu kodu içeren dosyalar reddedilir.
- Yükleme klasörü yapısı: yıl / ay, tek klasör, özel klasör veya FoldPress klasörlerini yansıtma.
- Türkçe / aksanlı dosya adlarını temizleme, küçük harf, boyut sınırı.

### Bulut içe aktarma
- **Google Drive** (Dokümanlar / E-Tablolar / Slaytlar DOCX / XLSX / PPTX olarak), **Dropbox**, **OneDrive**.
- **WebDAV / kendi bulutunuz**: Nextcloud, ownCloud, Synology, QNAP, pCloud, Yandex Disk, Seafile… dahili dosya tarayıcısıyla.
- **URL'den içe aktarma** (yalnızca herkese açık bağlantılar). Yönlendirmeler ve "indirmeniz başlayacak" sayfaları takip edilir; gerçek dosya adı ve türü sunucudan alınır, böylece `/download?id=5` gibi bağlantılar da çalışır.
- **Canlı içe aktarma kuyruğu**: kuyruktaki her dosya yükleme kartında sunucu indirme ilerlemesi, hız ve kalan süreyle listelenir. Tek dosyayı ya da tüm kuyruğu iptal edin — indirme sunucuda da durur.

### Ortam penceresi (Ortam ekle, öne çıkarılmış görsel, galeri)
- Arama, açılır-kapanır alt klasörler, dosya sayıları ve sürükle bırakla taşıma içeren **klasör paneli**.
- **"Yeni yüklemeler"** hedef klasörü gösterir; pencereden çıkmadan klasör oluşturun.
- Belgeler, arşivler ve kod için **FoldPress dosya simgeleri**; her dosyada hızlı **önizle**, **URL kopyala** ve **gelişmiş düzenleme**.
- Yeniden tasarlanan **Adresten ekle**: **Ortam kütüphanesine indir** ve canlı ilerleme.
- Ek dosya bilgileri, galeri ve oynatma listesi ayarlarında yenilenen alanlar, seçim kutuları, onay kutuları ve hizalama düğmeleri.

### Ayarlar
- Sekmeli ayarlar sayfası: Genel, Yüklemeler, Dosya Türleri, Görsel Boyutları, WebP / AVIF, Bulut, Sistem.
- Kaynak, alan adı ve yönlendirme URI'si için kopyalama düğmeli **adım adım bulut kurulumu** ve WebDAV bağlantı testi.
- **Kaydedilmemiş değişiklik koruması**: alt çubuk ve değişen her ayarı adıyla, eski ve yeni değeriyle listeleyen iptal penceresi.

### Her yerde
- Tamamen **AJAX**, sayfa yenileme yok. Tarayıcı uyarıları yerine özel modal ve bildirimler (bildirim konumu ayarlanabilir).
- **Ortam penceresi entegrasyonu**: *Ortam ekle*, öne çıkan görsel seçici ve blok düzenleyicide klasör paneli ve FoldPress tasarımı.
- Duyarlı ve dokunmatik dostu, klavye ile erişilebilir.
- CSS ve JS için izole `afp-` ad alanı — WordPress veya diğer eklentilerle çakışma yok. Üçüncü taraf JavaScript paketlenmez.
- Çeviriye hazır; **Türkçe dahil** (600+ ifade).

## Ekran görüntüleri

| | |
|---|---|
| ![Dosya yöneticisi](screenshots/tr/01-file-manager.png) **Dosya yöneticisi** — klasör ağacı, ızgara, gruplar | ![Liste ve seçim](screenshots/tr/02-list-select.png) **Liste görünümü** — üç durumlu seçim ve toplu işlem çubuğu |
| ![Ayrıntılar](screenshots/tr/03-details.png) **Ayrıntılar paneli** — tüm alanlar, geçmiş rozeti | ![Sağ tık menüsü](screenshots/tr/04-context-menu.png) Kısayollu **sağ tık menüsü** |
| ![Yükleme](screenshots/tr/05-upload.png) **Yükleme kartı** — hız, kalan süre, WebP dönüştürme | ![Belge önizleme](screenshots/tr/06-preview-document.png) Sunucunuzda işlenen **Word önizlemesi** |
| ![Tablo önizleme](screenshots/tr/07-preview-sheet.png) Sayfa sekmeli **Excel önizlemesi** | ![Ses oynatıcı](screenshots/tr/08-audio-player.png) Görselleştiricili **ses oynatıcı** |
| ![Kısayollar](screenshots/tr/09-shortcuts.png) **Kısayollar ve yardım** | ![Geçmiş](screenshots/tr/10-history.png) Geri yüklemeli **değişiklik geçmişi** |
| ![Kod düzenleyici](screenshots/tr/11-code-editor.png) Tema / eklenti düzenleyicilerinin yerini alan **kod düzenleyici** | ![Gelişmiş düzenleme](screenshots/tr/12-advanced-edit.png) *Kullanıldığı yerler* ile **gelişmiş düzenleme** |
| ![İçe aktarma kuyruğu](screenshots/tr/13-import-queue.png) **İçe aktarma kuyruğu** — canlı ilerleme, hız, kalan süre | ![Ortam ekle](screenshots/tr/14-media-modal.png) **Ortam ekle** — klasör paneli ve hızlı işlemler |
| ![Adresten ekle](screenshots/tr/15-insert-from-url.png) **Adresten ekle** — kütüphaneye indirme | ![Galeri](screenshots/tr/16-gallery-settings.png) **Galeri düzenleme** — yenilenen ayarlar |
| ![Bulut ayarları](screenshots/tr/17-settings-cloud.png) **Bulut ayarları** — adım adım kurulum | ![Kaydedilmemiş değişiklikler](screenshots/tr/18-settings-unsaved.png) **Kaydedilmemiş değişiklikler** — her ayar adıyla |

## Kurulum

1. Eklenti ZIP'ini indirin (veya bu depoyu `wp-content/plugins/appstrdesign-foldpress` içine klonlayın).
2. **Eklentiler › Yeni Ekle › Eklenti Yükle**, ZIP'i seçin, **Şimdi Kur**, **Etkinleştir**.
3. **Ortamlar › Kütüphane**'yi açın — FoldPress otomatik açılır. Ayarlar yönetim menüsünde **FoldPress** altındadır.

Gereksinimler: WordPress 6.0+, PHP 7.4+. ZIP özellikleri için ZipArchive; dönüştürme için WebP / AVIF destekli GD veya Imagick.

## Klavye kısayolları

| Tuşlar | İşlem |
|---|---|
| `Ctrl` + `A` | Sayfadaki tüm dosyaları seç |
| `Ctrl` + tık / `Shift` + tık | Seçime ekle / aralık seç |
| `Ctrl` + `X` / `Ctrl` + `V` | Kes / açık klasöre yapıştır (taşı) |
| `M` | Seçimi bir klasöre taşı |
| `F2` | Yeniden adlandır |
| `Delete` | Seçimi sil |
| `P` / `Enter` | Önizle / ayrıntılar |
| `N` / `U` | Yeni klasör / yükle |
| `/` | Ara |
| `?` | Kısayol yardımı |
| Kod düzenleyici: `Ctrl` + `S`, `Ctrl` + `F`, `Ctrl` + `H`, `Ctrl` + `G`, `Ctrl` + `/`, `F11` | Kaydet, bul, değiştir, satıra git, yorum, tam ekran |
| Oynatıcı: `Boşluk` / `K`, `J` / `L`, `↑` / `↓`, `M`, `F` | Oynat/duraklat, ±10 sn, ses, sessiz, tam ekran |

## Bulut içe aktarma kurulumu

**FoldPress › Ayarlar › Bulut** bölümünü açın. Her servis adım adım talimatları ve kopyalanacak kaynak / alan adı / yönlendirme URI'sini gösterir.

| Servis | Gerekenler |
|---|---|
| Google Drive | API anahtarı, OAuth istemci kimliği, proje numarası (App ID). *Drive API* ve *Picker API* etkin olmalı. |
| Dropbox | App key ve *Chooser domains* listesine eklenmiş alan adınız. |
| OneDrive | Tek sayfalı uygulama yönlendirme URI'si tanımlı Azure uygulama (istemci) kimliği. |
| WebDAV | Adres, kullanıcı adı ve uygulama parolası. *Bağlantıyı test et* ile deneyin. |
| URL | Hiçbir şey — yalnızca etkinleştirin. |

> **iCloud Drive** için herkese açık bir web API'si yoktur, bu yüzden entegre edilemez. Bu dosyaları WebDAV destekli bir servise eşitleyin.

## Güvenlik

- Her AJAX isteği nonce ve yetki kontrolleriyle korunur (`upload_files`, `edit_post`, `delete_post`, `edit_themes`, `edit_plugins`, `manage_options`).
- Tüm girdiler temizlenir, tüm çıktılar kaçışlanır; veritabanı sorguları hazırlanmış ifadeler kullanır.
- Yüklemeler ve içe aktarmalar WordPress'in kendi tür denetimi ve FoldPress listesinden geçer; PHP, HTML, SVG ve diğer çalıştırılabilir türler engellidir; sunucu kodu içeren dosyalar reddedilir.
- ZIP çıkarma yol atlatmayı engeller, öğe sayısını ve boyutu sınırlar, yalnızca izinli türleri alır.
- Belge önizlemeleri XML'i ağ erişimi kapalı ayrıştırır ve DTD'leri reddeder (XXE yok).
- URL içe aktarma özel ağları engeller; bulut indirmeleri sağlayıcıların alan adlarıyla sınırlıdır.
- Yedek klasörü listelemeye ve betik çalıştırmaya karşı korunur.

## Harici servisler

FoldPress tamamen çevrimdışı çalışır. İsteğe bağlı özellikler **yalnızca siz etkinleştirip kullandığınızda** üçüncü taraflarla iletişim kurar:

- **Microsoft Office Online görüntüleyici** — "Tam düzen" belge görünümü (dosya adresi Microsoft tarafından yüklenir).
- **Google Drive / Dropbox / OneDrive** — kullanıcı açtığında sağlayıcıların resmi seçicileri kendi alan adlarından yüklenir.
- **WordPress.org API** — kod düzenleyicideki *Tanıma bak* düğmesi geliştirici belgelerini açar.

## Sık sorulan sorular

**Dosyaları klasörlere koyduğumda taşınıyorlar mı?** Hayır. Klasörler sanaldır (özel bir taksonomi), bu yüzden adresler asla değişmez. Yalnızca isteğe bağlı *fiziksel yeniden adlandırma* dosyalara dokunur ve tüm bağlantıları günceller.

**Klasik kütüphaneye dönebilir miyim?** Evet — bir *Klasik kütüphane* bağlantısı vardır ve her değişiklik Ayarlar'dan kapatılabilir.

**Kaldırınca ne olur?** Klasörler silinir; ortam dosyalarınız ve dönüştürme yedekleri korunur.

## Değişiklik günlüğü

Tam değişiklik günlüğü için [`readme.txt`](appstrdesign-foldpress/readme.txt) dosyasına bakın.

## Lisans

GPLv2 veya üzeri. © AppsTRDesign — [wplugins.shop](https://wplugins.shop)
