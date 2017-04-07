<?php
/**
 * Sends Slack Team Invites as appropriate
 *
 * @since 1.1.0
 *
 * @package EDD_Slack
 * @subpackage EDD_Slack/core/ssl-only/slack-invites
 */

defined( 'ABSPATH' ) || die();

class EDD_Slack_Invites {
	
	/**
	 * EDD_Slack_Invites constructor.
	 *
	 * @since 1.0.0
	 */
	function __construct() {
			
		// Check to see if Customer Invites are enabled
		if ( edd_get_option( 'slack_app_team_invites_customer', false ) ) {

			// Adds a Checkbox to the Purchase Form for Customers to be added to the Slack Team
			add_action( 'edd_purchase_form_before_submit', array( $this, 'customers_slack_invite_checkbox' ) );

			// Checks if a Customer should be added to a Slack Team, then sends off the Invite
			add_action( 'edd_complete_purchase', array( $this, 'add_customer_to_slack_team_via_form' ) );

		}

		// Check to see if Vendor Invites are enabled
		if ( class_exists( 'EDD_Front_End_Submissions' ) &&
		   edd_get_option( 'slack_app_team_invites_vendor', false ) ) {

			// Adds a Checkbox to the Vendor Submission Form for Vendors to be added to the Slack Team
			add_filter( 'fes_load_registration_form_fields', array( $this, 'vendors_slack_invite_checkbox' ), 10, 2 );
			
			// Checks if a Vendor should be added to a Slack Team
			add_action( 'edd_post_insert_vendor', array( $this, 'add_vendor_to_slack_team_via_form' ), 10, 2 );

		}
		
	}
	
	/**
	 * Adds a Checkbox to the Purchase Form for Customers to be added to the Slack Team
	 * 
	 * @access		public
	 * @since		1.1.0
	 * @return		void
	 */
	public function customers_slack_invite_checkbox() {
		
		// Defaults to having the checkbox "checked". Allows altering the functionality to be Opt-in rather than Opt-out
		$checked = apply_filters( 'slack_app_team_invites_customer_default', 1 );
		
		?>
		
		<fieldset id="edd_slack_send_customer_team_invite_fieldset">
			<div class="edd-slack-send-customer-team-invite">
				<input name="edd_slack_send_customer_team_invite" type="checkbox" id="edd_slack_send_customer_team_invite" value="1" <?php checked( $checked, 1, true ); ?>/>
				<label for="edd_slack_send_customer_team_invite">
					test
				</label>
			</div>
		</fieldset>

	<?php
		
	}
	
	/**
	 * Sends a Slack Team Invite to Customers if they've Opted-in
	 * 
	 * @param		integer $payment_id Payment ID
	 *                                     
	 * @access		public
	 * @since		1.1.0
	 * @return		void
	 */
	public function add_customer_to_slack_team_via_form( $payment_id ) {
		
		// If they've Opted-in to being added to the Slack Team
		if ( ! empty( $_POST['edd_slack_send_customer_team_invite'] ) ) {
		
			$email = $_POST['edd_email'];
			$first_name = $_POST['edd_first'];
			$last_name = $_POST['edd_last'];

			$channels = implode( ',', edd_get_option( 'slack_app_team_invites_customer_channels', array() ) );
			
			$this->send_invite( $email, $channels, $first_name, $last_name );
			
		}
		
	}
	
	/**
	 * Adds our own Checkbox to the EDD FES Registration Form to send out Slack Team Invites to new Vendors
	 * 
	 * @param		array  $fields Array of EDD FES Field Objects
	 * @param		object $form   EDD FES Registration Form Object
	 *                                                  
	 * @access		public
	 * @since		1.1.0
	 * @return		array  Modified Field Object Array
	 */
	public function vendors_slack_invite_checkbox( $fields, $form ) {
		
		$checkbox = new FES_Checkbox_Field( 'edd_slack_send_vendor_team_invite', 'registration' );
		
		// Grab all default Characteristics so we can set them
		$characteristics = $checkbox->get_characteristics();
		
		// Name Attribute
		$characteristics['name'] = 'edd_slack_send_vendor_team_invite';
		
		// Checkbox Options
		$characteristics['options'] = array(
			'test'
		);
		
		// Give this an empty Array if you want it to default to being unselected
		$characteristics['selected'] = apply_filters( 'slack_app_team_invites_vendor_default', array( 'test' ) );
		
		$checkbox->set_characteristics( $characteristics );
		
		$fields['edd_slack_send_vendor_team_invite'] = $checkbox;
		
		return $fields;
		
	}
	
	/**
	 * Sends a Slack Team Invite to Vendors if they've Opted-in
	 * 
	 * @param	  integer $insert_id $wpdb Insert ID
	 * @param	  array   $args		 $wpdb Column Key/Value Pairs
	 *														
	 * @access	  public
	 * @since	  1.1.0
	 * @return	  void
	 */
	public function add_vendor_to_slack_team_via_form( $insert_id, $args ) {
		
		// If they've Opted-in to being added to the Slack Team
		if ( ! empty( $_POST['edd_slack_send_customer_team_invite'] ) ) {
		
			$email = $args['email'];
			$first_name = $_POST['first_name'];
			$last_name = $_POST['last_name'];

			$channels = implode( ',', edd_get_option( 'slack_app_team_invites_vendor_channels', array() ) );
			
			$this->send_invite( $email, $channels, $first_name, $last_name );
			
		}
		
	}
	
	/**
	 * Sends a Slack Team Invite via Email
	 * 
	 * @param		string $email      Email Address (Required)
	 * @param		string $channels   Comma-separated Channel IDs/Names to auto-sub the User to. These must be PUBLIC Channels
	 * @param		string $first_name First Name (Pre-populates the Sign-up Form in Slack)
	 * @param		string $last_name  Last Name (Pre-populates the Sign-up Form in Slack)
	 *                                                                         
	 * @access		private
	 * @since		1.1.0
	 * @return		void
	 */
	private function send_invite( $email, $channels = '', $first_name = '', $last_name = '' ) {
			
		$args = array(
			'email' => $email,
			'channels' => $channels,
			'first_name' => $first_name,
			'last_name' => $last_name,
		);

		$invite = EDDSLACK()->slack_api->post( 
			'users.admin.invite',
			array(
				'body' => $args
			)
		);
		
	}
	
}

$integrate = new EDD_Slack_Invites();