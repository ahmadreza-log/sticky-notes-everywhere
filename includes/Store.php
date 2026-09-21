<?php
/**
 * CRUD for {$wpdb->prefix}stne_notes.
 *
 * Public arrays use single-word keys. MySQL columns stay snake_case.
 *
 * Public keys: id, user, title, content, color, scope, path, heading,
 * x, y, width, height, z, minimized, created, updated.
 *
 * @package StickyNotesEverywhere
 */

declare(strict_types=1);

namespace StickyNotesEverywhere;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Database access for notes.
 */
final class Store
{
    /**
     * Notes visible on a path: global rows plus page rows for that path.
     *
     * @return list<array<string, mixed>>
     */
    public static function listed(int $user, string $path): array
    {
        global $wpdb;

        $table = Database::table();
        $path  = self::path($path);
        $sql   = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d AND (scope = 'global' OR (scope = 'page' AND page_path = %s)) ORDER BY z_index ASC, id ASC",
            $user,
            $path
        );

        $rows = $wpdb->get_results($sql, ARRAY_A);

        return array_map(array(self::class, 'cast'), is_array($rows) ? $rows : array());
    }

    /**
     * Every note owned by a user (tray).
     *
     * @return list<array<string, mixed>>
     */
    public static function owned(int $user): array
    {
        global $wpdb;

        $table = Database::table();
        $sql   = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d ORDER BY updated_at DESC, id DESC",
            $user
        );

        $rows = $wpdb->get_results($sql, ARRAY_A);

        return array_map(array(self::class, 'cast'), is_array($rows) ? $rows : array());
    }

    /**
     * Row count used to enforce max.
     */
    public static function count(int $user): int
    {
        global $wpdb;

        $table = Database::table();

        return (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE user_id = %d", $user)
        );
    }

    /**
     * One row by id, or null.
     *
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        global $wpdb;

        $table = Database::table();
        $row   = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id),
            ARRAY_A
        );

        return is_array($row) ? self::cast($row) : null;
    }

    /**
     * Insert a note for $user.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>|null
     */
    public static function create(int $user, array $input): ?array
    {
        global $wpdb;

        $now  = current_time('mysql');
        $data = self::sanitize($input);
        $ok   = $wpdb->insert(
            Database::table(),
            array(
                'user_id'    => $user,
                'title'      => $data['title'],
                'content'    => $data['content'],
                'color'      => $data['color'],
                'scope'      => $data['scope'],
                'page_path'  => $data['path'],
                'page_title' => $data['heading'],
                'pos_x'      => $data['x'],
                'pos_y'      => $data['y'],
                'width'      => $data['width'],
                'height'     => $data['height'],
                'z_index'    => $data['z'],
                'minimized'  => $data['minimized'],
                'created_at' => $now,
                'updated_at' => $now,
            ),
            array(
                '%d', '%s', '%s', '%s', '%s', '%s', '%s',
                '%f', '%f', '%d', '%d', '%d', '%d', '%s', '%s',
            )
        );

        if ($ok === false) {
            return null;
        }

        return self::find((int) $wpdb->insert_id);
    }

    /**
     * Update a row. Missing keys keep previous values.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>|null
     */
    public static function update(int $id, array $input): ?array
    {
        global $wpdb;

        $existing = self::find($id);
        if ($existing === null) {
            return null;
        }

        $data = self::sanitize($input, $existing);
        $wpdb->update(
            Database::table(),
            array(
                'title'      => $data['title'],
                'content'    => $data['content'],
                'color'      => $data['color'],
                'scope'      => $data['scope'],
                'page_path'  => $data['path'],
                'page_title' => $data['heading'],
                'pos_x'      => $data['x'],
                'pos_y'      => $data['y'],
                'width'      => $data['width'],
                'height'     => $data['height'],
                'z_index'    => $data['z'],
                'minimized'  => $data['minimized'],
                'updated_at' => current_time('mysql'),
            ),
            array('id' => $id),
            array(
                '%s', '%s', '%s', '%s', '%s', '%s',
                '%f', '%f', '%d', '%d', '%d', '%d', '%s',
            ),
            array('%d')
        );

        return self::find($id);
    }

    /**
     * Delete one row.
     */
    public static function delete(int $id): bool
    {
        global $wpdb;

        return $wpdb->delete(Database::table(), array('id' => $id), array('%d')) !== false;
    }

    /**
     * Bulk delete for the Tools table.
     *
     * @param list<int> $ids
     */
    public static function purge(array $ids): int
    {
        global $wpdb;

        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === array()) {
            return 0;
        }

        $table = Database::table();
        $in    = implode(',', array_fill(0, count($ids), '%d'));
        $sql   = $wpdb->prepare("DELETE FROM {$table} WHERE id IN ({$in})", ...$ids); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        $count = $wpdb->query($sql);

        return is_int($count) ? $count : 0;
    }

    /**
     * Admin listing with filters.
     *
     * @param array{search?: string, user?: int, scope?: string, orderby?: string, order?: string, limit?: int, page?: int} $args
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public static function query(array $args): array
    {
        global $wpdb;

        $table   = Database::table();
        $where   = array('1=1');
        $params  = array();
        $search  = isset($args['search']) ? trim((string) $args['search']) : '';
        $user    = isset($args['user']) ? (int) $args['user'] : 0;
        $scope   = isset($args['scope']) ? sanitize_key((string) $args['scope']) : '';
        $orderby = isset($args['orderby']) ? sanitize_key((string) $args['orderby']) : 'updated';
        $order   = isset($args['order']) && strtolower((string) $args['order']) === 'asc' ? 'ASC' : 'DESC';
        $limit   = max(1, min(100, (int) ($args['limit'] ?? 20)));
        $page    = max(1, (int) ($args['page'] ?? 1));
        $offset  = ($page - 1) * $limit;

        $columns = array(
            'id'      => 'id',
            'title'   => 'title',
            'color'   => 'color',
            'scope'   => 'scope',
            'heading' => 'page_title',
            'user'    => 'user_id',
            'updated' => 'updated_at',
            'created' => 'created_at',
        );

        if (! isset($columns[$orderby])) {
            $orderby = 'updated';
        }
        $column = $columns[$orderby];

        if ($user > 0) {
            $where[]  = 'user_id = %d';
            $params[] = $user;
        }

        if (in_array($scope, array('page', 'global'), true)) {
            $where[]  = 'scope = %s';
            $params[] = $scope;
        }

        if ($search !== '') {
            $like     = '%' . $wpdb->esc_like($search) . '%';
            $where[]  = '(title LIKE %s OR content LIKE %s OR page_title LIKE %s OR page_path LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $clause = implode(' AND ', $where);
        $count  = "SELECT COUNT(*) FROM {$table} WHERE {$clause}";
        $select = "SELECT * FROM {$table} WHERE {$clause} ORDER BY {$column} {$order} LIMIT %d OFFSET %d";

        $total = (int) ($params !== array()
            ? $wpdb->get_var($wpdb->prepare($count, ...$params))
            : $wpdb->get_var($count));

        $params[] = $limit;
        $params[] = $offset;
        $rows     = $wpdb->get_results($wpdb->prepare($select, ...$params), ARRAY_A);

        return array(
            'items' => array_map(array(self::class, 'cast'), is_array($rows) ? $rows : array()),
            'total' => $total,
        );
    }

    /**
     * Site-relative path. Strips scheme/host. Keeps query string for wp-admin.
     */
    public static function path(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '/';
        }

        if (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://')) {
            $path  = (string) wp_parse_url($raw, PHP_URL_PATH);
            $query = (string) wp_parse_url($raw, PHP_URL_QUERY);
            $raw   = $path . ($query !== '' ? '?' . $query : '');
        }

        $raw = '/' . ltrim($raw, '/');
        $raw = preg_replace('#/+#', '/', $raw) ?? $raw;

        return substr($raw, 0, 500);
    }

    /**
     * Clamp a payload. $existing fills missing keys on update.
     * Accepts new keys (path, heading, x, y, z) and legacy keys.
     *
     * @param array<string, mixed> $input
     * @param array<string, mixed> $existing
     * @return array{
     *   title: string,
     *   content: string,
     *   color: string,
     *   scope: string,
     *   path: string,
     *   heading: string,
     *   x: float,
     *   y: float,
     *   width: int,
     *   height: int,
     *   z: int,
     *   minimized: int
     * }
     */
    public static function sanitize(array $input, array $existing = array()): array
    {
        $colors = array_keys(Settings::colors());
        $color  = isset($input['color']) ? sanitize_key((string) $input['color']) : (string) ($existing['color'] ?? 'yellow');
        if (! in_array($color, $colors, true)) {
            $color = 'yellow';
        }

        $scope = isset($input['scope']) ? sanitize_key((string) $input['scope']) : (string) ($existing['scope'] ?? 'page');
        if (! in_array($scope, array('page', 'global'), true)) {
            $scope = 'page';
        }

        $title = isset($input['title'])
            ? sanitize_text_field((string) $input['title'])
            : (string) ($existing['title'] ?? '');
        $title = substr($title, 0, 190);

        $content = isset($input['content'])
            ? sanitize_textarea_field((string) $input['content'])
            : (string) ($existing['content'] ?? '');

        $rawpath = $input['path'] ?? $input['page_path'] ?? null;
        $path    = $rawpath !== null
            ? self::path((string) $rawpath)
            : (string) ($existing['path'] ?? '/');

        $rawheading = $input['heading'] ?? $input['page_title'] ?? null;
        $heading    = $rawheading !== null
            ? sanitize_text_field((string) $rawheading)
            : (string) ($existing['heading'] ?? '');
        $heading = substr($heading, 0, 190);

        $x = $input['x'] ?? $input['pos_x'] ?? null;
        $y = $input['y'] ?? $input['pos_y'] ?? null;
        $z = $input['z'] ?? $input['z_index'] ?? null;

        return array(
            'title'     => $title,
            'content'   => $content,
            'color'     => $color,
            'scope'     => $scope,
            'path'      => $path,
            'heading'   => $heading,
            'x'         => self::float($x, (float) ($existing['x'] ?? 48), -4000, 8000),
            'y'         => self::float($y, (float) ($existing['y'] ?? 96), -4000, 8000),
            'width'     => self::int($input['width'] ?? null, (int) ($existing['width'] ?? 240), 180, 520),
            'height'    => self::int($input['height'] ?? null, (int) ($existing['height'] ?? 220), 80, 720),
            'z'         => self::int($z, (int) ($existing['z'] ?? 1), 1, 9999),
            'minimized' => ! empty($input['minimized'] ?? $existing['minimized'] ?? false) ? 1 : 0,
        );
    }

    /**
     * Map a MySQL row (or mixed keys) to the public single-word shape.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function cast(array $row): array
    {
        return array(
            'id'        => (int) ($row['id'] ?? 0),
            'user'      => (int) ($row['user'] ?? $row['user_id'] ?? 0),
            'title'     => (string) ($row['title'] ?? ''),
            'content'   => (string) ($row['content'] ?? ''),
            'color'     => (string) ($row['color'] ?? 'yellow'),
            'scope'     => (string) ($row['scope'] ?? 'page'),
            'path'      => (string) ($row['path'] ?? $row['page_path'] ?? '/'),
            'heading'   => (string) ($row['heading'] ?? $row['page_title'] ?? ''),
            'x'         => (float) ($row['x'] ?? $row['pos_x'] ?? 48),
            'y'         => (float) ($row['y'] ?? $row['pos_y'] ?? 96),
            'width'     => (int) ($row['width'] ?? 240),
            'height'    => (int) ($row['height'] ?? 220),
            'z'         => (int) ($row['z'] ?? $row['z_index'] ?? 1),
            'minimized' => ! empty($row['minimized']),
            'created'   => (string) ($row['created'] ?? $row['created_at'] ?? ''),
            'updated'   => (string) ($row['updated'] ?? $row['updated_at'] ?? ''),
        );
    }

    /**
     * Clamp a float or keep $fallback.
     *
     * @param mixed $value
     */
    private static function float($value, float $fallback, float $min, float $max): float
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        return max($min, min($max, (float) $value));
    }

    /**
     * Clamp an int or keep $fallback.
     *
     * @param mixed $value
     */
    private static function int($value, int $fallback, int $min, int $max): int
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        return max($min, min($max, (int) $value));
    }
}
