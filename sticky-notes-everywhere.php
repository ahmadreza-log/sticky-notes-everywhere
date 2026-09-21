<?php
/**
 * Plugin Name:       Sticky Notes Everywhere
 * Plugin URI:        https://github.com/ahmadreza-log/sticky-notes-everywhere
 * Description:       Private, draggable sticky notes on every frontend page and in wp-admin.
 * Version:           1.2.0
 * Requires at least: 6.0
 * Tested up to:      7.1
 * Requires PHP:      8.0
 * Author:            Ahmadreza Ebrahimi
 * Author URI:        https://ahmadreza.me
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sticky-notes-everywhere
 * Domain Path:       /languages
 *
 * This is the only file WordPress loads when the plugin is active.
 * It defines STNE_* constants, includes class files, registers activation,
 * loads translations, and starts Plugin::init on plugins_loaded.
 *
 * Direct HTTP access is blocked. PHP 8.0+ types are required.
 *
 * @package StickyNotesEverywhere
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

if (! defined('STNE_VERSION')) {
    define('STNE_VERSION', '1.2.0');
}

if (! defined('STNE_FILE')) {
    define('STNE_FILE', __FILE__);
}

if (! defined('STNE_DIR')) {
    define('STNE_DIR', plugin_dir_path(__FILE__));
}

if (! defined('STNE_URL')) {
    define('STNE_URL', plugin_dir_url(__FILE__));
}

require_once STNE_DIR . 'includes/Settings.php';
require_once STNE_DIR . 'includes/Database.php';
require_once STNE_DIR . 'includes/Store.php';
require_once STNE_DIR . 'includes/Rest.php';
require_once STNE_DIR . 'includes/Plugin.php';

if (is_admin()) {
    require_once STNE_DIR . 'includes/Admin.php';
}

register_activation_hook(__FILE__, array(\StickyNotesEverywhere\Plugin::class, 'activate'));

/**
 * Load /languages/{domain}-{locale}.mo on self-hosted sites.
 *
 * Translators edit the .po files. Compile with:
 *   wp i18n make-pot . languages/sticky-notes-everywhere.pot
 *   wp i18n make-mo languages
 *
 * WordPress.org language packs override this folder when the plugin is hosted there.
 */
function stne_locale(): void
{
    load_plugin_textdomain(
        'sticky-notes-everywhere',
        false,
        dirname(plugin_basename(STNE_FILE)) . '/languages'
    );
}
add_action('init', 'stne_locale');

add_action('plugins_loaded', array(\StickyNotesEverywhere\Plugin::class, 'init'));
