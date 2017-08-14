<?php
/*
Plugin Name: Client Status
Plugin URI: http://judenware.com/projects/wordpress/client-status/
Description: Status dashboard to keep track of updates for your clients' WordPress sites. Allows sending email to administrators and also to clients when new updates are needed.
Author: ericjuden
Version: 1.4
Author URI: http://www.judenware.com
Site Wide Only: false
Network: false
*/

define('CLIENT_STATUS_VERSION', '1.4');

require_once(ABSPATH . 'wp-includes/pluggable.php');
require_once('constants.php');
require_once(CLIENT_STATUS_CLASS_DIR . '/options.php');
require_once(CLIENT_STATUS_CLASS_DIR . '/client.php');
require_once(CLIENT_STATUS_CLASS_DIR . '/client-type.php');

$client_status = new Client_Status();

class Client_Status {
	var $options;
	var $clients;
    var $client_types;
    var $plugin_info = array();
    var $theme_info = array();
	
	function Client_Status(){
		$this->options =& new Client_Status_Options();
		
		add_action('admin_init', array(&$this, 'admin_init'));
		add_action('admin_menu', array(&$this, 'admin_menu'));
		add_action('admin_print_scripts', array(&$this, 'admin_print_scripts'));
		add_action('admin_print_styles', array(&$this, 'admin_print_styles'));
		add_action('client_status_after_client_title', array(&$this, 'after_client_title'), 1, 3);
		add_action('client_status_client_info', array(&$this, 'client_info'), 1, 3);
		add_action('client_status_refresh_all_clients_action', array(&$this, 'refresh_all_clients'));
		//add_action('client_status_after_client_quick_info', array(&$this, 'after_quick_info'), 10, 2);
		add_action('init', array(&$this, 'init'));
		add_action('manage_client_status_client_posts_custom_column', array(&$this, 'custom_client_columns'));
		add_action('right_now_content_table_end', array(&$this, 'right_now_content_table_end'));
		add_filter('manage_edit-client_status_client_columns', array(&$this, 'edit_client_columns'));
		add_filter('wp_mail_from_name', array(&$this, 'fix_from_name'));
		add_filter('xmlrpc_methods', array(&$this, 'xmlrpc_methods'));
		register_deactivation_hook(__FILE__, array(&$this, 'deactivate'));

		$this->clients = new Client_Status_Client();
		$this->client_types = new Client_Status_Client_Type();
		
		if($this->options->install_type == CLIENT_STATUS_INSTALL_TYPE_DASHBOARD){
			$this->clients->options =& $this->options;
			
			if($this->options->allow_cron_updates > 0){
				if(isset($this->options->update_scheduled) && $this->options->update_scheduled == 0){
					wp_schedule_event(time(), $this->options->update_frequency, 'client_status_refresh_all_clients_action');
					$this->options->update_scheduled = 1;
					$this->options->save();
				}
			}
		}
	}
	
	function after_client_title($post, $data, $site_info){
	    if(!empty($data)){
    		
    		// Show update count for site
    		if($site_info['update_count'] > 0){ 
?>
			<div class="update-site"><span class="update-count"><?php echo $site_info['update_count']; ?></span></div>
<?php
    		}
    
    		// Show/hide quick information
    		if($this->options->show_quick_info){
?>
    		<span class="quick-info"><?php echo $site_info['core_text']; ?>, <?php echo $site_info['plugin_text']; ?>, <?php echo $site_info['theme_text']; ?>, <?php echo $site_info['index_status_text'];?></span>
<?php
    		}
	    }
	}
	
	function admin_init(){
		$this->options->register();
		
		if(!isset($this->options->install_type)){
			$this->options->install_type = 1;
		}
		
		if(!isset($this->options->update_frequency)){
			$this->options->update_frequency = "hourly";
		}
		
		if(!isset($this->options->allow_cron_updates)){
			$this->options->allow_cron_updates = 1;
		}
		
		if(!isset($this->options->update_scheduled)){
			$this->options->update_scheduled = 0;
		}
		
		if(!isset($this->options->admin_emails)){
			$this->options->admin_emails = array();
		}
		
		if(!isset($this->options->expand_client_info)){
			$this->options->expand_client_info = 0;
		}
		
		if(!isset($this->options->show_quick_info)){
			$this->options->show_quick_info = 1;
		}
		
		if(!isset($this->options->version) || (CLIENT_STATUS_VERSION > $this->options->version)){
			$this->plugin_update();
		}
		
		$this->options->save();
		
		wp_register_style('client_status_stylesheet', CLIENT_STATUS_PLUGIN_URL . '/style.css');
		
		load_plugin_textdomain('client-status', false, CLIENT_STATUS_PLUGIN_DIR . '/languages');
	}
	
	function admin_menu(){
		add_options_page('Client Status Options', 'Client Status', 'update_core', 'client-status-options', array(&$this, 'plugin_options'));
		if($this->options->install_type == CLIENT_STATUS_INSTALL_TYPE_DASHBOARD){
			add_dashboard_page('Client Status', 'Client Status', 'update_core', 'client-status-dashboard', array(&$this, 'plugin_dashboard'));
			remove_meta_box('postcustom', 'client_status_client', 'normal');
		}
	}
	
	function admin_print_scripts(){
		wp_enqueue_script('jquery');
	}
	
	function admin_print_styles(){
		wp_enqueue_style('client_status_stylesheet');
	}
	
	function client_info($post, $data, $site_info){
?>
	    <div style="float: left; width: 45%;">
			<h3><?php _e('Updates'); ?></h3>
			<p>
				<em><?php echo _('Last Update: ') . $site_info['last_update']; ?></em>
				<input type="image" name="refresh-<?php echo $post->ID; ?>" id="refresh-<?php echo $post->ID; ?>" src="<?php echo CLIENT_STATUS_IMAGE_URL; ?>/arrow_refresh.png" />
				<?php if($data !== false && property_exists($data, 'error')){ ?>
				<p><img class="status" src="<?php echo CLIENT_STATUS_IMAGE_PROBLEM; ?>" /><a href="<?php echo $site_info['client_url'] . CLIENT_STATUS_SETTINGS_URL; ?>" class="status_problem" target="_blank"><?php echo (($data->error != '') ? $data->error : _('An error has occurred')); ?></a></p>
				<?php } ?>
				<?php if($data === false){ ?>
				<p><img class="status" src="<?php echo CLIENT_STATUS_IMAGE_PROBLEM; ?>" /><a href="post.php?post=<?php echo $post->ID; ?>&action=edit" class="status_problem"><?php _e('You must enter a site URL to retrieve data'); ?></a></p>
				<?php } ?>
			</p>
			<p><?php echo $site_info['core_text']; ?><?php if($data->updates->core->response == "upgrade"){ ?><div class="core"><input class="client-checkbox" type="checkbox" name="client-<?php echo $post->ID; ?>-core" id="client-<?php echo $post->ID; ?>-core" value="upgrade" /> <label for="client-<?php echo $post->ID; ?>-core"><strong><?php _e('Update WordPress'); ?></strong></label></div><?php } ?></p> 
			<p><?php echo $site_info['plugin_text']; ?></p>
<?php
		if($site_info['plugin_update_count'] > 0){
			foreach($data->updates->plugins->plugin as $plugin){
?>
			<div class="plugin">
<?php
				$info = array();
				$slug = strval($plugin->slug);
				if(array_key_exists($slug, $this->plugin_info)){
					$info = $this->plugin_info[$slug];
				} else {
					$info = plugins_api('plugin_information', array('slug' => $slug ));
					$this->plugin_info[$slug] = $info;
				}
?>
				<input class="client-checkbox" type="checkbox" name="client-<?php echo $post->ID; ?>-plugins[]" id="client-<?php echo $post->ID; ?>-plugins[]" value="<?php echo $plugin->file; ?>" />&nbsp;
				<strong><?php echo $plugin->name; ?></strong><br />
				<?php echo _('You have version ') . $plugin->current_version . _(' installed.') . _(' Update to ') . $plugin->new_version; ?>
<?php
				if ( isset($info->tested) && version_compare($info->tested, $data->version, '>=') ) {
					echo '<br />' . sprintf(__('Compatibility with WordPress %1$s: 100%% (according to its author)'), $data->version);
				} elseif (isset($info->compatibility) && isset($info->compatibility[$data-version]) && isset($info->compatibility[$data->version][$plugin->new_version]) ) {
					echo $info->compatibility[$data->version][$plugin->new_version];
					echo '<br />' . sprintf(__('Compatibility with WordPress %1$s: %2$d%% (%3$d "works" votes out of %4$d total)'), $data->version, $compat[0], $compat[2], $compat[1]);
				} else {
					echo '<br />' . sprintf(__('Compatibility with WordPress %1$s: Unknown'), $data->version);
				}
?>
			</div>
<?php
	        }
		}
?>
			<p><?php echo $site_info['theme_text']; ?></p>
<?php
		if($site_info['theme_update_count'] > 0){
			foreach($data->updates->themes->theme as $theme){
?>
			<div class="theme">
				<input class="client-checkbox" type="checkbox" name="client-<?php echo $post->ID; ?>-themes[]" id="client-<?php echo $post->ID; ?>-themes[]" value="<?php echo $theme->stylesheet; ?>" />&nbsp;
				<strong><?php echo $theme->name; ?></strong><br />
				<?php echo _('You have version ') . $theme->current_version . _(' installed.') . _(' Update to ') . $theme->new_version; ?>
			</div>
<?php
			}
		}
		do_action('client_status_after_client_update_info', $post, $data);
?>
		</div>
<?php
		
		if($data !== false && property_exists($data, 'plugin_version') && $data->plugin_version > 1.1){
?>
		<div style='float: left; width: 45%;'>
			<h3><?php echo _('Server Information'); ?><a href="<?php echo $site_info['data_url']; ?>" target="_blank" alt="<?php _e('View client data file (for debugging)'); ?>" title="<?php _e('View client data file (for debugging)'); ?>"><img class="helper" src="<?php echo CLIENT_STATUS_IMAGE_URL; ?>/transmit_blue.png" /></a></h3>
			<p><?php echo _('Server OS: ') . $data->os_version; ?></p>
			<p><?php echo _('PHP Version: ') . $data->php_version; ?></p>
			<p><?php echo _('MySQL Version: ') . $data->mysql_version; ?></p>
			<p><?php echo $site_info['index_status_text']; ?></p>
			<p><?php echo _('Pending Comments: '); ?><a href="<?php echo $site_info['client_url'] . CLIENT_STATUS_WP_COMMENTS_URL; ?>"><?php echo $data->comments->pending; ?></a></p>
			<p><?php echo _('Pending Posts: '); ?><a href="<?php echo $site_info['client_url'] . CLIENT_STATUS_WP_POSTS_URL; ?>"><?php echo $data->posts->pending; ?></a></p>
			<p><?php echo _('XML-RPC Enabled: '); ?><a href="<?php echo $site_info['client_url'] . 'wp-admin/options-writing.php'; ?>" target="_blank"><?php echo (isset($data->enable_xmlrpc) && $data->enable_xmlrpc > 0) ? _('Yes') : _('No'); ?></a></p>
			<?php do_action('client_status_after_client_server_info', $post, $data); ?>
		</div>
<?php
		}
	}
	
	function custom_client_columns($column){
		global $post;
		switch($column){
			case 'client_type':
				echo get_the_term_list($post->ID, 'client_status_client_type', '', ', ', '');
				break;
		}
	}
	
	function deactivate(){
		wp_clear_scheduled_hook('client_status_refresh_all_clients_action');
	}
	
	function decrypt($string, $key = '') {
  		$result = '';
  		if($key == ''){
  			$key = md5($this->options->security_key);
  		}
  		$string = base64_decode($string);
  		for($i=0; $i<strlen($string); $i++) {
    		$char = substr($string, $i, 1);
    		$keychar = substr($key, ($i % strlen($key))-1, 1);
    		$char = chr(ord($char)-ord($keychar));
    		$result.=$char;
  		}
  		return $result;
	}
	
	function edit_client_columns($columns){
		$columns = array(
			'cb' => '<input type="checkbox" />',
			'title' => 'Title',
			'client_type' => _('Client Type'),
			'date' => _x('Date', 'column name'),
		);
		
		return $columns;
	}
	
	function encrypt($string, $key = '') {
  		$result = '';
		if($key == ''){
  			$key = md5($this->options->security_key);
  		}
  		for($i=0; $i<strlen($string); $i++) {
    		$char = substr($string, $i, 1);
    		$keychar = substr($key, ($i % strlen($key))-1, 1);
    		$char = chr(ord($char)+ord($keychar));
    		$result.=$char;
  		}
  		return base64_encode($result);
	}
	
	function fix_from_name($name){
		$name = get_bloginfo('blogname');
		return $name;
	}
	
	function init(){
		
	}
	
	function plugin_dashboard(){
		require_once 'dashboard.php';
	}
	
	function plugin_options(){
		require_once 'client-status-options.php';
	}
	
	function plugin_update(){
		switch($this->options->version){
			case '1.3.3':
			default:
				// Clear scheduled hook and let it reschedule.
				wp_clear_scheduled_hook('client_status_refresh_all_clients_action');
				$this->options->update_scheduled = 0;
		}
		
		// Update to new version number
		$this->options->version = CLIENT_STATUS_VERSION;
		$this->options->save();
	}
	
	function right_now_content_table_end(){
		if (is_admin() && $this->options->install_type == CLIENT_STATUS_INSTALL_TYPE_DASHBOARD) {
			$num_clients = wp_count_posts('client_status_client');
			$text = _n(_('Client'), _('Clients'), $num_clients->publish);
			
			echo "<tr>";
	        $num = "<a href='edit.php?post_type=client_status_client'>$num_clients->publish</a>";
	        $text = "<a href='index.php?page=client-status-dashboard'>$text</a>";
	        
	        echo '<td class="first b">' . $num . '</td>';
		    echo '<td class="t">' . $text . '</td>';
		    echo '</tr>';
	    }
	}

	function refresh_all_clients(){
		global $post, $wpdb, $blog_id, $current_user;
		
		$clients = $this->clients->get_multi();
		foreach($clients as $post){
			setup_postdata($post);
			$custom = get_post_custom($post->ID);
		
			$client_url = $custom['_client_url'][0];
			if($client_url != ""){
				$ret = wp_remote_post($client_url . CLIENT_STATUS_DATA_URL . "?security_key=" . md5($this->options->security_key));
				$updated = time();
				
				$this->clients->refresh($post->ID, &$custom, &$ret, $updated);
			}
		}
	}
	
	function update_encryption($old_hash){
		global $post;
		$clients = $this->clients->get_multi();
		foreach($clients as $post){
			setup_postdata($post);
			$custom = get_post_custom($post->ID);
			
			$username = $this->decrypt($custom['_client_username'][0], $old_hash);
			$password = $this->decrypt($custom['_client_password'][0], $old_hash);
			
			update_post_meta($post->ID, "_client_username", $this->encrypt($username));
			update_post_meta($post->ID, "_client_password", $this->encrypt($password));
		}
	}
	
	function xmlrpc_methods($methods){
		$methods['client_status_update'] = array(&$this, 'xmlrpc_update');
	    return $methods;
	}
	
	function xmlrpc_update($args){	    
		global $wp_xmlrpc_server;		
		$wp_xmlrpc_server->escape($args);
		
		$username = $args[0];
	    $password = $args[1];
	    $data = $args[2];
 
	    // Let's run a check to see if credentials are okay
	    if ( !$user = $wp_xmlrpc_server->login($username, $password) ) {
	        return $wp_xmlrpc_server->error;
	    }
	    
	    require_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');
	    
	    // Update plugins
	    if(!empty($data['plugins'])){
	        $upgrader = new Plugin_Upgrader(new Bulk_Plugin_Upgrader_Skin());
	        $upgrader->bulk_upgrade($data['plugins']);
	    }
	    
	    // Update themes
	    if(!empty($data['themes'])){
	        $upgrader = new Theme_Upgrader(new Bulk_Theme_Upgrader_Skin());
	        $upgrader->bulk_upgrade($data['themes']);
	    }
	    
	    // Update core
	    if(!empty($data['core'])){
	        require_once(ABSPATH . 'wp-admin/includes/update.php');
	        $core_updates = get_core_updates();

	        if(!empty($core_updates)){
    	        $upgrader = new Core_Upgrader(new WP_Upgrader_Skin(array('url' => '')));
    	        $upgrader->upgrade($core_updates[0]);
	        }
	    }
	}
}
?>