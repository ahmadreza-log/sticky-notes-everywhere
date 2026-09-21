<?php
/**
 * wp-admin: Tools list and Settings form.
 *
 * Menus (no top-level hijack):
 * - Tools → Sticky Notes  (stne-notes)
 * - Settings → Sticky Notes (stne-settings)
 *
 * Capability: manage_options.
 *
 * @package StickyNotesEverywhere
 */

declare(strict_types=1);

namespace StickyNotesEverywhere;

if (! defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/Table.php';

/**
 * Admin screens.
 */
final class Admin
{
    /** Required capability. */
    private const CAP = 'manage_options';

    /** Tools submenu slug. */
    private const NOTES = 'stne-notes';

    /** Settings submenu slug. */
    private const OPTIONS = 'stne-settings';

    /**
     * Register menus, Settings API, CSS, and delete handlers.
     */
    public static function init(): void
    {
        add_action('admin_menu', array(self::class, 'menu'));
        add_action('admin_init', array(self::class, 'register'));
        add_action('admin_enqueue_scripts', array(self::class, 'assets'));
        add_action('admin_post_stne_delete_note', array(self::class, 'destroy'));
        add_filter('set-screen-option', array(self::class, 'persist'), 10, 3);
    }

    /**
     * Register stne_settings with the Settings API.
     */
    public static function register(): void
    {
        register_setting(
            'stne_settings_group',
            Settings::OPTION,
            array(
                'type'              => 'array',
                'sanitize_callback' => array(Settings::class, 'sanitize'),
                'default'           => Settings::defaults(),
                'show_in_rest'      => false,
            )
        );
    }

    /**
     * Add Tools and Settings pages.
     */
    public static function menu(): void
    {
        $hook = add_management_page(
            __('Sticky Notes', 'sticky-notes-everywhere'),
            __('Sticky Notes', 'sticky-notes-everywhere'),
            self::CAP,
            self::NOTES,
            array(self::class, 'notes')
        );

        add_options_page(
            __('Sticky Notes', 'sticky-notes-everywhere'),
            __('Sticky Notes', 'sticky-notes-everywhere'),
            self::CAP,
            self::OPTIONS,
            array(self::class, 'options')
        );

        if (is_string($hook)) {
            add_action('load-' . $hook, array(self::class, 'screen'));
        }
    }

    /**
     * Per-page option plus bulk-delete on load.
     */
    public static function screen(): void
    {
        add_screen_option(
            'per_page',
            array(
                'label'   => __('Notes per page', 'sticky-notes-everywhere'),
                'default' => 20,
                'option'  => 'stne_notes_per_page',
            )
        );

        self::bulk();
    }

    /**
     * Bulk Delete from WP_List_Table. Filter submits are ignored.
     */
    private static function bulk(): void
    {
        if (! current_user_can(self::CAP)) {
            return;
        }

        $raw    = isset($_REQUEST['action']) ? wp_unslash($_REQUEST['action']) : '';
        $second = isset($_REQUEST['action2']) ? wp_unslash($_REQUEST['action2']) : '';
        $action = is_string($raw) && $raw !== '-1'
            ? sanitize_key($raw)
            : sanitize_key((string) $second);

        if (isset($_REQUEST['filter_action']) || $action !== 'delete') {
            return;
        }

        check_admin_referer('bulk-stne_notes');

        $ids = isset($_REQUEST['note_ids']) && is_array($_REQUEST['note_ids'])
            ? array_map('absint', wp_unslash($_REQUEST['note_ids']))
            : array();

        $count = Store::purge($ids);

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'    => self::NOTES,
                    'deleted' => $count,
                ),
                admin_url('tools.php')
            )
        );
        exit;
    }

    /**
     * Persist the notes-per-page screen option.
     *
     * @param mixed $status Previous filter value.
     * @param mixed $option Option name.
     * @param mixed $value  Submitted value.
     * @return mixed
     */
    public static function persist($status, $option, $value)
    {
        if ($option === 'stne_notes_per_page') {
            return absint($value);
        }

        return $status;
    }

    /**
     * Admin CSS only on this plugin’s screens.
     */
    public static function assets(string $hook): void
    {
        if (! in_array($hook, array('tools_page_' . self::NOTES, 'settings_page_' . self::OPTIONS), true)) {
            return;
        }

        wp_enqueue_style(
            'stne-admin',
            \STNE_URL . 'admin/css/stne-admin.css',
            array(),
            \STNE_VERSION
        );
    }

    /**
     * Tools → Sticky Notes.
     */
    public static function notes(): void
    {
        if (! current_user_can(self::CAP)) {
            return;
        }

        $table = new Table();
        $table->prepare_items();
        $count = isset($_GET['deleted']) ? absint(wp_unslash($_GET['deleted'])) : 0;
        ?>
        <div class="wrap sne-admin">
            <h1><?php esc_html_e('Sticky Notes Everywhere', 'sticky-notes-everywhere'); ?></h1>
            <?php if ($count > 0) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php echo esc_html(sprintf(
                        /* translators: %d: deleted count */
                        _n('%d note deleted.', '%d notes deleted.', $count, 'sticky-notes-everywhere'),
                        $count
                    )); ?></p>
                </div>
            <?php endif; ?>
            <p class="description">
                <?php esc_html_e('Private notes users pinned on the site. Deleting a row removes it for that user.', 'sticky-notes-everywhere'); ?>
                <a href="<?php echo esc_url(admin_url('options-general.php?page=' . self::OPTIONS)); ?>">
                    <?php esc_html_e('Settings', 'sticky-notes-everywhere'); ?>
                </a>
            </p>
            <form method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr(self::NOTES); ?>">
                <?php $table->search_box(__('Search notes', 'sticky-notes-everywhere'), 'stne-notes'); ?>
                <?php $table->display(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Settings → Sticky Notes.
     */
    public static function options(): void
    {
        if (! current_user_can(self::CAP)) {
            return;
        }

        $data  = Settings::all();
        $roles = wp_roles()->roles;
        ?>
        <div class="wrap sne-admin">
            <h1><?php esc_html_e('Sticky Notes settings', 'sticky-notes-everywhere'); ?></h1>
            <p>
                <a href="<?php echo esc_url(admin_url('tools.php?page=' . self::NOTES)); ?>">
                    <?php esc_html_e('All Notes', 'sticky-notes-everywhere'); ?>
                </a>
            </p>

            <form method="post" action="options.php" class="sne-settings">
                <?php settings_fields('stne_settings_group'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Where notes appear', 'sticky-notes-everywhere'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr(Settings::OPTION); ?>[frontend]" value="1" <?php checked(! empty($data['frontend'])); ?>>
                                    <?php esc_html_e('Show on the public site', 'sticky-notes-everywhere'); ?>
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr(Settings::OPTION); ?>[admin]" value="1" <?php checked(! empty($data['admin'])); ?>>
                                    <?php esc_html_e('Show inside wp-admin', 'sticky-notes-everywhere'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="stne-max"><?php esc_html_e('Maximum notes per user', 'sticky-notes-everywhere'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="stne-max" name="<?php echo esc_attr(Settings::OPTION); ?>[max]" min="1" max="200" value="<?php echo esc_attr((string) $data['max']); ?>" class="small-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Allowed roles', 'sticky-notes-everywhere'); ?></th>
                        <td>
                            <fieldset>
                                <?php foreach ($roles as $slug => $role) : ?>
                                    <label>
                                        <input type="checkbox" name="<?php echo esc_attr(Settings::OPTION); ?>[roles][]" value="<?php echo esc_attr($slug); ?>" <?php checked(in_array($slug, (array) $data['roles'], true)); ?>>
                                        <?php echo esc_html(translate_user_role($role['name'])); ?>
                                    </label>
                                    <br>
                                <?php endforeach; ?>
                                <p class="description"><?php esc_html_e('Only logged-in users with a checked role can create and see their own notes.', 'sticky-notes-everywhere'); ?></p>
                            </fieldset>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Save settings', 'sticky-notes-everywhere')); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Single-row delete via admin-post.php + nonce.
     */
    public static function destroy(): void
    {
        $id = isset($_GET['note_id']) ? absint(wp_unslash($_GET['note_id'])) : 0;
        if (! current_user_can(self::CAP) || $id <= 0 || ! check_admin_referer('stne_delete_note_' . $id)) {
            wp_die(esc_html__('Forbidden', 'sticky-notes-everywhere'));
        }

        Store::delete($id);

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'    => self::NOTES,
                    'deleted' => 1,
                ),
                admin_url('tools.php')
            )
        );
        exit;
    }
}
