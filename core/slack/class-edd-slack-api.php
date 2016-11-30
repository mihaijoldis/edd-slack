<?php
/**
 * Provides Slack API integration functionality.
 *
 * @since 1.0.0
 *
 * @package EDD_Slack
 * @subpackage EDD_Slack/core/slack
 */

defined( 'ABSPATH' ) || die();

class EDD_Slack_API {

    /**
	 * EDD_Slack_API constructor.
	 *
	 * @since 1.0.0
	 */
    function __construct() {
    }
    
    /**
     * @var         EDD_Slack_API $oath_key Authenticates requests for the Slack App Integration
     * @since       1.0.0
     */
    private $oath_key = '';
    
    /**
     * @var         EDD_Slack_API $api_endpoint Slack's Web API Endpoint. This is only used for Slack App Integration
     * @since       1.0.0
     */
    public $api_endpoint = 'https://slack.com/api/';
    
    /**
     * [[Description]]
     * @param [[Type]] $client_id    [[Description]]
     * @param [[Type]] $redirect_url [[Description]]
     */
    public function request_oauth_code( $client_id, $redirect_url ) {
        
        //chat:write:user
        //commands
        
        $authorize = wp_remote_get(
            'https://slack.com/oauth/authorize?client_id=' . $client_id . '&scope=chat:write:user,commands',
            array(
                'timeout' => 10,
            )
        );
        
    }
    
    /**
     * [[Description]]
     * @param [[Type]] $client_id     [[Description]]
     * @param [[Type]] $client_secret [[Description]]
     * @param [[Type]] $redirect_url  [[Description]]
     */
    public function store_oauth_token( $client_id, $client_secret, $redirect_url ) {
        
        $code = $_GET['code'];
        
        $token = $this->get( 
            'oauth.access?client_id=' . $client_id . '&client_secret=' . $client_secret . '&code=' . $code . '&redirect_uri=' . $redirect_url,
            array(
                'timeout' => 10,
            )
        );
        
        file_put_contents( $token, './token.txt' );
        
    }

    /**
	 * Push an "incoming webhook" to Slack.
	 *
	 * Incoming webhooks are messages to Slack.
	 *
	 * @since 1.0.0
	 */
    public function push_incoming_webhook( $hook, $args = array() ) {

        $args = wp_parse_args( $args, array(
            'channel'    => null,
            'username'   => null,
            'icon_emoji' => null,
            'icon_url'   => null,
            'text'       => null,
        ) );

        $result = wp_remote_post( $hook, array(
            'body' => 'payload=' . wp_json_encode( $args ),
        ) );

        return $result;
        
    }

    /**
     * Make an HTTP DELETE request - for deleting data
     * 
     * @param       string      $method  URL of the API request method
     * @param       array       $args    Assoc array of arguments (if any)
     * @param       int         $timeout Timeout limit for request in seconds
     *                                                                
     * @access      public
     * @since       1.0.0
     * @return      array|false Assoc array of API response, decoded from JSON
     */
    public function delete( $method, $args = array(), $timeout = 10 ) {
        return $this->make_request( 'delete', $method, $args, $timeout );
    }
    
    /**
     * Make an HTTP GET request - for retrieving data
     * 
     * @param   string      $method  URL of the API request method
     * @param   array       $args    Assoc array of arguments (usually your data)
     * @param   int         $timeout Timeout limit for request in seconds
     * @return  array|false Assoc array of API response, decoded from JSON
     */
    public function get( $method, $args = array(), $timeout = 10 ) {
        return $this->make_request( 'get', $method, $args, $timeout );
    }
    
    /**
     * Make an HTTP PATCH request - for performing partial updates
     * 
     * @param       string      $method  URL of the API request method
     * @param       array       $args    Assoc array of arguments (usually your data)
     * @param       int         $timeout Timeout limit for request in seconds
     *                                                                
     * @access      public
     * @since       1.0.0
     * @return      array|false Assoc array of API response, decoded from JSON
     */
    public function patch( $method, $args = array(), $timeout = 10 ) {
        return $this->make_request( 'patch', $method, $args, $timeout );
    }
    
    /**
     * Make an HTTP POST request - for creating and updating items
     * 
     * @param       string      $method  URL of the API request method
     * @param       array       $args    Assoc array of arguments (usually your data)
     * @param       int         $timeout Timeout limit for request in seconds
     *                                                                
     * @access      public
     * @since       1.0.0
     * @return      array|false Assoc array of API response, decoded from JSON
     */
    public function post( $method, $args = array(), $timeout = 10 ) {
        return $this->make_request( 'post', $method, $args, $timeout );
    }
    
    /**
     * Make an HTTP PUT request - for creating new items
     * 
     * @param       string      $method  URL of the API request method
     * @param       array       $args    Assoc array of arguments (usually your data)
     * @param       int         $timeout Timeout limit for request in seconds
     * 
     * @access      public
     * @since       1.0.0
     * @return      array|false Assoc array of API response, decoded from JSON
     */
    public function put( $method, $args = array(), $timeout = 10 ) {
        return $this->make_request( 'put', $method, $args, $timeout );
    }
    
    /**
     * Performs the underlying HTTP request
     * 
     * @param       string      $http_verb The HTTP verb to use: get, post, put, patch, delete
     * @param       string      $method    The API method to be called
     * @param       array       $args      Assoc array of parameters to be passed
     * @param       int $timeout
     *                  
     * @access      private
     * @since       1.0.0
     * @return      array|false Assoc array of decoded result
     */
    private function make_request( $http_verb, $method, $args = array(), $timeout = 10 ) {
        
        $args = wp_parse_args( $args, array(
            'method' => $http_verb,
            'timeout' => $timeout,
            'headers' => array(),
        ) );

        $url = $this->api_endpoint . '/' . $method;

        $args['headers']['Authorization'] = 'apikey ' . $this->api_key;

        $response = wp_remote_request( $url, $args );
        return json_decode( $response['body'] );

    }

}