<?php
class Client_Status_Client_Type {
	var $taxonomy = 'client_status_client_type';
	
	function Client_Status_Client_Type(){
		add_action('init', array(&$this, 'init'));
	}
	
	function get_multi(){
		return get_terms(array($this->taxonomy));
	}
	
	function get($term_id){
		return get_term($term_id, $this->taxonomy, OBJECT);
	}
	
	function init(){
		register_taxonomy(
			$this->taxonomy,
			array('client_status_client'),
			array(
				'public' => true,
				'hierarchical' => true,
				'labels' => array(
					'name' => __('Client Types'),
					'singular_name' => __('Client Type'),
					'add_new' => __('Add New'),
					'add_new_item' => __('Add New Client Type'),
                    'all_items' => __('All Client Types'),
					'edit' => __('Edit'),
					'edit_item' => __('Edit Client Type'),
					'new_item' => __('New Client Type'),
					'view' => __('View'),
					'view_item' => __('View Client Type'),
					'search_items' => __('Search Client Types'),
					'not_found' => __('No client types found'),
					'not_found_in_trash' => __('No client types found in Trash'),
				),
				'rewrite' => array('slug' => 'client-type'),
			)
		);
	}
}
?>