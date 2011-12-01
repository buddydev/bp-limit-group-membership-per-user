<?php
/**
 * Plugin Name:BP Limit Group Membership
 * Plugin URI:http://buddydev.com/plugins/bp-limit-group-membership/
 * Author: Brajesh Singh
 * Author URI: http://buddydev.com/members/sbrajesh
 * Version : 1.0
 * License: GPL
 * Description: Restricts the no. of Groups a user can join
 */

class BPLimitGroupMembership{
    
    private static $instance;
    
    private function __construct() {
        
        add_action('wp_footer',array($this,'ouput_js'),200);
        add_action('bp_get_group_join_button',array($this,'fix_join_button'),100);
        add_filter('bp_groups_auto_join',array($this,'can_join'));
        add_filter('bp_core_admin_screen',array($this,'limit_group_join_admin_screen'));
        add_action('wp',array($this,'check_group_create'),2);
    }
    
    function get_instance(){
        if(!isset (self::$instance))
            self::$instance=new self();
        
        return self::$instance;
    }
    
    function get_limit(){
        return bp_get_option('group_membership_limit',0);
    }
    function get_group_count($user_id){
        global $bp, $wpdb;
		
	return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT m.group_id) FROM {$bp->groups->table_name_members} m WHERE m.user_id = %d AND m.is_confirmed = 1 AND m.is_banned = 0", $user_id ) );
		
    }
    function can_join(){
       
        $limit=self::get_limit();
        if(is_super_admin())
            return true;
        //if user is not logged in or the limit is set to zero, return false
        if(!(is_user_logged_in()&&$limit))
            return false;
        
        $user_id=bp_loggedin_user_id();
        //check how many groups the user has already joined
        $group_count=self::get_group_count($user_id);//get_user_meta( $user_id, 'total_group_count', true );
      
        if($group_count<$limit)
            return true;
        
        return false;
    }
    
   //prevent listing of members for invitation
    
    function filter_invite_list(){
        
    }
    
    function  get_friends_not_to_invite(){
        global $wpdb,$bp;
        $user_id=bp_loggedin_user_id();
        $limit=self::get_limit();
        //get all friends who can not be invited
        $user_ids=friends_get_friend_user_ids($user_id);
        
        if(empty($user_ids))
            return array();
        
        $user_list='('.join(',',$user_ids).')';
        ///find all the users who have not exhusted the mebership count
        
        
        
       $query="SELECT user_id, count(group_id) as gcount FROM {$bp->groups->table_name_members} WHERE user_id IN {$user_list} order by user_id";
      
       $query=$wpdb->prepare($query,$limit);
       $selected=array();
       $results=$wpdb->get_results($query);
       foreach((array)$results as $row){
           if($row->gcount>$limit)
               $selected[]=$row->user_id;
       }
       return $selected;
       
    }
    //do not allow joining by posting to activity/forum topic
    //this applies to logged in user only
    
    
    
    //hide the join button
    function  fix_join_button($btn){
        
        if(self::can_join())
            return $btn;
        //otherwise check if the button is for requesting membership
        if($btn['id']=='request_membership'||$btn['id']=='join_group')
        $btn='';
        return $btn;
    }
    //do not allow inviting the members who have exhusted their limit
    function ouput_js(){
        //load only on group create and group invite pages
     
     if(!(bp_is_group_creation_step( 'group-invites' )||bp_is_group_invites()) )
         return;
     ///fields to restrict
     $users=self::get_friends_not_to_invite();
?>

<script type='text/javascript'>
    var group_member_restriction_list=<?php echo json_encode($users).";";?>
    var count=group_member_restriction_list.length;
    for(var i=0;i<count;i++){
        jQuery("input#f-"+group_member_restriction_list[i]).prop('disabled',true);
    }
    
</script>
     
<?php
    } 
    
  /**
 * Show the option on BuddyPress settings page to Limit the group
 */
function limit_group_join_admin_screen(){
?>
<table class="form-table">
<tbody>
<tr>
	<th scope="row"><?php _e( 'Limit Groups Membership Per User' ) ?></th>
		<td>
                    <p><?php _e( 'How many Groups a user can join?') ?></p>
                    <label><input type="text" name="bp-admin[group_membership_limit]" id="group_membership_limit" value="<?php echo bp_get_option( 'group_membership_limit',0 );?>" /></label><br>
                </td>
	</tr>
</tbody>
</table>				
<?php
}  


function restrict_group_create($user_id=null){
	global $bp;

    //no restriction to site admin
    if (!bp_is_group_create() ||is_super_admin())
		return false;
    //if we are here,It is group creation step

    if(!$user_id)
	$user_id=$bp->loggedin_user->id;
    //even in cae of zero, it will return true
    if(!empty($_COOKIE['bp_new_group_id']))
        return;//this is intermediate step of group creation
    
    if(!self::can_join()){

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
function check_group_create(){
	global $bp;
	if(!function_exists('bp_is_active')||!bp_is_active('groups'))
		return; //do not cause headache
	
	self::restrict_group_create();
}


}

BPLimitGroupMembership::get_instance();

?>