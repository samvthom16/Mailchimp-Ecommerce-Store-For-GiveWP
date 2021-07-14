<?php

	class MES_API_BASE extends MES_BASE{

		function getAPIKey(){ return ''; }
		function getBaseURL(){ return ''; }

		function slugify( $text ){
			$text = preg_replace('~[^\pL\d]+~u', '-', $text);
			$text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
			$text = preg_replace('~[^-\w]+~', '', $text);
			$text = trim($text, '-');
			$text = preg_replace('~-+~', '-', $text);
			$text = strtolower($text);
			if ( empty( $text ) ) {
		    return 'n-a';
		  }
			return $text;
		}

		/*
		* TAKES EMAIL ADDRESS AS ARGUMENT AND RETURNS MD5 HASH
		*/
		function getSubscriberHash( $email_address ){ return md5( strtolower( $email_address ) ); }

		function _isEmail( $email ) {
		   $find1 = strpos( $email, '@' );
		   $find2 = strpos( $email, '.' );
		   return ( $find1 !== false && $find2 !== false && $find2 > $find1 );
		}

		function getHTTPHeader(){ return array(); }

		function processRequest( $partUrl, $postParams = array(), $deleteFlag = false ){

			$url = $this->getBaseURL() . $partUrl;
			$auth = base64_encode( 'user:' . $this->getAPIKey() );

			//echo $url."<br>";

			$ch = curl_init();
			curl_setopt( $ch, CURLOPT_URL, $url );
			curl_setopt( $ch, CURLOPT_HTTPHEADER, $this->getHTTPHeader() );
			curl_setopt( $ch, CURLOPT_USERAGENT, 'PHP-MCAPI/2.0' );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_TIMEOUT, 10 );

			if( count( $postParams ) ){
				curl_setopt( $ch, CURLOPT_POST, true );
				curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $postParams ) );
			}

			if( $deleteFlag ){
				curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "DELETE" );
			}

			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

			$result = curl_exec($ch);
			return json_decode($result);
		}

		function cachedProcessRequest( $partUrl, $postParams = array() ){
			$cache_key = 'stripe-mc1' . md5( $partUrl );
			$data = array();

			// Get any existing copy of our transient data
			if ( false === ( $data = get_transient( $cache_key ) ) ) {
			    $data = $this->processRequest( $partUrl, $postParams );
			    set_transient( $cache_key, $data, 5 * MINUTE_IN_SECONDS );
			}
			return $data;
		}
	}
