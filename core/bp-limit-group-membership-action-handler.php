<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit( 0 );
}

/**
 * Special tanks to Matteo for reporting the issue /helping with code suggestion for the case when user opens the join link directly
 */
class BP_Limit_Group_Membership_Action_Handler {

	/**
	 * Singleton Instance.
	 *
	 * @var BP_Limit_Group_Membership_Action_Handler
	 */
	private static $instance;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->setup();
		//add_filter('bp_core_admin_screen',array($this,'limit_group_join_admin_screen'));//show super admin option to set the maximum no.
	}

	/**
	 * Get singleton instance
	 *
	 * @return BP_Limit_Group_Membership_Action_Handler
	 */
	public static function get_instance() {

		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Setup
	 */
	private function setup() {


		// remove the group join button.
		add_filter( 'bp_get_group_join_button', array( $this, 'fix_join_button' ), 100 );
		// check if we can allow autojoin.
		add_filter( 'bp_groups_auto_join', array( $this, 'can_join' ) );

		// remove the bp hooks.
		add_action( 'bp_init', array( $this, 'remove_hooks' ), 2 );
		// check if group can be created.
		add_action( 'bp_screens', array( $this, 'check_group_create' ), 2 );
		// for normal bp action(when a user opens the join link), thanks to Matteo.
		add_action( 'bp_actions', array( $this, 'action_join_group' ) );
		add_action( 'bp_screens', array( $this, 'action_request_membership' ), 2 );

		// check ajaxed join/leave group.
		add_action( 'wp_ajax_joinleave_group', array( $this, 'ajax_joinleave_group' ), 0 );

		// we filter the invite list using javascript, in 1.6, we won't need it.
		add_action( 'wp_footer', array(
			$this,
			'ouput_js',
		), 200 );
	}

	/**
	 * Filter and hide Group join button
	 *
	 * @param array $btn button config.
	 *
	 * @return string
	 */
	public function fix_join_button( $btn ) {

		if ( self::can_join() ) {
			return $btn;
		}
		// otherwise check if the button is for requesting membership.
		if ( $btn['id'] == 'request_membership' || $btn['id'] == 'join_group' ) {
			$btn = '';
		}

		return $btn;
	}

	/**
	 * Remove action hooks
	 */
	public function remove_hooks() {
		remove_action( 'bp_actions', 'groups_action_join_group' );
	}

	/**
	 * Handle group join action.
	 */
	public function action_join_group() {

		if ( ! bp_is_single_item() || ! bp_is_groups_component() || ! bp_is_current_action( 'join' ) ) {
			return;
		}

		// Nonce check.
		if ( ! check_admin_referer( 'groups_join_group' ) ) {
			return;
		}

		$bp = buddypress();
		// already a member, let BuddyPress handle this case.
		if ( groups_is_user_member( $bp->loggedin_user->id, $bp->groups->current_group->id ) ) {
			return;
		}

		if ( ! self::can_join() ) {
			bp_core_add_message( apply_filters( 'restrict_group_membership_message', __( 'You already have the maximum no. of groups allowed. You can not create or join new groups!' ) ), 'error' );
			bp_core_redirect( bp_get_group_permalink( $bp->groups->current_group ) );
		} else {
			// default Bp handler.
			groups_action_join_group();
		}

	}

	/**
	 * Handle membership request.
	 */
	public function action_request_membership() {
		if ( ! bp_is_group() || ! bp_is_current_action( 'request-membership' ) ) {
			return;
		}

		if ( ! self::can_join() ) {
			bp_core_add_message( apply_filters( 'restrict_group_membership_message', __( 'You already have the maximum no. of groups allowed. You can not request for a new group membership!' ) ), 'error' );
			bp_core_redirect( bp_get_group_permalink( groups_get_current_group() ) );
		}
	}

	/**
	 * Acts as a wall to the default request handler. allows only if the limit is not reached.
	 *
	 * @return bool|void
	 */
	public function ajax_joinleave_group() {

		$bp = buddypress();

		if ( ! $group = new BP_Groups_Group( $_POST['gid'], false, false ) ) {
			return false;
		}

		if ( groups_is_user_member( $bp->loggedin_user->id, $group->id ) ) {
			// let BuddyPress handle it.
			return;
		}
		// notify if not allowed to join.
		if ( ! self::can_join() ) {
			echo apply_filters( 'restrict_group_membership_message', __( 'You already have the maximum no. of groups allowed. You can not create or join new groups!', 'bp-limit-group-membership-per-user' ) );
			exit( 0 );
		}
		// in all other cases, users are allowed to join and we do not need to worry.
	}

	/**
	 * Js to disable inviting users with exhusted limit.
	 */
	public function ouput_js() {
		// load only on group create and group invite pages.
		if ( ! bp_is_group_creation_step( 'group-invites' ) && ! bp_is_group_invites() ) {
			return;
		}

		// fields to restrict.
		$users = self::get_friends_not_to_invite();
		?>

        <script type='text/javascript'>
            var group_member_restriction_list = <?php echo json_encode( $users ) . ";";?>
            var count = group_member_restriction_list.length;
            for (var i = 0; i < count; i++) {
                jQuery("input#f-" + group_member_restriction_list[i]).prop('disabled', true);
            }
        </script>
		<?php
	}

	/**
	 * Check if we should allow creating group or not
	 */
	public function check_group_create() {

		// do not cause headache.
		if ( ! bp_is_active( 'groups' ) ) {
			return;
		}

		self::restrict_group_create();
	}

	/**
	 * Restrict group creation.
	 *
	 * @param int $user_id user id.
	 */
	public function restrict_group_create( $user_id = null ) {

		// no restriction to site admin.
		if ( ! bp_is_group_create() || is_super_admin() ) {
			return;
		}
		// if we are here,It is group creation step.
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		// even in cae of zero, it will return true.
		if ( ! empty( $_COOKIE['bp_new_group_id'] ) ) {
			return;
		}
		// this is intermediate step of group creation.
		if ( ! self::can_join() ) {
			bp_core_add_message( apply_filters( 'restrict_group_membership_message', __( 'You already have the maximum no. of groups allowed. You can not create or join new groups!' ) ), 'error' );
			remove_action( 'wp', 'groups_action_create_group', 3 );
			bp_core_redirect( bp_get_root_domain() . '/' . bp_get_groups_slug() );
		}

	}

	/**
	 * Get the limit set by the admin.
	 *
	 * @return int
	 */
	public static function get_limit() {
		return apply_filters( 'bp_limit_group_membership_limit', bp_get_option( 'group_membership_limit', 0 ) );
	}

	/**
	 * Get groups count for a user.
	 *
	 * @param int $user_id user id.
	 *
	 * @return int
	 */
	public static function get_group_count( $user_id ) {
		global $wpdb;
		$bp = buddypress();

		return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT m.group_id) FROM {$bp->groups->table_name_members} m WHERE m.user_id = %d AND m.is_confirmed = 1 AND m.is_banned = 0", $user_id ) );
	}

	/**
	 * Can current User join a new group?
	 *
	 * @return bool true if allowed else false
	 */
	public static function can_join() {
		return apply_filters( 'bp_limit_group_membership_allow_group_join', self::is_allowed_to_join() );
	}

	/**
     * Is user allowed to join new groups ?
     *
	 * @return bool
	 */
	private static function is_allowed_to_join() {
		if ( is_super_admin() ) {
			return true;
		}

		$limit = self::get_limit();
		// if user is not logged in or the limit is set to zero, there is no possibility of joining.
		if ( ! is_user_logged_in() || ! $limit ) {
			return false;
		}

		$user_id = bp_loggedin_user_id();
		// check how many groups the user has already joined.
		$group_count = self::get_group_count( $user_id );
		//get_user_meta( $user_id, 'total_group_count', true );

		if ( $group_count < $limit ) {
			return true;
		}

		return false;
    }

	/**
	 * Get the list of friends who should not be invited.
	 *
	 * @return array
	 */
	public function get_friends_not_to_invite() {
		global $wpdb;

		$bp = buddypress();

		$user_id = get_current_user_id();
		$limit   = self::get_limit();
		// get all friends who can not be invited.
		$user_ids = friends_get_friend_user_ids( $user_id );

		if ( empty( $user_ids ) ) {
			return array();
		}
		$user_list = '(' . join( ',', $user_ids ) . ')';

		// find all the users who have not exhusted the membership count.
		$query = "SELECT user_id, COUNT(group_id) AS gcount FROM {$bp->groups->table_name_members} WHERE user_id IN {$user_list} ORDER BY user_id";

		$query = $wpdb->prepare( $query, $limit );
		$selected = array();
		$results = $wpdb->get_results( $query );
		foreach ( (array) $results as $row ) {
			if ( $row->gcount > $limit ) {
				$selected[] = $row->user_id;
			}
		}

		return $selected;
	}
}

BP_Limit_Group_Membership_Action_Handler::get_instance();
