<?php 
class Client_Status_Client {
	var $options;
	
	function Client_Status_Client(){
		add_action('init', array(&$this, 'init'));
		add_action('save_post', array(&$this, 'save_post'));
	}
	
	function get($post_id){
	    return get_post($post_id);
	}
	
	function get_multi($args = array()){
		$defaults = array(
			'post_type' => 'client_status_client', 
			'post_status' => 'publish',
			'orderby' => 'title', 
			'order' => 'ASC', 
			'posts_per_page' => -1,
			'numberposts' => -1
		);
		$r = wp_parse_args($args, $defaults);
		
		return get_posts($r);
	}
	
	function init(){
		register_post_type(
			'client_status_client',
			array(
				'capability_type' => 'post', 
				'exclude_from_search' => false,
				'hierarchical' => false,
				'labels' => array(
					'name' => __('Clients'),
					'singular_name' => __('Client'),
					'add_new' => __('Add New'),
					'add_new_item' => __('Add New Client'),
					'edit' => __('Edit'),
					'edit_item' => __('Edit Client'),
					'new_item' => __('New Client'),
					'view' => __('View'),
					'view_item' => __('View Client'),
					'search_items' => __('Search Clients'),
					'not_found' => __('No clients found'),
					'not_found_in_trash' => __('No clients found in Trash'),
				),
				'menu_icon' => CLIENT_STATUS_PLUGIN_URL . '/images/status_online.png',
				'public' => false,
				'publicly_queryable' => true,
				'query_var' => true,
				'register_meta_box_cb' => array(&$this, 'metabox_register'),
				'rewrite' => array('slug' => 'client', 'with_front' => false),
				'show_in_nav_menus' => false,
				'show_ui' => true,
				'supports' => array('title','custom-fields'),
			) 
		);
	}
	
	function metabox_info(){
		global $post, $client_status;
		$custom = get_post_custom($post->ID);
		
		// Code to run xml-rpc on client server
		$params = array($client_status->decrypt($custom['_client_username'][0]), $client_status->decrypt($custom['_client_password'][0]));
		$params = xmlrpc_encode_request('client_status_update', $params);
		$request = new WP_Http;
		$result = $request->request($custom['_client_url'][0] . 'xmlrpc.php',
	        array('method' => 'POST', 'body' => $params));
	        
        echo "result=";
        print_r($result);
        
        // End code
	    
	?>
		<div id="client_meta_info">
			<label for="client_url"><strong><?php _e('Site URL'); ?></strong></label><br />
			<input type="url" name="client_url" id="client_url" value="<?php echo ((!empty($custom['_client_url'])) ? $custom['_client_url'][0] : ""); ?>" size="50" /><br />
			<br />
			<label for="client_username"><strong><?php _e('Site Username'); ?></strong></label><br />
			<input type="text" name="client_username" id="client_username" value="<?php echo ((!empty($custom['_client_username'])) ? $client_status->decrypt($custom['_client_username'][0]) : ""); ?>" size="25" /><br />
			<br />
			<label for="client_password"><strong><?php _e('User Password'); ?></strong></label><br />
			<input type="password" name="client_password" id="client_password" value="<?php echo ((!empty($custom['_client_password'])) ? $client_status->decrypt($custom['_client_password'][0]) : ""); ?>" size="25" /><br />
			<br />
			<label for="client_email"><strong><?php _e('Client Email'); ?></strong></label><br />
			<em><?php _e('Allows sending status emails to client; <br />separate multiple emails with a comma')?></em><br />
			<textarea name="client_email" id="client_email" cols="40" rows="5"><?php echo ((!empty($custom['_client_email'])) ? $custom['_client_email'][0] : ""); ?></textarea>
		</div>
		
		<div id="client_meta_status">
	<?php
		if(!empty($custom['_client_url']) && $custom['_client_url'][0] != ""){
			// Try retrieving information from server
			$data = false;
			try {
			    $ret = wp_remote_post($custom['_client_url'][0] . CLIENT_STATUS_DATA_URL . "?security_key=" . md5($this->options->security_key));
			    
			    // Hide error messages from simple_xml_load_string. They can be enabled by adding &debug=1 to url
			    if(isset($_GET['debug']) && $_GET['debug'] == 1){
			        libxml_use_internal_errors(false);
			    } else {
			        libxml_use_internal_errors(true);
			    }
			    $data = simplexml_load_string($ret['body']);
			    
			    if(!isset($_GET['debug'])){
			        libxml_clear_errors();
			    }
			} catch(Exception $e){
			    // Do nothing
			}

			$this->refresh($post->ID, &$custom, &$ret, time());
			
			if(!empty($data)){
				if(!property_exists($data, 'error')){
					$plugin_update_count = count($data->updates->plugins->plugin);
					$theme_update_count = count($data->updates->themes->theme);
					echo "<p>" . (($data->is_public == 0) ? "<img class='status' src='" . CLIENT_STATUS_IMAGE_ERROR . "' />" : "<img class='status' src='" . CLIENT_STATUS_IMAGE_OK . "' />") . _('Status: ') . (($data->is_public == 0) ? "<a class='status_error' href='". $custom['_client_url'][0] . CLIENT_STATUS_WP_PRIVACY_URL ."' target='_blank' title='". _('Adjust privacy settings') ."' alt='". _('Adjust privacy settings') ."'>" . _('Not Indexable') . "</a>" : "<a class='status_ok' href='". $custom['_client_url'][0] . CLIENT_STATUS_WP_PRIVACY_URL ."' target='_blank' title='". _('Adjust privacy settings') ."' alt='". _('Adjust privacy settings') ."'>" . _('Indexable') . "</a>") . "</p>"; 
					echo "<p>" . (($data->updates->core->response == "upgrade") ? "<img class='status' src='" . CLIENT_STATUS_IMAGE_ERROR . "' />" : "<img class='status' src='" . CLIENT_STATUS_IMAGE_OK . "' />") . _('WordPress Version: ') . "<a class='". (($data->updates->core->response == "upgrade") ? "status_error" : "status_ok") ."' href='". $custom['_client_url'][0] . CLIENT_STATUS_WP_UPDATE_CORE_URL ."' target='_blank'>" . $data->version . "</a></p>"; 
					echo "<p>" . (($plugin_update_count > 0) ? "<img class='status' src='" . CLIENT_STATUS_IMAGE_PLUGIN_ERROR . "' />" : "<img class='status' src='" . CLIENT_STATUS_IMAGE_OK . "' />") . _('Plugin Updates: ') . "<a class='". (($plugin_update_count > 0) ? "status_error" : "status_ok") ."' href='". $custom['_client_url'][0] . CLIENT_STATUS_WP_UPDATE_PLUGINS_URL ."' target='_blank'>" .  $plugin_update_count . "</a></p>"; 
					echo "<p>" . (($theme_update_count > 0) ? "<img class='status' src='" . CLIENT_STATUS_IMAGE_THEME_PROBLEM . "' />" : "<img class='status' src='" . CLIENT_STATUS_IMAGE_OK . "' />") . _('Theme Updates: ') . "<a class='". (($theme_update_count > 0) ? "status_error" : "status_ok") ."' href='". $custom['_client_url'][0] . CLIENT_STATUS_WP_UPDATE_THEMES_URL ."' target='_blank'>" .  $theme_update_count . "</a></p>";
				} else {
					echo "<p><img class='status' src='". CLIENT_STATUS_IMAGE_PROBLEM ."' /><a href='". $custom['_client_url'][0] . CLIENT_STATUS_SETTINGS_URL ."' class='status_problem' target='_blank'>" . (($data->error != '') ? $data->error : _('An error has occurred')) . "</a></p>";
				}
				
    			if(property_exists($data, 'plugin_version') && $data->plugin_version > 1.1){
    				echo "<p>" . _('Server OS: ') . $data->os_version . "</p>";
    				echo "<p>" . _('PHP Version: ') . $data->php_version . "</p>";
    				echo "<p>" . _('MySQL Version: ') . $data->mysql_version . "</p>";
    			}
			}
		}
	?>
		</div>
		<div class="clear"></div>
	<?php
	}
	
	function metabox_register() {
		add_meta_box('client_status_client', __('Client Information'), array(&$this, 'metabox_info'), 'client_status_client', 'normal', 'high');
	}
	
	function save_post(){
		global $post, $client_status;
		
		if($post->post_type == 'client_status_client'){
			if(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE){
	    		return;
			}
			
			// Check for trailing slash...add if missing
			$client_url = $_POST['client_url'];
			if($client_url != ""){
				$last_char = substr($client_url, strlen($client_url) -1, 1);
				if($last_char != "/"){
					$client_url .= "/";
				}
			}
			$client_emails = $_POST['client_email'];
			if($client_emails != ""){
				$client_emails = str_replace("\r\n", ",", $client_emails);
			}
			update_post_meta($post->ID, "_client_url", $client_url);
			update_post_meta($post->ID, "_client_email", $client_emails);
			update_post_meta($post->ID, "_client_username", $client_status->encrypt($_POST['client_username']));
			update_post_meta($post->ID, "_client_password", $client_status->encrypt($_POST['client_password']));
		}
	}
	
	function send_status_email($toEmails, $data, $client_url){
		$headers = "From: ". get_bloginfo('admin_email') ."\r\n";
		$headers .= "Content-Type: text/html\r\n";
		$subject = _('New Website Updates available');
		$message = "<html><body>";
		$strikes = 0;
		
		if(!empty($data)){
			if($data->updates->core->response == "upgrade") { $strikes++; }
			if($plugin_update_count > 0) { $strikes++; }
			if($theme_update_count > 0) { $strikes++; }
			
			if($strikes > 0){
				$message .= "<p>" . _('Your website, ') . $client_url ._(', has updates available:') . "</p>";
				if(!property_exists($data, 'error')){
					$message .= "<ul>";
					if($data->is_public == 0){
						$message .= "<li><a href='". $client_url . CLIENT_STATUS_WP_PRIVACY_URL ."' target='_blank'>" . _('Not indexable') . "</a></li>"; 
					}
					if($data->updates->core->response == "upgrade"){
						$message .= "<li><a href='". $client_url . CLIENT_STATUS_WP_UPDATE_CORE_URL ."' target='_blank'>" . _('New version of WordPress available') . "</a></li>";
					}
					$plugin_update_count = count($data->updates->plugins->plugin);
					if($plugin_update_count > 0){
						$message .= "<li><a href='". $client_url . CLIENT_STATUS_WP_UPDATE_PLUGINS_URL ."' target='_blank'>" . _('New plugin updates available') . "</a>";
						$message .= "<ul>";
						foreach($data->updates->plugins->plugin as $plugin){
							$message .= "<li><strong>" . $plugin->name . "</strong> - " . _('You have version ') . $plugin->current_version . _(' installed.') . _(' Update to ') . $plugin->new_version . ".</li>";
						}
						$message .= "</ul></li>";
					}
					$theme_update_count = count($data->updates->themes->theme);
					if($theme_update_count > 0){
						$message .= "<li><a href='". $client_url . CLIENT_STATUS_WP_UPDATE_THEMES_URL ."' target='_blank'>" . _('New theme updates available') . "</a>";
						$message .= "<ul>";
						foreach($data->updates->themes->theme as $theme){
							$message .= "<li><strong>" . $theme->name . "</strong> - " . _('You have version ') . $theme->current_version . _(' installed.') . _(' Update to ') . $theme->new_version . ".</li>";
						}
						$message .= "</ul></li>";
					}
					$message .= "</ul>";
				} else {
					$message .= "<p><a href='". $client_url . CLIENT_STATUS_SETTINGS_URL ."' target='_blank'>" . (($data->error != '') ? $data->error : _('An error has occurred')) . "</a></p>";
				}
			}
		}
		$message .= "</body></html>";
		if($strikes > 0){
			return wp_mail($toEmails, $subject, $message, $headers);
		}
		return false;
	}
	
	function refresh($post_id, &$custom, &$ret, $updated){
		global $wpdb, $blog_id, $current_user;
		
		// Update post custom fields
		if($custom['_client_data'][0] != $ret['body']){	// New updates...save and send email
			update_post_meta($post_id, "_client_data", $ret['body']);
			$custom['_client_data'][0] = $ret['body'];
			$client_url = $custom['_client_url'][0];
			
			$data = simplexml_load_string($custom['_client_data'][0]);
			
			// Send email to admins				
			if (empty($id)){
				$id = (int) $blog_id;
			}
			$blog_prefix = $wpdb->get_blog_prefix($id);
			$users = $wpdb->get_results( "SELECT user_id, user_id AS ID, user_login, display_name, user_email, meta_value FROM $wpdb->users, $wpdb->usermeta WHERE {$wpdb->users}.ID = {$wpdb->usermeta}.user_id AND meta_key = '{$blog_prefix}capabilities' ORDER BY {$wpdb->users}.display_name" );
				
			$toEmails = "";
			foreach($users as $u){
				if(in_array($u->ID, $this->options->admin_emails)){
					if($toEmails != ""){
						$toEmails .= "," . $u->user_email;
					} else {
						$toEmails = $u->user_email;
					}
				}
			}
			if($toEmails != ""){
				$this->send_status_email($toEmails, $data, $client_url);
			}
			
			// Send email to clients					
			if(isset($custom['_client_email']) && $custom['_client_email'][0] != ""){
				$this->send_status_email($custom['_client_email'][0], $data, $client_url);
			}
		}
		update_post_meta($post_id, "_client_last_update", $updated);
		$custom['_client_last_update'][0] = $updated;
	}
	
	function update_site($post_id, $data){
	    global $client_status;
	    $custom = get_post_custom($post_id);
	    
	    set_time_limit(60);
		
		// Code to run xml-rpc on client server
		$params = array($client_status->decrypt($custom['_client_username'][0]), $client_status->decrypt($custom['_client_password'][0]), $data);
		$params = xmlrpc_encode_request('client_status_update', $params);
		$request = new WP_Http;
		$result = $request->request($custom['_client_url'][0] . 'xmlrpc.php',
	        array('method' => 'POST', 'body' => $params));

	    
	    update_post_meta($post_id, '_client_upgrade_response', $result);
	    update_post_meta($post_id, '_client_upgrade_time', time());
	}
}
?>