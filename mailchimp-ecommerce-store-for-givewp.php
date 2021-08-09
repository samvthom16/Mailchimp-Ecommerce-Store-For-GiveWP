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

			add_action( 'wp_ajax_mes_test', array( $this, 'test' ) );
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

			return $args;
		}

		function sync( $id ){
			$mailchimpAPI = MES_MAILCHIMP_API::getInstance();

			$payment = new Give_Payment( $id );

			// if mc_eid retreive user

			$customer = array(
				'id'						=> $mailchimpAPI->getSubscriberHash( $payment->email ),
				'opt_in_status' => false,
				'email_address'	=> $payment->email
			);

			// adding utm tags

			$order = array(
				'id'										=> strval( $payment->transaction_id ),
				'order_total'						=> $payment->total,
				'currency_code'					=> $payment->currency,
				'processed_at_foreign'	=> $payment->date,
				'customer'							=> $customer,
				'landing_site'					=> bloginfo( 'url' )
			);

			echo "<pre>";
			print_r( $order );
			echo "</pre>";

			return $mailchimpAPI->createOrder( 'donation', $order );
		}

		// LISTENS FOR EVENT: GIVEWP UPDATES THE STATUS TO PUBLISHED/COMPLETED
		function listen( $id, $status, $old_status ){
			if( $status == 'publish' ){

				//echo "Listen from MC plugin";

				// SYNC ONLY IF THE NEW STATUS IS PUBLISH
				$this->sync( $id );
			}
		}

		function test(){

			$action = $_GET[ 'mes_action' ];

			switch( $action ){

				case 'sync':
					$id = $_GET['id'];
					echo $id;

					$response = $this->sync( $id );

					echo "<pre>";
					print_r( $response );
					echo "</pre>";
					break;

				case 'give':
					$id = $_GET['id'];
					echo $id;

					$payment = new Give_Payment( $id );

					echo "<pre>";
					print_r( $payment );
					echo "</pre>";

					break;

			}

			wp_die();
		}

		function syncProducts(){
			$mailchimpAPI = MES_MAILCHIMP_API::getInstance();
			$mailchimpAPI->syncProducts();
			echo "Products are synced";
			wp_die();
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
