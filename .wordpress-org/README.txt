WordPress.org plugin directory assets
====================================

Do NOT put these files in the plugin ZIP / SVN `/trunk`.
WordPress.org reads them from SVN `/assets` only.

https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/

Upload (svn add) to:
  https://plugins.svn.wordpress.org/sticky-notes-everywhere/assets/

Required filenames (already named):

  icon-128x128.jpg
  icon-256x256.jpg
  icon.svg                    (optional; used when the browser supports SVG)

  banner-772x250.jpg          (header on the plugin page)
  banner-1544x500.jpg         (HiDPI / retina header)
  banner-772x250-rtl.jpg      (RTL locales)
  banner-1544x500-rtl.jpg

  screenshot-1.jpg … screenshot-4.jpg
  Captions live in readme.txt under == Screenshots == (same order).

After the plugin is approved you can also drop this folder into GitHub
as `.wordpress-org/` if you use 10up/action-wordpress-plugin-asset-update.

---

این فایل‌ها را داخل ZIP افزونه یا پوشه trunk نگذارید.
وردپرس آن‌ها را فقط از SVN مسیر /assets می‌خواند.

بعد از تأیید افزونه، همین فایل‌ها را در
plugins.svn.wordpress.org/sticky-notes-everywhere/assets
آپلود کنید.
