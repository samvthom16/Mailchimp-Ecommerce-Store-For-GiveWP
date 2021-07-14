<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


if ( ! class_exists( 'Sputznik_Give_Settings_Tab' ) ) :

	class Sputznik_Give_Settings_Tab extends Give_Settings_Page {

		public function __construct() {
			$this->id    = 'spgmctab';
			$this->label = __( 'Mailchimp Ecommerce', 'spgmc' );

			$this->default_tab = 'spgmc-mailchimp-options';

			parent::__construct();
		}


		/**
		 * Get settings array.
		 *
		 * @return array
		 */
		public function get_settings() {
			$settings = [];

			$current_section = give_get_current_setting_section();

			switch ( $current_section ) {
				case 'spgmc-mailchimp-options':
					$settings = [
						[
							'id'   => 'give_title_data_control_2',
							'type' => 'title',
						],
						[
							'name'    => __( 'Api Key', 'spgmc' ),
							'id'      => 'mes_api_key',
							'type'    => 'text',
						],
						[
							'name'    => __( 'Server', 'spgmc' ),
							'id'      => 'mes_server',
							'type'    => 'text',
						],
						[
							'name'    => __( 'Store ID', 'spgmc' ),
							'id'      => 'mes_store_id',
							'type'    => 'text',
						],
						[
							'id'   => 'give_title_data_control_2',
							'type' => 'sectionend',
						],
					];
					break;
			}

			return $settings;
		}


		/**
		 * Get sections.
		 *
		 * @return array
		 */
		public function get_sections() {
			$sections = [ 'spgmc-mailchimp-options' => __( 'General Settings', 'spgmc' ) ];
			return apply_filters( 'give_get_sections_' . $this->id, $sections );
		}


	}

	return new Sputznik_Give_Settings_Tab();

endif;
