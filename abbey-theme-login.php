<?php

class Abbey_Theme_Login{

	private $login_form_css;
	private $login_error;
	private $login_details;

	/**
	 * Constructor function where all hooks are loaded 
	 * @since: 
	 */
	function __construct(){

		/**
		 * Action hook where the wordpress logo is changed in login page
		 * The uploaded logo of the current site is displayed instead 
		 */
		add_action( "login_head", array( $this, "show_site_logo" ) );

		/**
		 * Change the default url where the logo is linked to when clicked 
		 * The url will now point to the current site homepage not wordpress
		 */
		add_filter( 'login_headerurl', array( $this, "site_url" ) );
		add_filter( 'login_headertitle', array( $this, "site_description" ) );

		add_action('wp_login', array( $this, "email_user" ), 10, 2);
		
		add_action('init', array( $this, "register_login_css" ) );
		add_action( 'wp_footer', array( $this, "print_login_css" ) );
		add_action( "wp", array( $this, "logout_user" ) );
		
		add_filter( 'login_form_bottom', array( $this, "add_to_login_form" ) );
		add_filter( 'wp_login_errors', array( $this, "redirect_login_error" ), 10, 2 );

		add_shortcode( 'loginform', array( $this, 'show_login_form' ) );

		$this->init();
		
	}

	/**
	 * Display the current site logo at the top of the login form 
	 * this method is hooked to wp login_head 
	 * The uploaded logo will be displayed through css 
	 * @since: 
	 */
	function show_site_logo(){
		// bail if there is no uploaded logo //	
		if( !has_custom_logo() ) return;
		
			$logo = get_theme_mod("custom_logo");
			$logo_attachment = wp_get_attachment_image_src( $logo, "full" );
			$logo_url = $logo_attachment[0]; 
		?>
		<!-- start outputting the css to change the background logo -->
		<style>
			#login a{
				background-image: url(<?php echo esc_url( $logo_url );?>)!important;
				background-size: 240px !important;
				width: 240px !important;
				max-width: 80%;
			}
		</style>
		<?php
	}

	/** 
	 * Return the current site homepage for the logo 
	 * the default url is wordpress url but we will change it to the current site homepage 
	 * @since: 
	 */
	function site_url( $url ){
		$url = home_url( "/" );
		return $url;
	}

	/**
	 * Display the site description under the logo 
	 * @uses: wp_trim_words 		to trim the default site description to 25 characters 
	 * @since: 
	 */
	function site_description( $title ){
		return wp_trim_words( get_bloginfo( "description" ), 25, "" );
	}

	/** 
	 * A core method for this plugin where property values are assigned 
	 * This method setup default values for our class  properties 
	 * @since: 
	 * @uses: get_transient 		this is use to check if there is a transient value of login error 
	 */
	function init(){

		// a simple indicator for loading our css, this make sure we only load css when the shortcode is present //
		$this->login_form_css = false;

		// container for saving the user entered username and password //
		$this->login_details = array();

		// simple container for indicating the login error //
		$this->login_error = "";

		/** 
		 * Check if there was an an error message set already
		 * If set, the login error is copied  to our class property login_error 
		 */
		if( false !== ( $error = get_transient( "abbey_login_error" ) ) )
			$this->login_error = get_transient( "abbey_login_error" );

		/**
		 * Check if there we have a login details stored 
		 * This will be used to prefill the login form with the details of a user if present 
		 */

		if( false !== ( $details = get_transient( "abbey_login_details" ) ) )
			$this->login_details = get_transient( "abbey_login_details" );
	}

	/**
	 * Display the actual form from the details passed from the shortcode 
	 * The form might be prefilled if there are some login details present 
	 * The form will also display with an error message if there is any 
	 * @since: 
	 */
	function show_login_form( $atts ){
		
		/**
		 * Default shortcode attributes 
		 * echo 		boolean			indicate if the form should be 
		 */
		$default =  array(
			'echo'           => false,
			'redirect'       => admin_url(),
			'value_username' => !empty( $this->login_details[ "username" ] ) ? $this->login_details[ "username" ] : ""
		);
		$this->login_form_css = true;

		$args = shortcode_atts( $default, $atts );

		ob_start();	?>
			<?php if( !empty( $this->login_error ) ) : ?>
				<div class="login-errors alert alert-warning">
				 	<?php echo $this->login_error; ?> 
				</div>
			<?php endif; ?>
			<?php echo wp_login_form( $args ); ?>
			<div style="direction: ltr;"><?php print_r( $this->login_details ); ?></div>
		
		<?php return ob_get_clean(); 
	}

	function add_to_login_form( $args ){
		$login_page = home_url( add_query_arg( NULL, NULL ) );
		$nonce_field = wp_nonce_field( 'abbey_login_form', 'abbey_login_form_nonce', true, false );
		return "
		<input type='hidden' name='abbey_theme_login' value='true' />
		<input type='hidden' name='login_form_url' value='$login_page' />
		$nonce_field
		";
	}

	function redirect_login_error( $errors, $redirect_url ){
		if( empty( $_POST["abbey_theme_login"] ) || empty( $errors ) )
			return $errors; 
		$login_url = !empty( $_POST["login_form_url"] ) ? esc_url( $_POST["login_form_url"] ) : home_url( "/login" );
		if( is_wp_error( $errors ) ){
			$this->login_error = "";
			foreach ( $errors->get_error_codes() as $code ) {
				foreach ( $errors->get_error_messages( $code ) as $error_message ) {
					$this->login_error .= '	' . $error_message . "<br />\n";
					break;
				}
			}
			$redirect_url = add_query_arg( "error", "true", $login_url );
			$this->login_details[ "username" ] = $_POST["log"];
			$this->login_details[ "ip_address" ] = $_SERVER[ "REMOTE_ADDR" ];

			set_transient( "abbey_login_details", $this->login_details, HOUR_IN_SECONDS );
			set_transient( "abbey_login_error", $this->login_error, HOUR_IN_SECONDS );
			wp_safe_redirect( $redirect_url );
			exit();
		}
	}

	function logout_user(){
		global $post;
		if( !empty( $post ) ){
			if( !is_page() )
				return;
			$title = apply_filters( "the_title", $post->post_title );
			if( preg_match( "/^login$/i", $title ) && is_user_logged_in() ){
				$current_user = wp_get_current_user();
				$this->login_details[ "username" ] = $current_user->user_login;
				$this->login_details[ "email" ] = $current_user->user_email;

				wp_logout();
				wp_set_current_user(0);
			}
		}
	}

	function register_login_css(){ 
		wp_register_style( 'abbey-login-form-css', plugin_dir_url( __FILE__ ) . '/css/login-form.css' );
	}

	function print_login_css(){
		if( $this->login_form_css )
			wp_print_styles( "abbey-login-form-css" );
	}

	function email_user( $user_login, $user ){
		if( empty( $user_login ) || is_wp_error( $user ) )
			return;
		wp_set_current_user( $user->ID );
		$headers = array();
		$admin_email = get_option( "admin_email" );

		$headers[] = "Reply-To:".get_bloginfo("name"). "'s Admin <".$admin_email.">";
		$headers[] = "Content-Type: text/html";
		$to = array ( $admin_email, $user->user_email );
		$mail = sprintf( '<div style="font-size:13px;padding: 25px 30px;border-bottom: 2px solid #000;"> 
								<p> On: %1$s, Time: %2$s </p>
							</div>
							<div style="height:64px;padding: 50px 30px; background-color:#ccc; font-size: 24px;">
								<h4> %3$s successfully logged into %4$s from %5$s address  </h4>
							</div>
							<div style="font-size:13px; padding: 30px 15px; text-align: center; border-top: 2px solid #000;">
								<p> &copy; <a href="%6$s" title="Visit homepage">%4$s</a> %7$s </p>
							</div>',
						date("Y/m/d"), 
						date( "h:i:sa" ),
						ucwords( $user_login ), 
						get_bloginfo( "name" ), 
						$_SERVER['REMOTE_ADDR'],
						home_url( "/" ),
						date( "Y" )
					);
		$send = wp_mail ( $to, __( "Login Notice" ), $mail, $headers );
	}

}

new Abbey_Theme_Login();
