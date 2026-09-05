<?php
/**
 * Uninstall cleanup.
 *
 * Removes this plugin's own settings only. The `_rhot_phone_digits` order
 * meta is deliberately left in place: it lives on financial records, deleting
 * it would mean writing to every order on the store during an uninstall, and
 * re-installing later would otherwise require a full re-index. It is inert
 * data that costs nothing to keep.
 *
 * @package RH_Order_Track
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'rhot_settings' );
delete_option( 'rhot_backfill_cursor' );
