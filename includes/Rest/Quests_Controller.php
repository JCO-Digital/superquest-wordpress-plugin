<?php
/**
 * REST routes the block editor uses to list and refresh quests.
 *
 * @package SuperQuest
 */

namespace SuperQuest\Rest;

use SuperQuest\Admin\Menu;
use SuperQuest\Options;
use SuperQuest\Quests;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * `superquest/v1/quests` and `superquest/v1/quests/refresh`.
 */
class Quests_Controller extends WP_REST_Controller {

	/**
	 * Route namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'superquest/v1';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'quests';

	/**
	 * Registers the routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/refresh',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'refresh_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Anyone who can edit posts can pick a quest for a block.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return bool
	 */
	public function get_items_permissions_check( $request ): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Returns the organisation and its cached quests.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return WP_REST_Response
	 */
	public function get_items( $request ): WP_REST_Response {
		return rest_ensure_response( $this->payload() );
	}

	/**
	 * Refetches the quests from the SuperQuest API, then returns them.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return WP_REST_Response|\WP_Error
	 */
	public function refresh_items( $request ) {
		$result = Quests::refresh();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $this->payload() );
	}

	/**
	 * The response shape shared by both routes.
	 *
	 * @return array<string, mixed>
	 */
	private function payload(): array {
		$can_manage = current_user_can( 'manage_options' );

		return array(
			'organization_id' => Options::organization_id(),
			'quests'          => Quests::items(),
			'message'         => Quests::message(),
			'updated'         => Quests::updated(),
			'can_manage'      => $can_manage,
			'settings_url'    => $can_manage ? Menu::url() : '',
		);
	}

	/**
	 * Schema of the response.
	 *
	 * @return array<string, mixed>
	 */
	public function get_item_schema(): array {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'superquest-quests',
			'type'       => 'object',
			'properties' => array(
				'organization_id' => array(
					'description' => __( 'The configured SuperQuest organisation ID.', 'superquest' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'quests'          => array(
					'description' => __( 'The quests available to the organisation.', 'superquest' ),
					'type'        => 'array',
					'readonly'    => true,
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'id'    => array( 'type' => 'string' ),
							'title' => array( 'type' => 'string' ),
						),
					),
				),
				'message'         => array(
					'description' => __( 'Message returned by the SuperQuest API with the last fetch.', 'superquest' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'updated'         => array(
					'description' => __( 'Unix timestamp of the last fetch, 0 when never fetched.', 'superquest' ),
					'type'        => 'integer',
					'readonly'    => true,
				),
				'can_manage'      => array(
					'description' => __( 'Whether the current user can change the plugin settings.', 'superquest' ),
					'type'        => 'boolean',
					'readonly'    => true,
				),
				'settings_url'    => array(
					'description' => __( 'URL of the settings screen, empty when the user cannot manage settings.', 'superquest' ),
					'type'        => 'string',
					'readonly'    => true,
				),
			),
		);

		return $this->add_additional_fields_schema( $this->schema );
	}
}
