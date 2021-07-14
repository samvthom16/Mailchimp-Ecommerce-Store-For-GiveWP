<?php

$inc_files = array(
	'class-mes-api-base.php',
	'class-mes-mailchimp-api.php',
);

foreach( $inc_files as $inc_file ){
	require_once( $inc_file );
}
