<?php
/**
 * Plugin option stne_settings (Settings API).
 *
 * Keys (single word):
 * - frontend (bool) — overlay on the public site
 * - admin (bool) — overlay inside wp-admin
 * - max (int 1–200) — notes per user
 * - roles (list of role slugs)
 *
 * Older keys enable_frontend, enable_admin, max_notes are still read and written
 * through sanitize() so existing installs keep working.
 *
 * @package StickyNotesEverywhere
 */

declare(strict_types=1);

namespace StickyNotesEverywhere;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Read, sanitize, and persist settings.
 */
final class Settings
{
    /** Unique option name. */
    public const OPTION = 'stne_settings';

    /**
     * Defaults used when the option is missing.
     *
     * @return array{frontend: bool, admin: bool, max: int, roles: list<string>}
     */
    public static function defaults(): array
    {
        return array(
            'frontend' => true,
            'admin'    => true,
            'max'      => 40,
            'roles'    => array('administrator', 'editor', 'author', 'contributor', 'subscriber'),
        );
    }

    /**
     * Stored option merged with defaults. Maps legacy keys if present.
     *
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        $stored = get_option(self::OPTION, array());
        if (! is_array($stored)) {
            $stored = array();
        }

        if (! array_key_exists('frontend', $stored) && array_key_exists('enable_frontend', $stored)) {
            $stored['frontend'] = $stored['enable_frontend'];
        }
        if (! array_key_exists('admin', $stored) && array_key_exists('enable_admin', $stored)) {
            $stored['admin'] = $stored['enable_admin'];
        }
        if (! array_key_exists('max', $stored) && array_key_exists('max_notes', $stored)) {
            $stored['max'] = $stored['max_notes'];
        }

        return wp_parse_args($stored, self::defaults());
    }

    /**
     * One setting by key.
     *
     * @param mixed $fallback Value when the key is missing.
     * @return mixed
     */
    public static function get(string $key, $fallback = null)
    {
        $all = self::all();

        return $all[$key] ?? $fallback;
    }

    /**
     * Sanitize a Settings API payload. Accepts both new and legacy field names.
     *
     * @param mixed $data Raw option.
     * @return array<string, mixed>
     */
    public static function sanitize($data): array
    {
        if (! is_array($data)) {
            $data = array();
        }

        $defaults = self::defaults();
        $roles    = array();

        if (isset($data['roles']) && is_array($data['roles'])) {
            foreach ($data['roles'] as $role) {
                $role = sanitize_key((string) $role);
                if ($role !== '' && get_role($role)) {
                    $roles[] = $role;
                }
            }
        }

        $frontend = $data['frontend'] ?? $data['enable_frontend'] ?? false;
        $admin    = $data['admin'] ?? $data['enable_admin'] ?? false;
        $max      = $data['max'] ?? $data['max_notes'] ?? $defaults['max'];

        return array(
            'frontend' => ! empty($frontend),
            'admin'    => ! empty($admin),
            'max'      => max(1, min(200, absint($max))),
            'roles'    => $roles !== array() ? array_values(array_unique($roles)) : $defaults['roles'],
        );
    }

    /**
     * Persist a sanitized payload (autoload off).
     *
     * @param array<string, mixed> $data
     */
    public static function update(array $data): void
    {
        update_option(self::OPTION, self::sanitize($data), false);
    }

    /**
     * Paper colors: slug => hex. Overlay and REST only accept these slugs.
     *
     * @return array<string, string>
     */
    public static function colors(): array
    {
        return array(
            'yellow' => '#fff3a3',
            'pink'   => '#ffc6d9',
            'blue'   => '#bfe4ff',
            'green'  => '#c8f0b8',
            'orange' => '#ffd0a3',
            'purple' => '#e0c8ff',
            'mint'   => '#b8f0e4',
            'peach'  => '#ffd8c8',
        );
    }

    /**
     * Whether this WordPress user may use sticky notes.
     *
     * Filter: stne_user_can (bool $allowed, WP_User $user).
     */
    public static function can(?int $id = null): bool
    {
        $user = $id ? get_userdata($id) : wp_get_current_user();
        if (! $user instanceof \WP_User || $user->ID <= 0) {
            return false;
        }

        $roles = self::get('roles', array());
        if (! is_array($roles) || $roles === array()) {
            return is_user_logged_in();
        }

        $allowed = (bool) array_intersect($roles, (array) $user->roles);

        return (bool) apply_filters('stne_user_can', $allowed, $user);
    }
}
