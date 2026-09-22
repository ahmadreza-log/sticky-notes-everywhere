<?php
/**
 * Plugin Name:       Sticky Notes Everywhere
 * Plugin URI:        https://github.com/ahmadreza-log/sticky-notes-everywhere
 * Description:       Private, draggable sticky notes on every frontend page and in wp-admin. Requires WordPress 6.5 or later.
 * Version:           1.3.1
 * Requires at least: 6.5
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
 * Requires WordPress 6.5+ for performant PHP translation files (.l10n.php).
 * Direct HTTP access is blocked. PHP 8.0+ types are required.
 *
 * @package StickyNotesEverywhere
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

if (! defined('STNE_VERSION')) {
    define('STNE_VERSION', '1.3.1');
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

/**
 * True when WordPress is 6.5 or newer (PHP translation files).
 */
function stne_compatible(): bool
{
    global $wp_version;

    return isset($wp_version) && version_compare((string) $wp_version, '6.5', '>=');
}

/**
 * Activation: refuse WordPress older than 6.5, then install the table.
 */
function stne_activate(): void
{
    if (! stne_compatible()) {
        deactivate_plugins(plugin_basename(STNE_FILE));
        wp_die(
            esc_html__('Sticky Notes Everywhere requires WordPress 6.5 or later.', 'sticky-notes-everywhere'),
            esc_html__('Plugin activation error', 'sticky-notes-everywhere'),
            array('back_link' => true)
        );
    }

    \StickyNotesEverywhere\Plugin::activate();
}
register_activation_hook(__FILE__, 'stne_activate');

/**
 * Load translations from /languages.
 *
 * WordPress 6.5+ prefers sticky-notes-everywhere-{locale}.l10n.php
 * (performant PHP translations). Translators still edit the .po files.
 *
 *   wp i18n make-pot . languages/sticky-notes-everywhere.pot
 *   wp i18n make-php languages
 */
function stne_locale(): void
{
    // Bundled fa_IR ships in /languages. WordPress.org still auto-loads once hosted.
    load_plugin_textdomain( // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound
        'sticky-notes-everywhere',
        false,
        dirname(plugin_basename(STNE_FILE)) . '/languages'
    );
}
add_action('init', 'stne_locale');

/**
 * Boot the plugin only on WordPress 6.5+.
 */
function stne_boot(): void
{
    if (! stne_compatible()) {
        add_action('admin_notices', 'stne_notice');
        return;
    }

    \StickyNotesEverywhere\Plugin::init();
}
add_action('plugins_loaded', 'stne_boot');

/**
 * Admin notice when the site is below WordPress 6.5.
 */
function stne_notice(): void
{
    if (! current_user_can('activate_plugins')) {
        return;
    }

    echo '<div class="notice notice-error"><p>';
    echo esc_html__('Sticky Notes Everywhere requires WordPress 6.5 or later.', 'sticky-notes-everywhere');
    echo '</p></div>';
}
