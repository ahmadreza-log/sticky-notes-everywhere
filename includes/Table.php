<?php
/**
 * WP_List_Table of every user’s notes (Tools → Sticky Notes).
 *
 * Override method names (get_columns, prepare_items, column_*) must match
 * WordPress core. Our class and local variables use single words.
 *
 * @package StickyNotesEverywhere
 */

declare(strict_types=1);

namespace StickyNotesEverywhere;

if (! defined('ABSPATH')) {
    exit;
}

if (! class_exists('\WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Notes table: search, scope/user filters, bulk delete.
 */
final class Table extends \WP_List_Table
{
    /**
     * Plural stne_notes is the bulk nonce (bulk-stne_notes).
     */
    public function __construct()
    {
        parent::__construct(array(
            'singular' => 'stne_note',
            'plural'   => 'stne_notes',
            'ajax'     => false,
        ));
    }

    /**
     * Visible columns.
     *
     * @return array<string, string>
     */
    public function get_columns(): array
    {
        return array(
            'cb'      => '<input type="checkbox" />',
            'color'   => __('Color', 'sticky-notes-everywhere'),
            'title'   => __('Note', 'sticky-notes-everywhere'),
            'scope'   => __('Scope', 'sticky-notes-everywhere'),
            'page'    => __('Page', 'sticky-notes-everywhere'),
            'user'    => __('User', 'sticky-notes-everywhere'),
            'updated' => __('Updated', 'sticky-notes-everywhere'),
        );
    }

    /**
     * Sortable columns mapped to Store query keys.
     *
     * @return array<string, array{0: string, 1: bool}>
     */
    protected function get_sortable_columns(): array
    {
        return array(
            'title'   => array('title', false),
            'scope'   => array('scope', false),
            'user'    => array('user', false),
            'updated' => array('updated', true),
        );
    }

    /**
     * Bulk actions.
     *
     * @return array<string, string>
     */
    protected function get_bulk_actions(): array
    {
        return array(
            'delete' => __('Delete', 'sticky-notes-everywhere'),
        );
    }

    /**
     * Scope and user filters above the table.
     *
     * @param string $which top|bottom
     */
    protected function extra_tablenav($which): void
    {
        if ($which !== 'top') {
            return;
        }

        $scope = isset($_GET['stne_scope']) ? sanitize_key((string) wp_unslash($_GET['stne_scope'])) : '';
        $user  = isset($_GET['stne_user']) ? absint(wp_unslash($_GET['stne_user'])) : 0;
        ?>
        <div class="alignleft actions">
            <label class="screen-reader-text" for="stne-filter-scope"><?php esc_html_e('Filter by scope', 'sticky-notes-everywhere'); ?></label>
            <select name="stne_scope" id="stne-filter-scope">
                <option value=""><?php esc_html_e('All scopes', 'sticky-notes-everywhere'); ?></option>
                <option value="page" <?php selected($scope, 'page'); ?>><?php esc_html_e('This page', 'sticky-notes-everywhere'); ?></option>
                <option value="global" <?php selected($scope, 'global'); ?>><?php esc_html_e('Everywhere', 'sticky-notes-everywhere'); ?></option>
            </select>
            <label class="screen-reader-text" for="stne-filter-user"><?php esc_html_e('Filter by user', 'sticky-notes-everywhere'); ?></label>
            <input type="number" name="stne_user" id="stne-filter-user" value="<?php echo $user > 0 ? esc_attr((string) $user) : ''; ?>" placeholder="<?php esc_attr_e('User ID', 'sticky-notes-everywhere'); ?>" style="width:110px">
            <?php submit_button(__('Filter', 'sticky-notes-everywhere'), '', 'filter_action', false); ?>
        </div>
        <?php
    }

    /**
     * Query the current page of rows.
     */
    public function prepare_items(): void
    {
        $limit   = $this->get_items_per_page('stne_notes_per_page', 20);
        $orderby = isset($_GET['orderby']) ? sanitize_key((string) wp_unslash($_GET['orderby'])) : 'updated';
        $order   = isset($_GET['order']) ? strtolower(sanitize_text_field((string) wp_unslash($_GET['order']))) : 'desc';
        $search  = isset($_GET['s']) ? sanitize_text_field((string) wp_unslash($_GET['s'])) : '';
        $scope   = isset($_GET['stne_scope']) ? sanitize_key((string) wp_unslash($_GET['stne_scope'])) : '';
        $user    = isset($_GET['stne_user']) ? absint(wp_unslash($_GET['stne_user'])) : 0;

        $result = Store::query(array(
            'search'  => $search,
            'user'    => $user,
            'scope'   => $scope,
            'orderby' => $orderby,
            'order'   => $order,
            'limit'   => $limit,
            'page'    => $this->get_pagenum(),
        ));

        $this->_column_headers = array($this->get_columns(), array(), $this->get_sortable_columns(), 'title');
        $this->items           = $result['items'];

        $this->set_pagination_args(array(
            'total_items' => $result['total'],
            'per_page'    => $limit,
        ));
    }

    /**
     * Checkbox column.
     *
     * @param array<string, mixed> $item
     */
    protected function column_cb($item): string
    {
        return sprintf(
            '<input type="checkbox" name="note_ids[]" value="%d">',
            (int) $item['id']
        );
    }

    /**
     * Color swatch.
     *
     * @param array<string, mixed> $item
     */
    protected function column_color($item): string
    {
        $colors = Settings::colors();
        $hex    = $colors[$item['color']] ?? '#fff3a3';

        return sprintf(
            '<span class="sne-swatch" title="%s" style="background:%s"></span>',
            esc_attr((string) $item['color']),
            esc_attr($hex)
        );
    }

    /**
     * Title, excerpt, and row delete action.
     *
     * @param array<string, mixed> $item
     */
    protected function column_title($item): string
    {
        $title   = trim((string) $item['title']) !== '' ? (string) $item['title'] : __('Untitled note', 'sticky-notes-everywhere');
        $excerpt = wp_trim_words(wp_strip_all_tags((string) $item['content']), 16, '…');
        $erase   = wp_nonce_url(
            add_query_arg(
                array(
                    'action'  => 'stne_delete_note',
                    'note_id' => (int) $item['id'],
                ),
                admin_url('admin-post.php')
            ),
            'stne_delete_note_' . (int) $item['id']
        );

        $actions = array(
            'delete' => sprintf(
                '<a href="%s" class="submitdelete" onclick="return confirm(%s);">%s</a>',
                esc_url($erase),
                esc_attr(wp_json_encode(__('Delete this note?', 'sticky-notes-everywhere'))),
                esc_html__('Delete', 'sticky-notes-everywhere')
            ),
        );

        $html = '<strong>' . esc_html($title) . '</strong>';
        if ($excerpt !== '') {
            $html .= '<div class="description">' . esc_html($excerpt) . '</div>';
        }

        return $html . $this->row_actions($actions);
    }

    /**
     * page vs global.
     *
     * @param array<string, mixed> $item
     */
    protected function column_scope($item): string
    {
        return $item['scope'] === 'global'
            ? esc_html__('Everywhere', 'sticky-notes-everywhere')
            : esc_html__('This page', 'sticky-notes-everywhere');
    }

    /**
     * Linked heading and path.
     *
     * @param array<string, mixed> $item
     */
    protected function column_page($item): string
    {
        $label = trim((string) $item['heading']) !== ''
            ? (string) $item['heading']
            : (string) $item['path'];

        return sprintf(
            '<a href="%s">%s</a><div class="description"><code>%s</code></div>',
            esc_url((string) $item['path']),
            esc_html($label),
            esc_html((string) $item['path'])
        );
    }

    /**
     * Linked display name, or #id if the user was deleted.
     *
     * @param array<string, mixed> $item
     */
    protected function column_user($item): string
    {
        $user = get_userdata((int) $item['user']);
        if (! $user) {
            return '#' . (int) $item['user'];
        }

        return sprintf(
            '<a href="%s">%s</a>',
            esc_url(get_edit_user_link($user->ID)),
            esc_html($user->display_name)
        );
    }

    /**
     * Localized date/time.
     *
     * @param array<string, mixed> $item
     */
    protected function column_updated($item): string
    {
        $raw = (string) $item['updated'];
        if ($raw === '') {
            return '—';
        }

        $ts = strtotime($raw);

        return $ts ? esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $ts)) : esc_html($raw);
    }

    /**
     * Fallback for unknown columns.
     *
     * @param array<string, mixed> $item
     * @param string               $column_name
     */
    protected function column_default($item, $column_name): string
    {
        return esc_html((string) ($item[$column_name] ?? ''));
    }

    /**
     * Empty state.
     */
    public function no_items(): void
    {
        esc_html_e('No sticky notes found.', 'sticky-notes-everywhere');
    }
}
