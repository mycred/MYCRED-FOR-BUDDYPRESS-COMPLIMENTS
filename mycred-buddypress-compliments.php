<?php
/**
 * Plugin Name: myCRED for BuddyPress Compliments
 * Description: Award or deduct points from users in myCRED for sending compliments via the BuddyPress Compliments plugin.
 * Version: 1.0.2
 * Tags: points, tokens, credit, management, reward, charge, buddpress, buddypress-compliments
 * Author: Gabriel S Merovingi
 * Author URI: http://www.merovingi.com
 * Author Email: support@mycred.me
 * Requires at least: WP 4.0
 * Tested up to: WP 4.6
 * Text Domain: mycred_bp_compliments
 * Domain Path: /lang
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
if ( ! class_exists( 'myCRED_BP_Compliments' ) ) :
	final class myCRED_BP_Compliments {

		// Plugin Version
		public $version             = '1.0.2';

		// Instnace
		protected static $_instance = NULL;

		// Current session
		public $session             = NULL;

		public $slug                = '';
		public $domain              = '';
		public $plugin              = NULL;
		public $plugin_name         = '';
		protected $update_url       = 'http://mycred.me/api/plugins/';

		/**
		 * Setup Instance
		 * @since 1.0
		 * @version 1.0
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		/**
		 * Not allowed
		 * @since 1.0
		 * @version 1.0
		 */
		public function __clone() { _doing_it_wrong( __FUNCTION__, 'Cheatin&#8217; huh?', '1.0' ); }

		/**
		 * Not allowed
		 * @since 1.0
		 * @version 1.0
		 */
		public function __wakeup() { _doing_it_wrong( __FUNCTION__, 'Cheatin&#8217; huh?', '1.0' ); }

		/**
		 * Define
		 * @since 1.0
		 * @version 1.0
		 */
		private function define( $name, $value, $definable = true ) {
			if ( ! defined( $name ) )
				define( $name, $value );
		}

		/**
		 * Require File
		 * @since 1.0
		 * @version 1.0
		 */
		public function file( $required_file ) {
			if ( file_exists( $required_file ) )
				require_once $required_file;
		}

		/**
		 * Construct
		 * @since 1.0
		 * @version 1.0
		 */
		public function __construct() {

			$this->slug        = 'mycred-buddypress-compliments';
			$this->plugin      = plugin_basename( __FILE__ );
			$this->domain      = 'mycred_bp_compliments';
			$this->plugin_name = 'myCRED for BuddyPress Compliments';

			$this->define_constants();
			$this->plugin_updates();

			add_filter( 'mycred_setup_hooks',    array( $this, 'register_hook' ) );
			add_action( 'mycred_init',           array( $this, 'load_textdomain' ) );
			add_action( 'mycred_all_references', array( $this, 'add_badge_support' ) );
			add_action( 'mycred_load_hooks',    'mycred_bp_compliments_load_hook' );

		}

		/**
		 * Define Constants
		 * @since 1.0
		 * @version 1.0
		 */
		public function define_constants() {

			$this->define( 'MYCRED_BP_COMPLIMENTS_VER',  $this->version );
			$this->define( 'MYCRED_BP_COMPLIMENTS_SLUG', $this->slug );
			$this->define( 'MYCRED_DEFAULT_TYPE_KEY',    'mycred_default' );

		}

		/**
		 * Includes
		 * @since 1.0
		 * @version 1.0
		 */
		public function includes() { }

		/**
		 * Load Textdomain
		 * @since 1.0
		 * @version 1.0
		 */
		public function load_textdomain() {

			// Load Translation
			$locale = apply_filters( 'plugin_locale', get_locale(), $this->domain );

			load_textdomain( $this->domain, WP_LANG_DIR . '/' . $this->slug . '/' . $this->domain . '-' . $locale . '.mo' );
			load_plugin_textdomain( $this->domain, false, dirname( $this->plugin ) . '/lang/' );

		}

		/**
		 * Plugin Updates
		 * @since 1.0
		 * @version 1.0
		 */
		public function plugin_updates() {

			add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_plugin_update' ), 390 );
			add_filter( 'plugins_api',                           array( $this, 'plugin_api_call' ), 390, 3 );
			add_filter( 'plugin_row_meta',                       array( $this, 'plugin_view_info' ), 390, 3 );

		}

		/**
		 * Register Hook
		 * @since 1.0
		 * @version 1.0
		 */
		public function register_hook( $installed ) {

			if ( ! function_exists( 'bp_compliments_init' ) ) return $installed;

			$installed['bp-compliments'] = array(
				'title'       => __( 'BuddyPress Compliments', $this->domain ),
				'description' => __( 'Awards %_plural% to users for sending or receiving compliments.', $this->domain ),
				'callback'    => array( 'myCRED_Hook_BP_Compliments' )
			);

			return $installed;

		}

		/**
		 * Add Badge Support
		 * @since 1.0
		 * @version 1.0
		 */
		public function add_badge_support( $references ) {

			if ( ! function_exists( 'bp_compliments_init' ) ) return $references;

			$references['giving_compliment']    = __( 'Giving a Compliment (BuddyPress Compliments)', $this->domain );
			$references['receiving_compliment'] = __( 'Receiving a Compliment (BuddyPress Compliments)', $this->domain );

			return $references;

		}

		/**
		 * Plugin Update Check
		 * @since 1.0
		 * @version 1.0
		 */
		public function check_for_plugin_update( $checked_data ) {

			global $wp_version;

			if ( empty( $checked_data->checked ) )
				return $checked_data;

			$args = array(
				'slug'    => $this->slug,
				'version' => $this->version,
				'site'    => site_url()
			);
			$request_string = array(
				'body'       => array(
					'action'     => 'version', 
					'request'    => serialize( $args ),
					'api-key'    => md5( get_bloginfo( 'url' ) )
				),
				'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' )
			);

			// Start checking for an update
			$response = wp_remote_post( $this->update_url, $request_string );

			if ( ! is_wp_error( $response ) ) {

				$result = maybe_unserialize( $response['body'] );

				if ( is_object( $result ) && ! empty( $result ) )
					$checked_data->response[ $this->slug . '/' . $this->slug . '.php' ] = $result;

			}

			return $checked_data;

		}

		/**
		 * Plugin View Info
		 * @since 1.0
		 * @version 1.0
		 */
		public function plugin_view_info( $plugin_meta, $file, $plugin_data ) {

			if ( $file != $this->plugin ) return $plugin_meta;

			$plugin_meta[] = sprintf( '<a href="%s" class="thickbox" aria-label="%s" data-title="%s">%s</a>',
				esc_url( network_admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . $this->slug .
				'&TB_iframe=true&width=600&height=550' ) ),
				esc_attr( __( 'More information about this plugin', $this->domain ) ),
				esc_attr( $this->plugin_name ),
				__( 'View details', $this->domain )
			);

			return $plugin_meta;

		}

		/**
		 * Plugin New Version Update
		 * @since 1.0
		 * @version 1.0
		 */
		public function plugin_api_call( $result, $action, $args ) {

			global $wp_version;

			if ( ! isset( $args->slug ) || ( $args->slug != $this->slug ) )
				return $result;

			// Get the current version
			$args = array(
				'slug'    => $this->slug,
				'version' => $this->version,
				'site'    => site_url()
			);
			$request_string = array(
				'body'       => array(
					'action'     => 'info', 
					'request'    => serialize( $args ),
					'api-key'    => md5( get_bloginfo( 'url' ) )
				),
				'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' )
			);

			$request = wp_remote_post( $this->update_url, $request_string );

			if ( ! is_wp_error( $request ) )
				$result = maybe_unserialize( $request['body'] );

			return $result;

		}

	}
endif;

function mycred_bp_compliments_plugin() {
	return myCRED_BP_Compliments::instance();
}
mycred_bp_compliments_plugin();

/**
 * Load BP Compliments Hook
 * Finally we need to load the hook class. It is recommended you use the mycred_pre_init
 * action hook because then the class will only load if myCRED is installed and will load
 * in the correct moment. I do recommend you still check if class exists myCRED_Hook in case
 * someone really nuts on customizing myCRED on your website. 
 * @since 1.0
 * @version 1.0.1
 */
if ( ! function_exists( 'mycred_bp_compliments_load_hook' ) ) :
	function mycred_bp_compliments_load_hook() {

		if ( class_exists( 'myCRED_Hook_BP_Compliments' ) || ! function_exists( 'bp_compliments_init' ) ) return;

		class myCRED_Hook_BP_Compliments extends myCRED_Hook {

			/**
			 * Construct
			 */
			function __construct( $hook_prefs, $type = MYCRED_DEFAULT_TYPE_KEY ) {

				parent::__construct( array(
					'id'       => 'bp-compliments',
					'defaults' => array(
						'giving'    => array(
							'creds'     => 0,
							'log'       => '%plural% for giving a compliment',
							'limit'     => '0/x'
						),
						'receiving' => array(
							'creds'     => 0,
							'log'       => '%plural% for receiving compliments',
							'limit'     => '0/x'
						)
					)
				), $hook_prefs, $type );

			}

			/**
			 * Run
			 * This class method is fired of by myCRED when it's time to load all hooks.
			 * It should be used to "hook" into the plugin we want to add support for or the
			 * appropriate WordPress instances. Anything that must be loaded for this hook to work
			 * needs to be called here.
			 * @since 1.0
			 * @version 1.0
			 */
			public function run() {

				add_action( 'bp_compliments_after_save', array( $this, 'new_compliment' ) );

			}

			/**
			 * New Compliment
			 * Not sure but looking at the BuddyPress Compliments plugin, this seems to be the
			 * best place to detect new compliments being given. We get an object to play with that contains
			 * the senders and receives user IDs, which we need. Otherwise, how will we know who to give points to?
			 * @since 1.0
			 * @version 1.0
			 */
			public function new_compliment( $bp_compliments_object ) {

				// Can not award guests
				if ( ! is_user_logged_in() ) return;

				// We start with the person giving the compliment
				if ( $this->prefs['giving']['creds'] != 0 && ! $this->core->exclude_user( $bp_compliments_object->sender_id ) ) {

					// If we are not over the hook limit, award points
					if ( ! $this->over_hook_limit( 'giving', 'giving_compliment', $bp_compliments_object->sender_id ) )
						$this->core->add_creds(
							'giving_compliment',
							$bp_compliments_object->sender_id,
							$this->prefs['giving']['creds'],
							$this->prefs['giving']['log'],
							$bp_compliments_object->receiver_id,
							array( 'ref_type' => 'user' ),
							$this->mycred_type
						);

				}

				// We then finish with the person reciving it
				if ( $this->prefs['receiving']['creds'] != 0 && ! $this->core->exclude_user( $bp_compliments_object->receiver_id ) ) {

					// If we are not over the hook limit, award points
					if ( ! $this->over_hook_limit( 'receiving', 'receiving_compliment', $bp_compliments_object->receiver_id ) )
						$this->core->add_creds(
							'receiving_compliment',
							$bp_compliments_object->receiver_id,
							$this->prefs['receiving']['creds'],
							$this->prefs['receiving']['log'],
							$bp_compliments_object->sender_id,
							array( 'ref_type' => 'user' ),
							$this->mycred_type
						);

				}

			}

			/**
			 * Preferences
			 * If the hook has settings, it has to be added in using this class method.
			 * @since 1.0
			 * @version 1.0
			 */
			public function preferences() {

				$prefs = $this->prefs;

?>
<label class="subheader"><?php _e( 'Giving a Compliment', 'mycred_bp_compliments' ); ?></label>
<ol>
	<li>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'giving' => 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'giving' => 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['giving']['creds'] ); ?>" size="8" /></div>
	</li>
	<li>
		<label for="<?php echo $this->field_id( array( 'giving' => 'limit' ) ); ?>"><?php _e( 'Limit', 'mycred_bp_compliments' ); ?></label>
		<?php echo $this->hook_limit_setting( $this->field_name( array( 'giving' => 'limit' ) ), $this->field_id( array( 'giving' => 'limit' ) ), $prefs['giving']['limit'] ); ?>
	</li>
</ol>
<label class="subheader"><?php _e( 'Log template', 'mycred_bp_compliments' ); ?></label>
<ol>
	<li>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'giving' => 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'giving' => 'log' ) ); ?>" value="<?php echo esc_attr( $prefs['giving']['log'] ); ?>" class="long" /></div>
		<span class="description"><?php echo $this->available_template_tags( array( 'general', 'user' ) ); ?></span>
	</li>
</ol>
<label class="subheader"><?php _e( 'Receiving a Compliment', 'mycred_bp_compliments' ); ?></label>
<ol>
	<li>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'receiving' => 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'receiving' => 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['receiving']['creds'] ); ?>" size="8" /></div>
	</li>
	<li>
		<label for="<?php echo $this->field_id( array( 'receiving' => 'limit' ) ); ?>"><?php _e( 'Limit', 'mycred_bp_compliments' ); ?></label>
		<?php echo $this->hook_limit_setting( $this->field_name( array( 'receiving' => 'limit' ) ), $this->field_id( array( 'receiving' => 'limit' ) ), $prefs['receiving']['limit'] ); ?>
	</li>
</ol>
<label class="subheader"><?php _e( 'Log template', 'mycred_bp_compliments' ); ?></label>
<ol>
	<li>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'receiving' => 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'receiving' => 'log' ) ); ?>" value="<?php echo esc_attr( $prefs['receiving']['log'] ); ?>" class="long" /></div>
		<span class="description"><?php echo $this->available_template_tags( array( 'general', 'user' ) ); ?></span>
	</li>
</ol>
<?php

			}

			/**
			 * Sanitise Preferences
			 * While myCRED does some basic sanitization of the data you submit in the settings,
			 * we do need to handle our hook limits since 1.6. If your settings contain a checkbox (or multiple)
			 * then you should also use this method to handle the submission making sure the checkbox values are
			 * taken care of.
			 * @since 1.0
			 * @version 1.0
			 */
			function sanitise_preferences( $data ) {

				if ( isset( $data['giving']['limit'] ) && isset( $data['giving']['limit_by'] ) ) {
					$limit = sanitize_text_field( $data['giving']['limit'] );
					if ( $limit == '' ) $limit = 0;
					$data['giving']['limit'] = $limit . '/' . $data['giving']['limit_by'];
					unset( $data['giving']['limit_by'] );
				}

				if ( isset( $data['receiving']['limit'] ) && isset( $data['receiving']['limit_by'] ) ) {
					$limit = sanitize_text_field( $data['receiving']['limit'] );
					if ( $limit == '' ) $limit = 0;
					$data['receiving']['limit'] = $limit . '/' . $data['receiving']['limit_by'];
					unset( $data['receiving']['limit_by'] );
				}

				return $data;

			}

		}

	}
endif;
