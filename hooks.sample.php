<?php
/*
Plugin Name: Client Status Extender
Plugin URI: http://judenware.com/projects/wordpress/client-status/
Description: Example plugin to extend the functionality of Client Status
Author: ericjuden
Version: 1.4
Author URI: http://www.judenware.com
Site Wide Only: false
Network: false
*/

define('CSE_DEFAULT_PRIORITY', 10);

add_action('client_status_after_client_title', after_title, CSE_DEFAULT_PRIORITY, 2);
add_action('client_status_after_client_quick_info', after_quick_info, CSE_DEFAULT_PRIORITY, 2);
add_action('client_status_after_client_update_info', after_update_info, CSE_DEFAULT_PRIORITY, 2);
add_action('client_status_after_client_server_info', after_server_info, CSE_DEFAULT_PRIORITY, 2);
add_action('client_stastus_after_client_info', after_info, CSE_DEFAULT_PRIORITY, 2);

//Append stuff to title bar (occurs after quick info and before edit link)
function after_title($post, $data){
	echo "<span style='color: #FF0000; font-weight: bold;'>ran after_title hook</span>";
}

//Append stuff to the end of the quick info section
function after_quick_info($post, $data){
	echo "<span style='color: #FF0000; font-weight: bold;'>ran after_quick_info hook</span>";
}

//At the end of each client update information
function after_update_info($post, $data){
	echo "<span style='color: #FF0000; font-weight: bold;'>ran after_update_info hook</span>";
}

//At the end of each client server info section
function after_server_info($post, $data){
	echo "<span style='color: #FF0000; font-weight: bold;'>ran after_server_info hook</span>";
}

//At the end of each client (outside of Updates and Server Information)
function after_info($post, $data){
	echo "<span style='color: #FF0000; font-weight: bold;'>ran after_info hook</span>";
}
?>