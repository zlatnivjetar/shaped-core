<?php
/**
 * Dashboard REST API — canonical MotoPress seasons.
 *
 * Namespace : shaped/v1
 * Auth      : X-Shaped-API-Key header via shaped_dashboard_auth()
 *
 * GET    /dashboard/seasons
 * POST   /dashboard/seasons
 * PUT    /dashboard/seasons/{id}
 * DELETE /dashboard/seasons/{id}
 *
 * @package Shaped_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Shaped_Dashboard_Seasons_Api {

	const NAMESPACE = 'shaped/v1';

	public static function init(): void {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	public static function register_routes(): void {
		register_rest_route( self::NAMESPACE, '/dashboard/seasons', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'get_seasons' ],
				'permission_callback' => 'shaped_dashboard_auth',
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ __CLASS__, 'create_season' ],
				'permission_callback' => 'shaped_dashboard_auth',
			],
		] );

		register_rest_route( self::NAMESPACE, '/dashboard/seasons/(?P<id>\d+)', [
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ __CLASS__, 'update_season' ],
				'permission_callback' => 'shaped_dashboard_auth',
				'args'                => [
					'id' => [ 'required' => true, 'type' => 'integer', 'minimum' => 1 ],
				],
			],
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ __CLASS__, 'delete_season' ],
				'permission_callback' => 'shaped_dashboard_auth',
				'args'                => [
					'id' => [ 'required' => true, 'type' => 'integer', 'minimum' => 1 ],
				],
			],
		] );
	}

	/**
	 * GET /dashboard/seasons
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_seasons( WP_REST_Request $request ) {
		if ( ! function_exists( 'MPHB' ) ) {
			return new WP_Error( 'motopress_unavailable', 'MotoPress Hotel Booking plugin is required.', [ 'status' => 503 ] );
		}

		$seasons = MPHB()->getSeasonRepository()->findAll( [
			'post_status' => self::editable_post_statuses(),
			'orderby'     => 'ID',
			'order'       => 'ASC',
		] );

		if ( ! is_array( $seasons ) ) {
			$seasons = [];
		}

		$usage_map = self::build_season_rate_usage_map();

		$payload = array_values( array_map(
			static fn( $season ) => self::build_season_payload( $season, $usage_map ),
			$seasons
		) );

		return new WP_REST_Response( [
			'seasons'      => $payload,
			'generated_at' => gmdate( 'c' ),
		], 200 );
	}

	/**
	 * POST /dashboard/seasons
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_season( WP_REST_Request $request ) {
		if ( ! function_exists( 'MPHB' ) ) {
			return new WP_Error( 'motopress_unavailable', 'MotoPress Hotel Booking plugin is required.', [ 'status' => 503 ] );
		}

		$fields = self::parse_write_fields( $request );
		if ( is_wp_error( $fields ) ) {
			return $fields;
		}

		$entity = self::make_season_entity( 0, $fields, null );
		$id     = MPHB()->getSeasonRepository()->save( $entity );

		if ( ! $id ) {
			return new WP_Error( 'save_failed', 'Failed to create the season.', [ 'status' => 500 ] );
		}

		return new WP_REST_Response( [
			'season'     => self::build_season_payload( $entity, [] ),
			'updated_at' => gmdate( 'c' ),
		], 201 );
	}

	/**
	 * PUT /dashboard/seasons/{id}
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update_season( WP_REST_Request $request ) {
		if ( ! function_exists( 'MPHB' ) ) {
			return new WP_Error( 'motopress_unavailable', 'MotoPress Hotel Booking plugin is required.', [ 'status' => 503 ] );
		}

		$season_id = (int) $request->get_param( 'id' );
		$existing  = MPHB()->getSeasonRepository()->findById( $season_id );

		if ( ! $existing ) {
			return new WP_Error( 'not_found', "Season {$season_id} not found.", [ 'status' => 404 ] );
		}

		$fields = self::parse_write_fields( $request );
		if ( is_wp_error( $fields ) ) {
			return $fields;
		}

		// Preserve repeat_until_date when repeat_period stays 'year'.
		$repeat_until = $fields['repeat_period'] === 'year'
			? $existing->getRepeatUntilDate()
			: null;

		$entity = self::make_season_entity( $season_id, $fields, $repeat_until );
		$saved  = MPHB()->getSeasonRepository()->save( $entity );

		if ( ! $saved ) {
			return new WP_Error( 'save_failed', 'Failed to save the season.', [ 'status' => 500 ] );
		}

		return new WP_REST_Response( [
			'season'     => self::build_season_payload( $entity, [] ),
			'updated_at' => gmdate( 'c' ),
		], 200 );
	}

	/**
	 * DELETE /dashboard/seasons/{id}
	 *
	 * Moves the season to WordPress trash rather than permanently deleting it.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function delete_season( WP_REST_Request $request ) {
		if ( ! function_exists( 'MPHB' ) ) {
			return new WP_Error( 'motopress_unavailable', 'MotoPress Hotel Booking plugin is required.', [ 'status' => 503 ] );
		}

		$season_id = (int) $request->get_param( 'id' );
		$existing  = MPHB()->getSeasonRepository()->findById( $season_id );

		if ( ! $existing ) {
			return new WP_Error( 'not_found', "Season {$season_id} not found.", [ 'status' => 404 ] );
		}

		$trashed = wp_trash_post( $season_id );

		if ( ! $trashed ) {
			return new WP_Error( 'delete_failed', 'Failed to trash the season.', [ 'status' => 500 ] );
		}

		return new WP_REST_Response( [
			'deleted_season_id' => $season_id,
			'deleted_at'        => gmdate( 'c' ),
		], 200 );
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Parse and validate the writable season fields from the request body.
	 *
	 * @param WP_REST_Request $request
	 * @return array|WP_Error  Keys: title, start_date, end_date, days, repeat_period.
	 */
	private static function parse_write_fields( WP_REST_Request $request ) {
		$body = $request->get_json_params();

		if ( ! is_array( $body ) ) {
			return new WP_Error( 'bad_request', 'Expected a JSON object in the request body.', [ 'status' => 400 ] );
		}

		// title
		$title = isset( $body['title'] ) && is_string( $body['title'] )
			? sanitize_text_field( trim( $body['title'] ) )
			: '';

		if ( $title === '' ) {
			return new WP_Error( 'invalid_data', 'title is required and must be a non-empty string.', [ 'status' => 400 ] );
		}

		// start_date / end_date
		$start_date = isset( $body['start_date'] ) && is_string( $body['start_date'] ) ? $body['start_date'] : '';
		$end_date   = isset( $body['end_date'] )   && is_string( $body['end_date'] )   ? $body['end_date']   : '';

		if ( ! self::is_valid_date( $start_date ) ) {
			return new WP_Error( 'invalid_data', 'start_date must be a valid YYYY-MM-DD date.', [ 'status' => 400 ] );
		}

		if ( ! self::is_valid_date( $end_date ) ) {
			return new WP_Error( 'invalid_data', 'end_date must be a valid YYYY-MM-DD date.', [ 'status' => 400 ] );
		}

		if ( $start_date > $end_date ) {
			return new WP_Error( 'invalid_data', 'start_date must be on or before end_date.', [ 'status' => 400 ] );
		}

		// days
		if ( ! isset( $body['days'] ) || ! is_array( $body['days'] ) ) {
			return new WP_Error( 'invalid_data', 'days must be a non-empty array of integers from 0 through 6.', [ 'status' => 400 ] );
		}

		$days = array_values( array_unique( array_map( 'intval', $body['days'] ) ) );

		foreach ( $days as $day ) {
			if ( $day < 0 || $day > 6 ) {
				return new WP_Error( 'invalid_data', 'Each day must be an integer from 0 (Sunday) through 6 (Saturday).', [ 'status' => 400 ] );
			}
		}

		if ( empty( $days ) ) {
			return new WP_Error( 'invalid_data', 'days must contain at least one weekday.', [ 'status' => 400 ] );
		}

		// repeat_period
		$repeat_period = isset( $body['repeat_period'] ) && is_string( $body['repeat_period'] )
			? $body['repeat_period']
			: '';

		if ( ! in_array( $repeat_period, [ 'none', 'year' ], true ) ) {
			return new WP_Error( 'invalid_data', 'repeat_period must be "none" or "year".', [ 'status' => 400 ] );
		}

		return compact( 'title', 'start_date', 'end_date', 'days', 'repeat_period' );
	}

	/**
	 * Construct a MotoPress Season (or RecurrentSeason) entity from parsed fields.
	 *
	 * @param int            $id           0 for new seasons.
	 * @param array          $fields       Validated fields from parse_write_fields().
	 * @param \DateTime|null $repeat_until Existing stop date to preserve, or null.
	 * @return \MPHB\Entities\Season|\MPHB\Entities\RecurrentSeason
	 */
	private static function make_season_entity( int $id, array $fields, $repeat_until ) {
		$atts = [
			'id'                => $id,
			'title'             => $fields['title'],
			'description'       => '',
			'start_date'        => new DateTime( $fields['start_date'] ),
			'end_date'          => new DateTime( $fields['end_date'] ),
			'days'              => $fields['days'],
			'repeat_period'     => $fields['repeat_period'],
			'repeat_until_date' => $repeat_until,
		];

		if ( $fields['repeat_period'] === 'year' ) {
			return new \MPHB\Entities\RecurrentSeason( $atts );
		}

		return new \MPHB\Entities\Season( $atts );
	}

	/**
	 * Normalize a season entity to the dashboard contract shape.
	 *
	 * @param object $season    MotoPress season entity.
	 * @param array  $usage_map season_id => rate_count.
	 * @return array<string, mixed>
	 */
	private static function build_season_payload( $season, array $usage_map ): array {
		$repeat_until = $season->getRepeatUntilDate();
		$season_id    = (int) $season->getId();

		return [
			'id'                => $season_id,
			'title'             => $season->getTitle(),
			'description'       => $season->getDescription(),
			'start_date'        => self::format_date( $season->getStartDate() ),
			'end_date'          => self::format_date( $season->getEndDate() ),
			'repeat_period'     => (string) $season->getRepeatPeriod(),
			'repeat_until_date' => $repeat_until instanceof DateTimeInterface
				? $repeat_until->format( 'Y-m-d' )
				: null,
			'days'              => array_values( array_map( 'intval', $season->getDays() ) ),
			'is_recurring'      => (bool) $season->isRecurring(),
			'used_by_rates'     => $usage_map[ $season_id ] ?? 0,
		];
	}

	/**
	 * Build a map of season_id => count of rates that reference it.
	 *
	 * @return array<int, int>
	 */
	private static function build_season_rate_usage_map(): array {
		$rates = MPHB()->getRateRepository()->findAll( [
			'post_status' => self::editable_post_statuses(),
		] );

		if ( ! is_array( $rates ) ) {
			return [];
		}

		$usage = [];

		foreach ( $rates as $rate ) {
			$seen = [];
			foreach ( $rate->getSeasonPrices() as $sp ) {
				$sid = (int) $sp->getSeasonId();
				if ( $sid > 0 && ! isset( $seen[ $sid ] ) ) {
					$seen[ $sid ]  = true;
					$usage[ $sid ] = ( $usage[ $sid ] ?? 0 ) + 1;
				}
			}
		}

		return $usage;
	}

	/**
	 * Format a DateTime-like value as YYYY-MM-DD.
	 *
	 * @param mixed $value
	 * @return string|null
	 */
	private static function format_date( $value ): ?string {
		return $value instanceof DateTimeInterface ? $value->format( 'Y-m-d' ) : null;
	}

	/**
	 * Validate that a string is a real calendar date in YYYY-MM-DD format.
	 *
	 * @param string $value
	 * @return bool
	 */
	private static function is_valid_date( string $value ): bool {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return false;
		}

		$d = DateTime::createFromFormat( 'Y-m-d', $value );

		return $d && $d->format( 'Y-m-d' ) === $value;
	}

	/**
	 * Post statuses that should be visible to pricing operators.
	 *
	 * @return string[]
	 */
	private static function editable_post_statuses(): array {
		return [ 'publish', 'pending', 'draft', 'future', 'private' ];
	}
}
