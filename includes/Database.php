<?php
/**
 * Custom table {$wpdb->prefix}stne_notes and one-time migrations.
 *
 * Columns stay snake_case because MySQL already stores them that way.
 * PHP and REST use single-word keys; Store::cast maps between the two.
 *
 * Option stne_db_version tracks schema. 1.0.0 used sne_notes / sne_*.
 *
 * @package StickyNotesEverywhere
 */

declare(strict_types=1);

namespace StickyNotesEverywhere;

if (! defined('ABSPATH')) {
    exit;
}

// dbDelta, SHOW TABLES, and a one-time RENAME of this plugin's table.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange

/**
 * Install and upgrade the notes table.
 */
final class Database
{
    /** Schema string stored in stne_db_version. */
    public const VERSION = '1.2.0';

    /** Option that holds VERSION. */
    public const SCHEMA = 'stne_db_version';

    /**
     * Prefixed table name for this site.
     */
    public static function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'stne_notes';
    }

    /**
     * Create/alter the table with dbDelta, then migrate old option/table names.
     */
    public static function install(): void
    {
        global $wpdb;

        self::migrate();
        self::rename();

        if (get_option(Settings::OPTION, false) === false) {
            add_option(Settings::OPTION, Settings::defaults(), '', false);
        }

        $table   = self::table();
        $collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            title varchar(190) NOT NULL DEFAULT '',
            content longtext NOT NULL,
            color varchar(32) NOT NULL DEFAULT 'yellow',
            scope varchar(16) NOT NULL DEFAULT 'page',
            page_path varchar(500) NOT NULL DEFAULT '',
            page_title varchar(190) NOT NULL DEFAULT '',
            pos_x float NOT NULL DEFAULT 48,
            pos_y float NOT NULL DEFAULT 96,
            width smallint unsigned NOT NULL DEFAULT 240,
            height smallint unsigned NOT NULL DEFAULT 220,
            z_index smallint unsigned NOT NULL DEFAULT 1,
            minimized tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY user_scope (user_id, scope),
            KEY user_path (user_id, page_path(191))
        ) {$collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        update_option(self::SCHEMA, self::VERSION, false);
    }

    /**
     * Re-run install when stne_db_version does not match VERSION.
     */
    public static function upgrade(): void
    {
        if ((string) get_option(self::SCHEMA, '') !== self::VERSION) {
            self::install();
        }
    }

    /**
     * Copy sne_settings and sne_db_version into stne_* once.
     */
    private static function migrate(): void
    {
        $legacy = get_option('sne_settings', false);
        if (is_array($legacy) && get_option(Settings::OPTION, false) === false) {
            add_option(Settings::OPTION, Settings::sanitize($legacy), '', false);
        }

        $old = get_option('sne_db_version', false);
        if (is_string($old) && get_option(self::SCHEMA, false) === false) {
            add_option(self::SCHEMA, $old, '', false);
        }
    }

    /**
     * Rename wp_sne_notes to wp_stne_notes when only the old table exists.
     */
    private static function rename(): void
    {
        global $wpdb;

        $legacy = $wpdb->prefix . 'sne_notes';
        $next   = self::table();

        $haslegacy = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $legacy)) === $legacy;
        $hasnext   = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $next)) === $next;

        if ($haslegacy && ! $hasnext) {
            $wpdb->query('RENAME TABLE `' . esc_sql($legacy) . '` TO `' . esc_sql($next) . '`'); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }
    }
}
