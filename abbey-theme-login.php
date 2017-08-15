<?php
/**
 * 
 * A simple class that handles Wordpress login page 
 * Replace wordpress logo in login page with site uploaded logo 
 * Provide a simple shortcode to display login form in posts and pages 
 * Add additional verification and authentication to wordpress login process 
 *
 * version: 0.1 
 * author: Rabiu Mustapha 
 *
 *
 */

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

		/**
		 * WP filter to change the title attribute for the login page logo
		 * this attribute will now display the current site description albeit trimmed 
		 * @see: __CLASS__::site_description 
		 * @since: 1.0
		 */
		add_filter( 'login_headertitle', array( $this, "site_description" ) );

		/**
		 * WP action hook for a successful login 
		 * Email the Site admin and logged in user when the user has logged in successfully 
		 * @see: __CLASS__::email_user 
		 * @since: 1.0
		 */
		add_action('wp_login', array( $this, "email_user" ), 10, 2);
		
		/**
		 * Register the login css file to WP enqueue
		 * the file wont be enqueued yet, it will be enqueued only when there is a login shortcode on the page
		 * @since: 1.0
		 */
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
	 * @since: 1.0
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
			#login h1 a{
				background-image: url(<?php echo esc_url( $logo_url );?>)!important;
				background-size: 68% !important;
				width: 240px !important;
				max-width: 80%;
			}
		</style>
		<?php
	}

	/** 
	 * Return the current site homepage for the logo 
	 * the default url is wordpress url but we will change it to the current site homepage 
	 * @since: 1.0 
	 */
	function site_url( $url ){
		$url = home_url( "/" );
		return $url;
	}

	/**
	 * Display the site description under the logo 
	 * @uses: wp_trim_words 		to trim the default site description to 25 characters 
	 * @since: 1.0
	 */
	function site_description( $title ){
		return wp_trim_words( get_bloginfo( "description" ), 25, "" );
	}

	/** 
	 * A core method for this plugin where property values are assigned 
	 * This method setup default values for our class  properties 
	 * @since: 1.0
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
	 * The form will also display with an error message if there is any login error
	 * @since: 
	 */
	function show_login_form( $atts ){
		
		/**
		 * Default shortcode attributes 
		 	* echo 				boolean			indicate if the form should be echoed or returned 
		 	* redirect 			string 			the url to redirect to on successful login 
		 	* value_username 	string 			a name that will be prefilled in the username field of the form 
		 *
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
				<div class="login-errors alert alert-warning"><?php echo $this->login_error; ?> </div>
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

	/**
	 * Print CSS for login form
	 * the CSS is used to style the login form generated from shortcode
	 * the CSS styles doesnt apply to wordpress login page i.e wp-login.php
	 * @see: css/login-form.css to edit or tweak the styles  
	 * @since: 1.0
	 *
	 */
	function print_login_css(){
		/* 
		 * bail if there is no shortcode
		 * this var login_form_css is set to true only when there is shortcode in the page
		 * this make sure that our styles are not being printed unnecessarily
		 */
		if( !$this->login_form_css ) return ;
		
		wp_print_styles( "abbey-login-form-css" );
	}

	/**
	 * Send an email to the admin or logged in user at successful attempt 
	 * this method is hooked to wp_login action hook which fires at successful login
	 * @uses: wp_mail
	 * @since: 1.0
	 */
	function email_user( $user_login, $user ){

		// bail when there is an error with the current logged in user //
		if( empty( $user_login ) || is_wp_error( $user ) ) return;

		// set the global user information to the current active user //
		wp_set_current_user( $user->ID );

		//header options that will be passed to wp_mail eg CC, BCC, //
		$headers = array();

		// get the admin email address //
		$admin_email = get_option( "admin_email" );

		// set the reply to header //
		$headers[] = "Reply-To:".get_bloginfo("name"). "'s Admin <".$admin_email.">";

		// set content type of the mail //
		$headers[] = "Content-Type: text/html";

		// sent to admin email and the logged in user email //
		$to = array ( $admin_email, $user->user_email );

		/** Markup of the emal body that will be sent, the markup is in HTML with inline CSS */
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
		//send the email //
		$send = wp_mail ( $to, __( "Login Notice" ), $mail, $headers );
	}

}

new Abbey_Theme_Login();
