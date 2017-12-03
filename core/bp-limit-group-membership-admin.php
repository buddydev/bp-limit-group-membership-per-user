<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit( 0 );
}

/**
 * Admin Helper.
 */
class BP_Limit_Group_Membership_Admin_Helper {
	/**
	 * Singleton instance.
	 *
	 * @var BP_Limit_Group_Membership_Admin_Helper
	 */
	private static $instance;

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'bp_admin_init', array( $this, 'register_settings' ), 20 );
	}

	/**
	 * Get singleton instance.
	 *
	 * @return BP_Limit_Group_Membership_Admin_Helper
	 */
	public static function get_instance() {

		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		// Add the settings section.
		add_settings_section( 'bp_limit_group_membership',
			__( 'Limit Group membership Settings', 'bp-limit-group-membership' ),
			array( $this, 'reg_section' ),
			'buddypress'
		);

		// Allow loading form via jax or not?
		add_settings_field( 'group_membership_limit',
			__( 'How many Groups a user can join?', 'bp-limit-group-membership' ),
			array( $this, 'settings_field' ),
			'buddypress',
			'bp_limit_group_membership'
		);

		register_setting( 'buddypress', 'group_membership_limit', 'intval' );
	}

	/**
	 * Section details.
	 */
	public function reg_section() {

	}

	/**
	 * Render settings.
	 */
	public function settings_field() {
		$val = bp_get_option( 'group_membership_limit', 0 ); ?>

        <label>
            <input type="text" name="group_membership_limit" id="group_membership_limit" value="<?php echo $val; ?>"/>
        </label><br/>

	<?php }

}

BP_Limit_Group_Membership_Admin_Helper::get_instance();
