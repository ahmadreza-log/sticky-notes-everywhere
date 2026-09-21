<?php
/**
 * Overlay lifecycle: table upgrade, REST, admin screens, CSS/JS, markup.
 *
 * Hooks:
 * - wp_enqueue_scripts / admin_enqueue_scripts → assets when the user is allowed
 * - wp_footer / admin_footer → empty #sne-app root (dir=rtl|ltr)
 *
 * window.stneConfig is inlined before public/js/stne-public.js.
 *
 * @package StickyNotesEverywhere
 */

declare(strict_types=1);

namespace StickyNotesEverywhere;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Plugin entry after plugins_loaded.
 */
final class Plugin
{
    /**
     * Upgrade schema, register REST, load Admin in wp-admin, enqueue overlay.
     */
    public static function init(): void
    {
        Database::upgrade();
        Rest::init();

        if (is_admin()) {
            if (! class_exists(Admin::class, false)) {
                require_once \STNE_DIR . 'includes/Admin.php';
            }
            Admin::init();
        }

        add_action('wp_enqueue_scripts', array(self::class, 'frontend'));
        add_action('admin_enqueue_scripts', array(self::class, 'backend'));
        add_action('wp_footer', array(self::class, 'markup'));
        add_action('admin_footer', array(self::class, 'markup'));
    }

    /**
     * Activation: create table and seed stne_settings once.
     */
    public static function activate(): void
    {
        Database::install();

        if (get_option(Settings::OPTION, false) === false) {
            add_option(Settings::OPTION, Settings::defaults(), '', false);
        }
    }

    /**
     * True when a logged-in allowed role may see the overlay on this request.
     */
    public static function allowed(): bool
    {
        if (! is_user_logged_in() || ! Settings::can()) {
            return false;
        }

        if (is_admin()) {
            return (bool) Settings::get('admin', true);
        }

        return (bool) Settings::get('frontend', true);
    }

    /**
     * Public-site assets.
     */
    public static function frontend(): void
    {
        if (! self::allowed()) {
            return;
        }

        self::assets();
    }

    /**
     * wp-admin overlay assets (same CSS/JS as the public site).
     */
    public static function backend(): void
    {
        if (! self::allowed()) {
            return;
        }

        self::assets();
    }

    /**
     * Print the overlay root. hidden until JS runs. dir drives RTL CSS.
     */
    public static function markup(): void
    {
        if (! self::allowed()) {
            return;
        }

        printf(
            '<div id="sne-app" class="sne-app" dir="%s" hidden></div>',
            is_rtl() ? 'rtl' : 'ltr'
        );
    }

    /**
     * Enqueue unminified CSS/JS and print window.stneConfig.
     *
     * Config keys are single words: root, nonce, max, colors, admin, rtl, i18n.
     */
    private static function assets(): void
    {
        $handle = 'stne-public';

        wp_enqueue_style(
            $handle,
            \STNE_URL . 'public/css/stne-public.css',
            array(),
            \STNE_VERSION
        );

        wp_enqueue_script(
            $handle,
            \STNE_URL . 'public/js/stne-public.js',
            array(),
            \STNE_VERSION,
            true
        );

        $config = array(
            'root'   => esc_url_raw(rest_url(Rest::NAMESPACE . '/')),
            'nonce'  => wp_create_nonce('wp_rest'),
            'max'    => (int) Settings::get('max', 40),
            'colors' => Settings::colors(),
            'admin'  => is_admin(),
            'rtl'    => is_rtl(),
            'i18n'   => array(
                'add'        => __('New note', 'sticky-notes-everywhere'),
                'all'        => __('All notes', 'sticky-notes-everywhere'),
                'hide'       => __('Hide notes', 'sticky-notes-everywhere'),
                'show'       => __('Show notes', 'sticky-notes-everywhere'),
                'title'      => __('Title', 'sticky-notes-everywhere'),
                'body'       => __('Write a note…', 'sticky-notes-everywhere'),
                'untitled'   => __('Untitled note', 'sticky-notes-everywhere'),
                'page'       => __('This page', 'sticky-notes-everywhere'),
                'everywhere' => __('Everywhere', 'sticky-notes-everywhere'),
                'pin'        => __('Show on every page', 'sticky-notes-everywhere'),
                'unpin'      => __('Show only on this page', 'sticky-notes-everywhere'),
                'minimize'   => __('Minimize', 'sticky-notes-everywhere'),
                'restore'    => __('Restore', 'sticky-notes-everywhere'),
                'color'      => __('Color', 'sticky-notes-everywhere'),
                'erase'      => __('Delete', 'sticky-notes-everywhere'),
                'confirm'    => __('Delete this note?', 'sticky-notes-everywhere'),
                'empty'      => __('No notes yet. Click + to add one.', 'sticky-notes-everywhere'),
                'error'      => __('Something went wrong. Please try again.', 'sticky-notes-everywhere'),
                'drag'       => __('Drag the top bar to move this note.', 'sticky-notes-everywhere'),
                'open'       => __('Open page', 'sticky-notes-everywhere'),
                'close'      => __('Close', 'sticky-notes-everywhere'),
            ),
        );

        $json = wp_json_encode($config);
        if (is_string($json)) {
            wp_add_inline_script($handle, 'window.stneConfig = ' . $json . ';', 'before');
        }
    }
}
