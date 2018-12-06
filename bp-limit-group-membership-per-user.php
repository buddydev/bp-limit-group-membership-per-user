<?php

/**
 * Plugin Name: BuddyPress Limit Group Membership
 * Plugin URI: https://buddydev.com/plugins/bp-limit-group-membership/
 * Author: BuddyDev
 * Author URI: https://buddydev.com/members/sbrajesh
 * Version : 1.0.7
 * License: GPL
 * Description: Restricts the no. of Groups a user can join
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 0 );
}

class BP_Limit_Group_Membership_Loader {

	/**
	 * Singleton instance.
	 *
	 * @var BP_Limit_Group_Membership_Loader
	 */
	private static $instance = null;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->setup();
	}

	/**
	 * Get the singleton instance.
	 *
	 * @return BP_Limit_Group_Membership_Loader
	 */
	public static function get_instance() {

		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Setup actions
	 */
	private function setup() {
		add_action( 'bp_loaded', array( $this, 'load' ) );
		add_action( 'bp_init', array( $this, 'load_textdomain' ), 2 );
	}

	/**
	 * Load required files
	 */
	public function load() {
		$path = plugin_dir_path( __FILE__ );

		require_once $path . 'core/bp-limit-group-membership-action-handler.php';
		require_once $path . 'core/bp-limit-group-membership-admin.php';
	}

	/**
	 * Load translation
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'bp-limit-group-membership-per-user', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

}

// initialize.
BP_Limit_Group_Membership_Loader::get_instance();
