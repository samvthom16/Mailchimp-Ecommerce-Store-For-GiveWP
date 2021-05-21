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


	$inc_files = array(
		'plugins/wp-async-task/wp-async-task.php'
	);

	foreach( $inc_files as $inc_file ){
		require_once( $inc_file );
	}

	class JPB_Async_Task extends WP_Async_Task {

		protected $action = 'save_post';

		/**
		 * Prepare data for the asynchronous request
		 *
		 * @throws Exception If for any reason the request should not happen
		 *
		 * @param array $data An array of data sent to the hook
		 *
		 * @return array
		 */
		protected function prepare_data( $data ) {
			$post_id = $data[0];
			return array( 'post_id' => $post_id );
		}

		/**
		 * Run the async task action
		 */
		protected function run_action() {
			$post_id = $_POST['post_id'];
			$post = get_post( $post_id );
			if ( $post ) {
				// Assuming $this->action is 'save_post'
				do_action( "wp_async_$this->action", $post->ID, $post );
			}
		}

	}
	new JPB_Async_Task;

	function really_slow_process( $id ){
		update_post_meta( $id, 'test', 'samuel' . time() );
	}
	add_action( 'wp_async_save_post', 'really_slow_process', 10, 2 );

	function mes_test( $id, $status, $old_status ){

		$payment = new Give_Payment( $id );

		$data = $payment->ID . " " . $payment->total . " " . $payment->key;

		//echo "<pre>";
		//print_r( $data );
		//echo "</pre>";

		update_post_meta( 14195, 'test', $data );

	}

	add_action( 'give_update_payment_status', 'mes_test', 10, 3 );
