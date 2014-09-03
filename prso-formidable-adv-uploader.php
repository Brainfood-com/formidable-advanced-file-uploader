<?php
/*
 * Plugin Name: Formidable Advanced File Uploader
 * Description: Multiple file uploader with advanced options for the Formidable Pro plugin.
 * Author: Benjamin Moody
 * Version: 1.0
 * Author URI: http://www.benjaminmoody.com
 * License: GPL2+
 */

//Define plugin constants
define( 'PRSOFRMADVUPLOADER__MINIMUM_WP_VERSION', '3.0' );
define( 'PRSOFRMADVUPLOADER__VERSION', '1.0' );
define( 'PRSOFRMADVUPLOADER__DOMAIN', 'prso_frm_adv_uploader_plugin' );

//Plugin admin options will be available in global var with this name, also is database slug for options
define( 'PRSOFRMADVUPLOADER__OPTIONS_NAME', 'prso_frm_adv_uploader_options' );

define( 'PRSOFRMADVUPLOADER__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PRSOFRMADVUPLOADER__PLUGIN_URL', plugin_dir_url( __FILE__ ) );

//Include plugin classes
require_once( PRSOFRMADVUPLOADER__PLUGIN_DIR . 'class.prso-formidable-adv-uploader.php'               );

//Set Activation/Deactivation hooks
register_activation_hook( __FILE__, array( 'PrsoFrmAdvUploader', 'plugin_activation' ) );
register_deactivation_hook( __FILE__, array( 'PrsoFrmAdvUploader', 'plugin_deactivation' ) );

//Set plugin config
$config_options = array();

//Instatiate plugin class and pass config options array
new PrsoFrmAdvUploader( $config_options );