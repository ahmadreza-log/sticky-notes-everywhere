=== Sticky Notes Everywhere ===
Contributors: ahmadrezaebrahimi
Tags: notes, sticky-notes, dashboard, productivity, admin
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Private, draggable sticky notes on every frontend page and inside wp-admin. Each user only sees their own notes.

== Description ==

Sticky Notes Everywhere lets logged-in users pin private notes onto any page of the site, including wp-admin.

Notes stay on the current page unless you pin them to appear everywhere. Each person only sees the notes they created. Nothing is sent to an external service.

= Features =

* Drag a note by its top bar; resize from the corner
* Pin a note so it follows you across pages
* Eight paper colors, minimize, and delete
* A tray that lists all of your notes
* Temporarily hide notes in this browser
* Keyboard shortcut: Alt+N for a new note
* Site owners can review and delete notes from Tools

JavaScript and CSS ship inside the plugin. Source is unminified so it stays human readable.

= Development =

Source and build notes: https://github.com/ahmadreza-log/sticky-notes-everywhere

== Installation ==

1. Upload the `sticky-notes-everywhere` folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins screen.
3. Log in on the site. A yellow + button appears for allowed roles.
4. Optional: open Settings → Sticky Notes to choose roles and where notes appear.

== Screenshots ==

1. Sticky notes on any public WordPress page, with add / list / hide buttons.
2. A single note: title, body, pin, color, and delete.
3. The All notes tray in wp-admin, with page notes and notes pinned everywhere.
4. Settings → Sticky Notes: where notes appear, the per-user limit, and allowed roles.

== Frequently Asked Questions ==

= Who can see my notes? =

Only you, unless a site administrator deletes them from Tools → Sticky Notes.

= Do notes work for visitors who are logged out? =

No. The overlay loads only for logged-in users whose role is allowed in settings.

= Does this plugin phone home? =

No. Notes are stored in your WordPress database. A hide/show preference is stored in your browser only.

= How do I stop the overlay on the public site? =

Uncheck “Show on the public site” under Settings → Sticky Notes. You can also hide notes for yourself with the eye button.

== Privacy ==

This plugin does not track users, does not load remote scripts or fonts, and does not contact third-party servers.

It stores note title, body, color, position, and the page path in a custom table on your site, tied to the WordPress user ID. Administrators can list and delete those rows.

The optional hide-notes preference uses `localStorage` in the user’s browser and never leaves that browser.

== Changelog ==

= 1.2.0 =
* Use single-word class, method, variable, and REST keys. Settings keys: frontend, admin, max, roles.

= 1.1.1 =
* Fix RTL layout: note spawn side, resize handle, text alignment, and tray list.

= 1.1.0 =
* Align with WordPress.org plugin guidelines: unique prefixes, Settings API, Tools/Settings menus, readme.txt, and a GPL license file.
* Document privacy: no tracking and no remote assets.

= 1.0.0 =
* First release.

== Upgrade Notice ==

= 1.2.0 =
REST and settings keys are now single words. Existing notes and options are migrated automatically.

= 1.1.0 =
Settings move to Settings → Sticky Notes. The all-notes list moves to Tools → Sticky Notes.

== Source ==

JavaScript and CSS are included unminified in `public/` and `admin/`.
