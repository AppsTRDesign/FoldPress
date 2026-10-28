<div align="center">

<img src="banners/github-social-1280x640-en.png" alt="FoldPress — the file manager WordPress was missing" width="100%">

# FoldPress

**The file manager WordPress was missing.**
Folders, drag & drop, bulk actions, ZIP / UNZIP, WebP / AVIF, a full code editor, Google-Drive-style previews and cloud import — in one fast, secure, AJAX interface.

**English** · [Türkçe](README-tr.md)

![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759b?logo=wordpress&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php&logoColor=white)
![License](https://img.shields.io/badge/License-GPLv2%2B-16a34a)
![Languages](https://img.shields.io/badge/i18n-EN%20%7C%20TR-4f46e5)

</div>

---

## Contents

- [Why FoldPress](#why-foldpress)
- [Features](#features)
- [Screenshots](#screenshots)
- [Installation](#installation)
- [Keyboard shortcuts](#keyboard-shortcuts)
- [Cloud import setup](#cloud-import-setup)
- [Security](#security)
- [External services](#external-services)
- [FAQ](#faq)
- [Changelog](#changelog)
- [License](#license)

## Why FoldPress

The WordPress media library is a flat list. Once a site has a few hundred files it becomes hard to find anything, impossible to organise, and every routine job (renaming, converting, cleaning up) needs another plugin. FoldPress replaces the **Media Library**, **Add New Media**, the **attachment edit screen** and the **Theme / Plugin File Editors** with one modern, consistent, mobile-friendly interface.

## Features

### File manager
- **Unlimited nested folders** with a tree sidebar, connector lines and live file counts. Folders are virtual, so file URLs never change.
- **Drag & drop** files into folders, folders into folders, and files from your computer straight into any folder.
- **Multi-select** with click, `Ctrl` + click, `Shift` + click and `Ctrl + A`. Tri-state checkboxes in grid and list view.
- **Bulk actions**: move, cut & paste, compress to ZIP, download, delete.
- **Folder selection**: select several folders (or all of them) and move, ZIP (structure kept) or delete them.
- **Right-click menu** everywhere: preview, details, rename, move, cut, ZIP, unzip, copy URL, download, delete, new folder, **new file**.
- **Create files** from the menu (txt, md, css, js, json, csv…). The extension is checked against the allowed types before anything is created.
- **Grid / list views**, **group by file type**, search, sorting, load-more or numbered pagination.
- **Details panel** with every core field: uploaded on / by, attached to, file name, type, size, dimensions, alt text, title, caption, description, URL.
- **Inline upload card** with an animated progress bar, percentage counter, upload speed, time left and per-file status.
- **ZIP / UNZIP** on the server. Archives are extracted into folders that mirror their structure; only allowed types are imported.

### Previews (Google Drive style)
- Images with thumbnails strip, zoom, keyboard and swipe navigation.
- **Word, Excel, PowerPoint, OpenDocument** rendered on your own server (no third party): pages, tables, every sheet, every slide.
- Markdown, text / code with line numbers, CSV as a table, the content list of ZIP files.
- Optional **Microsoft Office full-layout** view for public sites.
- **Custom video & audio player**: seek preview, speed, loop, picture-in-picture, full screen, keyboard shortcuts and a live audio spectrum visualiser.

### Advanced editor
- Replaces the attachment edit screen with a clean two-column editor.
- **Physical rename**: renames the file and every generated size on disk and **updates every reference in the database** — posts, pages, blocks, Elementor JSON, WooCommerce galleries, widgets, theme mods, options, term and user meta (serialization-safe).
- **Rename history** with one-click restore of any earlier name.
- **Where used**: finds the file by media ID and by URL in content, galleries, custom fields, Elementor, WooCommerce, users, categories and site settings — with filters, status badges and edit / view links.
- Generated sizes list, crop & rotate, unsaved-changes guard with a list of what will be discarded.

### Code editor (replaces the Theme & Plugin File Editors)
- File tree, tabs, breadcrumb, status bar, light / dark themes, full-screen mode.
- Syntax highlighting for **PHP, HTML, CSS / SCSS / LESS, JavaScript / TypeScript / JSX, JSON, XML / SVG, Markdown, SQL, YAML, Shell, Diff, Java, C#, C / C++** and more.
- Find & replace, go to line, comment toggle, bracket and tag matching, linting for CSS / JS / JSON / HTML.
- **Documentation lookup** for every WordPress function in the file.
- **Safe saving**: a full PHP syntax check runs before anything is written; WordPress' own fatal-error protection is used whenever the server allows it. Real fatal errors restore the file and point to the exact line; hosts that block loopback requests still save valid code. Every result is shown in a modal.
- Edit text files from the media library too.

### Optimisation
- **WebP / AVIF conversion** on upload and for existing media, with Low / Medium / High / Custom quality.
- Every original is **backed up**; one click restores all originals, deletes the converted files and removes the backup folder.
- Big-image threshold, JPEG quality, image sizes, optional sizes on / off.

### Uploads & file types
- The **File Types list is the single rule**: enabled types upload, disabled types are refused everywhere. Add custom types with a MIME type and an on / off switch.
- Executable and script-page types (PHP, HTML, SVG…) are always blocked. Files containing server-side code are refused.
- Upload folder structure: year / month, single folder, custom folder, or mirror FoldPress folders.
- Clean Turkish / accented file names, lowercase names, size limit.

### Cloud import
- **Google Drive** (including Docs / Sheets / Slides exported to DOCX / XLSX / PPTX), **Dropbox**, **OneDrive**.
- **WebDAV / your own cloud**: Nextcloud, ownCloud, Synology, QNAP, pCloud, Yandex Disk, Seafile… with a built-in file browser.
- **Import from URL** (public links only). Redirects and "your download will start" pages are followed; the real file name and type come from the server, so links like `/download?id=5` work too.
- **Live import queue**: every queued file is listed in the upload card with server download progress, speed and time left. Cancel one file or the whole queue — the download stops on the server too.

### Media dialog (Add Media, featured image, gallery)
- **Folder panel** with search, collapsible sub folders, file counts and drag & drop to move files.
- **"New uploads go to"** shows the target folder; create folders without leaving the dialog.
- **FoldPress file icons** for documents, archives and code, with quick **preview**, **copy URL** and **advanced edit** on every file.
- **Insert from URL** redesigned, with **Download to media library** and live progress.
- Redesigned fields, selects, checkboxes and alignment buttons in attachment details, gallery and playlist settings.

### Settings
- Tabbed settings page: General, Uploads, File Types, Image Sizes, WebP / AVIF, Cloud, System.
- **Step-by-step cloud setup** with copy buttons for origins, domains and redirect URIs, plus a WebDAV connection test.
- **Unsaved-changes guard**: a bottom bar and a discard dialog that lists every changed setting by name with its old and new value.

### Everywhere
- Fully **AJAX**, no page reloads. Custom modals and toasts instead of browser alerts (toast position is configurable).
- **Media modal integration**: the folder panel and FoldPress design inside *Add Media*, the featured-image picker and the block editor.
- Responsive and touch-friendly, keyboard accessible.
- Isolated `afp-` namespace for CSS and JS — no conflicts with WordPress or other plugins. No third-party JavaScript is bundled.
- Translation ready; **Turkish included** (600+ strings).

## Screenshots

| | |
|---|---|
| ![File manager](screenshots/en/01-file-manager.png) **File manager** — folder tree, grid, groups | ![List & selection](screenshots/en/02-list-select.png) **List view** — tri-state selection and bulk bar |
| ![Details](screenshots/en/03-details.png) **Details panel** — every field, history badge | ![Context menu](screenshots/en/04-context-menu.png) **Right-click menu** with shortcuts |
| ![Upload](screenshots/en/05-upload.png) **Upload card** — speed, ETA, WebP conversion | ![Document preview](screenshots/en/06-preview-document.png) **Word preview** rendered on your server |
| ![Spreadsheet preview](screenshots/en/07-preview-sheet.png) **Excel preview** with sheet tabs | ![Audio player](screenshots/en/08-audio-player.png) **Audio player** with visualiser |
| ![Shortcuts](screenshots/en/09-shortcuts.png) **Shortcuts & help** | ![History](screenshots/en/10-history.png) **Rename history** with restore |
| ![Code editor](screenshots/en/11-code-editor.png) **Code editor** replacing the theme / plugin editors | ![Advanced edit](screenshots/en/12-advanced-edit.png) **Advanced edit** with *Where used* |
| ![Import queue](screenshots/en/13-import-queue.png) **Import queue** — live progress, speed, ETA | ![Add Media](screenshots/en/14-media-modal.png) **Add Media** — folder panel and quick actions |
| ![Insert from URL](screenshots/en/15-insert-from-url.png) **Insert from URL** — download to the library | ![Gallery](screenshots/en/16-gallery-settings.png) **Gallery editing** — redesigned settings |
| ![Cloud settings](screenshots/en/17-settings-cloud.png) **Cloud settings** — step-by-step setup | ![Unsaved changes](screenshots/en/18-settings-unsaved.png) **Unsaved changes** — every setting by name |

## Installation

1. Download the plugin ZIP (or clone this repository into `wp-content/plugins/appstrdesign-foldpress`).
2. **Plugins › Add New › Upload Plugin**, choose the ZIP, **Install**, **Activate**.
3. Open **Media › Library** — FoldPress opens automatically. Settings live under **FoldPress** in the admin menu.

Requirements: WordPress 6.0+, PHP 7.4+. ZipArchive is needed for ZIP features; GD or Imagick (with WebP / AVIF support) for conversion.

## Keyboard shortcuts

| Keys | Action |
|---|---|
| `Ctrl` + `A` | Select all files on the page |
| `Ctrl` + click / `Shift` + click | Add to selection / select a range |
| `Ctrl` + `X` / `Ctrl` + `V` | Cut / paste (move) into the open folder |
| `M` | Move selection to a folder |
| `F2` | Rename |
| `Delete` | Delete selection |
| `P` / `Enter` | Preview / details |
| `N` / `U` | New folder / upload |
| `/` | Search |
| `?` | Shortcuts help |
| Code editor: `Ctrl` + `S`, `Ctrl` + `F`, `Ctrl` + `H`, `Ctrl` + `G`, `Ctrl` + `/`, `F11` | Save, find, replace, go to line, comment, full screen |
| Player: `Space` / `K`, `J` / `L`, `↑` / `↓`, `M`, `F` | Play/pause, ±10 s, volume, mute, full screen |

## Cloud import setup

Open **FoldPress › Settings › Cloud**. Each service shows step-by-step instructions and the exact origin / domain / redirect URI to copy.

| Service | What you need |
|---|---|
| Google Drive | API key, OAuth client ID, project number (App ID). Enable *Drive API* and *Picker API*. |
| Dropbox | App key, your domain added to *Chooser domains*. |
| OneDrive | Azure app (client) ID with a single-page-application redirect URI. |
| WebDAV | Address, user name and an app password. Use *Test connection*. |
| URL | Nothing — just enable it. |

> **iCloud Drive** has no public web API, so it cannot be integrated. Sync those files to a WebDAV-capable service instead.

## Security

- Every AJAX request is protected by a nonce and capability checks (`upload_files`, `edit_post`, `delete_post`, `edit_themes`, `edit_plugins`, `manage_options`).
- All input is sanitised, all output escaped; database queries use prepared statements.
- Uploads and imports go through WordPress' own type checks plus FoldPress' list; PHP, HTML, SVG and other executable types are blocked; files containing server-side code are refused.
- ZIP extraction strips path traversal, limits entry count and size, and imports only allowed types.
- Document previews parse XML with network access disabled and refuse DTDs (no XXE).
- URL import blocks private networks; cloud downloads are restricted to the providers' hosts.
- Backups folder is protected against listing and script execution.

## External services

FoldPress works fully offline. Optional features talk to third parties **only when you enable and use them**:

- **Microsoft Office Online viewer** — "Full layout" document view (the file URL is loaded by Microsoft).
- **Google Drive / Dropbox / OneDrive** — the providers' official pickers are loaded from their domains when a user opens them.
- **WordPress.org API** — the code editor's *Look up* button opens the developer documentation.

## FAQ

**Are my files moved when I put them in folders?** No. Folders are virtual (a private taxonomy), so URLs never change. Only the optional *physical rename* touches files, and it updates every link.

**Can I go back to the classic library?** Yes — there is a *Classic library* link, and each replacement can be turned off in Settings.

**What happens on uninstall?** Folders are removed; your media files and converted-image backups are kept.

## Changelog

See [`readme.txt`](../appstrdesign-foldpress/readme.txt) for the full changelog.

## License

GPLv2 or later. © AppsTRDesign — [wplugins.shop](https://wplugins.shop)
