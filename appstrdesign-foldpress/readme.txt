=== AppsTRDesign - FoldPress ===
Contributors: appstrdesign
Tags: media library, folders, file manager, webp, code editor
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.5.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Pro media and file manager for WordPress: folders, drag and drop, bulk actions, ZIP, WebP/AVIF, code editor, document previews and cloud import.

== Description ==

**FoldPress is the file manager WordPress was missing.** It replaces the Media Library, Add New Media, the attachment edit screen and the Theme / Plugin File Editors with one fast, secure, fully AJAX interface that works the same on desktop, tablet and phone.

= File manager =

* Unlimited nested folders with a tree sidebar, connector lines and live counts. Folders are virtual, so file URLs never change.
* Drag and drop files into folders, folders into folders, and files from your computer into any folder.
* Multi-select with click, Ctrl + click, Shift + click and Ctrl + A, with tri-state checkboxes in grid and list view.
* Bulk move, cut and paste, ZIP, download and delete. Select several folders (or all) to move, ZIP or delete them.
* Right-click menu everywhere, including "New file" with extension validation and "New folder".
* Grid and list views, group by file type, search, sorting, load-more or numbered pagination.
* Details panel with every field: uploaded on / by, attached to, file name, type, size, dimensions, alt text, title, caption, description and URL.
* Inline upload card with animated progress, percentage, speed, time left and per-file status.
* ZIP and UNZIP on the server; archives are extracted into matching folders and only allowed file types are imported.

= Previews (Google Drive style) =

* Image viewer with thumbnails, zoom, keyboard and swipe navigation.
* Word, Excel, PowerPoint and OpenDocument files rendered on your own server: pages, tables, every sheet and every slide.
* Markdown, text and code with line numbers, CSV as a table, ZIP content listing.
* Optional Microsoft Office "full layout" view for public sites.
* Custom video and audio player with seek preview, speed, loop, picture-in-picture, full screen, shortcuts and a live audio visualiser.

= Advanced editor =

* Physical rename that renames the file and all generated sizes and updates every reference in the database: posts, blocks, Elementor, WooCommerce, widgets, theme mods, options, term and user meta (serialization-safe).
* Rename history with one-click restore.
* "Where used" finds the file by media ID and URL in content, galleries, custom fields, Elementor, WooCommerce, users, categories and site settings.
* Generated sizes, crop and rotate, and an unsaved-changes guard that lists exactly what will be discarded.

= Code editor =

* Replaces the Theme and Plugin File Editors: file tree, tabs, breadcrumb, status bar, light and dark themes, full screen.
* Syntax highlighting for PHP, HTML, CSS, SCSS, LESS, JavaScript, TypeScript, JSX, JSON, XML, SVG, Markdown, SQL, YAML, Shell, Diff, Java, C#, C and C++.
* Find and replace, go to line, comments, bracket and tag matching, linting for CSS, JS, JSON and HTML.
* Documentation lookup for every WordPress function in the file.
* Safe saving: a full PHP syntax check runs before anything is written, and WordPress' own fatal-error protection is used whenever the server allows it. Real fatal errors restore the file and point to the exact line; hosts that block loopback requests still save valid code.
* Text files from the media library can be edited too.

= Optimisation =

* WebP / AVIF conversion on upload and for existing media with Low, Medium, High or Custom quality.
* Every original is backed up. One click restores all originals, deletes converted files and empties the backup folder.
* Big-image threshold, JPEG quality, image sizes and optional theme / plugin sizes.

= Uploads and file types =

* The File Types list is the single rule: enabled types upload, disabled types are refused everywhere. Add custom types with their MIME type and an on / off switch.
* PHP, HTML, SVG and other executable types are always blocked, and files containing server-side code are refused.
* Upload folder structure (year / month, single folder, custom folder or mirror FoldPress folders), clean Turkish and accented file names, size limit.

= Cloud import =

* Google Drive (Docs, Sheets and Slides exported as DOCX, XLSX, PPTX), Dropbox and OneDrive through their official pickers.
* WebDAV for Nextcloud, ownCloud, Synology, QNAP, pCloud, Yandex Disk, Seafile and your own servers, with a built-in file browser.
* Import from public URLs. Redirects and "your download will start" pages are followed, and the real file name and type are taken from the server, so links like /download?id=5 work too.
* URL and cloud imports use the upload card: every queued file is listed with live server download progress, speed and time left. Cancelling a file (or the whole queue) also stops the download on the server.

= Media dialog (Add Media, featured image, gallery) =

* Folder panel with search, collapsible sub folders, file counts and drag and drop to move files.
* "New uploads go to" shows the target folder; create folders without leaving the dialog.
* FoldPress file icons for documents, archives and code, plus quick preview, copy URL and advanced edit on every file.
* Redesigned Insert from URL with "Download to media library" and live progress.
* Redesigned fields, selects, checkboxes and alignment buttons in attachment details, gallery and playlist settings.

= Settings =

* Tabbed settings page: General, Uploads, File Types, Image Sizes, WebP / AVIF, Cloud and System.
* Step-by-step cloud setup with copy buttons for origins, domains and redirect URIs, plus a WebDAV connection test.
* Unsaved-changes bar and a discard dialog that lists every changed setting by name with its old and new value.

= Everywhere =

* Fully AJAX with custom modals and notifications (position configurable).
* Folder panel and FoldPress design inside the media modal of the classic editor, the block editor and the front end.
* Responsive, touch-friendly and keyboard accessible, with a shortcuts help window.
* Isolated CSS / JS namespace, no bundled third-party JavaScript.
* Translation ready, Turkish included (600+ strings).

== Installation ==

1. Upload the plugin through Plugins > Add New > Upload Plugin, or extract it into `wp-content/plugins/appstrdesign-foldpress`.
2. Activate it.
3. Open Media > Library. Settings are under FoldPress in the admin menu.

ZipArchive is required for ZIP features; GD or Imagick with WebP / AVIF support for image conversion.

== Frequently Asked Questions ==

= Are files moved when I put them into folders? =

No. Folders are virtual, so URLs never change. Only the optional physical rename touches files, and it updates every link to them.

= Can I go back to the classic WordPress screens? =

Yes. Each replacement (library, attachment editor, file editors, media modal design) can be turned off in FoldPress > Settings, and a "Classic" link is always available.

= Why does saving a PHP file say the runtime check was skipped? =

Some hosts block WordPress from calling itself (loopback). FoldPress still runs a full PHP syntax check and saves valid code, and tells you that the extra runtime check was not possible.

= Is iCloud Drive supported? =

Apple offers no public web API for iCloud Drive files, so it cannot be integrated. Sync those files to a WebDAV service or upload them directly.

= What is removed on uninstall? =

Folders and settings. Your media files and the WebP / AVIF backups are kept.

== Screenshots ==

1. File manager with nested folder tree, grid view and type icons.
2. List view with tri-state selection and the bulk action bar.
3. Details panel with every attachment field and the rename history badge.
4. Right-click menu with keyboard shortcuts.
5. Inline upload card with speed, time left and WebP conversion.
6. Word document preview rendered on your server.
7. Excel preview with sheet tabs.
8. Audio player with live visualiser.
9. Keyboard shortcuts and help.
10. Rename history with one-click restore.
11. Code editor replacing the theme and plugin file editors.
12. Advanced edit screen with "Where used".
13. URL / cloud import queue with live download progress, speed and time left.
14. Add Media dialog with the folder panel, FoldPress file icons and quick actions.
15. Insert from URL with "Download to media library" and live progress.
16. Gallery editing with the folder panel and redesigned gallery settings.
17. Cloud import settings with step-by-step setup.
18. Unsaved-changes dialog listing every changed setting.

== External services ==

FoldPress works fully offline. One optional feature uses an external service:

* **Google Drive, Dropbox, OneDrive** (Settings > Cloud, off by default). When a user opens a picker, the provider's official script is loaded from its domain (apis.google.com / accounts.google.com, www.dropbox.com, js.live.net) and the selected files are downloaded by your server from the provider. Google: https://policies.google.com/privacy Dropbox: https://www.dropbox.com/privacy Microsoft: https://privacy.microsoft.com/privacystatement
* **Microsoft Office Online viewer** (Settings > General > "Office full-layout view"). When an administrator opens the "Full layout" view of a Word, Excel or PowerPoint file, the file's public URL is loaded in an iframe from view.officeapps.live.com, so Microsoft fetches the file to render it. Nothing is sent unless that view is opened. It can be turned off; the built-in quick preview never contacts any service. Terms: https://www.microsoft.com/servicesagreement Privacy: https://privacy.microsoft.com/privacystatement

The code editor's "Look up" button opens the WordPress.org developer documentation (api.wordpress.org) in a new tab, exactly like the core file editor.

== Changelog ==

= 1.5.3 =
* URL / cloud import: follows redirects and meta refresh pages, takes the real file name from Content-Disposition or the final address, detects the extension from the content type.
* URL import: browser-like request headers with a fallback for firewalls (HTTP 412 / 403 / 406), clear messages per HTTP status.
* Import queue: live server download progress, speed and remaining time; cancelling now stops the download on the server.
* Media modal: files no longer slide under the folder panel (infinite scrolling, gallery and featured image screens).
* Media modal: non-image files no longer show broken thumbnails in the preview.
* Media modal: redesigned sidebar fields, selects, checkboxes, alignment buttons and gallery settings.

= 1.5.2 =
* Media modal: fixed the empty gap and horizontal scrollbar next to the folder panel.
* Media modal: folder search, collapsible sub folders, upload target, FoldPress file icons, quick preview / copy URL / advanced edit on every file.
* Insert from URL: redesigned fields and "Download to media library".

= 1.5.1 =
* Cloud and URL imports run in the upload card queue: per-file status, speed, time left, cancel one or all.
* Upload card: tidier rows, summary chips, readable error messages.
* Fixed: PHP saves were refused on hosts that block loopback requests (core wraps the error as php_error); real fatal errors now show file and line.
* Settings: the unsaved-changes list shows readable setting names and old / new values.

= 1.5.0 =
* Cloud import: Google Drive, Dropbox, OneDrive, WebDAV (Nextcloud, ownCloud, Synology...) and URL import.
* New file from the right-click menu with extension validation.
* Fixed: enabled text types such as CSS and JS were refused by the core MIME check; JS is now allowed.
* Code editor: PHP syntax check before saving, saving works on hosts that block loopback requests, click positioning fixed, dark theme polish.
* Redesigned "Where used", sidebar folder selection row, unsaved bar.

= 1.4.0 =
* New FoldPress code editor replacing the Theme and Plugin File Editors: file tree, tabs, syntax highlighting (PHP, HTML, CSS/SCSS/LESS, JS/TS/JSX, JSON, XML, Markdown, SQL, YAML, Shell, Java, C#, C/C++ and more), find & replace, go to line, documentation lookup, light/dark themes, full screen. Saves through core so WordPress' PHP error protection still applies; every result is shown in a modal.
* Edit text media files (txt, md, csv, json, css...) from the file manager.
* Google Drive style previews: Word, Excel, PowerPoint, OpenDocument, Markdown, text, CSV and ZIP contents (rendered on your server), optional Office full layout.
* Custom video / audio player with seek preview, speed, loop, picture-in-picture, full screen, keyboard shortcuts and an audio spectrum visualizer.

= 1.3.0 =
* Own built-in lightbox (no third-party library): thumbnails, keyboard, swipe, zoom, download.
* Custom dropdowns for every select (search for long lists, folder tree indentation).
* Unsaved changes: Cancel button with a list of exactly what will be discarded.
* Complete "where used" scan by media ID and URL: content, blocks, galleries, all post meta, users, categories, site settings.
* Rename history with one-click restore (title and physical file name, all links updated).
* Select all folders from the sidebar; upload speed / ETA / total time.

= 1.2.0 =
* WebP / AVIF conversion on upload and for existing media, quality presets, full backup and one-click restore.
* File Types list is authoritative (custom types with on/off); fixed custom text types such as .md.
* Recursive folder delete (with everything inside), multi-folder selection, folder ZIP and bulk move.
* Inline animated upload progress (speed, ETA, counter), notification position setting.
* Redesigned shortcuts help, pro selection controls with tri-state checkboxes, list header checkbox.
* Fixed: lightbox preview, advanced edit access, details drawer overflow, media modal and classic grid layout.
* Isolated namespace (afp-) and scoped reset so styles never clash with WordPress or other plugins.

= 1.1.0 =
* Physical rename updates every reference in the database (posts, post meta incl. Elementor JSON, WooCommerce, options, term meta), serialization safe.
* New FoldPress attachment editor with usage finder, sizes list and danger zone.
* New settings admin: file types, upload folder structure, size limit, image sizes, quality, pagination, modal options, system info.
* Lightbox preview (GLightbox), pro upload panel, numbered / load-more pagination, folder tree guides, shortcuts help, cut & paste.
* FoldPress design for the WordPress media modal (classic, Gutenberg, front-end).


= 1.0.0 =
* Initial release.
