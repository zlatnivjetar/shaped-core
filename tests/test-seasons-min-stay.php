<?php
/**
 * Standalone contract tests for Shaped_Min_Stay_Rules.
 *
 * Run:  php tests/test-seasons-min-stay.php
 *
 * No WordPress or MotoPress install required — uses in-process stubs for
 * get_option / update_option.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// WordPress stubs
// ---------------------------------------------------------------------------

$_option_store = [];

function get_option( string $name, $default = false ) {
	global $_option_store;
	return array_key_exists( $name, $_option_store ) ? $_option_store[ $name ] : $default;
}

function update_option( string $name, $value ): bool {
	global $_option_store;
	$_option_store[ $name ] = $value;
	return true;
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

// ---------------------------------------------------------------------------
// Load class under test
// ---------------------------------------------------------------------------

require_once dirname( __DIR__ ) . '/includes/class-min-stay-rules.php';

// ---------------------------------------------------------------------------
// Test runner
// ---------------------------------------------------------------------------

$passes = 0;
$fails  = 0;

function t( bool $ok, string $label ): void {
	global $passes, $fails;
	if ( $ok ) {
		echo "PASS  {$label}\n";
		$passes++;
	} else {
		echo "FAIL  {$label}\n";
		$fails++;
	}
}

function reset_options(): void {
	global $_option_store;
	$_option_store = [];
}

function get_rules(): array {
	return get_option( 'mphb_min_stay_length', [] );
}

// ---------------------------------------------------------------------------
// validate()
// ---------------------------------------------------------------------------

t( Shaped_Min_Stay_Rules::validate( 1 ),    'validate: 1 is valid (lower bound)' );
t( Shaped_Min_Stay_Rules::validate( 28 ),   'validate: 28 is valid (upper bound)' );
t( Shaped_Min_Stay_Rules::validate( 14 ),   'validate: 14 is valid (mid range)' );
t( ! Shaped_Min_Stay_Rules::validate( 0 ),  'validate: 0 is invalid' );
t( ! Shaped_Min_Stay_Rules::validate( 29 ), 'validate: 29 is invalid' );
t( ! Shaped_Min_Stay_Rules::validate( -1 ), 'validate: negative is invalid' );
t( ! Shaped_Min_Stay_Rules::validate( '3' ),   'validate: string "3" is invalid' );
t( ! Shaped_Min_Stay_Rules::validate( 1.0 ),   'validate: float 1.0 is invalid' );
t( ! Shaped_Min_Stay_Rules::validate( null ),  'validate: null is invalid' );

// ---------------------------------------------------------------------------
// get_for_season() — no rules
// ---------------------------------------------------------------------------

reset_options();
t( Shaped_Min_Stay_Rules::get_for_season( 5 ) === 1,
	'get_for_season: defaults to 1 when no rules exist' );

// ---------------------------------------------------------------------------
// upsert() + get_for_season()
// ---------------------------------------------------------------------------

reset_options();
Shaped_Min_Stay_Rules::upsert( 5, 3 );
t( Shaped_Min_Stay_Rules::get_for_season( 5 ) === 3,
	'upsert+get: correct value returned after upsert' );

reset_options();
Shaped_Min_Stay_Rules::upsert( 5, 3 );
Shaped_Min_Stay_Rules::upsert( 5, 7 );
t( Shaped_Min_Stay_Rules::get_for_season( 5 ) === 7,
	'upsert: second call overwrites first for same season' );

reset_options();
Shaped_Min_Stay_Rules::upsert( 5, 3 );
Shaped_Min_Stay_Rules::upsert( 10, 5 );
t( Shaped_Min_Stay_Rules::get_for_season( 5 ) === 3,
	'upsert: season 5 unaffected by upsert of season 10' );
t( Shaped_Min_Stay_Rules::get_for_season( 10 ) === 5,
	'upsert: season 10 correct after independent upsert' );
t( count( get_rules() ) === 2,
	'upsert: two independent seasons produce two rules' );

// ---------------------------------------------------------------------------
// Accommodation-specific rules are preserved
// ---------------------------------------------------------------------------

reset_options();
update_option( 'mphb_min_stay_length', [
	[ 'min_stay_length' => 3, 'room_type_ids' => [ 42 ], 'season_ids' => [ 5 ] ],
] );
Shaped_Min_Stay_Rules::upsert( 5, 4 );
$rules = get_rules();
t( count( $rules ) === 2,
	'upsert: accommodation-specific rule (room_type_ids=[42]) is preserved' );
// The accommodation-specific rule should still be there.
$has_accom = false;
foreach ( $rules as $r ) {
	if ( $r['room_type_ids'] === [ 42 ] ) {
		$has_accom = true;
		break;
	}
}
t( $has_accom, 'upsert: accommodation-specific rule content is unchanged' );

// ---------------------------------------------------------------------------
// Multi-season all-accommodation rule: upsert splits it
// ---------------------------------------------------------------------------

reset_options();
update_option( 'mphb_min_stay_length', [
	[ 'min_stay_length' => 2, 'room_type_ids' => [ 0 ], 'season_ids' => [ 5, 10 ] ],
] );
Shaped_Min_Stay_Rules::upsert( 5, 4 );
$rules = get_rules();

t( Shaped_Min_Stay_Rules::get_for_season( 5 ) === 4,
	'split multi-season: season 5 gets new value' );
t( Shaped_Min_Stay_Rules::get_for_season( 10 ) === 2,
	'split multi-season: season 10 retains original value from remaining rule' );
t( count( $rules ) === 2,
	'split multi-season: result has 2 rules (season-5-specific + season-10 remainder)' );

// Verify seasons are in different rules now.
$season5_rule  = null;
$season10_rule = null;
foreach ( $rules as $r ) {
	if ( in_array( 5,  $r['season_ids'], true ) ) $season5_rule  = $r;
	if ( in_array( 10, $r['season_ids'], true ) ) $season10_rule = $r;
}
t( $season5_rule !== null && $season5_rule['min_stay_length'] === 4,
	'split multi-season: season-5 rule has correct min_stay_length' );
t( $season10_rule !== null && $season10_rule['min_stay_length'] === 2,
	'split multi-season: season-10 rule has correct min_stay_length' );
t( ! in_array( 5, $season10_rule['season_ids'] ?? [], true ),
	'split multi-season: season 5 removed from multi-season rule' );

// ---------------------------------------------------------------------------
// remove()
// ---------------------------------------------------------------------------

reset_options();
Shaped_Min_Stay_Rules::upsert( 5, 3 );
Shaped_Min_Stay_Rules::remove( 5 );
t( Shaped_Min_Stay_Rules::get_for_season( 5 ) === 1,
	'remove: returns 1 after removing the only rule for a season' );
t( count( get_rules() ) === 0,
	'remove: option is empty after removing the last rule' );

reset_options();
Shaped_Min_Stay_Rules::upsert( 5, 3 );
Shaped_Min_Stay_Rules::upsert( 10, 5 );
Shaped_Min_Stay_Rules::remove( 5 );
t( Shaped_Min_Stay_Rules::get_for_season( 5 ) === 1,
	'remove: season 5 gone after remove' );
t( Shaped_Min_Stay_Rules::get_for_season( 10 ) === 5,
	'remove: season 10 unaffected by removing season 5' );

// remove from a multi-season rule removes the season but keeps the rule
reset_options();
update_option( 'mphb_min_stay_length', [
	[ 'min_stay_length' => 2, 'room_type_ids' => [ 0 ], 'season_ids' => [ 5, 10 ] ],
] );
Shaped_Min_Stay_Rules::remove( 5 );
$rules = get_rules();
t( count( $rules ) === 1,
	'remove from multi-season: rule count stays 1' );
t( ! in_array( 5, $rules[0]['season_ids'] ?? [], true ),
	'remove from multi-season: season 5 no longer in season_ids' );
t( in_array( 10, $rules[0]['season_ids'] ?? [], true ),
	'remove from multi-season: season 10 still in season_ids' );

// remove last season in multi-season rule drops the rule entirely
reset_options();
update_option( 'mphb_min_stay_length', [
	[ 'min_stay_length' => 2, 'room_type_ids' => [ 0 ], 'season_ids' => [ 5 ] ],
] );
Shaped_Min_Stay_Rules::remove( 5 );
t( count( get_rules() ) === 0,
	'remove: rule dropped when last season_id is removed' );

// ---------------------------------------------------------------------------
// build_map()
// ---------------------------------------------------------------------------

reset_options();
t( Shaped_Min_Stay_Rules::build_map() === [],
	'build_map: empty map when no rules' );

reset_options();
Shaped_Min_Stay_Rules::upsert( 3, 2 );
Shaped_Min_Stay_Rules::upsert( 7, 5 );
$map = Shaped_Min_Stay_Rules::build_map();
t( ( $map[3] ?? null ) === 2, 'build_map: season 3 → 2' );
t( ( $map[7] ?? null ) === 5, 'build_map: season 7 → 5' );

// build_map ignores accommodation-specific rules
reset_options();
update_option( 'mphb_min_stay_length', [
	[ 'min_stay_length' => 3, 'room_type_ids' => [ 42 ], 'season_ids' => [ 5 ] ],
] );
$map = Shaped_Min_Stay_Rules::build_map();
t( ! isset( $map[5] ),
	'build_map: accommodation-specific rule is not included in map' );

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------

echo "\n{$passes} passed, {$fails} failed.\n";
exit( $fails > 0 ? 1 : 0 );
