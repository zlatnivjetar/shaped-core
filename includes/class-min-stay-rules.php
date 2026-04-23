<?php
/**
 * Manages MotoPress min-stay booking rules on behalf of the dashboard.
 *
 * Dashboard-owned rules use room_type_ids: [0] (all accommodation types)
 * and a single season_ids entry.  Accommodation-specific rules are never
 * touched; multi-season all-accommodation rules are split when the dashboard
 * takes ownership of one of their seasons.
 *
 * Storage key: mphb_min_stay_length  (WordPress option)
 * Row shape  : { min_stay_length: int, room_type_ids: int[], season_ids: int[] }
 *
 * @package Shaped_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Shaped_Min_Stay_Rules {

	/**
	 * Validate a minimum-stay value: must be a PHP integer in [1, 28].
	 *
	 * @param mixed $value
	 * @return bool
	 */
	public static function validate( $value ): bool {
		return is_int( $value ) && $value >= 1 && $value <= 28;
	}

	/**
	 * Return the all-accommodation min-stay nights for a season, or 1 if
	 * none is configured.  First matching rule wins (MotoPress priority order).
	 *
	 * @param int $season_id
	 * @return int
	 */
	public static function get_for_season( int $season_id ): int {
		foreach ( self::load_rules() as $rule ) {
			if ( self::rule_covers_all_accommodations( $rule ) &&
				self::rule_includes_season( $rule, $season_id )
			) {
				return max( 1, (int) $rule['min_stay_length'] );
			}
		}
		return 1;
	}

	/**
	 * Build a map of season_id => min_stay_nights from all-accommodation rules.
	 * First rule that covers a season wins.
	 *
	 * @return array<int, int>
	 */
	public static function build_map(): array {
		$map = [];
		foreach ( self::load_rules() as $rule ) {
			if ( ! self::rule_covers_all_accommodations( $rule ) ) {
				continue;
			}
			$nights = max( 1, (int) $rule['min_stay_length'] );
			foreach ( $rule['season_ids'] as $sid ) {
				$sid = (int) $sid;
				if ( $sid > 0 && ! isset( $map[ $sid ] ) ) {
					$map[ $sid ] = $nights;
				}
			}
		}
		return $map;
	}

	/**
	 * Create or replace the dashboard-managed all-accommodation min-stay rule
	 * for a season.  Any existing all-accommodation rule whose season_ids list
	 * includes this season is updated (the season is removed from the list,
	 * and the rule is dropped if no seasons remain).  The new rule is then
	 * prepended so it has the highest priority.
	 *
	 * Accommodation-specific rules (room_type_ids != [0]) are never altered.
	 *
	 * @param int $season_id
	 * @param int $nights
	 */
	public static function upsert( int $season_id, int $nights ): void {
		$updated = [];
		foreach ( self::load_rules() as $rule ) {
			if ( self::rule_covers_all_accommodations( $rule ) &&
				self::rule_includes_season( $rule, $season_id )
			) {
				$remaining = self::remove_season_from_ids( $rule['season_ids'], $season_id );
				if ( ! empty( $remaining ) ) {
					$rule['season_ids'] = $remaining;
					$updated[]          = $rule;
				}
				continue;
			}
			$updated[] = $rule;
		}

		array_unshift( $updated, [
			'min_stay_length' => $nights,
			'room_type_ids'   => [ 0 ],
			'season_ids'      => [ $season_id ],
		] );

		update_option( 'mphb_min_stay_length', $updated );
	}

	/**
	 * Remove all dashboard-managed all-accommodation rules for a season.
	 * Used when a season is trashed/deleted.
	 *
	 * @param int $season_id
	 */
	public static function remove( int $season_id ): void {
		$updated = [];
		foreach ( self::load_rules() as $rule ) {
			if ( self::rule_covers_all_accommodations( $rule ) &&
				self::rule_includes_season( $rule, $season_id )
			) {
				$remaining = self::remove_season_from_ids( $rule['season_ids'], $season_id );
				if ( ! empty( $remaining ) ) {
					$rule['season_ids'] = $remaining;
					$updated[]          = $rule;
				}
				continue;
			}
			$updated[] = $rule;
		}

		update_option( 'mphb_min_stay_length', $updated );
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * @return array<int, array>
	 */
	private static function load_rules(): array {
		$rules = get_option( 'mphb_min_stay_length', [] );
		if ( ! is_array( $rules ) ) {
			return [];
		}
		return array_filter( $rules, static fn( $r ) =>
			is_array( $r ) &&
			isset( $r['min_stay_length'], $r['room_type_ids'], $r['season_ids'] ) &&
			is_array( $r['room_type_ids'] ) &&
			is_array( $r['season_ids'] )
		);
	}

	private static function rule_covers_all_accommodations( array $rule ): bool {
		return in_array( 0, array_map( 'intval', $rule['room_type_ids'] ), true );
	}

	private static function rule_includes_season( array $rule, int $season_id ): bool {
		return in_array( $season_id, array_map( 'intval', $rule['season_ids'] ), true );
	}

	/**
	 * @param int[]|mixed[] $season_ids
	 * @param int           $season_id
	 * @return int[]
	 */
	private static function remove_season_from_ids( array $season_ids, int $season_id ): array {
		return array_values( array_filter(
			array_map( 'intval', $season_ids ),
			static fn( $sid ) => $sid !== $season_id
		) );
	}
}
