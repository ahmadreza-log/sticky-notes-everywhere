<?php
/**
 * Uninstall Sticky Notes Everywhere.
 *
 * WordPress runs this file only when the plugin is deleted (not on deactivate).
 * It drops custom tables and options for both current (stne_*) and 1.0.0 (sne_*) names.
 *
 * @package StickyNotesEverywhere
 */

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

$tables = array(
    $wpdb->prefix . 'stne_notes',
    $wpdb->prefix . 'sne_notes',
);

foreach ($tables as $table) {
    $wpdb->query('DROP TABLE IF EXISTS `' . esc_sql($table) . '`'); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

delete_option('stne_settings');
delete_option('stne_db_version');
delete_option('sne_settings');
delete_option('sne_db_version');
delete_metadata('user', 0, 'stne_notes_per_page', '', true);
delete_metadata('user', 0, 'sne_notes_per_page', '', true);
