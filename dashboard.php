<?php 
require_once(ABSPATH . 'wp-admin/includes/plugin-install.php');

// Security Checkpoint
if(!current_user_can('update_core')){
    die(__('Cheatin&#8217; uh?'));
}

global $post;
$date_format = get_option('date_format') . " " . get_option('time_format');
$timezone_offset = get_option('gmt_offset');

$refresh_clients = array();
$update_clients = array();
if(is_array($_REQUEST)){
	//print_r($_REQUEST);
	foreach($_REQUEST as $key=>$value){
		switch($key){
			case (strstr($key, 'refresh-')):
				$id = substr($key, 8, -2);
				$refresh_clients[$id] = $id;
				break;
				
			case (strstr($key, 'client-')):		// Updating clients
				$id = substr($key, 7);
				
				$index = '';
				if(strstr($id, '-')){    // Updating individual plugins or themes
				    $parts = explode('-', $id);
				    $id = $parts[0];
				    $index = $parts[1];
				    
				    // Look for plugins, themes
				    $update_clients[$id][$index] = $value;
				} else {                // Updating entire site
			        

				    // TODO: Need to write this code still
				    
				    
				}
				
				//$client = $this->clients->get($id);
				
				// Update the client
				//$this->clients->update_site($id);
				break;
			
		}
	}
}

// Run updates
if(!empty($update_clients)){
    foreach($update_clients as $client_id=>$data){
        $this->clients->update_site($id, $data);
    }
}
?>

<script type="text/javascript">
	jQuery(document).ready(function(){		
		jQuery('span.wp-has-submenu').click(function(){
			jQuery(this).next('.wp-submenu').slideToggle(600);
		});

		jQuery(".post-edit-link").click(function(event){event.stopPropagation();});
		jQuery(".client-checkbox").click(function(event){event.stopPropagation();});
		jQuery(".client_status_show_submenu").show();

		// Check/uncheck client plugins & themes
		
	});
	
	function switchTabs(tab){
        jQuery('.client_status_client').hide();
        jQuery('.'+tab).show();
    }
</script>

<div class="wrap">
	<form method="post">	
		<h2><?php _e('Client Status'); ?></h2>
<?php 
$client_types = $this->client_types->get_multi();

echo "<ul class='client_status_tabs'>";
echo "<li><a id='0' href='javascript:switchTabs(\"client_status_tab_all\");'>" . __('All') . "</a></li>";
foreach($client_types as $key=>$client_type){
	echo "<li><a id='". $client_type->term_id ."' href='javascript:switchTabs(\"client_status_tab_". $client_type->term_id ."\");'>" . $client_type->name . "</a></li>";
}
echo "</ul>";

$clients = $this->clients->get_multi();
if(!empty($clients)){
	echo "<ul id='client_status_list'>";
	
	foreach($clients as $post){
		setup_postdata($post);
		$custom = get_post_custom($post->ID);

		// Build URL to client site
	    $client_url = "";
		if(isset($custom['_client_url']) && $custom['_client_url'][0] != ''){
		    $client_url = $custom['_client_url'][0];
		}
		
		// Build URL to client data file
		$data_url = "";
		if($client_url != ""){
			$data_url = $client_url . CLIENT_STATUS_DATA_URL . "?security_key=" . md5($this->options->security_key);
		}
		
		// Do we need to refresh the data?
	    $data = "";
		if(!empty($refresh_clients) && array_key_exists($post->ID, $refresh_clients)){
			$ret = wp_remote_post($data_url);
			$updated = time();
			
			$this->clients->refresh($post->ID, &$custom, $ret, $updated);	
		}
		
		// Look for debugging to show errors....TEMP
	    if(isset($_GET['debug']) && $_GET['debug'] == 1){
	        libxml_use_internal_errors(false);
	    } else {
	        libxml_use_internal_errors(true);
	    }
		$data = simplexml_load_string($custom['_client_data'][0]);
		if(!isset($_GET['debug'])){
	        libxml_clear_errors();
	    }
	    
	    $site_info = array();
	    
	    $site_info['last_update'] = false;
	    if(!empty($data)){
	        $site_info['last_update'] = $custom['_client_last_update'][0];
			$site_info['last_update'] = date_i18n($date_format, $site_info['last_update'] + $timezone_offset * 3600);
			$site_info['client_url'] = $client_url;
			$site_info['data_url'] = $data_url;

			$site_info['plugin_update_count'] = count($data->updates->plugins->plugin);
    		$site_info['theme_update_count'] = count($data->updates->themes->theme);
    		
    		$site_info['strikes'] = 0;
    		$site_info['update_count'] = 0;
    		if($data->is_public == 0){ $site_info['strikes']++; }
    		if($data->updates->core->response == "upgrade") { $site_info['strikes']++;  $site_info['update_count']++; }
    		if($site_info['plugin_update_count'] > 0) { $site_info['strikes']++; $site_info['update_count']+=$site_info['plugin_update_count']; }
    		if($site_info['theme_update_count'] > 0) { $site_info['strikes']++; $site_info['update_count']+=$site_info['theme_update_count']; }
    		
    		$site_info['core_text'] = (($data->updates->core->response == "upgrade") ? "<img class='status' src='" . CLIENT_STATUS_IMAGE_ERROR . "' />" : "<img class='status' src='" . CLIENT_STATUS_IMAGE_OK . "' />") . _('WordPress Version: ') . "<a class='". (($data->updates->core->response == "upgrade") ? "status_error" : "status_ok") ."' href='". $client_url . CLIENT_STATUS_WP_UPDATE_CORE_URL ."' target='_blank' alt='". $data->version ."' title='". $data->version ."'>" . $data->version . "</a>";
    		$site_info['plugin_text'] = (($site_info['plugin_update_count'] > 0) ? "<img class='status' src='" . CLIENT_STATUS_IMAGE_PLUGIN_ERROR . "' />" : "<img class='status' src='" . CLIENT_STATUS_IMAGE_OK . "' />") . _('Plugin Updates: ') . "<a class='". (($site_info['plugin_update_count'] > 0) ? "status_error" : "status_ok") ."' href='". $client_url . CLIENT_STATUS_WP_UPDATE_PLUGINS_URL ."' target='_blank' alt='". $site_info['plugin_update_count'] ."' title='". $site_info['plugin_update_count'] ."'>" .  $site_info['plugin_update_count'] . "</a>";
    		$site_info['theme_text'] = (($site_info['theme_update_count'] > 0) ? "<img class='status' src='" . CLIENT_STATUS_IMAGE_THEME_PROBLEM . "' />" : "<img class='status' src='" . CLIENT_STATUS_IMAGE_OK . "' />") . _('Theme Updates: ') . "<a class='". (($site_info['theme_update_count'] > 0) ? "status_error" : "status_ok") ."' href='". $client_url . CLIENT_STATUS_WP_UPDATE_THEMES_URL ."' target='_blank' alt='". $site_info['theme_update_count'] ."' title='". $site_info['theme_update_count'] ."'>" .  $site_info['theme_update_count'] . "</a>";
    		$site_info['index_status_text'] = _('Status: ') . (($data->is_public == 0) ? "<a href='". $client_url . CLIENT_STATUS_WP_PRIVACY_URL ."' target='_blank' title='". _('Adjust privacy settings') ."' alt='". _('Adjust privacy settings') ."'>" . _('Not Indexable') . "</a>" : "<a href='". $client_url . CLIENT_STATUS_WP_PRIVACY_URL ."' target='_blank' title='". _('Adjust privacy settings') ."' alt='". _('Adjust privacy settings') ."'>" . _('Indexable') . "</a>");
	    }
		
	    // Get client types and add as class to li
		$terms = wp_get_post_terms($post->ID, 'client_status_client_type');
		$term_classes = ""; 
		foreach($terms as $key=>$term){
			$term_classes .= " client_status_tab_" . $term->term_id;
		}
		
		?>
		<li class="wp-has-submenu menu-top wp-menu-open client_status_client client_status_tab_all<?php echo $term_classes; ?>">
			<span class="wp-has-submenu menu-top">
				<div class="client_status_client_title">
					<input class="client-checkbox" type="checkbox" name="client-<?php echo $post->ID; ?>" id="client-<?php echo $post->ID; ?>" value="1" />
					<label for="client-<?php echo $post->ID; ?>"><?php the_title(); ?></label>
				</div>
				<?php do_action('client_status_after_client_title', $post, $data, $site_info); ?>
				<?php edit_post_link('Edit', ' &nbsp;', ''); ?>
				<div class="clear"></div>
	        </span>
	        <div class="wp-submenu <?php echo (($this->options->expand_client_info > 0) ? ' client_status_show_submenu' : ''); ?>">
	        	<div class='wp-submenu-content'>
	        		<?php do_action('client_status_client_info', $post, $data, $site_info); ?>
	        		<div class="clear"></div>
	        	</div>
	        	<div class="clear"></div>
        	</div>
    	</li>
		<?php
	}
	    ?>
	</ul>
	<input type="submit" class="button-primary" name="update-selected-clients" id="update-selected-clients" value="<?php _e('Update Selected'); ?>" />
<?php
}
?>
	</form>
</div>