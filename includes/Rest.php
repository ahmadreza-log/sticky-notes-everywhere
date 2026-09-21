<?php
/**
 * REST API for the current user's notes.
 *
 * Namespace: sticky-notes-everywhere/v1
 * GET|POST /notes
 * POST|PUT|PATCH|DELETE /notes/{id}
 *
 * Auth: logged-in cookie + X-WP-Nonce. Permission: Settings::can().
 * JSON body uses single-word keys (see Store::cast).
 *
 * @package StickyNotesEverywhere
 */

declare(strict_types=1);

namespace StickyNotesEverywhere;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * REST controllers.
 */
final class Rest
{
    /** Unique namespace (plugin slug + version). */
    public const NAMESPACE = 'sticky-notes-everywhere/v1';

    /**
     * Register rest_api_init.
     */
    public static function init(): void
    {
        add_action('rest_api_init', array(self::class, 'routes'));
    }

    /**
     * Collection and item routes.
     */
    public static function routes(): void
    {
        register_rest_route(
            self::NAMESPACE,
            '/notes',
            array(
                array(
                    'methods'             => 'GET',
                    'callback'            => array(self::class, 'index'),
                    'permission_callback' => array(self::class, 'can'),
                    'args'                => array(
                        'path' => array(
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                        'all'  => array(
                            'type' => 'boolean',
                        ),
                    ),
                ),
                array(
                    'methods'             => 'POST',
                    'callback'            => array(self::class, 'store'),
                    'permission_callback' => array(self::class, 'can'),
                ),
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/notes/(?P<id>\\d+)',
            array(
                array(
                    'methods'             => \WP_REST_Server::EDITABLE,
                    'callback'            => array(self::class, 'update'),
                    'permission_callback' => array(self::class, 'can'),
                    'args'                => array(
                        'id' => array(
                            'type'              => 'integer',
                            'required'          => true,
                            'sanitize_callback' => 'absint',
                        ),
                    ),
                ),
                array(
                    'methods'             => 'DELETE',
                    'callback'            => array(self::class, 'destroy'),
                    'permission_callback' => array(self::class, 'can'),
                    'args'                => array(
                        'id' => array(
                            'type'              => 'integer',
                            'required'          => true,
                            'sanitize_callback' => 'absint',
                        ),
                    ),
                ),
            )
        );
    }

    /**
     * Logged-in user with an allowed role.
     */
    public static function can(): bool
    {
        return is_user_logged_in() && Settings::can();
    }

    /**
     * List notes for this user. all=1 returns every note; otherwise filter by path.
     */
    public static function index(\WP_REST_Request $request): \WP_REST_Response
    {
        $user = get_current_user_id();
        $all  = (bool) $request->get_param('all');
        $path = (string) $request->get_param('path');

        $notes = $all
            ? Store::owned($user)
            : Store::listed($user, $path);

        return new \WP_REST_Response($notes, 200);
    }

    /**
     * Create a note. 400 when max is reached.
     *
     * @return \WP_REST_Response|\WP_Error
     */
    public static function store(\WP_REST_Request $request)
    {
        $user = get_current_user_id();
        $max  = (int) Settings::get('max', 40);

        if (Store::count($user) >= $max) {
            return new \WP_Error(
                'stne_limit',
                sprintf(
                    /* translators: %d: maximum notes */
                    __('You have reached the maximum of %d notes.', 'sticky-notes-everywhere'),
                    $max
                ),
                array('status' => 400)
            );
        }

        $note = Store::create($user, (array) $request->get_json_params());
        if ($note === null) {
            return new \WP_Error(
                'stne_create_failed',
                __('Could not create the note.', 'sticky-notes-everywhere'),
                array('status' => 500)
            );
        }

        return new \WP_REST_Response($note, 201);
    }

    /**
     * Update a note the current user owns. Administrators may edit any row.
     *
     * @return \WP_REST_Response|\WP_Error
     */
    public static function update(\WP_REST_Request $request)
    {
        $note = self::owned((int) $request['id']);
        if ($note instanceof \WP_Error) {
            return $note;
        }

        $updated = Store::update((int) $note['id'], (array) $request->get_json_params());
        if ($updated === null) {
            return new \WP_Error(
                'stne_update_failed',
                __('Could not update the note.', 'sticky-notes-everywhere'),
                array('status' => 500)
            );
        }

        return new \WP_REST_Response($updated, 200);
    }

    /**
     * Delete a note the current user owns. Administrators may delete any row.
     *
     * @return \WP_REST_Response|\WP_Error
     */
    public static function destroy(\WP_REST_Request $request)
    {
        $note = self::owned((int) $request['id']);
        if ($note instanceof \WP_Error) {
            return $note;
        }

        Store::delete((int) $note['id']);

        return new \WP_REST_Response(null, 204);
    }

    /**
     * Load a note and reject it when it belongs to someone else (unless manage_options).
     *
     * @return array<string, mixed>|\WP_Error
     */
    private static function owned(int $id)
    {
        $note = Store::find($id);
        if ($note === null) {
            return new \WP_Error(
                'stne_not_found',
                __('Note not found.', 'sticky-notes-everywhere'),
                array('status' => 404)
            );
        }

        $user = get_current_user_id();
        if ((int) $note['user'] !== $user && ! current_user_can('manage_options')) {
            return new \WP_Error(
                'stne_forbidden',
                __('You cannot edit this note.', 'sticky-notes-everywhere'),
                array('status' => 403)
            );
        }

        return $note;
    }
}
