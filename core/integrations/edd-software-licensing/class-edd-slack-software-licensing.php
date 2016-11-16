<?php
/**
 * EDD Software Licensing Integration
 *
 * @since 1.0.0
 *
 * @package EDD_Slack
 * @subpackage EDD_Slack/core/integrations/edd-software-licensing
 */

defined( 'ABSPATH' ) || die();

class EDD_Slack_Software_Licensing {
    
    /**
     * EDD_Slack_Software_Licensing constructor.
     *
     * @since 1.0.0
     */
    function __construct() {
        
        // Add New Triggers
        add_filter( 'edd_slack_triggers', array( $this, 'add_triggers' ) );
        
        // Add new Conditional Fields
        add_filter( 'edd_slack_notification_fields', array( $this, 'add_extra_fields' ) );
        
        // Fires when a License is Generated
        add_action( 'edd_sl_store_license', array( $this, 'edd_sl_store_license' ), 10, 4 );
        
        // Fires when a License is Activated
        add_action( 'edd_sl_post_set_status', array( $this, 'edd_sl_post_set_status' ), 10, 2 );
        
        // Fires when a License is Deactivated
        add_action( 'edd_sl_deactivate_license', array( $this, 'edd_sl_deactivate_license' ), 10, 2 );
        
        // Fires when a License is Upgraded
        add_action( 'edd_sl_license_upgraded', array( $this, 'edd_sl_license_upgraded' ), 10, 2 );
        
        // Inject some Checks before we do Replacements or send the Notification
        add_action( 'edd_slack_before_replacements', array( $this, 'before_notification_replacements' ), 10, 5 );
        
        // Add our own Replacement Strings
        add_filter( 'edd_slack_notifications_replacements', array( $this, 'custom_replacement_strings' ), 10, 4 );
        
        // Add our own Hints for the Replacement Strings
        add_filter( 'edd_slack_text_replacement_hints', array( $this, 'custom_replacement_hints' ), 10, 3 );
        
    }
    
    /**
     * Add our Triggers
     * 
     * @param       array $triggers EDD Slack Triggers
     *                                        
     * @access      public
     * @since       1.0.0
     * @return      array Modified EDD Slack Triggers
     */
    public function add_triggers( $triggers ) {

        $triggers['edd_sl_store_license'] = _x( 'New License Key Generated', 'New License Key Generated Trigger', EDD_Slack_ID );
        $triggers['edd_sl_activate_license'] = _x( 'License Key Activated', 'License Key Activated Trigger', EDD_Slack_ID );
        $triggers['edd_sl_deactivate_license'] = _x( 'License Key Deactivated', 'License Key Deactivated Trigger', EDD_Slack_ID );
        $triggers['edd_sl_license_upgraded'] = _x( 'License Upgraded', 'License Upgraded Trigger', EDD_Slack_ID );

        return $triggers;

    }
    
    /**
     * Conditionally Showing Fields within the Notification Repeater works by adding the Trigger as a HTML Class Name
     * 
     * @param       array $repeater_fields Notification Repeater Fields
     *                                                  
     * @access      public
     * @since       1.0.0
     * @return      array Notification Repeater Fields
     */
    public function add_extra_fields( $repeater_fields ) {
        
        // Make the Download Field Conditionally shown for our Triggers
        $repeater_fields['download']['field_class'][] = 'edd_sl_store_license';
        $repeater_fields['download']['field_class'][] = 'edd_sl_activate_license';
        $repeater_fields['download']['field_class'][] = 'edd_sl_deactivate_license';
        
        return $repeater_fields;
        
    }
    
    /**
     * Send a Slack Notification whenever a License Key is Generated. This does not trigger for Upgrades or Renewals.
     * 
     * @param       integer $license_id  License ID
     * @param       integer $download_id Post ID of the associated Download
     * @param       integer $payment_id  Payment ID
     * @param       string  $type        'default' for Single, 'bundle' for Bundle
     *                                                                      
     * @access      public
     * @since       1.0.0
     * @return      void
     */
    public function edd_sl_store_license( $license_id, $download_id, $payment_id, $type ) {
        
        // We need the Payment Meta to get accurate Customer Data
        $payment_meta = get_post_meta( $payment_id, '_edd_payment_meta', true );
        
        // This is the EDD Customer ID. This is not necessarily the same as the WP User ID
        $customer_id = get_post_meta( $payment_id, '_edd_payment_customer_id', true );
        $customer = new EDD_Customer( $customer_id );
        
        do_action( 'edd_slack_notify', 'edd_sl_store_license', array(
            'user_id' => $customer->user_id, // If the User isn't a proper WP User, this will be 0
            'name' => $payment_meta['user_info']['first_name'] . ' ' . $payment_meta['user_info']['last_name'],
            'email' => $payment_meta['user_info']['email'],
            'license_key' => edd_software_licensing()->get_license_key( $license_id ),
            'download_id' => $download_id,
            'expiration' => get_post_meta( $license_id, '_edd_sl_expiration', true ),
            'license_limit' => edd_software_licensing()->license_limit( $license_id ),
        ) );
        
    }
    
    /**
     * Fires whenever the License Status Changes
     * 
     * @param       integer $license_id License ID
     * @param       string  $status     License Status
     *                                          
     * @access      public
     * @since       1.0.0
     * @return      void
     */
    public function edd_sl_post_set_status( $license_id, $status ) {
        
        // We need the Payment ID and Payment Meta to get accurate Customer Data
        $payment_id = get_post_meta( $license_id, '_edd_sl_payment_id', true );
        $payment_meta = get_post_meta( $payment_id, '_edd_payment_meta', true );
        
        // This is the EDD Customer ID. This is not necessarily the same as the WP User ID
        $customer_id = get_post_meta( $payment_id, '_edd_payment_customer_id', true );
        $customer = new EDD_Customer( $customer_id );
        
        if ( $status == 'active' ) {
        
            do_action( 'edd_slack_notify', 'edd_sl_activate_license', array(
                'user_id' => $customer->user_id, // If the User isn't a proper WP User, this will be 0
                'name' => $payment_meta['user_info']['first_name'] . ' ' . $payment_meta['user_info']['last_name'],
                'email' => $payment_meta['user_info']['email'],
                'license_key' => edd_software_licensing()->get_license_key( $license_id ),
                'download_id' => edd_software_licensing()->get_download_id( $license_id ),
                'expiration' => get_post_meta( $license_id, '_edd_sl_expiration', true ),
                'site_count' => edd_software_licensing()->get_site_count( $license_id ),
                'license_limit' => edd_software_licensing()->license_limit( $license_id ),
            ) );
            
        }
        else if ( $status == 'inactive' ) {
            
            do_action( 'edd_slack_notify', 'edd_sl_deactivate_license', array(
                'user_id' => $customer->user_id, // If the User isn't a proper WP User, this will be 0
                'name' => $payment_meta['user_info']['first_name'] . ' ' . $payment_meta['user_info']['last_name'],
                'email' => $payment_meta['user_info']['email'],
                'license_key' => edd_software_licensing()->get_license_key( $license_id ),
                'download_id' => edd_software_licensing()->get_download_id( $license_id ),
                'expiration' => get_post_meta( $license_id, '_edd_sl_expiration', true ),
                'site_count' => edd_software_licensing()->get_site_count( $license_id ),
                'license_limit' => edd_software_licensing()->license_limit( $license_id ),
            ) );
            
        }
        
    }
    
    /**
     * Send a Slack Notification when a User Upgrades their License
     * 
     * @param       integer $license_id License ID of the License being Upgraded
     * @param       array   $args       Upgrade Arguments
     *                                          
     * @access      public
     * @since       1.0.0
     * @return      void
     */
    public function edd_sl_license_upgraded( $license_id, $args ) {
        
        /*
        $args = array(
            'payment_id'       => $payment_id,
            'old_payment_id'   => $old_payment_id,
            'download_id'      => $download_id,
            'old_download_id'  => $old_download_id,
            'old_price_id'     => $old_price_id,
            'upgrade_id'       => $upgrade_id,
            'upgrade_price_id' => false
        );
        */
        
        do_action( 'edd_slack_notify', 'edd_sl_license_upgraded', array(
            'license_id' => $license_id,
        ) );
        
    }
    
    /**
     * Inject some checks on whether or not to bail on the Notification
     * 
     * @param       object  $post            WP_Post Object for our Saved Notification Data
     * @param       array   $fields          Fields used to create the Post Meta
     * @param       string  $trigger         Notification Trigger
     * @param       string  $notification_id ID Used for Notification Hooks
     * @param       array   $args            $args Array passed from the original Trigger of the process
     *              
     * @access      public
     * @since       1.0.0
     * @return      void
     */
    public function before_notification_replacements( $post, $fields, $trigger, $notification_id, &$args ) {
        
        if ( $notification_id == 'rbm' ) {
        
            $args = wp_parse_args( $args, array(
                'license_id' => 0,
                'download_id' => 0,
                'payment_id' => 0,
                'bail' => false,
            ) );
            
            if ( $trigger == 'edd_sl_store_license' ||
                $trigger == 'edd_sl_activate_license' ||
                $trigger == 'edd_sl_deactivate_license' ) {
                
                // Download commented on doesn't match our Notification, bail
                if ( $fields['download'] !== 'all' && (int) $fields['download'] !== $args['download_id'] ) {
                    $args['bail'] = true;
                    return false;
                }
                
            }
            
        }
        
    }
    
    /**
     * Based on our Notification ID and Trigger, use some extra Replacement Strings
     * 
     * @param       array  $replacements    Notification Fields to check for replacements in
     * @param       string $trigger         Notification Trigger
     * @param       string $notification_id ID used for Notification Hooks
     * @param       array  $args            $args Array passed from the original Trigger of the process
     * 
     * @access      public
     * @since       1.0.0
     * @return      array  Replaced Strings within each Field
     */
    public function custom_replacement_strings( $replacements, $trigger, $notification_id, $args ) {

        if ( $notification_id == 'rbm' ) {

            switch ( $trigger ) {

                case 'edd_sl_store_license':
                case 'edd_sl_activate_license':
                case 'edd_sl_deactivate_license':
                    
                    // If this customer did not create an Account
                    if ( $args['user_id'] == 0 ) {
                        $replacements['%email%'] = $args['email'];
                        $replacements['%name%'] = $args['name'];
                        $replacements['%username%'] = _x( 'This Customer does not have an account', 'No Username Replacement Text', EDD_Slack_ID );
                    }
                    
                    $replacements['%download%'] = get_the_title( $args['download_id'] );
                    $replacements['%license_key%'] = $args['license_key'];
                    $replacements['%expiration%'] = date_i18n( get_option( 'date_format' ), $args['expiration'] );
                    $replacements['%license_limit%'] = $args['license_limit'];
                    
                    if ( $trigger !== 'edd_sl_store_license' ) {
                        $replacements['%site_count%'] = $args['site_count']; // There shouldn't be any activated sites for a new license
                    }
                    
                    break;
                    
                default:
                    break;

            }
            
        }
        
        return $replacements;
        
    }
    
    /**
     * Add Replacement String Hints for our Custom Trigger
     * 
     * @param       array $hints         The main Hints Array
     * @param       array $user_hints    General Hints for a User. These apply to likely any possible Trigger
     * @param       array $payment_hints Payment-Specific Hints
     *                                                    
     * @access      public
     * @since       1.0.0
     * @return      array The main Hints Array
     */
    public function custom_replacement_hints( $hints, $user_hints, $payment_hints ) {
        
        $licensing_hints = array(
            '%license_key%' => _x( 'The License Key', '%license_key% Hint Text', EDD_Slack_ID ),
            '%download%' => sprintf( _x( 'The %s the License Key is for', '%download% Hint Text', EDD_Slack_ID ), edd_get_label_singular() ),
            '%expiration%' => _x( 'The date when the License expires', '%expiration% Hint Text', EDD_Slack_ID ),
            '%site_count%' => _x( 'The number of sites the License is active on', '%site_count% Hint Text', EDD_Slack_ID ),
            '%license_limit%' => _x( 'The number of sites the License can be active on', '%license_limit% Hint Text', EDD_Slack_ID ),
        );
        
        $hints['edd_sl_store_license'] = array_merge( $user_hints, $licensing_hints );
        $hints['edd_sl_activate_license'] = array_merge( $user_hints, $licensing_hints );
        $hints['edd_sl_deactivate_license'] = array_merge( $user_hints, $licensing_hints );
        
        unset( $hints['edd_sl_store_license']['%site_count%'] ); // This one doesn't make sense in this context
        
        return $hints;
        
    }
    
}

$integrate = new EDD_Slack_Software_Licensing();