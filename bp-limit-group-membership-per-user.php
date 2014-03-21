<?php
/**
 * Plugin Name:BP Limit Group Membership
 * Plugin URI:http://buddydev.com/plugins/bp-limit-group-membership/
 * Author: Brajesh Singh
 * Author URI: http://buddydev.com/members/sbrajesh
 * Version : 1.0.4
 * License: GPL
 * Description: Restricts the no. of Groups a user can join
 */
/**
 * Special tanks to Matteo for reporting the issue /helping with code suggestion for the case when user opens the join link directly
 */
class BPLimitGroupMembership{
    
    private static $instance;
    
    private function __construct() {
        
        add_action( 'wp_footer', array( $this, 'ouput_js' ), 200 );//we filter the invite list using javascript, in 1.6, we won't need it
        add_action( 'bp_get_group_join_button', array( $this, 'fix_join_button' ), 100 );//remove the group join button
        add_filter( 'bp_groups_auto_join', array( $this, 'can_join' ) );///check if we can allow autojoin
       
        add_action( 'wp', array( $this, 'check_group_create' ), 2 );//check if group can be created
        add_action( 'init', array( $this, 'remove_hook' ), 2 );//remove the bp hooks
       //ajaxed join/leave
        add_action( 'wp_ajax_joinleave_group', array( $this, 'ajax_joinleave_group' ) );
        //for normal bp action(when a user opens the join link), thanks to Matteo
        add_action( 'bp_actions', array( $this, 'action_join_group' ) );
        
        //add_filter('bp_core_admin_screen',array($this,'limit_group_join_admin_screen'));//show super admin option to set the maximum no.
    }
    
    public static function get_instance(){
        if( !isset ( self::$instance ) )
            self::$instance = new self();
        
        return self::$instance;
    }
    
    public function get_limit(){
        return apply_filters( 'bp_limit_group_membership_limit', bp_get_option( 'group_membership_limit', 0 ) );
    }
    public function get_group_count( $user_id ){
        global $bp, $wpdb;
		
	return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT m.group_id) FROM {$bp->groups->table_name_members} m WHERE m.user_id = %d AND m.is_confirmed = 1 AND m.is_banned = 0", $user_id ) );
		
    }
    public static function can_join(){
       
        $limit = self::get_limit();
        if( is_super_admin() )
            return true;
        //if user is not logged in or the limit is set to zero, return false
        if( !( is_user_logged_in() && $limit ) )
            return false;
        
        $user_id = bp_loggedin_user_id();
        //check how many groups the user has already joined
        $group_count = self::get_group_count( $user_id );//get_user_meta( $user_id, 'total_group_count', true );
      
        if( $group_count < $limit )
            return true;
        
        return false;
    }
    
   //prevent listing of members for invitation
    
    public function filter_invite_list(){
        
    }
    
    public function  get_friends_not_to_invite(){
        global $wpdb, $bp;
        $user_id = get_current_user_id();
        $limit = self::get_limit();
        //get all friends who can not be invited
        $user_ids = friends_get_friend_user_ids( $user_id );
        
        if( empty( $user_ids ) )
            return array();
        
        $user_list = '(' . join( ',', $user_ids ) . ')';
        ///find all the users who have not exhusted the mebership count
        
        
        
       $query = "SELECT user_id, COUNT(group_id) AS gcount FROM {$bp->groups->table_name_members} WHERE user_id IN {$user_list} ORDER BY user_id";
      
       $query = $wpdb->prepare( $query, $limit );
       $selected = array();
       $results = $wpdb->get_results( $query );
       foreach( (array) $results as $row ){
           
           if( $row->gcount > $limit )
               $selected[] = $row->user_id;
       }
       return $selected;
       
    }
    //do not allow joining by posting to activity/forum topic
    //this applies to logged in user only
    
    
    
    //hide the join button
    public function  fix_join_button( $btn ){
        
        if( self::can_join() )
            return $btn;
        
        //otherwise check if the button is for requesting membership
        if( $btn['id'] == 'request_membership' || $btn['id'] == 'join_group' )
            $btn = '';
        
        return $btn;
    }
    
    //ajax group join/leave
    /* AJAX join or leave a group when clicking the "join/leave" button
     * A copy of bp_dtheme_ajax_joinleave_group function modified for our purpose
     *  */
    public function ajax_joinleave_group() {
	global $bp;

	if ( groups_is_user_banned( $bp->loggedin_user->id, $_POST['gid'] ) )
		return false;

	if ( !$group = new BP_Groups_Group( $_POST['gid'], false, false ) )
		return false;

	if ( !groups_is_user_member( $bp->loggedin_user->id, $group->id ) ) {
            if( !self::can_join() ){
                echo apply_filters('restrict_group_membership_message',__("You already have the maximum no. of groups allowed. You can not create or join new groups!"));
		return;
            }   
		if ( 'public' == $group->status ) {

			check_ajax_referer( 'groups_join_group' );

			if ( !groups_join_group( $group->id ) ) {
				_e( 'Error joining group', 'buddypress' );
			} else {
				echo '<a id="group-' . esc_attr( $group->id ) . '" class="leave-group" rel="leave" title="' . __( 'Leave Group', 'buddypress' ) . '" href="' . wp_nonce_url( bp_get_group_permalink( $group ) . 'leave-group', 'groups_leave_group' ) . '">' . __( 'Leave Group', 'buddypress' ) . '</a>';
			}

		} else if ( 'private' == $group->status ) {

			check_ajax_referer( 'groups_request_membership' );

			if ( !groups_send_membership_request( $bp->loggedin_user->id, $group->id ) ) {
				_e( 'Error requesting membership', 'buddypress' );
			} else {
				echo '<a id="group-' . esc_attr( $group->id ) . '" class="membership-requested" rel="membership-requested" title="' . __( 'Membership Requested', 'buddypress' ) . '" href="' . bp_get_group_permalink( $group ) . '">' . __( 'Membership Requested', 'buddypress' ) . '</a>';
			}
		}

	} else {

		check_ajax_referer( 'groups_leave_group' );

		if ( !groups_leave_group( $group->id ) ) {
			_e( 'Error leaving group', 'buddypress' );
		} else {
			if ( 'public' == $group->status ) {
				echo '<a id="group-' . esc_attr( $group->id ) . '" class="join-group" rel="join" title="' . __( 'Join Group', 'buddypress' ) . '" href="' . wp_nonce_url( bp_get_group_permalink( $group ) . 'join', 'groups_join_group' ) . '">' . __( 'Join Group', 'buddypress' ) . '</a>';
			} else if ( 'private' == $group->status ) {
				echo '<a id="group-' . esc_attr( $group->id ) . '" class="request-membership" rel="join" title="' . __( 'Request Membership', 'buddypress' ) . '" href="' . wp_nonce_url( bp_get_group_permalink( $group ) . 'request-membership', 'groups_send_membership_request' ) . '">' . __( 'Request Membership', 'buddypress' ) . '</a>';
			}
		}
	}
    }
    
   public function action_join_group(){
       global $bp;

	if ( !bp_is_single_item() || !bp_is_groups_component() || !bp_is_current_action( 'join' ) )
		return false;

	// Nonce check
	if ( !check_admin_referer( 'groups_join_group' ) )
		return false;

	// Skip if banned or already a member
	if ( !groups_is_user_member( $bp->loggedin_user->id, $bp->groups->current_group->id ) && !groups_is_user_banned( $bp->loggedin_user->id, $bp->groups->current_group->id ) &&self::can_join()) {

		// User wants to join a group that is not public
		if ( $bp->groups->current_group->status != 'public' ) {
			if ( !groups_check_user_has_invite( $bp->loggedin_user->id, $bp->groups->current_group->id ) ) {
				bp_core_add_message( __( 'There was an error joining the group.', 'buddypress' ), 'error' );
				bp_core_redirect( bp_get_group_permalink( $bp->groups->current_group ) );
			}
		}

		// User wants to join any group
		if ( !groups_join_group( $bp->groups->current_group->id ) )
			bp_core_add_message( __( 'There was an error joining the group.', 'buddypress' ), 'error' );
		else
			bp_core_add_message( __( 'You joined the group!', 'buddypress' ) );

		bp_core_redirect( bp_get_group_permalink( $bp->groups->current_group ) );
	}
        else if(!self::can_join()){
            //feedback
            bp_core_add_message(apply_filters('restrict_group_membership_message',__("You already have the maximum no. of groups allowed. You can not create or join new groups!")),'error');
            
        }

	bp_core_load_template( apply_filters( 'groups_template_group_home', 'groups/single/home' ) );
   } 
   
   
    //remove ajax handler
    //currently works for bp 1.5 bp-default theme
    public function remove_hook(){
        if( has_action( 'wp_ajax_joinleave_group', 'bp_dtheme_ajax_joinleave_group' ) )
            remove_action( 'wp_ajax_joinleave_group', 'bp_dtheme_ajax_joinleave_group' );
        
        if( has_action( 'bp_actions', 'groups_action_join_group' ) )
            remove_action( 'bp_actions', 'groups_action_join_group' );
        
    }
    
    
    //do not allow inviting the members who have exhusted their limit
        function ouput_js(){
            //load only on group create and group invite pages

         if( !( bp_is_group_creation_step( 'group-invites' ) || bp_is_group_invites() ) )
             return;
         ///fields to restrict
         $users = self::get_friends_not_to_invite();
    ?>

    <script type='text/javascript'>
        var group_member_restriction_list = <?php echo json_encode($users).";";?>
        var count = group_member_restriction_list.length;
        for( var i=0; i<count; i++ ){
            jQuery( "input#f-"+ group_member_restriction_list[i] ).prop( 'disabled', true );
        }

    </script>

    <?php
    } 
    
 


    public function restrict_group_create( $user_id=null ){
            global $bp;

        //no restriction to site admin
        if (!bp_is_group_create() ||is_super_admin())
                    return false;
        //if we are here,It is group creation step

        if( !$user_id )
            $user_id = get_current_user_id();
        //even in cae of zero, it will return true
        if( !empty( $_COOKIE['bp_new_group_id'] ) )
            return;//this is intermediate step of group creation

        if( !self::can_join() ){

                    bp_core_add_message(apply_filters('restrict_group_membership_message',__("You already have the maximum no. of groups allowed. You can not create or join new groups!")),'error');
                    remove_action( 'wp', 'groups_action_create_group', 3 );
                    bp_core_redirect(bp_get_root_domain().'/'.  bp_get_groups_slug());
        }


    }
    /**
     * Check if we should allow creating group or not
     * @global type $bp
     * @return type 
     */
    public function check_group_create(){
            global $bp;
            if( !function_exists( 'bp_is_active' ) || !bp_is_active( 'groups' ) )
                    return; //do not cause headache

            self::restrict_group_create();
    }


}

BPLimitGroupMembership::get_instance();

class BPLimitGroupMembershipAdminHelper{
    private static $instance;
    
    private function __construct() {
        add_action( 'bp_admin_init', array( $this, 'register_settings' ), 20 );
    }
    
    public static function get_instance(){
        if( !isset ( self::$instance ) )
                self::$instance = new self();
        return self::$instance;
    }
    
    public function register_settings(){
        // Add the ajax Registration settings section
            add_settings_section( 'bp_limit_group_membership',        __( 'Limit Group membership Settings',  'bp-limit-group-membership' ), array($this,'reg_section'),   'buddypress'              );
            // Allow loading form via jax or nt?
            add_settings_field( 'group_membership_limit', __( 'How many Groups a user can join?',   'bp-limit-group-membership' ), array($this,'settings_field'),   'buddypress', 'bp_limit_group_membership' );
            register_setting  ( 'buddypress',         'group_membership_limit',   'intval' );
    }
    
    function reg_section(){
        
    }
    
    function settings_field(){
            $val = bp_get_option('group_membership_limit',0);?>

         
                   
                    <label>
                        <input type="text" name="group_membership_limit" id="group_membership_limit" value="<?php echo $val;?>" /></label><br>
                    
   <?php }
    
}
BPLimitGroupMembershipAdminHelper::get_instance();
