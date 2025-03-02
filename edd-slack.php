<?php
/**
 * Plugin Name: Easy Digital Downloads - Slack
 * Plugin URL: http://easydigitaldownloads.com/downloads/slack
 * Description: Slack Integration for Easy Digital Downloads
 * Version: 1.1.2
 * Text Domain: edd-slack
 * Author: Sandhills Development, LLC
 * Author URI: https://sandhillsdev.com
 * Contributors: easydigitaldownloads, cklosows, littlerchicken, d4mation
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'EDD_Slack' ) ) {

	/**
	 * Main EDD_Slack class
	 *
	 * @since	  1.0.0
	 */
	class EDD_Slack {

		/**
		 * @var			EDD_Slack $plugin_data Holds Plugin Header Info
		 * @since		1.0.0
		 */
		public $plugin_data;

		/**
		 * @var			EDD_Slack $admin Admin Settings
		 * @since		1.0.0
		 */
		public $admin;

		/**
		 * @var			EDD_Slack $welcome_page Welcome Page
		 * @since		1.0.0
		 */
		public $welcome_page;

		/**
		 * @var			EDD_Slack $oauth_settings SSL-only OAUTH Settings
		 * @since		1.0.0
		 */
		public $oauth_settings;

		/**
		 * @var			EDD_Slack $slack_api EDD Slack API calls
		 * @since		1.0.0
		 */
		public $slack_api;

		/**
		 * @var			EDD_Slack $notification_handler Notifications System
		 * @since		1.0.0
		 */
		public $notification_handler;

		/**
		 * @var			EDD_Slack $notification_integration Integrates into our Notification System. Serves as an example on how to utiliz		t.
		 * @since	  1.0.0
		 */
		public $notification_integration;

		/**
		 * @var			EDD_Slack $notification_triggers Notification Triggers. Serves as an example on how to Trigger Notifications
		 * @since		1.0.0
		 */
		public $notification_triggers;

		/**
		 * @var			EDD_Slack $slack_rest_api holds our WP REST API endpoints for interacting with a Slack App
		 * @since		1.0.0
		 */
		public $slack_rest_api;

		/**
		 * @var			EDD_Slack $admin_errors Stores all our Admin Errors to fire at once
		 * @since		1.0.0
		 */
		private $admin_errors;

		/**
		 * @var			EDD_Slack $integration_errors Stores all our Integration Errors to fire at once
		 * @since		1.0.0
		 */
		private $integration_errors;

		/**
		 * Get active instance
		 *
		 * @access	  public
		 * @since	  1.0.0
		 * @return	  object self::$instance The one true EDD_Slack
		 */
		public static function instance() {

			static $instance = null;

			if ( null === $instance ) {
				$instance = new static();
			}

			return $instance;

		}

		protected function __construct() {

			$this->setup_constants();
			$this->load_textdomain();

			// Register our CSS/JS for the whole plugin
			add_action( 'init', array( $this, 'register_scripts' ) );

			// Handle licensing
			if ( class_exists( 'EDD_License' ) ) {
				$license = new EDD_License( __FILE__, 'Slack', EDD_Slack_VER, $this->plugin_data['Author'], null, null, 980982 );
			}

			if ( version_compare( get_bloginfo( 'version' ), '4.4' ) < 0 ) {

				$this->admin_errors[] = sprintf( _x( '%s requires v%s of %s or higher to be installed!', 'Outdated Dependency Error', 'edd-slack' ), '<strong>' . $this->plugin_data['Name'] . '</strong>', '4.4', '<a href="' . admin_url( 'update-core.php' ) . '"><strong>WordPress</strong></a>' );

				if ( ! has_action( 'admin_notices', array( $this, 'admin_errors' ) ) ) {
					add_action( 'admin_notices', array( $this, 'admin_errors' ) );
				}

			}

			if ( defined( 'EDD_VERSION' )
				&& ( version_compare( EDD_VERSION, '2.8' ) < 0 ) ) {

				$this->admin_errors[] = sprintf( _x( '%s requires v%s of %s or higher to be installed!', 'Outdated Dependency Error', 'edd-slack' ), '<strong>' . $this->plugin_data['Name'] . '</strong>', '2.8', '<a href="//wordpress.org/plugins/easy-digital-downloads/" target="_blank"><strong>Easy Digital Downloads</strong></a>' );

				if ( ! has_action( 'admin_notices', array( $this, 'admin_errors' ) ) ) {
					add_action( 'admin_notices', array( $this, 'admin_errors' ) );
				}

			}

			if ( has_action( 'admin_notices', array( $this, 'admin_errors' ) ) ) {
				return false;
			}

			// Load this first so that we have the ability to use it during Upgrade Routines
			require_once EDD_Slack_DIR . '/core/slack/class-edd-slack-api.php';
			$this->slack_api = new EDD_Slack_API();

			// Don't try to run upgrade routines unless we're on the Backend
			// Less hitting the DB for a version number as well as if there are noticies to show, it will be more apparent as to what needs done by the User
			// If they have hit the Backend via updating within WP this will check right away
			// If they do it via SFTP, it won't until the next time they load the Dashboard
			// Since I don't want literally every interaction within WP to check the Database for this, this is how it will be for now
			if ( is_admin() ) {

				$this->upgrade_routine();

			}

			$this->require_necessities();

		}

		/**
		 * Setup plugin constants
		 *
		 * @access	  private
		 * @since	  1.0.0
		 * @return	  void
		 */
		private function setup_constants() {

			// WP Loads things so weird. I really want this function.
			if ( ! function_exists( 'get_plugin_data' ) ) {
				require_once ABSPATH . '/wp-admin/includes/plugin.php';
			}

			// Only call this once, accessible always
			$this->plugin_data = get_plugin_data( __FILE__ );

			if ( ! defined( 'EDD_Slack_VER' ) ) {
				// Plugin version
				define( 'EDD_Slack_VER', '1.1.2' );
			}

			if ( ! defined( 'EDD_Slack_DIR' ) ) {
				// Plugin path
				define( 'EDD_Slack_DIR', plugin_dir_path( __FILE__ ) );
			}

			if ( ! defined( 'EDD_Slack_URL' ) ) {
				// Plugin URL
				define( 'EDD_Slack_URL', plugin_dir_url( __FILE__ ) );
			}

			if ( ! defined( 'EDD_Slack_FILE' ) ) {
				// Plugin File
				define( 'EDD_Slack_FILE', __FILE__ );
			}

		}

		/**
		 * Internationalization
		 *
		 * @access	  private
		 * @since	  1.0.0
		 * @return	  void
		 */
		private function load_textdomain() {

			// Set filter for language directory
			$lang_dir = EDD_Slack_DIR . '/languages/';
			$lang_dir = apply_filters( 'edd_slack_languages_directory', $lang_dir );

			// Traditional WordPress plugin locale filter
			$locale = apply_filters( 'plugin_locale', get_locale(), 'edd-slack' );
			$mofile = sprintf( '%1$s-%2$s.mo', 'edd-slack', $locale );

			// Setup paths to current locale file
			$mofile_local   = $lang_dir . $mofile;
			$mofile_global  = WP_LANG_DIR . '/' . 'edd-slack' . '/' . $mofile;

			if ( file_exists( $mofile_global ) ) {
				// Look in global /wp-content/languages/edd-slack/ folder
				// This way translations can be overridden via the Theme/Child Theme
				load_textdomain( 'edd-slack', $mofile_global );
			}
			else if ( file_exists( $mofile_local ) ) {
				// Look in local /wp-content/plugins/edd-slack/languages/ folder
				load_textdomain( 'edd-slack', $mofile_local );
			}
			else {
				// Load the default language files
				load_plugin_textdomain( 'edd-slack', false, $lang_dir );
			}

		}

		/**
		 * Run any appropriate Upgrade Routines
		 * If the upgrade requires User Interaction, it forwards the version to the pending_upgrade() method
		 *
		 * @access		private
		 * @since		1.1.0
		 * @return		bool|void Normally returns nothing, but will bail if appropriate
		 */
		private function upgrade_routine() {

			$last_upgrade = edd_get_option( 'slack_last_upgrade', false );

			// If nothing is set, assume they've never done an upgrade
			if ( ! $last_upgrade ) {
				$last_upgrade = '1.0.0';
			}

			if ( false === strpos( $last_upgrade, 'pending' ) ) {
				if ( version_compare( $last_upgrade, '1.1.0' ) < 0 ) {

					$oauth_token = edd_get_option( 'slack_app_oauth_token', false );

					if ( $oauth_token && '-1' !== $oauth_token ) {

						// Clear out values so Slack doesn't try to send Interactive Notifications (Or other related things) and fail
						$this->slack_api->revoke_oauth_token();
						edd_update_option( 'slack_app_oauth_token', '-1' );
						edd_delete_option( 'slack_app_has_client_scope' );

						// Set to pending since we need the User to re-link the Slack App
						$last_upgrade = 'pending-1.1.0';

					} else {
						$last_upgrade = '1.1.0';
					}
				}

				edd_update_option( 'slack_last_upgrade', $last_upgrade );
			}

			// Run any Pending routines
			if ( false  !== strpos( $last_upgrade, 'pending' ) ) {

				$pending_version = str_replace( 'pending-', '', $last_upgrade );

				$finished_pending = $this->pending_upgrade( $pending_version );

				// We're pending, this needs user interaction before we can proceed
				if ( ! $finished_pending ) {
					return false;
				}

			}

		}

		/**
		 * Check to see if Pending Upgrades have been completed by the User
		 *
		 * @param		string  $pending_version Upgrade that is Pending
		 *
		 * @access		private
		 * @since		1.1.0
		 * @return		boolean Whether the Pending Upgrade has been completed or not
		 */
		private function pending_upgrade( $pending_version ) {

			if ( $pending_version == '1.1.0' ) {

				$oauth_token = edd_get_option( 'slack_app_oauth_token', false );

				if ( ( $oauth_token && $oauth_token !== '-1' ) ||
			   ( ! isset( $_GET['error'] ) && isset( $_GET['token_type'] ) && isset( $_GET['section'] ) && $_GET['section'] == 'edd-slack-settings' ) ) {

					edd_update_option( 'slack_last_upgrade', $pending_version );

					return true;

				}
				else {

					$this->admin_errors[] = sprintf( _x( '%s v%s requires additional permissions for Interactive Notifications and Slash Commands. You will need to re-link your Slack App %s.', 'OAUTH Token Needs Updating v1.1.0', 'edd-slack' ), '<strong>' . $this->plugin_data['Name'] . '</strong>', $pending_version, '<a href="' . admin_url( 'edit.php?post_type=download&page=edd-settings&tab=extensions&section=edd-slack-settings' ) . '">here</a>' );

					add_action( 'admin_notices', array( $this, 'admin_errors' ) );

				}

			}

			return false;

		}

		/**
		 * Include different aspects of the Plugin
		 *
		 * @access	  private
		 * @since	  1.0.0
		 * @return	  void
		 */
		private function require_necessities() {

			if ( is_admin() ) {

				require_once EDD_Slack_DIR . '/core/admin/class-edd-slack-welcome.php';
				$this->welcome_page = new EDD_Slack_Welcome();

				require_once EDD_Slack_DIR . '/core/admin/class-edd-slack-admin.php';
				$this->admin = new EDD_Slack_Admin();

				if ( is_ssl() ) {

					require_once EDD_Slack_DIR . '/core/ssl-only/class-edd-slack-app-oauth-settings.php';
					$this->oauth_settings = new EDD_Slack_OAUTH_Settings();

				}

			}

			require_once EDD_Slack_DIR . '/core/notifications/class-edd-slack-notification-handler.php';
			$this->notification_handler = new EDD_Slack_Notification_Handler();

			require_once EDD_Slack_DIR . '/core/slack/class-edd-slack-notification-integration.php';
			$this->notification_integration = new EDD_Slack_Notification_Integration();

			require_once EDD_Slack_DIR . '/core/slack/class-edd-slack-notification-triggers.php';
			$this->notification_triggers = new EDD_Slack_Notification_Triggers();

			// Include Bundled Integrations with this Plugin
			// These also serve as an example of how to tie-in to this Plugin and utilize its functionality

			// If Comments are Enabled for Downloads
			if ( post_type_supports( 'download', 'comments' ) ) {
				require_once EDD_Slack_DIR . '/core/integrations/edd-comments/class-edd-slack-comments.php';
			}

			// If EDD Software Licensing is Active
			if ( class_exists( 'EDD_Software_Licensing' ) ) {

				if ( defined( 'EDD_SL_VERSION' ) &&
				  version_compare( EDD_SL_VERSION, '3.4.12' ) >= 0 ) {

					require_once EDD_Slack_DIR . '/core/integrations/edd-software-licensing/class-edd-slack-software-licensing.php';

				}
				else {

					$this->integration_errors[] = sprintf( _x( '%s includes features which integrate with %s, but v%s or greater of %s is required.', 'Outdated Integration Error', 'edd-slack' ), '<strong>' . $this->plugin_data['Name'] . '</strong>', '<a href="' . admin_url( 'update-core.php' ) . '"><strong>Easy Digital Downloads - Software Licenses</strong></a>', '3.4.12', '<a href="' . admin_url( 'update-core.php' ) . '"><strong>Easy Digital Downloads - Software Licenses</strong></a>' );

				}

			}

			// If EDD FES is Active
			if ( class_exists( 'EDD_Front_End_Submissions' ) ) {

				if ( defined( 'fes_plugin_version' ) &&
				  version_compare( fes_plugin_version, '2.4.2' ) >= 0 ) {

					require_once EDD_Slack_DIR . '/core/integrations/edd-frontend-submissions/class-edd-slack-frontend-submissions.php';

				}
				else {

					$this->integration_errors[] = sprintf( _x( '%s includes features which integrate with %s, but v%s or greater of %s is required.', 'Outdated Integration Error', 'edd-slack' ), '<strong>' . $this->plugin_data['Name'] . '</strong>', '<a href="' . admin_url( 'update-core.php' ) . '"><strong>Easy Digital Downloads - Frontend Submissions</strong></a>', '2.4.2', '<a href="' . admin_url( 'update-core.php' ) . '"><strong>Easy Digital Downloads - Frontend Submissions</strong></a>' );

				}

			}

			// If EDD Commissions is Active
			if ( defined( 'EDD_COMMISSIONS_VERSION' ) ) {

				if ( version_compare( EDD_COMMISSIONS_VERSION, '3.2.10' ) >= 0 ) {

					require_once EDD_Slack_DIR . '/core/integrations/edd-commissions/class-edd-slack-commissions.php';

				}
				else {

					$this->integration_errors[] = sprintf( _x( '%s includes features which integrate with %s, but v%s or greater of %s is required.', 'Outdated Integration Error', 'edd-slack' ), '<strong>' . $this->plugin_data['Name'] . '</strong>', '<a href="' . admin_url( 'update-core.php' ) . '"><strong>Easy Digital Downloads - Commissions</strong></a>', '3.2.10', '<a href="' . admin_url( 'update-core.php' ) . '"><strong>Easy Digital Downloads - Commissions</strong></a>' );

				}

			}

			// If EDD Purchase Limit is Active
			if ( class_exists( 'EDD_Purchase_Limit' ) ) {

				if ( defined( 'EDD_PURCHASE_LIMIT_VERSION' ) &&
				  version_compare( EDD_PURCHASE_LIMIT_VERSION, '1.2.16' ) >= 0 ) {

					require_once EDD_Slack_DIR . '/core/integrations/edd-purchase-limit/class-edd-slack-purchase-limit.php';

				}
				else {

					$this->integration_errors[] = sprintf( _x( '%s includes features which integrate with %s, but v%s or greater of %s is required.', 'Outdated Integration Error', 'edd-slack' ), '<strong>' . $this->plugin_data['Name'] . '</strong>', '<a href="' . admin_url( 'update-core.php' ) . '"><strong>Easy Digital Downloads - Purchase Limit</strong></a>', '1.2.16', '<a href="' . admin_url( 'update-core.php' ) . '"><strong>Easy Digital Downloads - Purchase Limit</strong></a>' );

				}

			}

			// If EDD Reviews is Active
			if ( class_exists( 'EDD_Reviews' ) ) {

				if ( version_compare( edd_reviews()->version, '2.0.5' ) >= 0 ) {

					require_once EDD_Slack_DIR . '/core/integrations/edd-reviews/class-edd-slack-reviews.php';

				}
				else {

					$this->integration_errors[] = sprintf( _x( '%s includes features which integrate with %s, but v%s or greater of %s is required.', 'Outdated Integration Error', 'edd-slack' ), '<strong>' . $this->plugin_data['Name'] . '</strong>', '<a href="' . admin_url( 'update-core.php' ) . '"><strong>Easy Digital Downloads - Reviews</strong></a>', '2.0.5', '<a href="' . admin_url( 'update-core.php' ) . '"><strong>Easy Digital Downloads - Reviews</strong></a>' );

				}

			}

			// If EDD Fraud Monitor is Active
			if ( defined( 'EDD_FM_VERSION' ) ) {

				if ( version_compare( EDD_FM_VERSION, '1.0.3' ) >= 0 ) {

					require_once EDD_Slack_DIR . '/core/integrations/edd-fraud-monitor/class-edd-slack-fraud-monitor.php';

				}
				else {

					$this->integration_errors[] = sprintf( _x( '%s includes features which integrate with %s, but v%s or greater of %s is required.', 'Outdated Integration Error', 'edd-slack' ), '<strong>' . $this->plugin_data['Name'] . '</strong>', '<a href="' . admin_url( 'update-core.php' ) . '"><strong>Easy Digital Downloads - Fraud Monitor</strong></a>', '1.0.3', '<a href="' . admin_url( 'update-core.php' ) . '"><strong>Easy Digital Downloads - Fraud Monitor</strong></a>' );

				}

			}

			// If EDD Recurring is Active
			if ( defined( 'EDD_RECURRING_VERSION' ) ) {

				if ( version_compare( EDD_RECURRING_VERSION, '2.6.9' ) >= 0 ) {

					require_once EDD_Slack_DIR . '/core/integrations/edd-recurring/class-edd-slack-recurring.php';

				}
				else {

					$this->integration_errors[] = sprintf( _x( '%s includes features which integrate with %s, but v%s or greater of %s is required.', 'Outdated Integration Error', 'edd-slack' ), '<strong>' . $this->plugin_data['Name'] . '</strong>', '<a href="' . admin_url( 'update-core.php' ) . '"><strong>Easy Digital Downloads - Recurring Payments</strong></a>', '2.6.9', '<a href="' . admin_url( 'update-core.php' ) . '"><strong>Easy Digital Downloads - Recurring Payments</strong></a>' );

				}

			}

			// Output all Integration-related Errors just above the Notification Repeater
			if ( ! empty( $this->integration_errors ) ) {

				add_action( 'edd_slack_before_repeater', array( $this, 'integration_errors' ) );

			}

			// SSL anywhere, not just Admin
			if ( is_ssl() ) {

				$oauth_token = edd_get_option( 'slack_app_oauth_token', false );

				// If we've got a linked Slack App
				if ( $oauth_token && $oauth_token !== '-1' ) {

					require_once EDD_Slack_DIR . '/core/ssl-only/class-edd-slack-ssl-rest.php';
					$this->slack_rest_api = new EDD_Slack_SSL_REST();

				}

				// If we've linked our Slack App previously or just now
				// This file is loaded at `plugins_loaded` and the data is saved at `init`, so we can't reliably check on that first load
				if ( ( $oauth_token && $oauth_token !== '-1' ) ||
			   ( ! isset( $_GET['error'] ) && isset( $_GET['token_type'] ) ) ) {

					require_once EDD_Slack_DIR . '/core/ssl-only/interactive-notifications/class-edd-slack-app-interactive-notification-settings.php';

					require_once EDD_Slack_DIR . '/core/ssl-only/slash-commands/class-edd-slack-app-slash-command-settings.php';

					// This file does mostly things on the Admin-side, but it runs Filters that need access to the Frontend based on results from the Admin-side
					// Primarily, replacing `#general` as appropriate
					// Since this is done within Interactive Notifications, we need the file to be loaded here
					require_once EDD_Slack_DIR . '/core/ssl-only/slack-invites/class-edd-slack-app-invites-settings.php';

				}

				// If we've been granted Client Scope previously or just now
				// This file is loaded at `plugins_loaded` and the data is saved at `init`, so we can't reliably check on that first load
				if ( edd_get_option( 'slack_app_has_client_scope', false ) ||
				   ( ! isset( $_GET['error'] ) && isset( $_GET['token_type'] ) && $_GET['token_type'] == 'team_invites' ) ) {

					// Here we actually send out the Invites if appropriate
					require_once EDD_Slack_DIR . '/core/ssl-only/slack-invites/class-edd-slack-app-invites.php';

				}

				// If Comments are Enabled for Downloads
				if ( post_type_supports( 'download', 'comments' ) ) {
					require_once EDD_Slack_DIR . '/core/ssl-only/integrations/edd-comments/class-edd-slack-app-comments.php';
				}

				// If EDD FES is Active
				if ( class_exists( 'EDD_Front_End_Submissions' ) ) {
					require_once EDD_Slack_DIR . '/core/ssl-only/integrations/edd-frontend-submissions/class-edd-slack-app-frontend-submissions.php';
				}

				// If EDD Fraud Monitor is Active
				if ( defined( 'EDD_FM_VERSION' ) ) {
					require_once EDD_Slack_DIR . '/core/ssl-only/integrations/edd-fraud-monitor/class-edd-slack-app-fraud-monitor.php';
				}

			}

		}

		/**
		 * Grab EDD Slack Notification Repeater Fields
		 *
		 * @param	  boolean $query Whether to run through Database Queries or not
		 *
		 * @access	  public
		 * @since	  1.0.0
		 * @return	  array   EDD Settings API Array
		 */
		public function get_notification_fields( $query = true ) {

			$downloads_array = array();
			$discount_codes_array = array();

			// Only run through all these queries when we need them
			if ( $query ) {

				$base_args = array(
					'posts_per_page' => -1,
					'orderby' => 'title',
					'order' => 'ASC',
				);

				$downloads = get_posts( array(
					'post_type' => 'download',
				) + $base_args );

				$downloads_query = wp_list_pluck( $downloads, 'post_title', 'ID' );

				// Sort before adding
				asort( $downloads_query );

				foreach ( $downloads_query as $download_id => $download_name ) {

					// If there's no variable Prices, continue
					if ( ! edd_has_variable_prices( $download_id ) ) {
						$downloads_array[ $download_id ] = $download_name;
						continue;
					}

					$variations = edd_get_variable_prices( $download_id );

					$downloads_array[ $download_id ] = $download_name . ' - ' . _x( 'All Variations', 'All Variations Option Text', 'edd-slack' );

					// Apparently edd_get_variable_prices() can't be trusted to get Price IDs
					// After the first Price ID it just has an empty String
					// Thankfully, EDD reliably starts its index for those at 1
					$price_id = 1;
					foreach ( $variations as $variation ) {

						$downloads_array[ $download_id . '-' . $price_id ] = $download_name . ' - ' . edd_get_price_option_name( $download_id, $price_id );

						$price_id++;

					}

				}

				$discount_codes = get_posts( array(
					'post_type' => 'edd_discount',
					'post_status'	=> array( 'active', 'inactive', 'expired' ),
				) + $base_args );

				foreach ( $discount_codes as $discount_code ) {

					// Post Meta is the Key, so wp_list_pluck() won't work here
					$code = get_post_meta( $discount_code->ID, '_edd_discount_code', true );
					$discount_codes_array[ $code ] = $discount_code->post_title . ' - ' . $code;

				}

			}

			return apply_filters( 'edd_slack_notification_fields', array(
				'slack_post_id' => array(
					'type' => 'hook',
					'std' => '',
					'field_class' => array(
						'edd-slack-field',
						'edd-slack-post-id',
					),
				),
				'admin_title' => array(
					'desc' => '',
					'label' => __( 'Indentifier for this Notification', 'edd-slack' ),
					'type' => 'text',
					'readonly' => false,
					'placeholder' => __( 'New Slack Notification', 'edd-slack' ),
					'std' => '',
					'field_class' => 'edd-slack-field',
					'label_tooltip_title' => __( 'Indentifier for this Notification', 'edd-slack' ),
					'label_tooltip_desc'  => __( 'Helps distinguish Notifications from one another on the Settings Screen. If left blank, your Notification will be labeled &ldquo;New Slack Notification&rdquo;.', 'edd-slack' ),
				),
				'trigger' => array(
					'desc' => '',
					'label' => __( 'Slack Trigger', 'edd-slack' ),
					'type' => 'select',
					'multiple' => false,
					'field_class' => array(
						'edd-slack-chosen',
						'edd-slack-field',
						'edd-slack-trigger',
						'required',
					),
					'options' => array(
						'' => _x( '-- Select a Slack Trigger --', 'Slack Trigger Default Label', 'edd-slack' ),
					 ) + $this->get_slack_triggers(),
					'placeholder' => _x( '-- Select a Slack Trigger --', 'Slack Trigger Default Label', 'edd-slack' ),
					'std' => '',
				),
				'download' => array(
					'desc' => '',
					'label' => edd_get_label_plural(),
					'type' => 'select',
					'multiple' => true,
					'field_class' => array(
						'edd-slack-chosen',
						'edd-slack-field',
						'edd-slack-download',
						'edd-slack-conditional',
						'edd_complete_purchase',
						'edd_discount_code_applied',
						'edd_failed_purchase',
						'required',
					),
					'options' => array(
						'all' => sprintf( _x( 'All %s', 'All items in a Select Field', 'edd-slack' ), edd_get_label_plural() ),
					) + $downloads_array,
					'placeholder' => sprintf( _x( '-- Select %s --', 'Select Field Default', 'edd-slack' ), edd_get_label_plural() ),
					'std' => '',
				),
				'exclude_download' => array(
					'desc' => '',
					'label' => sprintf( __( 'Exclude %s (Optional)', 'edd-slack' ), edd_get_label_plural() ),
					'type' => 'select',
					'multiple' => true,
					'field_class' => array(
						'edd-slack-chosen',
						'edd-slack-field',
						'edd-slack-exclude-download', // This is a conditional field, but not in the same way as others. It will be hidden/shown based on the value of .edd-slack-download
					),
					'options' => $downloads_array,
					'placeholder' => sprintf( _x( '-- Select %s --', 'Select Field Default', 'edd-slack' ), edd_get_label_plural() ),
					'std' => '',
				),
				'discount_code' => array(
					'desc' => '',
					'label' => _x( 'Discount Code', 'Discount Code Field Label', 'edd-slack' ),
					'type' => 'select',
					'multiple' => false,
					'field_class' => array(
						'edd-slack-chosen',
						'edd-slack-field',
						'edd-slack-download',
						'edd-slack-conditional',
						'edd_discount_code_applied',
						'required',
					),
					'options' => array(
						'' => _x( '-- Select Discount Code --', 'Discount Code Field Default', 'edd-slack'  ),
						'all' => _x( 'All Discount Codes', 'All Discount Codes Text', 'edd-slack' ),
					) + $discount_codes_array,
					'placeholder' => _x( '-- Select Discount Code --', 'Discount Code Field Default', 'edd-slack'  ),
					'std' => '',
				),
				'replacement_hints' => array(
					'type' => 'hook',
					'std' => '',
				),
				'message_pretext' => array(
					'desc' => '',
					'type'  => 'text',
					'label' => __( 'Message Pre-text (Optional)', 'edd-slack' ),
					'readonly' => false,
					'placeholder' => '',
					'std' => '',
					'field_class' => 'edd-slack-field',
					'label_tooltip_title' => __( 'Message Pre-text', 'edd-slack' ),
					'label_tooltip_desc'  => __( 'Shows directly below Username and above the Title/Message.', 'edd-slack' ),
				),
				'message_title'   => array(
					'desc' => '',
					'type'  => 'text',
					'label' => __( 'Message Title (Optional)', 'edd-slack' ),
					'readonly' => false,
					'placeholder' => '',
					'std' => '',
					'field_class' => 'edd-slack-field',
					'label_tooltip_title' => __( 'Message Title', 'edd-slack' ),
					'label_tooltip_desc'  => __( 'If left blank this will default to the Notification Identifier.', 'edd-slack' ),
				),
				'message_text'	=> array(
					'desc' => '',
					'type'  => 'textarea',
					'label' => __( 'Message (Optional)', 'edd-slack' ),
					'std' => '',
					'field_class' => 'edd-slack-field',
				),
				'webhook'		 => array(
					'desc' => '',
					'type'  => 'text',
					'label' => __( 'Slack Webhook URL (Optional)', 'edd-slack' ),
					'readonly' => false,
					'placeholder' => edd_get_option( 'edd_slack_webhook' ),
					'args'  => array(
						'label'		=> '<p class="description">' .
						__( 'You can override the above Webhook URL here.', 'edd-slack' ) .
						'</p>',
					),
					'std' => '',
					'field_class' => array(
						'edd-slack-field',
						'edd-slack-webhook-url',
					),
					'label_tooltip_title' => __( 'Slack Webhook URL', 'edd-slack' ),
					'label_tooltip_desc'  => __( 'This overrides the Default Webhook URL. This can be useful if you want a specific Notification to send to another Slack Team entirely.', 'edd-slack' ),
				),
				'channel'		 => array(
					'desc' => '',
					'type'  => 'text',
					'label' => __( 'Slack Channel (Optional)', 'edd-slack' ),
					'readonly' => false,
					'placeholder' => __( 'Webhook default', 'edd-slack' ),
					'std' => '',
					'field_class' => 'edd-slack-field',
					'label_tooltip_title' => __( 'Slack Channel', 'edd-slack' ),
					'label_tooltip_desc'  => __( 'This overrides the Default Channel defined by the Webhook URL. Notifications can be sent to individual Users instead by entering their Username like so: <code>@&laquo;username&raquo;</code>.', 'edd-slack' ),
				),
				'username'		=> array(
					'desc' => '',
					'type'  => 'text',
					'label' => __( 'Username (Optional)', 'edd-slack' ),
					'readonly' => false,
					'placeholder' => get_bloginfo( 'name' ),
					'std' => '',
					'field_class' => 'edd-slack-field',
					'label_tooltip_title' => __( 'Username', 'edd-slack' ),
					'label_tooltip_desc'  => sprintf( __( 'This controls who the Notification appears to be from. It does not have to be a valid User in your Slack Team. This will default to &ldquo;%s&rdquo;.', 'edd-slack' ), get_bloginfo( 'name' ) ),
				),
				'icon'			=> array(
					'desc' => '',
					'type'  => 'text',
					'label' => __( 'Icon Emoji or Image URL (Optional)', 'edd-slack' ),
					'readonly' => false,
					'placeholder' => __( 'Webhook default', 'edd-slack' ),
					'std' => '',
					'field_class' => 'edd-slack-field',
					'label_tooltip_title' => __( 'Icon Emoji or Image URL', 'edd-slack' ),
					'label_tooltip_desc'  => __( 'This accepts an Emoji (Example: <code>:rocket:</code>) or an Image URL. You can even use Custom Emojis defined within your Slack Team. If left empty, it will default to the Emoji or Image defined by the Webhook URL.', 'edd-slack' ),
				),
				'color'		  => array(
					'desc' => '',
					'type'  => 'color',
					'label' => __( 'Color', 'edd-slack' ),
					'std' => '#3299BB',
					'field_class' => 'edd-slack-field',
					'label_tooltip_title' => __( 'Color', 'edd-slack' ),
					'label_tooltip_desc'  => __( 'Shows next to Message Title and Message.', 'edd-slack' ),
				),
			) );

		}

		/**
		 * Returns a List of EDD Slack Triggers and their EDD Actions
		 *
		 * @access	  public
		 * @since	  1.0.0
		 * @return	  array EDD Slack Triggers
		 */
		public function get_slack_triggers() {

			$triggers = apply_filters( 'edd_slack_triggers', array(
				'edd_complete_purchase' => _x( 'Purchase Complete', 'Purchase Complete Trigger Label', 'edd-slack' ),
				'edd_failed_purchase' => _x( 'Purchase Failed', 'Purchase Failed Trigger Label', 'edd-slack' ),
				'edd_discount_code_applied' => _x( 'Discount Code Applied', 'Discount Code Applied Trigger Label', 'edd-slack' ),
				'edd_insert_user' => _x( 'New User Registration via EDD', 'New User Registration Trigger Label', 'edd-slack' ),
			) );

			asort( $triggers );

			return $triggers;

		}

		/**
		 * Register our CSS/JS to use later
		 *
		 * @access	  public
		 * @since	  1.0.0
		 * @return	  void
		 */
		public function register_scripts() {

			wp_register_style(
				'edd-slack-admin',
				EDD_Slack_URL . 'assets/css/admin.css',
				null,
				defined( 'WP_DEBUG' ) && WP_DEBUG ? time() : EDD_Slack_VER
			);

			wp_register_script(
				'edd-slack-admin',
				EDD_Slack_URL . 'assets/js/admin.js',
				array( 'jquery', 'jquery-effects-core', 'jquery-effects-highlight' ),
				defined( 'WP_DEBUG' ) && WP_DEBUG ? time() : EDD_Slack_VER,
				true
			);

			wp_localize_script(
				'edd-slack-admin',
				'eddSlack',
				apply_filters( 'edd_slack_localize_admin_script', array() )
			);

		}

		/**
		 * Utility Function to insert one Array into another at a specified Index. Useful for the Notification Repeater Field's Filter
		 *
		 * @param	  array   &$array	  Array being modified. This passes by reference.
		 * @param	  integer $index		Insertion Index. Even if it is an associative array, give a numeric index. Determine it by doing a foreach() until you hit your desired placement and then break out of the loop.
		 * @param	  array   $insert_array Array being Inserted at the Index
		 *
		 * @access	  public
		 * @since	  1.0.0
		 * @return	  void
		 */
		public function array_insert( &$array, $index, $insert_array ) {

			// First half before the cut-off for the splice
			$first_array = array_splice( $array, 0, $index );

			// Merge this with the inserted array and the last half of the splice
			$array = array_merge( $first_array, $insert_array, $array );

		}

		/**
		 * Show admin errors.
		 *
		 * @access	  public
		 * @since	  1.0.0
		 * @return	  HTML
		 */
		public function admin_errors() {
			?>
			<div class="error">
				<?php foreach ( $this->admin_errors as $notice ) : ?>
					<p>
						<?php echo $notice; ?>
					</p>
				<?php endforeach; ?>
			</div>
			<?php
		}

		/**
		 * Show Integration errors.
		 *
		 * @access	  public
		 * @since	  1.0.0
		 * @return	  HTML
		 */
		public function integration_errors() {
			?>
			<div class="integration-error">
				<?php foreach ( $this->integration_errors as $notice ) : ?>
					<p>
						<?php echo $notice; ?>
					</p>
				<?php endforeach; ?>
			</div>
			<?php
		}

		/**
		 * Allow redirecting to our Welcome Page on Activation
		 *
		 * @access	  public
		 * @since	  1.0.0
		 * @return	  void
		 */
		public static function activation() {

			set_transient( '_edd_slack_activation_redirect', true, 30 );

		}

	}

} // End Class Exists Check

/**
 * The main function responsible for returning the one true EDD_Slack
 * instance to functions everywhere
 *
 * @since	  1.0.0
 * @return	  \EDD_Slack The one true EDD_Slack
 */
add_action( 'plugins_loaded', 'EDD_Slack_load' );
function EDD_Slack_load() {

	if ( ! class_exists( 'Easy_Digital_Downloads' ) ) {

		if ( ! class_exists( 'EDD_Extension_Activation' ) ) {
			require_once 'includes/class.extension-activation.php';
		}

		$activation = new EDD_Extension_Activation( plugin_dir_path( __FILE__ ), basename( __FILE__ ) );
		$activation = $activation->run();

	}
	else {

		require_once __DIR__ . '/core/edd-slack-functions.php';
		EDDSLACK();

	}

}

register_activation_hook( __FILE__, array( 'EDD_Slack', 'activation' ) );
