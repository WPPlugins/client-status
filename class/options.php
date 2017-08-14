<?php
class Client_Status_Options {
	var $options;
	
	function Client_Status_Options(){
		$this->options = get_option('client_status_options', false);
		if($this->options === false){
		    $this->options = array();
		}
	}
	
	function __get($key){
		return $this->options[$key];
	}
	
	function __set($key, $value){
		$this->options[$key] = $value;
	}
	
	function __isset($key){
		return array_key_exists($key, $this->options);
	}
	
	function register(){
		register_setting('client_status_group', 'client_status_options');
	}
	
	function save(){
		update_option('client_status_options', $this->options);
	}
}
?>