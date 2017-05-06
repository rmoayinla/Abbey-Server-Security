<?php
/*

* Plugin Name: Abbey Server Security
* Description: Secure your wordpress server 
* Author: Rabiu Mustapha
* Version: 0.1
* Text Domain: abbey-secure-server

*/

class Abbey_Secure_Server{
	public function __construct(){
		add_filter( "query_vars", array( $this, "server_error_codes" ) );
		
		add_action( "init", array( $this, "add_custom_rewrite" ) ); 

		register_deactivation_hook( __FILE__, array( $this, 'flush_rewrite_rules' ) );
		register_activation_hook( __FILE__, array( $this, 'flush_rewrite_rules' ) );
	}

	function server_error_codes( $vars ){
		$vars[] = "s_error_code";
		return $vars;
	}

	function add_custom_rewrite(){
		add_rewrite_rule(
        's_error/err/([0-9]{3})/?$',
        'index.php?pagename=s_error&s_error_code=$matches[1]',
        'top'
    	);
	}

	function flush_rewrite_rules(){
		flush_rewrite_rules();
	}
}

require_once( plugin_dir_path( __FILE__ )."abbey-theme-login.php" );
new Abbey_Secure_Server();