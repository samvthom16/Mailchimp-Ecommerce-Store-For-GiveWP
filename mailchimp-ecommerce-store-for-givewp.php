<?php
	/*
    Plugin Name: Mailchimp Ecommerce Store For GiveWP
    Plugin URI: http://sputznik.com
    Description:
    Author: Samuel Thomas
    Version: 1.0
    Author URI: http://sputznik.com
    */


	define( 'mc_store_givewp', time() ); //1.4.7

	if ( ! defined( 'MES_GIVE_DIR' ) ) {
		define( 'MES_GIVE_DIR', dirname( __FILE__ ) );
	}


	$inc_files = array(
		'class-mes-base.php',
		'plugins/wp-async-task/wp-async-task.php',
		'API/api.php'
	);

	foreach( $inc_files as $inc_file ){
		require_once( $inc_file );
	}

	class MES_GIVEWP_SYNC extends MES_BASE{

		function __construct(){
			add_action( 'init', array( $this, 'addSettingsPage' ) );
			add_action( 'give_update_payment_status', array( $this, 'listen' ), 10, 3 );

			add_action( 'wp_ajax_mes_test', array( $this, 'testAjaxCallback' ) );
			add_action( 'wp_ajax_mes_sync_products', array( $this, 'syncProducts' ) );

			add_filter( 'give_stripe_prepare_metadata', array( $this, 'give_stripe_prepare_data' ), 10, 3 );
		}

		function addSettingsPage(){
			add_filter( 'give-settings_get_settings_pages', function($settings) {
				$settings[] = include MES_GIVE_DIR . '/class-me-settings-tab.php';
				return $settings;
			} );
		}

		function getMetafields(){
			return array(
				'utm_source',			// Google Analytics
				'utm_campaign',		// Google Analytics
				'utm_medium',			// Google Analytics
				'utm_term',				// Google Analytics
				'mc_cid',					// Mailchimp Campaign ID
				'mc_eid'					// Mailchimp User ID
			);
		}

		function parseMetaFieldsFromURL( $url, $args = array() ){
			$params = array();
			$url_components = parse_url( $url );
			if( isset( $url_components['query'] ) ){
				parse_str( $url_components['query'], $params );
				$metafields = $this->getMetafields();
				foreach( $metafields as $metafield ){
					$args[ $metafield ] = isset( $params[ $metafield ] ) ? $params[ $metafield ] : "";
				}
			}
			return $args;
		}

		function give_stripe_prepare_data( $args, $donation_id, $donation_data ){
			$params = array();
			if( is_array( $donation_data ) && isset( $donation_data['post_data'] ) && isset( $donation_data['post_data']['give-current-url'] ) ){
				$url = $donation_data['post_data']['give-current-url'];
				$url_components = parse_url( $url );
				if( isset( $url_components['query'] ) ){
					parse_str( $url_components['query'], $params );
					$metafields = $this->getMetafields();
					foreach( $metafields as $metafield ){
						$args[ $metafield ] = isset( $params[ $metafield ] ) ? $params[ $metafield ] : "";
					}
				}
			}

			$mailchimpAPI = MES_MAILCHIMP_API::getInstance();
			$args['mc_sid'] = $mailchimpAPI->getStoreID();

			//print_r( $args );

			//wp_die();

			return $args;
		}

		function addNameToMailchimpCustomer( $mc_customer, $customer = array() ){
			if( isset( $mc_customer->merge_fields ) ){
				if( isset( $mc_customer->merge_fields->FNAME ) ){
					$customer[ 'first_name' ] = $mc_customer->merge_fields->FNAME;
				}
				if( isset( $mc_customer->merge_fields->LNAME ) ){
					$customer[ 'last_name' ] = $mc_customer->merge_fields->LNAME;
				}
			}
			return $customer;
		}

		function createMailchimpCustomer( $payment ){

			$mailchimpAPI = MES_MAILCHIMP_API::getInstance();

			// DEFAULT CUSTOMER VALUE WHERE IT IS ONLY TRANSACTIONAL CUSTOMER
			$customer = array( 'opt_in_status'	=> false );

			$metafields = $this->getMetaFromPayment( $payment );

			// CHECK IF MAILCHIMP UNIQUE ID EXISTS
			if( isset( $metafields['mc_eid'] ) ){
				$mc_customer = $mailchimpAPI->getUniqueMember( $metafields['mc_eid'] );

				// IF MEMBER EXISTS THEN SET THE OPT_IN_STATUS TO TRUE
				if( $mc_customer!=null && isset( $mc_customer->email_address ) && !empty( $mc_customer->email_address ) ){
					$customer = $this->addNameToMailchimpCustomer( $mc_customer, $customer );
					$customer[ 'email_address' ] = $mc_customer->email_address;
					$customer[ 'opt_in_status' ] = true;
				}
			}

			// IF THE EMAIL ADDRESS DOES NOT EXIST, THAT MEANS DIDN'T GO THROUGH MAILCHIMP USER ID
			// THEN CHECK IN GIVE WP PAYMENT INFORMATION
			if( !isset( $customer[ 'email_address' ] ) ){
				$customer[ 'email_address' ] = $payment->email;

				// CHECK IF THERE EXISTS A MEMBER FOR THE SAME EMAIL ADDRESS IN THE STORE LIST
				// IF MEMBER EXISTS THEN SET THE OPT_IN_STATUS TO TRUE
				$mc_customer = $mailchimpAPI->getUniqueMember( $customer[ 'email_address' ] );
				if( $mc_customer!=null && isset( $mc_customer->email_address ) && !empty( $mc_customer->email_address ) ){
					$customer = $this->addNameToMailchimpCustomer( $mc_customer, $customer );
					$customer[ 'opt_in_status' ] = true;
				}
			}

			// CHECK AGAIN IF THE EMAIL ADDRESS EXISTS, IF YES THEN ADD ID
			// ADD SUBSCRIBER HASH AS ID FOR THE CUSTOMER ONLY IF EMAIL ADDRESS EXISTS
			if( isset( $customer[ 'email_address' ] ) && !empty( $customer[ 'email_address' ] ) ){
				$customer[ 'id' ]  = $mailchimpAPI->getSubscriberHash( $customer[ 'email_address' ] );
				return $customer;
			}

			// DEFAULT OPTION SHOULD BE TO RETURN NULL INCASE AN ERROR HAPPENS
			return null;
		}


		function sync( $id ){
			$payment = new Give_Payment( $id );

			// CREATE MAILCHIMP CUSTOMER FROM PAYMENT AND META INFORMATION
			$customer = $this->createMailchimpCustomer( $payment );

			// CREATE BASIC ORDER INFORMATION
			$order = array(
				'id'										=> strval( $payment->transaction_id ),
				'order_total'						=> $payment->total,
				'currency_code'					=> $payment->currency,
				'processed_at_foreign'	=> $payment->date,
				'customer'							=> $customer,
				'landing_site'					=> get_bloginfo( 'url' )
			);

			// ADD CAMPAIGN ID FROM THE UTM TAGS IF EXISTS
			$metafields = $this->getMetaFromPayment( $payment );
			//$this->test( $metafields );
			if( isset( $metafields[ 'mc_cid' ] ) && $metafields[ 'mc_cid' ] ){
				$order[ 'campaign_id' ] = $metafields[ 'mc_cid' ];
			}
			//$this->test( $order );

			$mailchimpAPI = MES_MAILCHIMP_API::getInstance();
			return $mailchimpAPI->createOrder( 'donation', $order );
		}

		// LISTENS FOR EVENT: GIVEWP UPDATES THE STATUS TO PUBLISHED/COMPLETED
		function listen( $id, $status, $old_status ){

			// SYNC ONLY IF THE NEW STATUS IS PUBLISH
			if( $status == 'publish' ){
				$this->sync( $id );
			}
		}



		function testAjaxCallback(){

			$action = $_GET[ 'mes_action' ];

			switch( $action ){

				case 'sync':
					$response = $this->sync( $_GET['id'] );
					$this->test( $response );
					break;

				case 'give':
					$payment = new Give_Payment( $_GET['id'] );
					$metafields = $this->getMetaFromPayment( $payment );

					echo "<pre>";
					print_r( $metafields );
					echo "</pre>";

					break;
			}

			wp_die();
		}

		// CONTAINS MC_EID, MC_cid & ALL THE UTM TAGS
		function getMetaFromPayment( $payment ){
			$metafields = array();
			$meta = $payment->get_meta();
			if( isset( $meta['_give_current_url'] ) ){
				$url = html_entity_decode( $meta['_give_current_url'] );
				$metafields = $this->parseMetaFieldsFromURL( $url );
			}
			return $metafields;
		}

		function syncProducts(){
			$mailchimpAPI = MES_MAILCHIMP_API::getInstance();
			$mailchimpAPI->syncProducts();
			echo "Products are synced";
			wp_die();
		}

		function test( $data ){
			echo "<pre>";
			print_r( $data );
			echo "</pre>";
		}

	}

	MES_GIVEWP_SYNC::getInstance();

	/*
	add_action( 'wp_ajax_mes_sync', function(){

		$mesSync = MES_GIVEWP_SYNC::getInstance();
		$response = $mesSync->sync( 14204 );

		echo "<pre>";
		print_r( $response );
		echo "</pre>";

		wp_die();
	} );
	*/
