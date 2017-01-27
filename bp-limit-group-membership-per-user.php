<?php

/**
 * Plugin Name: BuddyPress Limit Group Membership
 * Plugin URI: https://buddydev.com/plugins/bp-limit-group-membership/
 * Author: BuddyDev
 * Author URI: https://buddydev.com/members/sbrajesh
 * Version : 1.0.5
 * License: GPL
 * Description: Restricts the no. of Groups a user can join
 */
class BP_Limit_Group_Membership_Loader {

	private static $instance = null;

	private function __construct() {
		$this->setup();
	}

	/**
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
	}

	/**
	 * Load required files
	 */
	public function load() {
		$path = plugin_dir_path( __FILE__ );

		require_once $path . 'core/bp-limit-group-membership-action-handler.php';
		require_once $path . 'core/bp-limit-group-membership-admin.php';
	}

}

//initialize
BP_Limit_Group_Membership_Loader::get_instance();
