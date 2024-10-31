<?php
/*

    @link        https://www.revivify.social/

    Plugin Name: ReVivify Social
    Description: ReVivify Social plugin is here to help you boost your Social Network presence.
    Requires at least: 4.5
    Tested up to: 5.5
    Requires PHP: 5.6
    Author: Synex Technologies
    Author URI: https://www.synextechnologies.com/
    Version: 1.0.0
    License: GPL v2 or later
    License URI: http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

require_once('Facebook/autoload.php');
require_once('twitteroauth/autoload.php');
use Abraham\TwitterOAuth\TwitterOAuth;


class ReVivify_Social{ 
	
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'scripts' ) );

		add_action( 'admin_init', array( $this, 'setup_fields' ) );
		add_action( 'admin_init', array( $this, 'setup_account_fields' ) );				
		 
		add_action( 'admin_init', array( $this, 'setup_sections' ) );
		add_action( 'admin_menu', array( $this, 'create_plugin_settings_page' ) ); 
				
		add_action( 'wp_ajax_nopriv_sss_cron_activate', array( $this, 'cronstarter_activation' ) );
		add_action( 'wp_ajax_sss_cron_activate', array( $this, 'cronstarter_activation' ) );
		
		add_action( 'wp_ajax_nopriv_sss_cron_deactivate', array( $this, 'cronstarter_deactivate' ) );
		add_action( 'wp_ajax_sss_cron_deactivate', array( $this, 'cronstarter_deactivate' ) );

		register_activation_hook( __FILE__, array( $this, 'revivify_plugin_activation') );		
		register_deactivation_hook( __FILE__, array( $this, 'revivify_plugin_deactivation') );
		register_uninstall_hook( __FILE__,  array( __CLASS__,'revivify_plugin_uninstall') );  
		
		do_action('wp_enqueue_scripts');								
		
		add_action( 'wp_ajax_nopriv_sss_general_processing', array( $this, 'sss_general_processing' ) );
		add_action( 'wp_ajax_sss_general_processing', array( $this, 'sss_general_processing' ) );		

		add_filter( 'cron_schedules',  array( $this, 'isa_add_cron_recurrence_interval' ) );

		add_action( 'wp_synex_revivify_cronjob', array( $this, 'wp_synex_revivify_cronjob' ) );		
		
		add_action( 'rest_api_init', function () {
			register_rest_route('twitter/', 'callback', array(
				'methods' => array('GET', 'POST'),
				'callback' => 'twitter_callback'
			));
		} );
 
		function twitter_callback(){	
			//RO
			
			$settings_data = get_option( "sss_settings_data" );
			if ( array_key_exists("twKEYtmp", $settings_data) && array_key_exists("twSECRETtmp", $settings_data) ){
				$TW_KEY = $settings_data->twKEYtmp;
				$TW_SECRET = $settings_data->twSECRETtmp;								
				if (isset( $_GET["oauth_token"] ))		$oauth_token = sanitize_text_field($_GET["oauth_token"]);				
				if (isset( $_GET["oauth_verifier"] ))	$oauth_verifier = sanitize_text_field($_GET["oauth_verifier"]);				
				if (isset( $_POST["oauth_token"] ))		$oauth_token = sanitize_text_field($_POST["oauth_token"]);				
				if (isset( $_POST["oauth_verifier"] ))	$oauth_verifier = sanitize_text_field($_POST["oauth_verifier"]);				

				$connection = new TwitterOAuth($TW_KEY, $TW_SECRET);
				$request_tokens = $connection->oauth("oauth/access_token", array("oauth_consumer_key" => $TW_KEY , "oauth_token" => $oauth_token, "oauth_verifier" => $oauth_verifier));
				$oauth_access_token = $request_tokens["oauth_token"];;
				$oauth_access_token_secret = $request_tokens["oauth_token_secret"];
				$connection = new TwitterOAuth($TW_KEY, $TW_SECRET, $oauth_access_token, $oauth_access_token_secret);											
				$params = array('include_email' => 'true', 'include_entities' => 'false', 'skip_status' => 'true');
				$data = $connection->get('account/verify_credentials', $params); // get the data
				$twt_id = $data->id;
				$twt_email = $data->email;				
				$acc = json_decode('{}');
				$acc->key = $TW_KEY;
				$acc->secret = $TW_SECRET;				
				$acc->access_key = $oauth_access_token;
				$acc->access_secret = $oauth_access_token_secret;
				$acc->id = $twt_id;
				$acc->email = $twt_email;
				$acc->name = $data->name;
				$acc->status = "1";
				echo json_encode($acc);								
				unset($settings_data->twKEYtmp);
				unset($settings_data->twSECRETtmp);
				update_option('sss_settings_data', $settings_data);			
			}else
				echo "Something went wrong";			
			exit();
		}
	}
	
	//	█████ █████ █████ █████ █████ █████ █████ █████ █████ █████ █████ 
	
	public function revivify_plugin_activation() {
		
	}

	//	█████ █████ █████ █████ █████ █████ █████ █████ █████ █████ █████	
	
	public function revivify_plugin_deactivation() {		
		if( wp_next_scheduled( 'wp_synex_revivify_cronjob' ) )
			wp_clear_scheduled_hook( 'wp_synex_revivify_cronjob' );
		
		wp_unschedule_event($timestamp,"wp_synex_revivify_cronjob");
		
		$settings_data = get_option( "sss_settings_data" );
		
		delete_transient('syn_revivify_transient_upgrade');
	}	 
	 
	//	█████ █████ █████ █████ █████ █████ █████ █████ █████ █████ █████	
	
	public static function revivify_plugin_uninstall() {	
	//public static function uninstall() {	
		$settings_data = get_option( "sss_settings_data" );
		  
		//include_once(ABSPATH.'wp-admin/includes/plugin.php');
		//if(is_plugin_active( 'synex-revivify-social/wp-revivify-social.php' ))
		//	$this->revivify_plugin_deactivation();
		 
		if($settings_data && $settings_data->rod == 1)
		{
			delete_option("sss_settings_data");
			delete_option("sss_cron_marker");			
			delete_option("sss_log"); 
			delete_option("sss_shared_x_pos"); 
		}
	}	
	
	/*
		
	██████╗  ██████╗ ███████╗████████╗    ██████╗ ██████╗  ██████╗  ██████╗███████╗███████╗██╗███╗   ██╗ ██████╗ 
	██╔══██╗██╔═══██╗██╔════╝╚══██╔══╝    ██╔══██╗██╔══██╗██╔═══██╗██╔════╝██╔════╝██╔════╝██║████╗  ██║██╔════╝ 
	██████╔╝██║   ██║███████╗   ██║       ██████╔╝██████╔╝██║   ██║██║     █████╗  ███████╗██║██╔██╗ ██║██║  ███╗
	██╔═══╝ ██║   ██║╚════██║   ██║       ██╔═══╝ ██╔══██╗██║   ██║██║     ██╔══╝  ╚════██║██║██║╚██╗██║██║   ██║
	██║     ╚██████╔╝███████║   ██║       ██║     ██║  ██║╚██████╔╝╚██████╗███████╗███████║██║██║ ╚████║╚██████╔╝
	╚═╝      ╚═════╝ ╚══════╝   ╚═╝       ╚═╝     ╚═╝  ╚═╝ ╚═════╝  ╚═════╝╚══════╝╚══════╝╚═╝╚═╝  ╚═══╝ ╚═════╝     
	
	*/	
	
	//	█████ █████ █████ █████ █████ █████ █████ █████ █████ █████ █████
		
	public function LogAction($action){
		$settings_data = get_option( "sss_log" );
		if ( ! array_key_exists("action_log", $settings_data) || $settings_data->action_log == null )
			$settings_data->action_log = [];
		
		$rec = [];
		//$rec["time"] = time();
		$rec["time"] = gmdate("m/d/Y H:i", time());
		$rec["action"] = $action;
	
		if ( count($settings_data->action_log) > 10 )			
			array_shift( $settings_data->action_log );
		
		array_push($settings_data->action_log, $rec);
		update_option('sss_log', $settings_data);		
	}
  
	//	█████ █████ █████ █████ █████ █████ █████ █████ █████ █████ █████
 
	public function wp_synex_revivify_cronjob($accountID="", $pageID="", $postID=""){			
		$dt = new DateTime();		 
		$current = date("H:i", strtotime("now"));				
		$sss_cron_marker = get_option( "sss_cron_marker" );
		$settings_data = get_option( "sss_settings_data" );
		
		if ( $current == $sss_cron_marker )	// COLLISION 
			return "You can only share 1 post per minute";
		
		update_option('sss_cron_marker', $current);	
				
		$current_minute = $dt->format('H')*60 + $dt->format('i');		
		$shared = False;

		//RO
		$message = "Nothing shared";
		
		if($settings_data && $settings_data->accounts)
		foreach( $settings_data->accounts as $key => $acc ){			
			if($accountID !="" && $acc["id"] != $accountID)
				continue;
			
			foreach( $acc["pages"] as $key => $page ){				
				if ( (!isset($page["options"]) || $page["options"]->post_on_this_page!=1))	//SHOULD WE PUBLISH TO THIS PAGE
					continue;
					
				//GATHER PARAMETERS
				$hashtags = $page["options"]->post_hashtags;					
				$min_interval = $page["options"]->min_interval < 0 ? 0 : $page["options"]->min_interval;
				$min_post_age = $page["options"]->min_post_age;
				$max_post_age = $page["options"]->max_post_age;
				$share_multiple_times = $page["options"]->share_multiple_times;
				$share_order = $page["options"]->share_order;
				$weekdays = $page["options"]->weekdays;
				$weekday_times = $page["options"]->weekday_times;
				
				//GET PAGE SHARE COUNTER (CYCLES)
				$shared_counter = (int)$page["options"]->shared_counter;
				if ( $shared_counter == "" )
					$shared_counter=1;
				
				$timeToShare = False;
				$weekDay = date('N');
				$tmp = 1 << ($weekDay - 1);

				$timeMarker = "";
				$time = ceil($min_interval * 60);

				// CHECK IF WEEKDAY / TIME SET ( EXCLUDE MIN_INTERVAL)
				if( !empty($weekday_times) && !empty($weekdays) ){
					
					$time_array = explode(",", $weekday_times);					
					if(count($time_array)> 0) 
						foreach( $time_array as $time ){	
							if ($tmp & $weekdays && (($current_minute % $time)==0)){
								$timeMarker="WT_" . $time;
								$timeToShare = True;
								break;
							}else{
								 
							}
						}
				}else if ( !empty($weekdays) ){ // WEEKDAY + MIN_INTERVAL		
					if ($tmp & $weekdays && (($current_minute % $time)==0)){
						$timeMarker="WM_" . $weekdays . "_" .$time;
						$timeToShare = True;								
					}
				}else{ // MIN_INTERVAL											
					if ((($current_minute % $time)==0)){
						$timeMarker="MI_" . $time;
						$timeToShare = True;
					}
				}	
				if ( ! $timeToShare && $postID == "")	// NO INTERVAL
					continue;
				
				$settings_data->last_exec = time();
				$excluded_posts_general = is_array($settings_data->excluded_posts) ? $settings_data->excluded_posts : [];
				$excluded_posts_local = is_array($acc["excluded_posts"]) ? $acc["excluded_posts"] : [];;				
				$exclude_posts = array_merge($excluded_posts_general, $excluded_posts_local);
				
				for ($x = 0; $x < 2; $x++){
					//TWO EXECUTIONS: POSITION , POSITION+1 | IF ZERO POSTS WITH TIMES CONTINUE => CYCLE DONE => REMOVE ALL SRS META | IF POST, UPDATE SRS_PAGE meta +1					
					if ( ($shared_counter+$x ) > $share_multiple_times)	break;
					$shared_counter = (int)$shared_counter + (int)$x;
					$args = array(						
						'post_type' => array('post'),	//'post_type' => array('post','page'),
						'post_status' => 'publish',
						'post__not_in' => $exclude_posts,
						'posts_per_page' => -1,
						'orderby' => 'publish_date',
						'order' => ( $share_order == 1) ? 'DESC' : 'ASC',	
						'ignore_sticky_posts' => true,
					);				
					
					if( $postID == ""){
						
						$args['date_query'] = array(
							array(								
								'after' => '-'.$max_post_age.' days',
								'column' => 'post_date',
							),
							array(								
								'before' => '-'.$min_post_age.' days',
								'column' => 'post_date',
							)
						);
						
						$args['meta_query'] = array(
							'relation' => 'OR',
							array(
								'key' => 'SRS_SHARED_'.$page["id"],		//CHANGE to SRS_<PAGE_ID>
								'compare' => 'NOT EXISTS'	//SET POSITION TO 1
							),
							array(
								'key' => 'SRS_SHARED_'.$page["id"],
								'type' => 'numeric',
								'value' => $shared_counter , //+ $x, //$share_multiple_times , //+ $x,	//2,
								'compare' => '<'			//CUR POSITION OPTION IN PAGE
							),
						);
					}else
						$args['p'] = $postID;						
					
					$qry = new WP_Query($args);					
					if( (!isset($qry) || !$qry->have_posts()) && $postID == "")
							continue;
					break;
				}
				
				// SHARE FOUND
				if ( (!isset($qry) || !$qry->have_posts()) && $shared_counter < $share_multiple_times){		//	NO POSTS INCREMENTING SHARED COUNTER
					$shared_counter+=1;
					$page["options"]->shared_counter = $shared_counter;
				}else if (!isset($qry) || !$qry->have_posts()){			//	NO POSTS AVAILABLE
					//$this->LogAction("NO POSTS AVAILABLE");
				}else{
					foreach ($qry->posts as $p){
						$p_title = $p->post_title; 
						$post_ID = $p->ID; 
						$post_link = get_permalink($post_ID);						
						$post_shares = get_post_meta( $post_ID, "SRS_SHARED_".$page["id"], true);
						$post_shares =  $post_shares == "" ? 0 : $post_shares ;													

						if( $acc["priority"] != "own" ){
							$args = array(
								'method'      => 'POST',
								'redirection' => 0,
								'timeout'     => 30,
								'body'        => array(
									'apiKey' => $settings_data->apiKey,
									'account' => $acc["id"],
									'page_id' => $page["id"],
									'action' => "publish",
									'timeMarker' => $timeMarker,
									'message' => $p_title . " " . $hashtags,
									'link' => $post_link
								)
							);
							$response = wp_remote_post('https://www.revivify.social/account/API', $args);								
							$http_code = wp_remote_retrieve_response_code( $response );
							$message = sanitize_text_field($response);

							if ($response["response"]["code"] != 200){ 
								$shared = False;
								if ($response["response"]["code"] == 302)
									$message="Revivify Server Issue"; 
							}
							else
								$shared = True;
						}else{	//OWN
							try{
								if( $acc["type"] == "tw" ){
									$connection = new TwitterOAuth($acc["apiKey"], $acc["apiSecretKey"], $acc["apiAccessKey"], $acc["apiAccessSecretKey"] );
									
									switch($page["options"]->share_type){
										case 0: //Content/Format
											$statues = $connection->post("statuses/update", ["status" => ($p_title . " " . $post_link. " " . $hashtags)] );
										break;
									}			
								}
								
								if( $acc["type"] == "fb" ){	
									$fb = new Facebook\Facebook([
										'app_id' => $acc["apiKey"],
										'app_secret' => $acc["apiSecretKey"],
										'default_graph_version' => 'v6.0',
										// . . .
									]);

									switch($page["options"]->share_type){
										case 0: //Content/Format
											$response = $fb->post('/'.$page["id"].'/feed',
												array(
													"message" => $p_title,
													"link" => $post_link
												),
												$page["access_token"]
											);										
										break;
									}
								}
								$shared = True;
							}catch (Exception $e){
								$shared = False;
							}
						}
						
						if( $shared == True ){
							$this->LogAction( "<b>Page:</b> ". $page["name"] ." (".$acc["type"].")<br/><b>Post:</b> <a href='".$post_link."'>".$p_title."</a>");						
							if($post_shares < $share_multiple_times && $postID == ""){
								$diff = $shared_counter-$post_shares;
								if(  $diff >= 2 )
									update_post_meta( $p->ID, "SRS_SHARED_".$page["id"], $shared_counter);
								else
									update_post_meta( $p->ID, "SRS_SHARED_".$page["id"], $post_shares+1);
							}
						}
						break;
					}
					$page["options"]->shared_counter = $shared_counter;
				}
				
				
				if($postID == "")
					update_option('sss_settings_data', $settings_data);
			}
		}
		
		if ($shared)	// Direct CronFunc Call Validation
			return "Shared Successfully";		
		else
			return $message; 		
	}
	
	//	█████ █████ █████ █████ █████ █████ █████ █████ █████ █████ █████

	//RECURSIVE
	public function json_sanitize_data($data, $arr){		
		$sanitized_data = [];
		$sanitized_arr = [];

		if ($arr == true){		
			foreach($data as $val){
				$sanitized_data = [];
				foreach( $val as $key => $d ){
					if ( $key == "email"){
						if ( ! is_email($d) ) return "";
						$sanitized_data[$key] = sanitize_email( $d );						
					}
					else
						$sanitized_data[$key] = sanitize_text_field( $d );
				}
				array_push( $sanitized_arr , $sanitized_data );				
			}			
			return $sanitized_arr;
		}
		
		//return "";
		//NON-ARRAY
		foreach( $data as $key => $d ){
			if ( is_array( $d )){				
				//$sanitized_data[$key] = $this->json_sanitize_data( $d , true);	
				$value = $this->json_sanitize_data( $d , true);	
				if($value == "")
					return ""; 
				else
					$sanitized_data[$key] = $value;			
				continue;
			}

			switch($key){
				case "post_on_this_page" : case "monday" : case "tuesday": case "wednesday": case "thursday": case "friday": case "saturday": case "sunday":  case "share_order" : 
					$value = abs( sanitize_text_field($d) );
					$d= ( is_numeric( $value ) && ($value==1 || $value==0)) ? $value : 1;
				break;
				case "min_interval":
					$value = abs( sanitize_text_field($d) );
					$d= ( is_numeric( $value ) && $value > 0) ? $value : 0.01;
				break;
				case "weekday_times": 											
					$time=explode(",", sanitize_text_field($d));
					if ($d != "")
						foreach( $time as $k => $t){
							if (!preg_match("/^(?:2[0-3]|[01][0-9]):[0-5][0-9]$/", $t) )
								return "";
						}					
				break;
                case "min_post_age" : case "max_post_age" : case "share_multiple_times" :
					$value = abs( sanitize_text_field($d) );
					$d= ( is_numeric( $value ) && $value < 36500) ? $value : 36500;
				break;
				case "share_type" :	
					$d = 0;
				break;
				case "post_hashtags": case "page": case "action":
					$d=sanitize_text_field($d);
				break;
				case "email":
					$d = sanitize_email( $d );					
					if ( ! is_email($d) )
						return "";					
				break;
				default:
					$d=sanitize_text_field($d);
					//error_log("JSON Default: " . $key . ", ". $d . ", ". is_numeric($d));					
			}

			$sanitized_data[$key] = sanitize_text_field( $d );
		}
		return $sanitized_data;
	}	
	
	/*
	
	███████╗██████╗  ██████╗ ███╗   ██╗████████╗              ██████╗  █████╗  ██████╗██╗  ██╗
	██╔════╝██╔══██╗██╔═══██╗████╗  ██║╚══██╔══╝              ██╔══██╗██╔══██╗██╔════╝██║ ██╔╝
	█████╗  ██████╔╝██║   ██║██╔██╗ ██║   ██║       █████╗    ██████╔╝███████║██║     █████╔╝ 
	██╔══╝  ██╔══██╗██║   ██║██║╚██╗██║   ██║       ╚════╝    ██╔══██╗██╔══██║██║     ██╔═██╗ 
	██║     ██║  ██║╚██████╔╝██║ ╚████║   ██║                 ██████╔╝██║  ██║╚██████╗██║  ██╗
	╚═╝     ╚═╝  ╚═╝ ╚═════╝ ╚═╝  ╚═══╝   ╚═╝                 ╚═════╝ ╚═╝  ╚═╝ ╚═════╝╚═╝  ╚═╝
	
	*/
	
	public function refresh_requests($settings_data, $apiKey){
		$args = array(
			'method'      => 'POST',
			'redirection' => 0,
			'timeout'     => 30,
			'body'        => array(
				'apiKey' => $settings_data->apiKey,
				'account' => $sanitized_data["id"],
				'action' => "credit"
			)
		);
		$response = wp_remote_post('https://www.revivify.social/account/API', $args);								
		$http_code = wp_remote_retrieve_response_code( $response );		

		if ($response["response"]["code"] == 200){		
			$settings_data->requests = sanitize_text_field(json_decode($response["body"])->requests);
		}
	}
	
	public function sss_general_processing(){
		$type = sanitize_text_field($_POST["type"]);
		$settings_data = get_option( "sss_settings_data" ); //RO
		
		if ( check_ajax_referer( 'settings-action_synex-social-share', 'nonce', false ) == false ) {
			wp_send_json_error();
			return;
		}

		$data = $_POST["data"];

		if( !empty($data) )
			$sanitized_data = $this->json_sanitize_data($data, false);

		if($sanitized_data == "" && ! in_array($type, ["refresh_action_log","reset_action_log" ]) ){
			wp_send_json_error();
			return;
		}
		
		$default = json_decode('{}');
		$default->post_on_this_page = 0;
		$default->min_interval = 1;
		$default->min_post_age = 0;
		$default->max_post_age = 30;
		$default->share_multiple_times = 1;
		
		switch($type){
			case "remove_accounts" :
				$target = $sanitized_data["target"];
				$accid = $sanitized_data["accid"];
				if($settings_data && $settings_data->accounts)
					foreach( $settings_data->accounts as $key => $acc ){
						if( ! array_key_exists("id",$acc) ){
							unset($settings_data->accounts[$key]);
							continue;
						}

						if($accid !="" && $acc["id"] == $accid && $target=="second" ){
							unset($settings_data->accounts[$key]);
							break;						
						}else
							if($accid !="") 
								continue;
		
						if( ( $acc["priority"] == "second" || $acc["priority"] == "main" )  && $target=="main" ){
							$settings_data->apiKey = "";
							$settings_data->requests = "";
							unset($settings_data->accounts[$key]);											
						}
						else if ( $acc["priority"] == "own" && $acc["type"] == "fb" && $target=="ownfb")
							unset($settings_data->accounts[$key]);
						else if ( $acc["priority"] == "own" && $acc["type"] == "tw" && $target=="owntw")
							unset($settings_data->accounts[$key]);
						else if( $acc["priority"] == $target)
							unset($settings_data->accounts[$key]);
					}
				else{
					$settings_data->accounts = [];
					$settings_data->apiKey = "";
					$settings_data->requests = "";					
				}
					
				update_option('sss_settings_data', $settings_data);
				wp_send_json_success( __( 1, 'sss_settings' ) );				
			break;
			case "save_api";
				$apiKey = $sanitized_data["apiKey"];
				$settings_data->apiKey = $apiKey;
				update_option('sss_settings_data', $settings_data);
				wp_send_json_success( __( 1, 'sss_settings' ) );
			break;
			case "accounts":	//SAVE ACCOUNT DETAILS
				$id 			= $sanitized_data["id"];
				$accessToken 	= $sanitized_data["access_token"];
				$email 			= $sanitized_data["email"];
				$pages 			= $sanitized_data["pages"];				
 
				if ( $sanitized_data["priority"] == "main" ){						
					$found = False;		

					if ($settings_data && $settings_data->accounts){
						foreach( $settings_data->accounts as $key => $acc ){							
							if (  ( strcmp($acc["id"], $sanitized_data["id"] ) == 0 || strcmp($acc["email"], $sanitized_data["email"] ) == 0 ) && $acc["priority"] == "own" )
								$found = True;						
							
							if( $acc["priority"] != "own")
								unset($settings_data->accounts[$key]);
						};
					}
					else
						$settings_data->accounts = [];
				
					if (! $found){
						foreach( $sanitized_data["pages"] as $key => $page )
							$sanitized_data["pages"][$key]["options"] = $default;
					
						array_push($settings_data->accounts, $sanitized_data);
						$settings_data->apiKey = $sanitized_data["apikey"];
					}
										
					$this->refresh_requests($settings_data, $settings_data->apiKey);
					
				}else{	//IF ADDITIONAL OR OWN	
					$found=False;

					if($sanitized_data["priority"] == "own" && $sanitized_data["type"]=="fb"){
						$fb = new Facebook\Facebook([
							'app_id' => $sanitized_data["apiKey"],	//$acc["apiKey"],
							'app_secret' => $sanitized_data["apiSecretKey"], //$acc["apiSecretKey"],
							'default_graph_version' => 'v6.0',								
						]);
					}
					
					if ($settings_data && $settings_data->accounts){
						foreach( $settings_data->accounts as $key => $acc ){
							if (  ( strcmp($acc["id"], $sanitized_data["id"] ) == 0 || strcmp($acc["email"], $sanitized_data["email"] ) == 0 ) && 
									($acc["priority"] == "main" ||  $acc["priority"] == "second") ){
								$found=True;
								break;
							}
							
							if (  ( strcmp($acc["id"], $sanitized_data["id"] ) == 0 || strcmp($acc["email"], $sanitized_data["email"] ) == 0 ) && $acc["priority"] == "own" ){						
								foreach( $sanitized_data["pages"] as $key2 => $page ){
									$sanitized_data["pages"][$key2]["options"] = $default;
									
									if($sanitized_data["type"]=="fb"){
										$longLivedToken = $fb->getOAuth2Client()->getLongLivedAccessToken( $page["access_token"] );
										$fb->setDefaultAccessToken($longLivedToken);
										$response = $fb->sendRequest('GET', $page["id"], ['fields' => 'access_token'])->getDecodedBody();
										$sanitized_data["pages"][$key2]["access_token"] = $response['access_token'];								
									}
								}							
								$settings_data->accounts[$key] = $sanitized_data;
								
								$found=True;
								break;
							}
						}
					}//else
						
					
					if (!$found){		
						foreach( $sanitized_data["pages"] as $key => $page ){							
							if($sanitized_data["priority"] == "own"  && $sanitized_data["type"]=="fb"){								
								$longLivedToken = $fb->getOAuth2Client()->getLongLivedAccessToken( $page["access_token"] );
								$fb->setDefaultAccessToken($longLivedToken);
								$response = $fb->sendRequest('GET', $page["id"], ['fields' => 'access_token'])->getDecodedBody();
								$sanitized_data["pages"][$key]["access_token"] = $response['access_token'];										
							}
							
							$sanitized_data["pages"][$key]["options"] = $default;
						}
						array_push($settings_data->accounts, $sanitized_data);
					}
				}
				update_option('sss_settings_data', $settings_data);
				wp_send_json_success( __( 1, 'sss_settings' ) );
			break;
			case "share_now":
				$accountID = $sanitized_data["accountID"];
				$pageID = $sanitized_data["pageID"];
				$postID = $sanitized_data["postID"];
				$result = $this->wp_synex_revivify_cronjob($accountID,"",$postID);
				if($result == 1)
					wp_send_json_success( __( 1, 'sss_settings' ) );
				else
					wp_send_json_error($result);
			break;
			case "own_accounts_tw":			
				define('CONSUMER_KEY', $sanitized_data["key"]);
				define('CONSUMER_SECRET', $sanitized_data["secret"] );				
				define('OAUTH_CALLBACK', get_site_url() . "/wp-json/twitter/callback"); 
				
				try{
					$connection = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET);
					$request_token = $connection->oauth("oauth/request_token", array("oauth_callback" => OAUTH_CALLBACK ));
					
					if ($connection->getLastHttpCode() == 200) {				
						$settings_data->twKEYtmp = CONSUMER_KEY;
						$settings_data->twSECRETtmp = CONSUMER_SECRET;
						update_option('sss_settings_data', $settings_data);					
					}else{
						wp_send_json_error();
						return;
					}
				}catch (Exception $e){
					wp_send_json_error();
				}
				
				$oauth_token = $request_token["oauth_token"];
				wp_send_json_success( __( "https://api.twitter.com/oauth/authorize?oauth_token=".$oauth_token, 'sss_settings' ) );
			break;
			case "sss_get_posts":				
				$search = $sanitized_data["search"];
				$page = $sanitized_data["page"];
				$catID = $sanitized_data["catID"];
				$account = $sanitized_data["account"];	

				//$nonce = $sanitized_data["n"];				
				
				$settings_data = get_option( "sss_settings_data" );	
				$args = array(
					'post_type'=> 'post',
					'orderby'    => 'ID',
					's' => $search,
					'post_status' => 'publish',
					'order'    => 'DESC',
					'posts_per_page' => 20,
					'paged' => $page,
					'cat' => $catID
				);

				$posts=[];				
				$posts["n"] = wp_create_nonce( 'settings-action_synex-social-share' );
				$posts["posts"] = [];
				$posts["excluded"] = [] ;
				$posts["general_excluded"] = [] ;
				$cur_excluded=[];
				
				$pages = array();
				foreach( $settings_data->accounts as $key => $acc )
					if ( strcmp($acc["id"], $account) == 0){				
						if ( ! array_key_exists("excluded_posts", $settings_data->accounts[$key]) )
							$posts["excluded"]=[];
						else		
							$posts["excluded"]=$settings_data->accounts[$key]["excluded_posts"];
						
						foreach( $acc["pages"] as $key => $page )
							$pages[$page["id"]] = $page["name"];						
							
						break;
					}
				
				if ( array_key_exists("excluded_posts", $settings_data) )
					$posts["general_excluded"]=$settings_data->excluded_posts;

				$result = new WP_Query( $args );
				if ( $result-> have_posts() ){
					while ( $result->have_posts() ) {
						$result->the_post(); 
						$data = [];
						array_push($data, get_the_ID());
						array_push($data, get_the_title());				

						if( in_array(get_the_ID(), $posts["excluded"]) )
							array_push($data, 1);
						else
							array_push($data, 0);

						if( in_array(get_the_ID(), $posts["general_excluded"]) )
							array_push($data, 1);
						else
							array_push($data, 0);
						
						$shared_on = "";
						foreach ($pages as $key => $value){ 
							if (metadata_exists( 'post', get_the_ID(), "SRS_SHARED_" . $key))
								if ($shared_on == "")
									$shared_on = $value." (".get_post_meta( get_the_ID(), "SRS_SHARED_".$key, true).")";
								else
									$shared_on .= ", (".get_post_meta( get_the_ID(), "SRS_SHARED_".$key, true).")";						
						}
						array_push($data, $shared_on);
						array_push($posts["posts"], $data);		

						
					}
				}
				wp_reset_query();
				wp_send_json_success( __( $posts, 'sss_settings' ) );			
			break;
			case "sss_get_pages":				
				$res=[];
				foreach( $settings_data->accounts as $key => $value){			
					if ( strcmp($value["id"], $sanitized_data["id"] ) == 0 ){
						$res=$value["pages"];
						break;
					}
				}
				wp_send_json_success( __( $res, 'sss_settings' ) );
			break;
			case "general_account":			
				$selected = $sanitized_data["selected"];
				$selectedPage = $sanitized_data["page"];
				$rod = $settings_data->rod;
				foreach( $settings_data->accounts as $key => $acc ){
					if ( strcmp($acc["id"], $sanitized_data["id"]) == 0){													
						foreach( $acc["pages"] as $key => $page ){
								if ( strcmp($page["id"], $selectedPage) == 0){
									$page["remove_on_delete"]=$rod;
									wp_send_json_success( __( $page, 'sss_settings' ) );
									break;
								}
						}						
						wp_send_json_success( __( [], 'sss_settings' ) );
						return;
					}
				}
				//wp_send_json_error();
				wp_send_json_success( __( ["remove_on_delete" => $rod], 'sss_settings' ) );
			break;	
			case "reset_settings":
				foreach( $settings_data->accounts as $key => $acc ){
					if ( strcmp($acc["id"], $sanitized_data["id"]) == 0){
						if ( count($acc["pages"]) == 0 || $sanitized_data["page"]=="-"){
							wp_send_json_error();
							return;
						}
					
						foreach( $acc["pages"] as $key2 => $page ){
							if ( strcmp($page["id"], $sanitized_data["page"]) != 0)
								continue;
													
							$settings_data->accounts[$key]["pages"][$key2]["options"] = $default;
							update_option('sss_settings_data', $settings_data);
							wp_send_json_success( __( 1, 'sss_settings' ) );
							return;
						}
						break;							
					}							
				}
				wp_send_json_success( __( 1, 'sss_settings' ) );
			break;
			case "reset_counters":
				foreach( $settings_data->accounts as $key => $acc ){
					if ( strcmp($acc["id"], $sanitized_data["id"]) == 0){
						foreach( $acc["pages"] as $key2 => $page ){
							$post_ids = get_posts( array(
								'numberposts'   => -1,
								'fields'        => 'ids',
								'post_type'     => array('post', 'page'),
								'post_status'   => array('publish', 'auto-draft', 'trash', 'pending', 'draft'),
							) );							

							foreach( $post_ids as $post_id ) {
								delete_post_meta($post_id, 'SRS_SHARED_'.$page["id"]);						
							}
							$page["options"]->shared_counter=1; 
						}
						update_option('sss_settings_data', $settings_data);
					}
				}
				wp_send_json_success( __( 1, 'sss_settings' ) );
			break;
			case "general":	//SAVE GENERAL SETTINGS	
				if( !array_key_exists("id", $sanitized_data) || !array_key_exists("page", $sanitized_data) || $sanitized_data["id"] == "Select" || $sanitized_data["page"] == "-"  ){					
					$settings_data->rod = $sanitized_data["remove_on_delete"] == null ? 0 : 1;
					update_option('sss_settings_data', $settings_data);					
					wp_send_json_success( __( 1, 'sss_settings' ) );
					return;					
				}
								
				if ( count($settings_data->accounts ) == 0){
					wp_send_json_error();				
					return;
				} else{
					$found=0;
					foreach( $settings_data->accounts as $key => $acc ){
						if ( count($acc["pages"]) == 0){
							$option_data="Something went wrong...";
							wp_send_json_success( __( 1, 'sss_settings' ) );
							break;							
						}

						if ( strcmp($acc["id"], $sanitized_data["id"]) == 0){
							foreach( $acc["pages"] as $key2 => $page ){
								if ( strcmp($page["id"], $sanitized_data["page"]) != 0)
									continue;

								$selected_weekdays = 0;
								$selected_weekdays = $selected_weekdays | ($sanitized_data["monday"] == null || $sanitized_data["monday"] == "" ? 0 : 1);
								$selected_weekdays = $selected_weekdays | ($sanitized_data["tuesday"] == null || $sanitized_data["tuesday"] == "" ? 0 : 2);
								$selected_weekdays = $selected_weekdays | ($sanitized_data["wednesday"] == null || $sanitized_data["wednesday"] == "" ? 0 : 4);
								$selected_weekdays = $selected_weekdays | ($sanitized_data["thursday"] == null || $sanitized_data["thursday"] == "" ? 0 : 8);
								$selected_weekdays = $selected_weekdays | ($sanitized_data["friday"] == null || $sanitized_data["friday"] == "" ? 0 : 16);
								$selected_weekdays = $selected_weekdays | ($sanitized_data["saturday"] == null || $sanitized_data["saturday"] == "" ? 0 : 32);
								$selected_weekdays = $selected_weekdays | ($sanitized_data["sunday"] == null || $sanitized_data["sunday"] == "" ? 0 : 64);
								
								$weekday_times = $sanitized_data["weekday_times"];
								$minutes = "";
								if(!empty($weekday_times)){
									$time_array = explode(",", $weekday_times);
									
									foreach ($time_array as $value) {
										$tmp = explode(':', $value);
										if( is_numeric($tmp[0]) && is_numeric($tmp[1]) ){
											if ( $minutes == "")
												$minutes = ($tmp[0]*60) + ($tmp[1]);
											else
												$minutes = $minutes . "," . (($tmp[0]*60) + ($tmp[1]));
										}
									}
								}	
								
								$post_on_this_page = $sanitized_data["post_on_this_page"];
								$min_interval = $sanitized_data["min_interval"];
								$min_post_age = $sanitized_data["min_post_age"];
								$max_post_age = $sanitized_data["max_post_age"];
								$share_multiple_times = $sanitized_data["share_multiple_times"];
								$remove_on_delete = $sanitized_data["remove_on_delete"];
								$share_type = $sanitized_data["share_type"];
								$share_order = $sanitized_data["share_order"];														
								$post_hashtags = $sanitized_data["post_hashtags"];

								$d=json_decode('{}');							
								$d->post_on_this_page = ($post_on_this_page == null || $post_on_this_page=="" || $post_on_this_page==0) ? 0 : 1;
								$d->min_interval= sanitize_text_field($min_interval);
								$d->min_post_age= sanitize_text_field($min_post_age);
								$d->max_post_age= sanitize_text_field($max_post_age);
								$d->share_multiple_times= sanitize_text_field($share_multiple_times);
								$d->post_hashtags= sanitize_text_field($post_hashtags);
								$d->share_order= sanitize_text_field($share_order);
								$d->share_type = sanitize_text_field($share_type);
								$d->weekdays = $selected_weekdays;
								$d->weekday_times = $minutes;

								$page["options"] = $d;
								$settings_data->accounts[$key]["pages"][$key2] = $page;
								$settings_data->rod = $remove_on_delete == null ? 0 : 1;
								
								update_option('sss_settings_data', $settings_data);
								wp_send_json_success( __( 1, 'sss_settings' ) );
								return;
							}
							break;
						}
					}
				}	
			break;
			case "reset_action_log":
				$sss_log = get_option( "sss_log" );
				unset($sss_log->action_log);
				update_option('sss_log', $sss_log);
				wp_send_json_success();
			break;
			case "refresh_action_log":
				$sss_log = get_option( "sss_log" );
				if( !empty($sss_log->action_log) ){
					wp_send_json_success( __( array_reverse($sss_log->action_log), 'sss_settings' ) );
				}
				else
					wp_send_json_success( __( "", 'sss_settings' ) );
			break;			
			case "include_exclude_post":
				global $wpdb;
				$postID = $sanitized_data["postID"];
				$operation = $sanitized_data["operation"];
				$account = $sanitized_data["account"];

				if ( count($settings_data->accounts ) == 0)
					wp_send_json_success( __( "Found", 'sss_settings' ) );
				else{
					$found=0;					
					switch($operation){
						case "exclude": case "include":
							foreach( $settings_data->accounts as $key => $acc ){
								if ( strcmp($acc["id"], $account) == 0){								
									if($operation == "exclude"){
										$cur_excluded = $settings_data->accounts[$key];
										if ( ! array_key_exists("excluded_posts", $settings_data->accounts[$key]) )
											$settings_data->accounts[$key]["excluded_posts"] = [$postID];
										else{						
											if( ! in_array($postID, $settings_data->accounts[$key]["excluded_posts"] ) )
												array_push($settings_data->accounts[$key]["excluded_posts"], $postID);
											else{									
												wp_send_json_error(); 
												return; 
											} 
										}
									}else{ // INCLUDE
										if ( array_key_exists("excluded_posts", $settings_data->accounts[$key]) ){							
											foreach( $settings_data->accounts[$key]["excluded_posts"]  as $k => $post  ){
												if ($post != $postID)
													continue;
												unset($settings_data->accounts[$key]["excluded_posts"][$k]);
												break;
											}
										}
									}
									update_option('sss_settings_data', $settings_data);
									wp_send_json_success( __( 1, 'sss_settings' ) );							
									return;
								}
							}
						break;
						case "genexclude": case "geninclude":
							if ( !array_key_exists("excluded_posts", $settings_data) ){
								if($operation == "genexclude")
									$settings_data->excluded_posts = [$postID];								
							}
							else{
								if($operation == "genexclude")
									array_push($settings_data->excluded_posts, $postID);
								else{
									foreach( $settings_data->excluded_posts  as $k => $post  ){
										if ($post != $postID)
											continue;
										unset($settings_data->excluded_posts[$k]);
									}
								}
							}
							update_option('sss_settings_data', $settings_data);
							wp_send_json_success( __( $res, 'sss_settings' ) );
							return;
						break;
					}
				}		
				wp_send_json_error();
			break;		
			case "default":
				//error_log("default");
			break;			
		}	
	}

	/*
	
	█████ █████ █████ █████ █████ █████ █████ █████ █████ █████ █████	
	
	 ██████╗ ██████╗ ███╗   ██╗███████╗████████╗
	██╔════╝██╔═══██╗████╗  ██║██╔════╝╚══██╔══╝
	██║     ██║   ██║██╔██╗ ██║███████╗   ██║   
	██║     ██║   ██║██║╚██╗██║╚════██║   ██║   
	╚██████╗╚██████╔╝██║ ╚████║███████║   ██║   
	 ╚═════╝ ╚═════╝ ╚═╝  ╚═══╝╚══════╝   ╚═╝   
	
	*/	
	
	public function scripts() {		
		if (is_admin()){
			wp_enqueue_style( 'Font Awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css' );		
			wp_enqueue_style( 'syn_rev_soc_font_tag', plugin_dir_url( __FILE__ ) . 'css/tagify.css' );			
			wp_enqueue_script( 'syn_rev_soc_font_tag', plugin_dir_url( __FILE__ ) . 'js/jQuery.tagify.min.js', array( 'jquery' ), null, true );												
			wp_enqueue_style( 'syn_rev_soc_time', plugin_dir_url( __FILE__ ) . 'css/timePicker.css' );			
			wp_enqueue_script( 'syn_rev_soc_time', plugin_dir_url( __FILE__ ) . 'js/jquery-timepicker.js', array( 'jquery' ), null, true );					
		}
		
		wp_enqueue_style( 'syn_rev_soc_gen', plugin_dir_url( __FILE__ ) . 'css/style.css' );
		wp_enqueue_script( 'syn_rev_soc_gen', plugin_dir_url( __FILE__ ) . 'js/scripts.js', array( 'jquery' ), null, true );

		//TODO Check localized scripts
		wp_localize_script( 'syn_rev_soc_gen', 'settings', array(
			'ajaxurl'    => admin_url( 'admin-ajax.php' ),
			'send_label' => __( 'Send report', 'sss_settings' ),
			'error'      => __( 'Sorry, something went wrong. Please try again', 'reportabug' )
		) );	

		wp_localize_script( 'settings_general', 'settings', array(
			'ajaxurl'    => admin_url( 'admin-ajax.php' ),
			'save' => __( 'Save', 'sss_settings_general' ),
			'start' => __( 'Start', 'sss_settings_general' ),
			'error'      => __( 'Sorry, something went wrong. Please try again', 'sss_settings_general' )
		) );
	}		
	
	//	█████ █████ █████ █████ █████ █████ █████ █████ █████ █████ █████	
	
	public function isa_add_cron_recurrence_interval( $schedules ) {
		$schedules['every_minute'] = array(
				'interval'  => 60,
				'display'   => __( 'Every Minute')
		);	 
		return $schedules;
	}

	//	█████ █████ █████ █████ █████ █████ █████ █████ █████ █████ █████ 
	
	public function cronstarter_activation() {
		
		if ( check_ajax_referer( 'settings-action_synex-social-share', 'nonce', false ) == false ) {
			wp_send_json_error();
			return;
		}
		
		if(!wp_next_scheduled("wp_synex_revivify_cronjob")) {
			wp_schedule_event(time(),'every_minute', 'wp_synex_revivify_cronjob');
		}		
				
		$settings_data = get_option( "sss_settings_data" );
		wp_send_json_success( __( 'Cron started', 'sss_settings' ) );
	}

	//	█████ █████ █████ █████ █████ █████ █████ █████ █████ █████ █████	
	
	public function cronstarter_deactivate() {
		
		if ( check_ajax_referer( 'settings-action_synex-social-share', 'nonce', false ) == false ) {
			wp_send_json_error();
			return;
		}		
		
		if( wp_next_scheduled( 'wp_synex_revivify_cronjob' ) )
			wp_clear_scheduled_hook( 'wp_synex_revivify_cronjob' );
		
		wp_unschedule_event($timestamp,"wp_synex_revivify_cronjob");
		
		$settings_data = get_option( "sss_settings_data" );
		wp_send_json_success( __( 'Cron stopped', 'sss_settings' ) );
	}	
	
	/*
	
	███████╗██████╗  ██████╗ ███╗   ██╗████████╗    ██████╗  █████╗  ██████╗ ███████╗
	██╔════╝██╔══██╗██╔═══██╗████╗  ██║╚══██╔══╝    ██╔══██╗██╔══██╗██╔════╝ ██╔════╝
	█████╗  ██████╔╝██║   ██║██╔██╗ ██║   ██║       ██████╔╝███████║██║  ███╗█████╗  
	██╔══╝  ██╔══██╗██║   ██║██║╚██╗██║   ██║       ██╔═══╝ ██╔══██║██║   ██║██╔══╝  
	██║     ██║  ██║╚██████╔╝██║ ╚████║   ██║       ██║     ██║  ██║╚██████╔╝███████╗
	╚═╝     ╚═╝  ╚═╝ ╚═════╝ ╚═╝  ╚═══╝   ╚═╝       ╚═╝     ╚═╝  ╚═╝ ╚═════╝ ╚══════╝
	
	*/		
	
	public function create_plugin_settings_page() {
		$page_title = 'ReVivify Social';
		$menu_title = 'ReVivify Social';
		$capability = 'manage_options';
		$slug = 'sss_fields';
		$callback = array( $this, 'sss_dashboard_content' );
		$icon = 'dashicons-admin-plugins';
		$icon = plugins_url( 'synex-revivify-social/images/re-icon.png' );
		$position = 100;
		add_menu_page(__( $page_title, 'my-textdomain' ), __( $menu_title, 'my-textdomain' ), $capability, 'theme-options', $callback, $icon);
	}

	//	█████ █████ █████ █████ █████ █████ █████ █████ █████ █████ █████	OK, SAFE
	
	public function setup_account_fields() {
		$fields = array(
			array(
				'uid' => 'account_field1',
				'label' => 'API Key',
				'section' => 'account_section_synex',				
				'type' => 'text',
				'options' => false,
				'placeholder' => '',
				'helper' => 'Register on our website or add account here',
				'supplemental' => 'Either by explicitly',
				'default' => ''
			));
		foreach( $fields as $field ){
			add_settings_field( $field['uid'], $field['label'], array( $this, 'field_callback' ), 'sss_accounts_section', $field['section'], $field );
			register_setting( 'sss_accounts_section', $field['uid'] );
		}	
	}
	
	//	█████ █████ █████ █████ █████ █████ █████ █████ █████ █████ █████	
	
	public function setup_fields() {
		$args = array(
				'orderby'   => 'name',
				'order'     => 'ASC',
				'hide_empty'    => '0',
		  );

		$categories = get_categories($args);
		$options_markup="";
		$options_markup .= sprintf( '<option value="%s">%s</option>', "ALL", "ALL" );				
		
		$t=[];
		foreach( $categories as $category ) { 
			$catID = $category->term_id;
			$catNAME = $category->name;
			$options_markup .= sprintf( '<option value="%s" >%s</option>', esc_html($category->term_id), esc_html($category->name) );				
			$t[$category->term_id] = $category->name;
		}
			
		$s=[ 0 => "Title + Link + Hashtags", 1 => "General Post Template", 2 => "Featured Image", 3 => "Post Link", 4 => "General Post Template + Featured Image", 5 => "General Post Template + Post Link"];

		$fields = array(
			array(
				'uid' => 'min_interval',
				'label' => 'Share Frequency',
				'section' => 'srs_general_section',
				'type' => 'number',
				'step' => 0.01,
				'options' => false,
				'placeholder' => '[hour]',
				'helper' => 'How frequent do you want posts to be shared. Max frequency 1 post/minute (0.01).',
				'supplemental' => 'Default: 1 post/hour (per page)',
				'default' => '1',
				'enabled' => True
			),
			array(
				'uid' => 'min_post_age',
				'label' => 'Mininum Post Age',
				'section' => 'srs_general_section',
				'type' => 'number',
				'options' => false,
				'placeholder' => '[days]',
				'helper' => 'Minimum age to consider (0 = today)',
				'supplemental' => 'Default: 0 days',
				'default' => '0',
				'enabled' => True
			),
			array(
				'uid' => 'max_post_age',
				'label' => 'Maximum Post Age',
				'section' => 'srs_general_section',
				'type' => 'number',
				'options' => false,
				'placeholder' => '[days]',
				'helper' => 'Maximum age to consider (today - 30)',
				'supplemental' => 'Default: 30 days',
				'default' => '30',
				'enabled' => True
			),
			array(
				'uid' => 'exclude_ct',
				'label' => '<span class="rv-text-pale">Exclude Category/Tags [PRO]</span>',
				'section' => 'srs_general_section',
				'type' => 'text',
				'options' => false,
				'placeholder' => '',
				'helper' => '',
				'supplemental' => 'Default: None (Empty)',
				'default' => '',
				'enabled' => false
			),
			array(
				'uid' => 'share_multiple_times',
				'label' => 'Share Multiple Times',
				'section' => 'srs_general_section',
				'type' => 'number',
				'options' => false,
				'placeholder' => '',
				'helper' => 'Share X times',
				'supplemental' => 'Default: 1 round',
				'default' => '1',
				'enabled' => True
			),
			array(
				'uid' => 'share_order',
				'label' => 'Order',
				'section' => 'srs_general_section',
				'type' => 'checkbox',
				'options' => false,
				'placeholder' => '',
				'helper' => 'Select the order (Oldest / Newest first)',
				'supplemental' => 'Default: Unchecked = Oldest first',
				'default' => '1',
				'enabled' => True
			),			
			array(
				'uid' => 'share_type',
				'label' => 'Share Type',
				'section' => 'srs_general_section',
				'type' => 'select',
				'options' => $s,
				'placeholder' => '',
				'helper' => ' Select post share template',
				'supplemental' => '* Default enabled, additional options available in PRO',
				'default' => '0',
				'enabled' => true
			),			
			array(
				'uid' => 'utm_google_analytics',
				'label' => '<span class="rv-text-pale">Google Analytics (UTM) [PRO]</span>',
				'section' => 'srs_general_section',
				'type' => 'textarea',
				'options' => false,
				'placeholder' => '',
				'helper' => '',
				'supplemental' => 'UTM will be appended to the url/link automatically. E.g. <br/>utm_source=facebook&utm_medium=cpc&utm_campaign={custom_field=asd}&utm_term=info&utm_content=your-numbers <br/>Note: Leave empty to disable, * Magic Tags Enabled',
				'default' => '',
				'enabled' => false
			),		
			array(
				'uid' => 'general_post_format',
				'label' => '<span class="rv-text-pale">General Post Template [PRO]</span>',
				'section' => 'srs_general_section',
				'type' => 'textarea',
				'options' => false,
				'placeholder' => '',
				'helper' => '',
				'supplemental' => '* Magic Tags Enabled',
				'default' => '',
				'enabled' => false
			),
			array(
				'uid' => 'post_hashtags',
				'label' => 'Hashtags',
				'section' => 'srs_general_section',
				'type' => 'textarea',
				'options' => false,
				'placeholder' => '',
				'helper' => '',
				'supplemental' => '',
				'default' => '',
				'enabled' => true
			),		
			array(
				'uid' => 'category_format_selection',
				'label' => '<span class="rv-text-pale">Category Based Template [PRO]</span>',
				'section' => 'srs_general_section',
				'type' => 'select',
				'options' => $t,
				'placeholder' => '',
				'helper' => ' Don\'t forget to save template for each category (Save General)',
				'supplemental' => '',
				'default' => 'maybe',
				'enabled' => false
			),
			array(
				'uid' => 'category_format',
				'label' => '',
				'section' => 'srs_general_section',
				'type' => 'textarea',
				'options' => false,
				'placeholder' => '',
				'helper' => '',
				'supplemental' => '* Magic Tags Enabled',
				'default' => '',
				'enabled' => false
			),
			array(
				'uid' => 'category_option',
				'label' => '<span class="rv-text-pale">Category vs Post Template Relation [PRO]</span>',
				'section' => 'srs_general_section',
				'type' => 'checkbox',
				'options' => false,
				'placeholder' => '',
				'helper' => 'Replace Post Template with Category Template',
				'supplemental' => 'Default: Unchecked = Append category template to post template',
				'default' => '1',
				'enabled' => false
			),
			array(
				'uid' => 'random_category',
				'label' => '<span class="rv-text-pale">Multi-category handling [PRO]</span>',
				'section' => 'srs_general_section',
				'type' => 'checkbox',
				'options' => false,
				'placeholder' => '',
				'helper' => ' Use only 1 random category template',
				'supplemental' => 'Default: Unchecked = Use everything from every category template that post belongs to',
				'default' => '1',
				'enabled' => false
			),
		);
		
		register_setting( 'sss_fields', "sss_settings_data" );
		foreach( $fields as $field )
			add_settings_field( $field['uid'], $field['label'], array( $this, 'field_callback' ), 'sss_fields', $field['section'], $field );		
	}

	//	█████ █████ █████ █████ █████ █████ █████ █████ █████ █████ █████	
	
	public function field_callback( $arguments ) {
		$nonce = wp_create_nonce( 'settings-action_synex-social-share' ) ;
		$options = get_option("sss_settings_data");	
		$value = get_option( $arguments['uid'] ); 
		if( ! $value )
			$value = $arguments['default'];		

		switch( $arguments['type'] ){
			case 'text': case 'number':		
				printf( '<input class="rv-input" name="%1$s" id="%1$s" type="%2$s" '.( array_key_exists("step", $arguments) ? "step=".$arguments["step"] : "").' data-nonce="'.$nonce.'" placeholder="%3$s" value="%4$s" '.($arguments["enabled"]==false ? "disabled" : "").' />', $arguments['uid'], $arguments['type'], $arguments['placeholder'], esc_html($value ));
				break;
			case 'button':
				printf( '<button name="%1$s" id="%1$s" data-nonce="'.$nonce.'" type="%2$s"> %3$s </button>', $arguments['uid'], $arguments['type'],  $arguments['placeholder'] );
				break;
			case 'textarea':
				printf( '<textarea class="rv-textearea" name="%1$s" id="%1$s" placeholder="%2$s" rows="5" cols="50" '.($arguments["enabled"]==false ? "disabled" : "").'>%3$s</textarea>', $arguments['uid'], $arguments['placeholder'], esc_html($value) );
				break;
			case 'radio':
				printf ( '<input name="%1$s"  id="%2$s" type="%3$s"  value="%4$s" '.checked( esc_html($value), 0, false ).' /> ', $arguments['name'], $arguments['uid'], $arguments['type'], esc_html($value));
				break;
			case 'select':
				if( ! empty ( $arguments['options'] ) && is_array( $arguments['options'] ) ){
					$options_markup = "";
					foreach( $arguments['options'] as $key => $label ){						
						$options_markup .= sprintf( '<option value="%s" %s '.($key == 0 ? "" : "disabled").'>%s</option>', $key, selected( esc_html($value), $key, false ), $label );					
					}
					printf( '<select  class="rv-input" name="%1$s" data-nonce="'.$nonce.'" id="%1$s" '.($arguments["enabled"]==false ? "disabled" : "").'>%2$s</select>', $arguments['uid'], $options_markup );
				}
				break;
			case 'checkbox':
				printf ( '<input name="%1$s"  id="%1$s" type="%2$s"  value="%3$s" '. ($arguments["enabled"]==false ? "disabled" : checked( esc_html($value), 0, false )).' /> ', $arguments['uid'], $arguments['type'],  esc_html($value));
				break;
		}

		if( $helper = $arguments['helper'] )
			printf( '<span class="helper '.($arguments["enabled"]==false ? "disableElement" : "").'"> %s</span>', $helper ); // Show it		

		if( $supplimental = $arguments['supplemental'] )
			printf( '<p class="description '.($arguments["enabled"]==false ? "disableElement" : "").'">%s</p>', $supplimental ); // Show it		
	}		
	
	//	█████ █████ █████ █████ █████ █████ █████ █████ █████ █████ █████	
	
	public function section_callback( $arguments ) {
		printf ( '<p>Post on this page: <input name="post_on_this_page"  id="post_on_this_page" type="checkbox"  value="1" '.checked( esc_html($settings_data->post_on_this_page), 1, false ).' /> </p>');
		echo '<p> Custom schedule: </p>';
		echo '<div class="weekdays-selector rv-mb-3">';
		echo '<input name="monday" id="monday"  data-value="1" class="weekdays" type="checkbox"  value="1"  />'.
			 '<label for="monday">MON</label>'.
			 '<input name="tuesday" id="tuesday"  data-value="2" class="weekdays" type="checkbox"  value="1"  />'.
			 '<label for="tuesday">TUE</label>'.
			 '<input name="wednesday" id="wednesday" data-value="4" class="weekdays" type="checkbox"  value="1"  />'.
			 '<label for="wednesday">WED</label>'.
			 '<input name="thursday" id="thursday" data-value="8" class="weekdays" type="checkbox"  value="1"  />'.
			 '<label for="thursday">THU</label>'.
			 '<input name="friday" id="friday" data-value="16" class="weekdays" type="checkbox"  value="1"  />'.
			 '<label for="friday">FRI</label>'.
			 '<input name="saturday" id="saturday" data-value="32" class="weekdays" type="checkbox"  value="1"  />'.
			 '<label for="saturday">SAT</label>'.
			 '<input name="sunday" id="sunday" data-value="64" class="weekdays" type="checkbox"  value="1"  />'.
			 '<label for="sunday">SUN</label></div>';

		echo '<input class="rv-input rv-mb-3" type="text" id="time-picker" readonly> <button class="rv-btn rv-btn-primary" id="addTime" >Add</button>';
		echo '<input class="rv-s-input" name="weekday_times" id="weekday_times" placeholder="e.g. 09:05 [00:00-23:59]" type="text" value="" />';
		echo '<b>Note</b>: Specific time circumvents "Share Frequency" parameter';
	}
	
	public function setup_sections() {
		add_settings_section( 'srs_general_section', '<h5><i class="fa fa-file-o rv-text-pale" aria-hidden="true"></i> Page settings</h5>', array( $this, 'section_callback' ), 'sss_fields' );
	}	

	//	█████ █████ █████ █████ █████ █████ █████ █████ █████ █████ █████	
	

	public function SchdulerRunning(){
		$running = False;
		$cron_jobs = get_option( 'cron' );
		forEach($cron_jobs as $key => $job)
			if(is_array ($cron_jobs[$key]["wp_synex_revivify_cronjob"]) ){
				$running = True;		
				break;
			}
		return $running;
	}

	public function sss_dashboard_content(){
			$nonce = wp_create_nonce( 'settings-action_synex-social-share' ) ;
			$ss_btn = $this->SchdulerRunning();
		?>


		<div class="rv-wrap rv-mt-2">
			<div class="rv-container">
				<div class="rv-row">
					<div class="rv-col-12 rv-col-md-9 rvs_main">	

						<div id="rvs_toggle_div">
							<div id="rvs_spinner_center">
								<i class="fa fa-circle-o-notch fa-spin fa-3x" ></i>
							</div>
							<div id="popup" class="rv-modal-box">  
							  <header>
								<a href="#" class="rv-js-modal-close rv-close">×</a>
								<h5><i class="fa fa-info-circle rv-text-pale" aria-hidden="true"></i> ReVivify Info</h5>
							  </header>
							  <div class="modal-body">
								<p id="notificationBody"></p>
							  </div>
							  <footer>
								<button class="rv-js-modal-close rv-btn rv-btn-primary rv-mr-2"> Close </button>
							  </footer>
							</div>								
						</div>
		

		
						<div class="rv-card rv-mb-2">						
							<div class="rv-card-header rv-bg-white">
								<img src="<?php echo esc_url(plugin_dir_url( __FILE__ ) . 'images/revivify_logo_synex_social_share.png'); ?>" height="60px"alt="Revivify - Synex Social Share"/>
							</div>
							<div class="rv-card-body">
								<div class="rv-row rv-m-0">
									<div class="rv-col-9">
										<div class="tab">
											<button class="rv-tab-btn tablinks" onclick="OpenOption(event, 'Accounts')">Accounts</button>
											<button class="rv-btn tablinks" onclick="OpenOption(event, 'General')" disabled >General</button>
											<button class="rv-btn tablinks" onclick="OpenOption(event, 'Posts')" disabled>Posts</button>
											<button class="rv-btn tablinks" onclick="OpenOption(event, 'ActionLogs')">Action Logs</button>
										</div>
									</div>
									<div class="rv-col-3 rv-text-right">
										<?php										
											if ($ss_btn == false)
												echo '<button class="rv-btn rv-btn-general" data-nonce="' . $nonce . '" data-type="start" id="scheduler" ><i class="fa fa-play" aria-hidden="true"></i> Start</button>';
											else
												echo '<button class="rv-btn rv-btn-accent" data-nonce="' . $nonce . '" data-type="stop" id="scheduler" ><i class="fa fa-pause" aria-hidden="true"></i> Stop</button>';
											
										?>
									</div>
								</div>
							</div>	
						</div> <!-- end of card -->
						<!-- █████ █████ █████ █████ █████ █████ █████ █████ █████ █████ █████ -->
						<div id="Accounts" class="tabcontent">
							<div class="rv-card rv-mb-2">
								<div class="rv-card-body rv-text-center">
									<h5 class="rv-mb-4"><i class="fa fa-user-circle rv-text-pale" aria-hidden="true"></i> Main account</h5>
									<div id="main_acc">									
									<?php										
										$main_acc = False;
										$settings_data = get_option( "sss_settings_data" );

										if( is_array($settings_data->accounts) && count($settings_data->accounts) > 0)
											foreach( $settings_data->accounts as $acc){
												if ( (strcmp($acc["priority"], "main") == 0)){
													$this->refresh_requests($settings_data, $settings_data->apiKey);
													$main_acc = True;												
													echo "Currently connected: <i class='fa fa-".($acc["type"]=="fb" ? "facebook" : "twitter")." rv-text-pale' aria-hidden='true'></i> <b>".sanitize_email($acc["email"])."</b>";
													break;
												}
											}											
										if( !empty($settings_data->apiKey) )
											$main_acc = True;			
										
									echo "</div>";									
									if($main_acc == false){
										echo '<button class="add_acc_fb rv-btn rv-btn-facebook rv-mt-4 rv-mr-2" data-nonce="' . $nonce . '" data-addacc="setfb" data-url="'.esc_url("https://www.revivify.social/account/login/WPfacebook?apikey=".$settings_data->apiKey).'" value="'.esc_url("https://www.revivify.social/account/login/WPfacebook?apikey=".$settings_data->apiKey).'" >' . 
										__( '<i class="fa fa-facebook" aria-hidden="true"></i> Connect Facebook Account', 'sss_settings' ) . '</button>'; 

										echo '<button class="add_acc_tw rv-btn rv-btn-twitter rv-mt-4 rv-mr-2" data-nonce="' . $nonce. '" data-addacc="settw" data-url="'.esc_url("https://www.revivify.social/account/login/WPtwitter?apikey=".$settings_data->apiKey).'" value="'.esc_url("https://www.revivify.social/account/login/WPtwitter?apikey=".$settings_data->apiKey).'" >' . 
										__( '<i class="fa fa-twitter" aria-hidden="true"></i> Connect Twitter Account', 'sss_settings' ) . '</button>';
									}
									
									echo '<div class="rv-m-4"><h5 class="rv-mb-3"><i class="fa fa-link rv-text-pale" aria-hidden="true"></i> Connect With API Key</h5><input class="rv-input-wide rv-mr-2" name="apiKey" id="apiKey" type="text" placeholder="ADD YOUR API KEY" value="'.esc_html($settings_data->apiKey).'"/>';
									if($main_acc == false)
										echo '<button class="save_api rv-btn rv-btn-primary" data-nonce="'.$nonce.'"> Save </button>'; 
									echo "</div><div class='rv-row rv-justify-center'><div class='rv-col-md-7 rv-col-12'><div class='rv-important'><i class='fa fa-info-circle' aria-hidden='true'></i> <b>Note: Changing the main account will remove any 'Additional accounts' you have connected.</b></div></div></div>";
									
									$fbKey=""; $fbSecretKey=""; $twKey=""; $twSecretKey="";
									if($settings_data && $settings_data->accounts)
										foreach( $settings_data->accounts as $acc){										
											if ( strcmp($acc["priority"], "own") == 0){
												if( $acc["type"] == "fb"){
													$fbKey = $acc["apiKey"];
													$fbSecretKey = $acc["apiSecretKey"];
												}else if( $acc["type"] == "tw"){
													$twKey = $acc["apiKey"];
													$twSecretKey = $acc["apiSecretKey"];
												}
											}
										}								

									//TODO: Find a way
									if($main_acc == true)
									{
									?>
										<h5 class="rv-mb-4"><i class="fa fa-plus-circle rv-text-pale" aria-hidden="true"></i> Additional accounts</h5>
										<div id="additional_acc" class="rv-mb-3">
										<?php
											if ($settings_data && $settings_data->accounts)
											foreach( $settings_data->accounts as $acc){
												if ( strcmp($acc["priority"], "second") == 0){
													echo "<div class='rv-row'><div class='rv-col-md-3 rv-col-12'></div>";
													echo "<div class='rv-col-md-1 rv-col-12 rv-text-left'>Connected: </div>";
													echo "<div class='rv-col-md-4 rv-col-8 rv-text-left '><b>".sanitize_email($acc["email"])."</b></div>";												
													echo '<div class="rv-col-md-4 rv-col-4 rv-text-left"><button class="rv-ml-2 rv-mb-2 clear_additional rv-btn rv-btn-primary" data-target="second" data-accid="'.esc_html($acc["id"]).'" data-nonce="' . $nonce . '" " > Remove </button></div>';
													echo "</div>";
												}
											}
										echo "</div>";
										echo '<button class="add_acc_fb rv-btn rv-btn-facebook rv-mr-2 rv-mb-2" data-nonce="' . $nonce . '" data-addacc="addfb" data-url="'.esc_url("https://www.revivify.social/account/login/WPfacebook/authorize?apikey=".$settings_data->apiKey).'" value="'.esc_url("https://www.revivify.social/account/login/WPfacebook/authorize?apikey=".$settings_data->apiKey).'" >' . __( '<i class="fa fa-facebook" aria-hidden="true"></i> Add Facebook Account', 'sss_settings' ) . '</button>';
										echo '<button class="add_acc_tw rv-btn rv-btn-twitter  rv-mr-2 rv-mb-2" data-nonce="' . $nonce . '" data-addacc="addtw" data-url="https://www.revivify.social/account/login/WPtwitter/authorize?apikey='.$settings_data->apiKey.'" value="'.esc_url("https://www.revivify.social/account/login/WPtwitter/authorize?apikey=".$settings_data->apiKey).'" >' . __( '<i class="fa fa-twitter" aria-hidden="true"></i> Connect Twitter Account', 'sss_settings' ) . '</button>';
									}
									?> 								
								</div> <!-- end of card body -->
								
								<?php echo '<div class="rv-card-footer rv-bg-white rv-text-right">'.
									'<div class="rv-row">'.
										'<div class="rv-col-12 rv-col-md-6 rv-pl-4 rv-pt-1 rv-text-left">'.
											'Remaining requests: <b>'.esc_html__($settings_data->requests == "" ? "-" : $settings_data->requests, "revivify-social").'</b>'.
										'</div>'.
										'<div class="rv-col-12 rv-col-md-6 rv-pr-4">'.
											'<button class="clear_main rv-btn rv-btn-primary" data-target="main" data-nonce="' . $nonce . '" " >' . 	__( 'Remove All Accounts', 'sss_settings' ) . '</button>'.
										'</div>'.
									'</div>'.
								'</div>'; ?>
								
							</div> <!-- end of card -->
							<div class="rv-card rv-mb-2">
								<div class="rv-card-body">									
									<h5>Use Your Own Keys</h5>
									<p> Use your own keys you acquired via Facebook App/API</p>
									<div class="rv-row rv-m-0">
										<div class="rv-col-12 rv-col-md-6 rv-pr-4">
											<div class="rv-card rv-mb-2">
												<div class="rv-card-header">
													<h5><i class="fa fa-facebook-square rv-facebook" aria-hidden="true"></i> FaceBook </h5>
												</div>									
												<div class="rv-card-body">									
												<?php echo '<div class="rv-row rv-p-2"><div class="rv-col-md-6 rv-col-12"><label for="apiKeyFB"> API Key: </label><br> <input class="rv-input-full rv-mb-2" name="apiKeyFB" id="apiKeyFB" type="text" placeholder="ADD YOUR API KEY" value="'.esc_html($fbKey).'"/></div>'; echo '<div class="rv-col-md-6 rv-col-12"><label for="apiSecretKeyFB"> API Secret Key: </label><br> <input class="rv-input-full rv-mb-2" name="apiSecretKeyFB" id="apiSecretKeyFB" type="text" placeholder="ADD YOUR SECRET KEY" value="'.esc_html($fbSecretKey).'"/></div></div>'; ?>
												</div><!-- end of card body -->
												<div class="rv-card-footer rv-bg-white rv-text-right">
												<?php echo '<button class="saveFBKeys rv-btn rv-btn-primary rv-mr-2" data-target="saveFBKeys" data-nonce="'.$nonce.'"> Save </button>'; echo '<button class="clear_fb rv-btn rv-btn-primary rv-mr-2" data-target="ownfb" data-nonce="' . $nonce . '" " >' . __( 'Remove', 'sss_settings' ) . '</button>';?>
												</div><!-- end of card footer -->
											</div><!-- end of card -->
										</div> <!-- end of col -->									
										<div class="rv-col-12 rv-col-md-6 rv-pr-4">									
											<div class="rv-card rv-mb-2">
												<div class="rv-card-header">
													<h5><i class="fa fa-twitter-square rv-twitter" aria-hidden="true"></i> Twitter</h5>
												</div>									
												<div class="rv-card-body">
													<?php echo '<div class="rv-row rv-p-2"><div class="rv-col-md-6 rv-col-12"><label for="apiKeyTW"> API Key: </label><br> <input class="rv-input-full rv-mb-2" name="apiKeyTW" id="apiKeyTW" type="text" placeholder="ADD YOUR API KEY" value="'.esc_html($twKey).'"/></div>';
													echo '<div class="rv-col-md-6 rv-col-12"><label for="apiSecretKeyTW"> API Secret Key: </label><br> <input class="rv-input-full rv-mb-2" name="apiSecretKeyTW" id="apiSecretKeyTW" type="text" placeholder="ADD YOUR SECRET KEY" value="'.esc_html($twSecretKey).'"/></div></div>';?>
												</div><!-- end of card body -->									
												<div class="rv-card-footer rv-bg-white rv-text-right">
												<?php
													echo '<button class="saveTWKeys rv-btn rv-btn-primary rv-mr-2"  data-target="saveTWKeys" data-nonce="'.$nonce.'"> Save </button>'; 
													echo '<button class="clear_tw rv-btn rv-btn-primary rv-mr-2" data-target="owntw" data-nonce="' . $nonce . '" " >' . 	__( 'Remove', 'sss_settings' ) . '</button>';
												?>
												</div><!-- end of card footer -->
											</div><!-- end of card -->
										</div> <!-- end of col -->									
									</div> <!-- end of rv-row-->									
								</div> <!-- end of card body -->								
								<?php
								echo "<div class='rv-row rv-justify-center rv-text-center'><div class='rv-col-md-7 rv-col-12'><div class='rv-important'><i class='fa fa-info-circle' aria-hidden='true'></i> <b>Note: You can't connect same account via your own keys and ReVivify at the same time.</b></div></div></div>";
								?>
							</div> <!-- end of card -->
						</div> <!--	█████ █████ █████ █████ █████ █████ █████ █████ █████ █████ █████ -->
						<div id="General" class="tabcontent" style="display: none">						
							<div class="rv-card rv-mb-2">
								<div class="rv-card-body">						
									<h5><i class="fa fa-cog rv-text-pale" aria-hidden="true"></i> General Settings</h5>				
									<form id="general_settings" >
										<p> Server time: <?php echo gmdate("m/d/Y H:i"); //date('m/d/Y H:i', time()); ?> </p>
										<p> General settings, select an account to adjust it. Settings are on per page basis.</p>
										<div class="rv-row rv-justify-start rv-pl-3"><div class='rv-col-md-7 rv-col-12'><div class='rv-important'><i class='fa fa-info-circle' aria-hidden='true'></i> <b>Note: Don't forget to enable/disable posting on each of the accounts via "Post on this page" option. </b></div></div></div>
										<?php										
											$options_markup="";
											//$options_markup .= sprintf( '<option value="%s" %s>%s</option>', "Select", true, "Select" );	
											if( is_array($settings_data->accounts) && count($settings_data->accounts) > 0)
											foreach( $settings_data->accounts as $acc )
												$options_markup .= sprintf( '<option value="%s" %s>%s</option>', esc_html($acc["id"]), selected( "test", esc_html($acc["email"]), false ), esc_html($acc["email"]). " (". esc_html($acc["type"]).")" );				
											printf( '<select class="rv-input" name="id" id="select_account" data-nonce="' . $nonce . '">%1$s</select>', $options_markup );					
											printf( '<select class="rv-input" name="page" id="select_page" data-nonce="' . $nonce . '" style="display: none";>%1$s</select>', "" );	
											settings_fields( 'sss_fields' );
											printf ( '<p class="rv-mt-3 " >Remove all data/parameters on plugin delete: <input name="remove_on_delete"  id="remove_on_delete" type="checkbox"  value="%1$s" '.checked( $settings_data->rod, 1, false ).' /> </p>', esc_html($settings_data->rod));		
											printf ( '<p class="rv-mt-3 disableElement">Enable WP pinger [PRO]: <input name="enable_pinger"  id="enable_pinger" type="checkbox"  value="0" disabled /> </p>');											

											echo '<div class="rv-mt-4" id="general_section" style="display:none">';
												do_settings_sections( 'sss_fields' );
											echo '</div>';											
										?>
									</form>							
								</div> <!-- end of card body -->							
								<?php
									echo '<div class="rv-card-footer rv-bg-white"><button class="save_general_settings rv-btn rv-btn-primary rv-mr-2" data-nonce="' . $nonce . '" data-post_id="' .  wp_create_nonce( 'synex-social-share' ) . '">' . __( 'Save general', 'sss_settings' ) . '</button>';
									echo '<button class="reset_general_settings rv-btn rv-btn-primary rv-mr-2" data-type="reset_settings" data-nonce="' . $nonce . '" data-post_id="' .  wp_create_nonce( 'synex-social-share' ) . '" style="display:none">' . __( 'Reset settings', 'sss_settings' ) . '</button></div>';
								?>							
							</div>
						</div> <!-- █████ █████ █████ █████ █████ █████ █████ █████ █████ █████ █████ -->
						<div id="Posts" class="tabcontent" style="display: none">
							<div class="rv-card rv-mb-2">
								<div class="rv-card-body">
									<form id="posts_settings" >
										<h5><i class="fa fa-files-o rv-text-pale" aria-hidden="true"></i> Select account/page</h5>
											<?php
													$nonce = wp_create_nonce( 'settings-action_synex-social-share' ) ;						
													//$options_markup = sprintf( '<option value="%s" %s>%s</option>', "Select", true, "Select" );				
													
													if( is_array($settings_data->accounts) && count($settings_data->accounts) > 0)
													//foreach( $settings_data->accounts as $acc )
														//$options_markup .= sprintf( '<option value="%s" %s>%s</option>', $acc["id"], selected( "test", $acc["email"], false ), $acc["email"]. " (".$acc["type"].")" );				
													printf( '<select class="rv-input" name="id" id="select_account_posts" data-nonce="' . $nonce . '">%1$s</select>', $options_markup );					
													
											?>
										<div id="posts_content" style="display:none">
											<h5 class="rv-mt-4">List of published posts</h5>
											<input class="rv-input" name="post_search" id="post_search" type="text" placeholder="Type something.."  />
											<?php
												$args = array(
													'orderby'   => 'name',
													'order'     => 'ASC',
													'hide_empty'    => '0',
												);
												$categories = get_categories($args);
												$options_markup = sprintf( '<option value="%s">%s</option>', "ALL", "ALL" );				
												
												foreach( $categories as $category ) { 
													$catID = $category->term_id;
													$catNAME = $category->name;
													$options_markup .= sprintf( '<option value="%s" >%s</option>', esc_html($category->term_id), esc_html($category->name) );				
												}
												printf( '<select class="rv-input" name="post_category" id="post_category" data-nonce="' . $nonce . '">%1$s</select>', $options_markup );
											?>
											<button class="rv-btn rv-btn-primary"  data-nonce="<?php echo $nonce ;?>" id="btn_search_posts" > Search </button>
											<br/>
											<table class="rv-table-sm rv-mt-3" id="post_list">
												<thead>
													<tr>
														<th>Title</th>
														<th class="rv-pr-3">Action</th>
													</tr>
												</thead>
												<tbody></tbody>
											</table>
										</div>
									</form>
									<?php 
										echo '<button class="load_more_posts rv-btn rv-btn-primary rv-mr-2" data-nonce="' . $nonce . '" data-post_id="' .  wp_create_nonce( 'synex-social-share' ) . '" disabled>' . __( 'Load more', 'sss_settings' ) . '</button>'; 
										echo '<button class="reset_general_settings rv-btn rv-btn-primary" data-type="reset_counters" data-nonce="' . $nonce . '" data-post_id="' .  wp_create_nonce( 'synex-social-share' ) . '">' . __( 'Reset All Counters', 'sss_settings' ) . '</button>';	
									?>									
								</div><!--end of card body-->
								<div class="rv-card-footer rv-bg-white rv-text-pale rv-text-right">
									<p>Here you can include or exlude posts both locally and globally. You can also explicitly share individual posts regardless of round and counters (Page settings).</p>								
								</div>
							</div><!--end of card-->
						</div>
						 <!-- █████ █████ █████ █████ █████ █████ █████ █████ █████ █████ █████ -->
						<div id="ActionLogs" class="tabcontent" style="display: none">
							<div class="rv-card rv-mb-2">
								<div class="rv-card-body">
									<h5 class="rv-mb-3"><i class="fa fa-file-text-o rv-text-pale" aria-hidden="true"></i> Action Logs</h5>
									<?php echo "<p>Server time: " . esc_html(gmdate("m/d/Y H:i")) . "<br/>Last ReVivify execution : " . ( esc_html($settings_data->last_exec) ? gmdate('m/d/Y H:i', esc_html($settings_data->last_exec)) : "-")."</p>"; ?>
								</div>
								<div class="rv-card-footer rv-bg-white">
									<?php echo '<button id="'.esc_html($catID).'" class="reset_action_log rv-btn rv-btn-primary" data-nonce="' . $nonce . '">' . __( 'Reset log', 'sss_settings' ) . '</button> <button id="refresh_action_log" class="rv-btn rv-btn-primary" data-nonce="' . $nonce . '">' . __( 'Refresh', 'sss_settings' ) . '</button>';?>
								</div>
							</div>
							<div class="rv-card rv-mb-2">
								<div class="rv-card-body">
									 <table class="rv-table-sm" id="action_log">
										<thead>
											<tr>
											  <th>Time</th>
											  <th>Action</th>
											</tr>
										</thead>
										<tbody>
											<?php
												$settings_data = get_option( "sss_log" );
												if( !empty($settings_data->action_log) )
												foreach( array_reverse($settings_data->action_log) as $action )
													echo "<tr><td>".esc_html($action["time"])."</td><td>".$action["action"]."</td><tr>";							
											?>
										</tbody>
									</table> 
								</div>
							</div>							
						</div>  															
					</div> <!-- end of rv-col -->
					<div class=" rv-col-12 rv-col-md-3 rv-pl-2 rv-pr-2">
						<div class="rv-pl-3 rv-pr-3 rv-pt-0">
							<a href="https://www.revivify.social/" target="_blank"><img src="<?php echo esc_url(plugin_dir_url( __FILE__ ) . 'images/revivify-pro-banner.jpg');?>" width="100%" alt="Revivify - Upgrade to PRO"></a>
						</div>					  
						<div class="rv-p-3">
							<a href="https://www.revivify.social/documentation/" target="_blank" class="rv-btn rv-btn-link rv-mb-2">Documentation</a>
						</div>	
					</div>
				</div> <!-- end of rv-row -->
			</div> <!-- end of rv-container -->
		</div>	<!--end of rv-wrap -->			
		<?php
	}
}

new ReVivify_Social();?>
