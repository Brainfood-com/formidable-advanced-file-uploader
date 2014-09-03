<?php
/**
* PrsoFrmAdvUploaderInit
* 
* This class adds a new custom field type to Formidable called 'Advanced Uploader'.
* The new field outputs an advanced file uploader to any form based on Plupload jquery plugin.
* 
* Plugin options allow devs to quickly alter advanced Plupload parameters from the admin area.
* These are then applied to all advanced upload fields in forms. Some parameters such as file size and
* allowed file extensions can be overriden on a field by field bases via the Formidable field settings.
*
* File Upload Process:
* 1. Plupload uploads files to temp folder in uploads folder, filename is encrypted in DOM.
* 2. Once form is succesfully submitted then files are added as wordpress attachments in media library
*	 they are validated, given a randomly created filename (security measure) before adding to library
* 3. Meta data on file attachment is added to Formidable entry table so that files can be displayed
*	 in form entry view with link to each file in media library
* 4. An option is added to entry view to allow attachments to be auto deleted when a form entry is deleted,
*	 although this is not required and files can stay in media library after entry is deleted.
*
* Security and Validation:
* 1. Empty index.php file is added to temp folder used by plupload in uploads dir
* 2. htaccess file is also added to folder to prevent scripts from running - see qqFileUploader.php
* 3. Both file extension AND mime type are validated when plupload attempts to save file into tmp dir
*	 finfo_file() is used to extract the mime type which is compared against $this->allowed_mimes which
*	 uses get_allowed_mime_types(); This can be filtered via 'prso_adv_uploader_reject_mimes'
* 4. Files uploaded to tmp dir by plupload have names encrypted and stored in a formidable input field in the dom
*	 file names are decrypted once the form has been submitted and the files are being processed into media library
* 5. Enryption of tmp file names in the dom should help prevent malicious code from being added and run from tmp folder.
*	 That said the htaccess, index.php, and mime validation should prevent that anyway.
* 
* @author	Ben Moody
*/

class PrsoFrmAdvUploaderInit {
	
	protected $plugin_path							= PRSOFRMADVUPLOADER__PLUGIN_DIR;
	protected $plugin_inc_path						= NULL; //Set in constuct
	protected $plugin_url							= PRSOFRMADVUPLOADER__PLUGIN_URL;
	protected $plugin_textdomain					= PRSOFRMADVUPLOADER__DOMAIN;
	
	public $prso_pluploader_args 					= NULL;
	private static $prso_pluploader_tmp_dir_name 	= 'prso-pluploader-tmp';
	private static $submit_nonce_key 				= 'prso-pluploader-loader-submit-nonce';
	private static $encrypt_key						= '4ddRp]4X5}R-WU';
	private $move_div 								= array();
	
	protected $plugin_options						= array();
	protected $user_interface						= NULL;
	
	protected $allowed_mimes						= array();
	
	//Frm meta keys
	private static $delete_files_meta_key		    = 'prso-pluploader-delete-files';
	
	// field type
	public static $field_type                       = 'prso_frm_pluploader';
	
	//*** PRSO PLUGIN FRAMEWORK METHODS - Edit at your own risk (go nuts if you just want to add to them) ***//
	
	function __construct() {
 		
 		//Set include path
 		$this->plugin_inc_path = PRSOFRMADVUPLOADER__PLUGIN_DIR . 'inc';
 		
 		//Cache plugin options
 		$this->plugin_options = get_option( PRSOFRMADVUPLOADER__OPTIONS_NAME );
 		
 		//Cache UI
 		$this->user_interface = $this->plugin_options['ui_select'];
 		
 		//Init plugin
 		$this->plugin_init();
 		
	}
	
	/**
	* plugin_init
	* 
	* 
	* @access 	private
	* @author	Ben Moody
	*/
	private function plugin_init() {
		
		//*** PRSO PLUGIN CORE ACTIONS ***//
		
		//Enqueue any custom scripts or styles
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		
		//Add any custom actions
		add_action( 'init', array( $this, 'add_actions' ) );
		
		//Add any custom filter
		add_action( 'after_setup_theme', array( $this, 'add_filters' ) );
		
		
		//*** ADD CUSTOM ACTIONS HERE ***//
		
		//Include Terms of Service plugin
		$tos_path = $this->plugin_inc_path . '/inc_frm_terms.php';
		if( file_exists($tos_path) ) {
			include_once( $tos_path );
			new PrsoFrmTermsFunctions();
		}
		
		//Include Video Uploader plugin if requested
		if( isset($this->plugin_options['video_plugin_status']) && ($this->plugin_options['video_plugin_status'] == 1) ) {
			$video_path = $this->plugin_inc_path . '/VideoUploader/class.prso-adv-video-uploader.php';
			
			if( file_exists($video_path) ) {
				include_once( $video_path );
				new PrsoAdvVideoUploader();
			}
		}
		
	}
	
	/**
	* enqueue_scripts
	* 
	* Called by $this->admin_init() to queue any custom scripts or stylesheets
	* 
	* @access 	public
	* @author	Ben Moody
	*/
	public function enqueue_scripts( $activate_fine_uploader = FALSE ) {
		
		//Init vars
		$debug_mode					= FALSE;
		$in_footer					= TRUE;
		$plupload_i18n_script		= NULL;
		$plupload_i18n_script_path	= NULL;
		
		//Register Plupload scripts -- NOT FOR ADMIN AREA!!
		if( !is_admin() ) {
			
			//Plupload Full Min
			if( !$debug_mode ) {
			
				wp_register_script( 'plupload-full-min', 
					plugins_url( '/inc/js/plupload/plupload.full.min.js', __FILE__), 
					array('jquery'), 
					'2.1.1', 
					$in_footer 
				);
				
			} else {
				
				wp_register_script( 'plupload-moxie', 
					plugins_url( '/inc/js/plupload/moxie.js', __FILE__), 
					array('jquery'), 
					'2.1.1', 
					$in_footer 
				);
			
				wp_register_script( 'plupload-full-min', 
					plugins_url( '/inc/js/plupload/plupload.dev.js', __FILE__), 
					array('plupload-moxie'), 
					'2.1.1', 
					$in_footer 
				);
				
			}
			//Enqueue scripts for Plupload
			wp_enqueue_script('plupload-full-min');
			
			//Detect User Interface
			switch( $this->user_interface ) {
				case 'jquery-ui':
				
					//JQuery UI Min
					wp_register_script( 'plupload-jquery-ui-core', 
						'http://ajax.googleapis.com/ajax/libs/jqueryui/1.10.2/jquery-ui.min.js', 
						array('jquery'), 
						'1.10.2', 
						$in_footer 
					);
			
					//Plupload JQuery UI
					wp_register_script( 'plupload-jquery-ui', 
						plugins_url( '/inc/js/plupload/jquery.ui.plupload/jquery.ui.plupload.js', __FILE__), 
						array('plupload-full-min'), 
						'2.1.1', 
						$in_footer 
					);
					
					//Register plupload init script
					wp_register_script( 'prso-pluploader-init', 
						plugins_url(  '/inc/js/init_plupload_jquery_ui.js', __FILE__), 
						array('plupload-full-min'), 
						'1.0', 
						$in_footer 
					);
					
					//Register plupload Styles
					wp_register_style( 'plupload-jquery-ui-core', 
						'http://ajax.googleapis.com/ajax/libs/jqueryui/1.10.2/themes/smoothness/jquery-ui.min.css', 
						NULL, 
						'1.10.2', 
						'screen' 
					);
					
					//Plupload JQuery UI Style
					wp_register_style( 'plupload-jquery-ui', 
						plugins_url(  '/inc/js/plupload/jquery.ui.plupload/css/jquery.ui.plupload.css', __FILE__), 
						array('plupload-jquery-ui-core'), 
						'2.1.1', 
						'screen' 
					);
					
					//Enqueue
					if( !is_admin() ) {
					
						//Scripts
				 		wp_enqueue_script('plupload-jquery-ui-core');
						wp_enqueue_script('plupload-jquery-ui');
						
						//Styles
						wp_enqueue_style('plupload-jquery-ui-core');
						wp_enqueue_style('plupload-jquery-ui');
						
					}			
					
					
					break;
				case 'queue':	
					
					//Plupload Queue Script
					wp_register_script( 'plupload-jquery-queue', 
						plugins_url(  '/inc/js/plupload/jquery.plupload.queue/jquery.plupload.queue.min.js', __FILE__), 
						array('plupload-full-min'), 
						'2.1.1', 
						$in_footer 
					);
					
					//Register plupload init script
					wp_register_script( 'prso-pluploader-init', 
						plugins_url(  '/inc/js/init_plupload_queue.js', __FILE__), 
						array('plupload-full-min'), 
						'1.0', 
						$in_footer 
					);
					
					//Plupload Queue Style
					wp_register_style( 'plupload-queue', 
						plugins_url(  '/inc/js/plupload/jquery.plupload.queue/css/jquery.plupload.queue.css', __FILE__), 
						array(), 
						'2.1.1', 
						'screen' 
					);
					
					//Enqueue
					if( !is_admin() ) {
					
						//Scripts
				 		wp_enqueue_script('plupload-jquery-queue');
						
						//Styles
						wp_enqueue_style('plupload-queue');
						
					}
					
					break;
				default:
					
					//Register plupload init script
					wp_register_script( 'prso-pluploader-init', 
						plugins_url(  '/inc/js/init_plupload_custom.js', __FILE__), 
						array('plupload-full-min'), 
						'1.0', 
						$in_footer 
					);
					
					break;
			}
			
			
			
			//i18n Scripts
			$plupload_i18n_script = apply_filters( 'prso_frm_pluploader_i18n_script', $plupload_i18n_script );
			
			//Register request plupload i18n script if found
			if( isset($this->plugin_path, $plupload_i18n_script) ) {
				
				$plupload_i18n_script_path = $this->plugin_url . 'inc/js/plupload/i18n/' . $plupload_i18n_script . '.js';
									
				wp_register_script( "plupload-i18n", 
					$plupload_i18n_script_path, 
					array('plupload-full-min'), 
					NULL, 
					$in_footer 
				);
				
				//i18n if requested
				wp_enqueue_script('plupload-i18n');
				
			}
			
			//Enqueue plupload activate script
			if( $activate_fine_uploader === TRUE ) {
				//Enqueue plugin plupload init script
				wp_enqueue_script('prso-pluploader-init');
				
				//Call helper to cache and localize vars requied for init
				$this->localize_pluploader_init_vars();
			}
				
		}
		
		if( is_admin() ) {
			
			//Register custom scripts for use with frm
			wp_register_script( 'prso-pluploader-entries', 
				plugins_url(  '/inc/js/frm-entries.js', __FILE__), 
				array('jquery'), 
				'1.0', 
				$in_footer 
			);	
			
			//Plugin Frm admin area style
			wp_register_style( 'plupload-frm-form-display', plugins_url('/inc/css/form-display.css', __FILE__), array(), '1.0', 'screen' );
			
			//Enqueue script for frm entry customization
			if( is_admin() && isset($_GET['page']) && $_GET['page'] === 'gf_entries' ) {
				wp_enqueue_script('prso-pluploader-entries');
				//Call method to set js object for 'prso-pluploader-entries'
				$this->localize_script_prso_pluploader_entries();
			}
			
			//Enqueue script for frm form display customization
			if( is_admin() && isset($_GET['page']) && $_GET['page'] === 'formidable' ) {
				wp_enqueue_style('plupload-frm-form-display');
			}
			
		}
		
	}
	
	/**
	* add_actions
	* 
	* Called in $this->admin_init() to add any custom WP Action Hooks
	* 
	* @access 	public
	* @author	Ben Moody
	*/
	public function add_actions() {
		
		//Init vars
		$allowed_mime_types = array(
			'3gp'	=>	'video/3gpp',
			'wmvv'	=>	'video/x-msvideo'	
		);
		
		$allowed_mime_types = wp_parse_args( get_allowed_mime_types(), $allowed_mime_types );
		
		//Cache array of mime types to reject out of hand
 		$this->allowed_mimes = apply_filters( 'prso_adv_uploader_reject_mimes', $allowed_mime_types );
		
		//Add Standard custom settings to field menu
		add_action( 'frm_field_options_form', array($this, 'pluploader_field_settings'), 9, 3 );
		
		// Add a placeholder on the form builder page
		add_action( 'frm_display_added_fields', array($this, 'pluploader_admin_field'));
		
		//Add the custom field html for form rendering
		add_action( 'frm_form_fields', array($this, 'pluploader_field_input'), 10, 2);
		
		//Actions to handle ajax upload requests
		add_action( 'wp_ajax_nopriv_prso-plupload-submit', array($this,'plupload_ajax_submit') );
		add_action( 'wp_ajax_prso-plupload-submit', array($this,'plupload_ajax_submit') );
		
		//Save any uploads as wp attachements in wp media library
		add_action( 'frm_after_create_entry', array($this, 'save_uploads_as_wp_attachments'), 10, 2 );
		add_action( 'frm_after_update_entry', array($this, 'save_uploads_as_wp_attachments'), 10, 2 );
		
		//Take actions when an entry is deleted
		add_action( 'frm_before_destroy_entry', array($this, 'pluploader_delete_entry'), 10, 2 );
		
	}
	
	/**
	* add_filters
	* 
	* Called in $this->admin_init() to add any custom WP Filter Hooks
	* 
	* @access 	public
	* @author	Ben Moody
	*/
	public function add_filters() {
		
		//Add a custom field button to the advanced formidable field selector menu
		add_filter( 'frm_pro_available_fields', array($this, 'add_field_buttons') );
		
		// save custom settings in a field
		add_filter( 'frm_update_field_options', array($this, 'pluploader_update_field_settings'), 10, 3 );
		
		add_filter( 'frm_display_value_atts', array($this, 'pluploader_display_type'), 10, 2 );
		
	}
	
	
	//*** CUSTOM METHODS SPECIFIC TO THIS PLUGIN ***//
	
	/**
	* add_field_buttons
	* 
	* Called by 'frm_pro_available_fields' formidable filter.
	* Adds a custom field button to the formidable fields menu
	*
	* Options:
	*	$group['name'] = 'advacned_fields'/'standard_fields'/'post_fields'
	* 
	* @access 	public
	* @author	Ben Moody
	*/
	public function add_field_buttons( $fields = array() ) {
	    
	    $fields[self::$field_type] = __('Adv Uploader', self::$field_type );
	    
	    return $fields;
	}
	
	/*
	* Show a placeholder on the form builder page
	*/
	public function pluploader_admin_field($field) {
	    if ( $field['type'] != self::$field_type ) {
            return;
        }
            
	    ?>
	    <div style="width:100%;margin-bottom:10px;text-align:center;">
        <div class="howto button-secondary frm_html_field"><?php _e('This is a placeholder for your advanced uploader field. <br/>View your form to see it in action.', 'formidable') ?></div>   
        </div>
	    <?php
	}
	
	/**
	* pluploader_field_input
	* 
	* Called by 'frm_form_fields' formidable action.
	* Add html required to render custom field
	* 
	* @access 	public
	* @author	Ben Moody
	*/	
	public function pluploader_field_input( $field, $field_name, $atts = array() ) {
	 	
	 	//Init vars
	 	$plupload_container = NULL;
	 	$errors             = array();
	 	$entry_id           = false;
	 	
	    if ( $field['type'] != self::$field_type ) {
	        return;
	    }
	    
	    $errors = isset($atts['errors']) ? $atts['errors'] : array();
	    
	    global $frm_vars;
        
        if ( ! is_array($frm_vars) ) {
            $frm_vars = array();
        }
        
        $entry_id = isset($frm_vars['editing_entry']) ? $frm_vars['editing_entry'] : false;
        
        // enqueue scripts now
        $this->pluploader_enqueue_scripts( $field );
		
		//Cache the hidden field that will store data on uploaded files				
		ob_start();
		?>
		<div class='frminput_container prso_plupload'>
		    <?php foreach ( (array) $field['value'] as $val ) { ?>
		    <input name='item_meta[<?php echo $field['id'] ?>][]' id='prso_form_pluploader_<?php echo $field['id'] ?>' type='hidden' value="<?php echo esc_attr($val) ?>" />
		    <?php } ?>
		</div>
		<?php
		$input = ob_get_contents();
		ob_end_clean();
		
		//Cache the div element used by pluploader jquery plugin
		ob_start();
			
		switch( $this->user_interface ) {
			case 'custom':
				?>
				<div id="filelist"><?php _ex( "Your browser doesn't have Flash, Silverlight or HTML5 support.", 'user alert message', self::$field_type ); ?></div>
				<div id='pluploader_<?php echo $field['id'] ?>'>
					<a id="pickfiles" href="javascript:;">[Select files]</a> 
					<a id="uploadfiles" href="javascript:;">[Upload files]</a>
				</div>
				<?php
						
				break;
			default:
				?>
				<div id="filelist"><?php _ex( "Your browser doesn't have Flash, Silverlight or HTML5 support.", 'user alert message', self::$field_type ); ?></div>
				<div id='pluploader_<?php echo $field['id'] ?>'></div>
				<?php
				break;
		}
			
		$plupload_container = ob_get_contents();
		ob_end_clean();
			
		//Run through filter to allow devs to move the div outside the form it they wish
		$input .= apply_filters( 'prso_frm_pluploader_container', $plupload_container, $field );
	    
	    echo $input;
	}
	
	public function get_default_field_settings() {
	    return array(
		    'prso_pluploader_file_extensions'   => isset($this->plugin_options['filter_file_type']) ? $this->plugin_options['filter_file_type'] : 'jpeg,bmp,png,gif',
		    'prso_pluploader_max_files'         => isset($this->plugin_options['max_files']) ? $this->plugin_options['max_files'] : 2,
		    'prso_pluploader_file_size'         => isset($this->plugin_options['max_file_size']) ? $this->plugin_options['max_file_size'] : 1,
		);
	}
	
	public function pluploader_field_settings( $field, $display, $values ) {
		
		if ( self::$field_type != $field['type'] ) {
		    return;
		}
		
		// set default values
		$field = array_merge(self::get_default_field_settings(), $field);
		
		?>
		<tr>
		    <td><label for="prso_pluploader_file_extensions">Allowed file extensions</label></td>
		    <td>
                <input type="text" size="40" name="field_options[prso_pluploader_file_extensions_<?php echo $field['id'] ?>]" id="prso_pluploader_file_extensions" value="<?php echo esc_attr($field['prso_pluploader_file_extensions']) ?>" />
                <div><small>Separated with commas (i.e. jpg, gif, png, pdf)</small></div>
            </td>
        </tr>
        
        <tr>
		    <td><label for="prso_pluploader_max_files">Max number of files</label></td>
		    <td>
                <input type="text" size="40" name="field_options[prso_pluploader_max_files_<?php echo $field['id'] ?>]" id="prso_pluploader_max_files" value="<?php echo esc_attr($field['prso_pluploader_max_files']) ?>" />
                <div><small>Number of files users can upload (defaults to 2)</small></div>
            </td>
        </tr>
        <tr>
            <td><label for="prso_pluploader_file_size">Maximum file size (MB)</label></td>
            <td>
                <input type="text" size="40" id="prso_pluploader_file_size" name="field_options[prso_pluploader_file_size_<?php echo $field['id'] ?>]" value="<?php echo esc_attr($field['prso_pluploader_file_size']) ?>" />
                <div><small>Max file size in MB (defaults to 1MB)</small></div>
            </td>
        </tr>
		<?php
		
	}
	
	/*
	* Update the options in a field
	*/
	public function pluploader_update_field_settings($field_options, $field, $values) {
        if ( $field->type != self::$field_type ) {
            return $field_options;
        }
            
        $defaults = self::get_default_field_settings();

        
        foreach ( $defaults as $opt => $default ) {
            $field_options[$opt] = isset($values['field_options'][$opt .'_'. $field->id]) ? $values['field_options'][$opt .'_'. $field->id] : $default;
        }
            
        return $field_options;
    }
	
	/**
	* pluploader_enqueue_scripts
	* 
	* Used to enqueue any scripts when form is loaded in front end
	* 
	* @access 	public
	* @author	Ben Moody
	*/
	public function pluploader_enqueue_scripts( $field ) {
		
		//Init vars
		$load_pluploader = TRUE;
		
		//Process field args for plupload javascript
		$this->activate_plupload_uploader( $field );
	    
	    //Enqueue plupload core scripts and styles
		$this->enqueue_scripts( TRUE ); 
	}
	
	/**
	* activate_plupload_uploader
	* 
	* Called during 'frm_form_fields' action via $this->pluploader_enqueue_scripts()
	* Loops through any custom field options and caches the vars required to
	* activate the plupload in a class global array - 'prso_pluploader_args'
	* 
	* @access 	public
	* @author	Ben Moody
	*/
	public function activate_plupload_uploader( $field = array() ) {
		
		//Init vars
		global $prso_frm_adv_uploader_options;
		$args = array();
		$output = NULL;
		
		if ( ! empty($field) && isset($field['id']) ) {
			
			//Cache any validation settings for this field
			$args['validation']['allowedExtensions'] = 'jpeg,bmp,png,gif';
			if( isset($field['prso_pluploader_file_extensions']) ) {
				$file_ext_validation = array();
				
				//Explode comma separated values
				$file_ext_validation = explode( ',', esc_attr($field['prso_pluploader_file_extensions']) );
				
				//Loop array of extensions and form a string for javascript array
				if( !empty($file_ext_validation) && is_array($file_ext_validation) ) {
					
					$args['validation']['allowedExtensions'] = NULL;
					
					foreach( $file_ext_validation as $ext ) {
						$args['validation']['allowedExtensions'].= trim($ext) .',';
					}
					
				}
				
			}
			
			//Cache max file size validation option
			if( isset($field['prso_pluploader_file_size']) && !empty($field['prso_pluploader_file_size']) ) {
				$size_limit_mb = (int) $field['prso_pluploader_file_size'];
				
				$args['validation']['sizeLimit'] = $size_limit_mb;
				
			} else {
				$args['validation']['sizeLimit'] = 1;
			}
			
			//Cache max number of files option
			$args['max_files'] = 2;
			if( isset($field['prso_pluploader_max_files']) && !empty($field['prso_pluploader_max_files']) ) {
				$max_files	 		= (int) $field['prso_pluploader_max_files'];
				$args['max_files'] 	= $max_files;
			}
			
			//Cache the file chunking options
			$args['chunking']['enabled'] = 0;
			if( isset($this->plugin_options['chunk_status']) && ($this->plugin_options['chunk_status'] == 1) ) {
				
				$args['chunking']['enabled'] = $this->plugin_options['chunk_size'] . 'mb';
				
			}
			
			//Cache Rename uploaded files option
			$args['rename_file_status'] = true;
			if( isset($this->plugin_options['rename_file_status']) ) {
				$args['rename_file_status'] 	= (bool) $this->plugin_options['rename_file_status'];
			}
			
			//Cache duplicates_status option
			$args['duplicates_status'] = true;
			if( isset($this->plugin_options['duplicates_status']) ) {
				$args['duplicates_status'] 	= (bool) $this->plugin_options['duplicates_status'];
			}
			
			//Cache drag drop option
			$args['drag_drop_status'] = false;
			if( isset($this->plugin_options['drag_drop_status']) ) {
				$args['drag_drop_status'] 	= (bool) $this->plugin_options['drag_drop_status'];
			}
			
			//Cache auto upload option
			$args['auto_upload'] = false;
			if( isset($this->plugin_options['auto_upload_status']) ) {
				$args['auto_upload'] 	= (bool) $this->plugin_options['auto_upload_status'];
			}
			
			//JQuery UI Settings
			$args['list_view'] = true;
			if( isset($this->plugin_options['list_view']) ) {
				$args['list_view'] 	= (bool) $this->plugin_options['list_view'];
			}
			$args['thumb_view'] = false;
			if( isset($this->plugin_options['thumb_view']) ) {
				$args['thumb_view'] 	= (bool) $this->plugin_options['thumb_view'];
			}
			$args['ui_view'] = 'list';
			if( isset($this->plugin_options['ui_view']) ) {
				$args['ui_view'] 	= $this->plugin_options['ui_view'];
			}
			
			//Custom UI Settings
			$args['browse_button_dom_id'] = 'pickfiles';
			if( isset($this->plugin_options['browse_button_dom_id']) ) {
				$args['browse_button_dom_id'] 	= $this->plugin_options['browse_button_dom_id'];
			}
			
			//Cache the unique field identifier plupload action
			$args['element'] = 'pluploader_' . $field['id'];
			
			//Cache the form Id of the form this field belongs to
			$args['form_id'] = (int) $field['form_id'];
			
			//Cache the args
			$this->prso_pluploader_args[$field['id']] = $args;
			
		}
		
	}
	
	/**
	* localize_pluploader_init_vars
	* 
	* Called during 'enqueue_scripts' action if displaying a formidable which contains the pluploader custom field
	* Loops through any custom field options and localizes the variables required by the init javascript file to
	* activate the plupload
	* 
	* @access 	public
	* @author	Ben Moody
	*/
	private function localize_pluploader_init_vars() {
		
		//Init vars
		$pluploader_code 	= NULL;
		$nonce				= NULL;
		$local_vars			= array();
		$local_obj_name		= 'WpPrsoPluploadPluginVars';
		
		if( !empty($this->prso_pluploader_args) && is_array($this->prso_pluploader_args) ) {
			
			//First create nonce string
			$nonce = wp_create_nonce( self::$submit_nonce_key );
			
			//Loop each pluploader field and cache vars required to activate each one
			foreach( $this->prso_pluploader_args as $field_id => $uploader_args ){
				
				//Check for minimum args
				if( isset($uploader_args['element']) ) {
					
					//Plupload element id
					$local_vars[$field_id]['element'] 				= $uploader_args['element'];
					
					//Cache max unmber of file allowed
					$local_vars[$field_id]['max_files'] 			= $uploader_args['max_files'];
					
					//Cache if filenames should be unique
					$local_vars[$field_id]['rename_file_status'] 			= $uploader_args['rename_file_status'];
					
					//Auto upload when files added
					$local_vars[$field_id]['auto_upload'] 			= $uploader_args['auto_upload'];
					
					//Runtimes
					$local_vars[$field_id]['runtimes'] 				= 'html5,flash,silverlight,html4';
					
					//Request url - wp ajax request
					$local_vars[$field_id]['wp_ajax_url'] 			= admin_url('admin-ajax.php');
					
					//Max file size 
					$local_vars[$field_id]['max_file_size'] 		= $uploader_args['validation']['sizeLimit'] . 'mb';
					
					//Enable chunking
					$local_vars[$field_id]['chunking'] 				= $uploader_args['chunking']['enabled'];
					
					//Cache params - Formidable Form ID
					$local_vars[$field_id]['params']['form_id'] 	= $uploader_args['form_id'];
					
					//Cache params - Formidable Field ID
					$local_vars[$field_id]['params']['field_id'] 	= $field_id;
					
					//Cache params - WP Nonce value
					$local_vars[$field_id]['params']['nonce'] 		= $nonce;
					
					//Cache filter - allowed filesize
					$local_vars[$field_id]['filters']['files'] 		= $uploader_args['validation']['allowedExtensions'];
					
					//Cache url to Flash file
					$local_vars[$field_id]['flash_url'] 			= plugins_url( '/inc/js/plupload/Moxie.swf', __FILE__);
					
					//Cache url to Silverlight url
					$local_vars[$field_id]['silverlight_url'] 		= plugins_url( '/inc/js/plupload/Moxie.xap', __FILE__);
					
					//JQuery UI Settings
					$local_vars[$field_id]['list_view']				= $uploader_args['list_view'];
					$local_vars[$field_id]['thumb_view']			= $uploader_args['thumb_view'];
					$local_vars[$field_id]['ui_view']				= esc_attr($uploader_args['ui_view']);
					
					//Custom UI Settings
					$local_vars[$field_id]['browse_button_dom_id']	= esc_attr($uploader_args['browse_button_dom_id']);
					
					//Cache drag drop option
					$local_vars[$field_id]['drag_drop_status']			= $uploader_args['drag_drop_status'];
					
					//Cache duplicates_status option
					$local_vars[$field_id]['duplicates_status']			= $uploader_args['duplicates_status'];
					
					//Set some basic translation strings
					$local_vars[$field_id]['i18n']['server_error'] 		= _x( "Server Error. File might be too large.", 'user error message', self::$field_type );
					$local_vars[$field_id]['i18n']['file_limit_error'] 	= _x( "Max file limit reached", 'user error message', self::$field_type );
					
				}
			}
			
			if( !empty($local_vars) ) {
				//Locallize vars for plupload script
				wp_localize_script( 'prso-pluploader-init', $local_obj_name, $local_vars );
			}
			
		}

	}
	
	/**
	* pluploader_ajax_submit
	* 
	* Called during 'wp_ajax_nopriv_prso-pluploader-submit' && 'wp_ajax_prso-pluploader-submit' ajax actions
	* Handles ajax request from Pluploader script, checks nonce, grabs validation options from frm form meta
	* then passes the validation options to the main File Uploader php script to process and move to server
	*
	* NOTE:: If validation options are not set in frm for this field the script will default to just images <= 0.5mb
	*		Script will not accept any .js or .php ot .html extensions regardless of validation settings.
	* 
	* @access 	public
	* @author	Ben Moody
	*/
	public function plupload_ajax_submit() {
		
		//Init vars
		$validation_args 	= array();
		$nonce_value		= NULL;
		
		//Cache the nonce for this action
		if( isset($_REQUEST['nonce']) ) {
			$nonce_value = esc_attr( $_REQUEST['nonce'] );
		}
		
		//First check nonce field
		if( !isset($nonce_value) || !wp_verify_nonce($nonce_value, self::$submit_nonce_key) ) {
			
			$result['error'] = 'Server error.';
			
			header("Content-Type: text/plain");
			echo json_encode($result);

			exit;
			
		}
		
		//Cache any validation settings passed from frm
		$validation_args = $this->pluploader_file_validation_settings();
		
		// Include the uploader class
		require_once $this->plugin_inc_path . '/qqFileUploader.php';
		
		$uploader = new qqFileUploader();
		
		// Specify the list of valid extensions, ex. array("jpeg", "xml", "bmp")
		if( isset($validation_args['allowedExtensions']) && is_array($validation_args['allowedExtensions']) ) {
			$uploader->allowedExtensions = $validation_args['allowedExtensions'];
		} else {
			//Set security default - images only
			$uploader->allowedExtensions = array('jpeg', 'bmp', 'png', 'gif');
		}
		
		// Specify max file size in bytes.
		if( isset($validation_args['sizeLimit']) && !empty($validation_args['sizeLimit']) ) {
			$uploader->sizeLimit = $validation_args['sizeLimit'];
		} else {
			//Set security default - 0.5 MB
			$uploader->sizeLimit = 0.5 * 1024 * 1024;
		}
		
		//Activate file chunking
		if( isset($validation_args['enable_chunked']) ) {
			$uploader->enable_chunked = $validation_args['enable_chunked'];
		}
		
		//Cache file rename status
		$uploader->rename_files = $validation_args['rename_files'];
		
		//Cache array of mime types to reject
		$uploader->allowed_mimes = $this->allowed_mimes;
		
		//Get wordpress uploads dir path
		$wp_uploads 		= wp_upload_dir();
		$wp_uploads_path 	= NULL;
		$tmp_upload_path	= NULL;
		
		if( isset($wp_uploads['basedir']) ) {
			$wp_uploads_path = $wp_uploads['basedir'];
			$tmp_upload_path = $wp_uploads_path . '/' . self::$prso_pluploader_tmp_dir_name;
			
			// If you want to use resume feature for uploader, specify the folder to save parts.
			$uploader->chunksFolder = $tmp_upload_path . '/chunks';
		}
		
		// Call handleUpload() with the name of the folder, relative to PHP's getcwd()
		$result = $uploader->handleUpload($tmp_upload_path);
		
		// To save the upload with a specified name, set the second parameter.
		// $result = $uploader->handleUpload('uploads/', md5(mt_rand()).'_'.$uploader->getName());
		
		//Encrypt filename before passing back to the dom
		if( isset($result['success']['file_id']) ) {
			$result['success']['file_id'] = $this->name_encrypt( $result['success']['file_id'] );
		}
		
		header("Content-Type: text/plain");
		die( json_encode($result) );
	}
	
	/**
	* pluploader_file_validation_settings
	* 
	* Called by $this->pluploader_ajax_submit()
	* Gets validation options for current field from the formidable form meta data
	* 
	* @access 	public
	* @author	Ben Moody
	*/
	public function pluploader_file_validation_settings() {
		
		//Init vars
		$form_field = array();
		$current_form_id = NULL;
		$current_field_id = NULL;
		$validation_args = array();
		
		//Cache the current form ID
		if( isset($_REQUEST['currentFormID']) && isset($_REQUEST['currentFieldID']) ) {
			
			//Allow devs to hook before we get the form's validation settings
			do_action('prso_frm_pluploader_pre_get_server_validation', TRUE);
			
			$current_form_id = (int) $_REQUEST['currentFormID'];
			
			$current_field_id = (int) $_REQUEST['currentFieldID'];
			
			//Get the field to validate
			$frm_field = new FrmField();
			$form_field = $frm_field->getOne($current_field_id);
			
			if ( $form_field ) {
				
				//This is our field, detect and cache the validation options
				if ( isset($form_field->field_options['prso_pluploader_file_extensions']) && !empty($form_field->field_options['prso_pluploader_file_extensions']) ) {
				    $form_field->field_options['prso_pluploader_file_extensions'] = str_replace(' ', '', $form_field->field_options['prso_pluploader_file_extensions']);
					$validation_args['allowedExtensions'] = explode(',', $form_field->field_options['prso_pluploader_file_extensions']);
				}
						
				if ( isset($form_field->field_options['prso_pluploader_file_size']) && !empty($form_field->field_options['prso_pluploader_file_size']) ) {
					$validation_args['sizeLimit'] = $this->toBytes( $form_field->field_options['prso_pluploader_file_size'] . 'm' );
				}
				
				if ( isset($this->plugin_options['chunk_status']) ) {
					$validation_args['enable_chunked'] = (bool) $this->plugin_options['chunk_status'];
				}
				
			}
			
			//Cache any options from the plugin settings
			
			//Rename uploaded files
			$validation_args['rename_files'] = true;
			if( isset($this->plugin_options['rename_file_status']) ) {
				$validation_args['rename_files'] = (bool) $this->plugin_options['rename_file_status'];
			}
			
		}
		
		//Allow devs to hook before we get the form's validation settings
		$validation_args = apply_filters('prso_frm_pluploader_server_validation_args', $validation_args, $form_field);
		
		return $validation_args;
		
	}
	
	/**
	* save_uploads_as_wp_attachments
	* 
	* Called by 'frm_after_create_entry' formidable action.
	* Detects any pluploader fields, then calls $this->process_uploads
	* to add a wp media library (attachment) post for each file as well as
	* move said file into the wp uploads folder on the server. Then saves
	* a serialized array of wp attachment post id's for each file uploaded
	* into the frm entry's details table.
	* 
	* @access 	public
	* @author	Ben Moody
	*/
	public function save_uploads_as_wp_attachments( $entry_id, $form_id ) {
		
		//Init vars
		$pluploader_field_data	= array();
		$wp_attachment_data		= array();
		$attachment_parent_ID	= NULL;
		
		// if not files were uploaded, don't continue checks
		if ( ! isset($_POST['plupload']) || empty($_POST['plupload']) ) {
		    return;
		}
		
		//First check that this form is using plupload to upload files
		$frm_field = new FrmField();
		$fields = $frm_field->getAll(array('fi.form_id' => $form_id, 'type' => self::$field_type));
		
		if ( empty($fields) ) {
		    //If this form does not contain a plupload custom field, don't continue
		    return;
		}
		
		//First let's cache file upload data for each of our pluploader fields
			
		//Sanitize
		if( is_array($_POST['plupload']) ) {
			foreach( $_POST['plupload'] as $key => $uploaded_files_array ) {
				$pluploader_field_data[$key] = array_map( 'esc_attr', $uploaded_files_array );
			}
		}
		
		//If there is some field data to process let's process it!
		$wp_attachment_data = $this->process_uploads( $pluploader_field_data, $entry_id, $form_id );
		
		// Add ids to global so it will be later linked to a created post
		if ( ! empty($wp_attachment_data) ) {
		    global $frm_vars;
		    foreach ( $wp_attachment_data as $medias ) {
		        foreach ( $medias as $media ) {
		            $frm_vars['media_id'][] = $media;
		        }
		    }
		    
		}
		
		//Action hook for successfully completed uploads
		do_action( 'prso_frm_pluploader_processed_uploads', $wp_attachment_data, $entry_id, $form_id );
		
	}
	
	/**
	* process_uploads
	* 
	* Called by $this->save_uploads_as_wp_attachments()
	* Loops the encoded file names of each upload from the form
	* calling methods to create a wp attachment and move the file into the uploads dir
	* Also calls a method to add a serialized array of all upload attachment post id's into
	* the frm entry details table.
	* 
	* @access 	private
	* @author	Ben Moody
	*/
	private function process_uploads( $pluploader_field_data = array(), $entry_id = false, $form_id = NULL ) {
		
		//Init vars
		$pluploader_wp_attachment_data = array(); //Cache the attachement post id's of each uploaded file for each field 
		
		if ( empty($pluploader_field_data) || ! is_array($pluploader_field_data) || ! $entry_id ) {
		    return $pluploader_wp_attachment_data;
		}
			
		//Loop each pluploader field and process it's files
		foreach ( $pluploader_field_data as $field_id => $file_uploads_array ) {
			
			//Loop each uploaded file for this pluploader field and process them
			if ( is_array($file_uploads_array) && !empty($file_uploads_array) ) {
				
				foreach ( $file_uploads_array as $upload_id => $uploaded_file ) {
					
					//Init vars
					$file_base_name = NULL;
					$attach_id		= NULL;
					
					//Decrypt file id to get the true file name
					$file_base_name = $this->name_decrypt( esc_attr($uploaded_file) );
					
					//Call function to add this file to wp media library and cache it's post id 
					if ( $attach_id = $this->insert_attachment( $upload_id, $file_base_name, $entry_id, $form_id ) ) {
						$pluploader_wp_attachment_data[$field_id][] = $attach_id;
					}
					
				}
				
			}
			
		}
		
		//Finally loop the array of pluploader_wp_attachment_data as update each formidable entry
		if ( !empty($pluploader_wp_attachment_data) ) {
			$frm_entry_meta = new FrmEntryMeta();
			foreach ( $pluploader_wp_attachment_data as $field_id => $field_upload_ids ) {
				$result = FALSE;
				if ( ! empty($field_upload_ids) ) {
					$result = $this->frm_update_entry_details( $entry_id, $field_id, $field_upload_ids );
				}
			}
		}
			
		
		return $pluploader_wp_attachment_data;
	}
	
	/**
	* insert_attachment
	* 
	* Called by $this->process_uploads().
	* Moves uploaded file out of fine uploads tmp dir and into wp uploads dir
	* Then creates a wp attachment post for the file returning it's attachment post id
	* 
	* @access 	public
	* @returns	int		$attach_id - WP attachment post id for file
	* @author	Ben Moody
	*/
	private function insert_attachment( $upload_id = NULL, $file_base_name = NULL, $entry_id = false, $form_id = NULL ) {
		
		//Init vars
		$pluploader_tmp_dir	= NULL;
		$uploaded_file_path	= NULL;
		$wp_dest_file_path	= NULL;
		$wp_upload_dir 		= NULL;
		$wp_filetype		= array();
		$attachment			= array();
		$attach_id			= FALSE;
		$attach_data		= array();
		$post_title			= NULL;
		$move_status		= FALSE;
		$attachment_parent_ID = NULL;
		
		if( isset($upload_id, $file_base_name) && $entry_id ) {
			
			//Allow devs to hook into the functio before getting wp info
			do_action( 'prso_frm_pluploader_pre_insert_attachment' );
			
			//Cache info on the wp uploads dir
			$wp_upload_dir = wp_upload_dir();
			
			//Cache path to plupload tmp directory
			$pluploader_tmp_dir = $wp_upload_dir['basedir'] . '/' . self::$prso_pluploader_tmp_dir_name . '/';
			
			//FILTER - Allow devs to filter the wp_upload_dir array before inserting attachment
			$wp_upload_dir = apply_filters( 'prso_frm_pluploader_wp_upload_dir', $wp_upload_dir );
			
			//Cache tmp location of file on server
			$uploaded_file_path = $pluploader_tmp_dir . $file_base_name;
			
			//Cache destination file path
			$wp_dest_file_path = $wp_upload_dir['path'] . '/' . $file_base_name;
			
			//First let's move this file into the wp uploads dir structure
			$move_status = $this->move_file( $uploaded_file_path, $wp_dest_file_path );
			
			//Check that the file we wish to add exsists
			if( $move_status === TRUE ) {
				
				//Cache file type
				$wp_filetype = wp_check_filetype( $wp_dest_file_path, null );
				
				//Error check
				if( !empty($wp_filetype) && is_array($wp_filetype) ) {
					
					//Has file renaming been enabled
					$_file_rename_status = TRUE;
					if( isset($this->plugin_options['rename_file_status']) ) {
						$_file_rename_status = (bool) $this->plugin_options['rename_file_status'];
					}
					
					//Set file attachment title
					if( $_file_rename_status ) {
						//Create a unique and descriptive post title - associate with form and entry
						$post_title = 'Form ' . esc_attr($form_id) . ' Entry ' . esc_attr($entry_id) . ' Fileupload ' . ($upload_id + 1);
					} else {
						$post_title = esc_attr($file_base_name);
					}
					
					
					//Create the attachment array required for wp_insert_attachment()
					$attachment = array(
						'guid'				=>	$wp_upload_dir['url'] . '/' . basename($wp_dest_file_path),
						'post_mime_type'	=>	$wp_filetype['type'],
						'post_title'		=>	$post_title,
						'post_content'		=>	'',
						'post_status'		=> 'inherit',
						//'post_parent'		=> $attachment_parent_ID
					);
					
					//Insert attachment
					$attach_id = wp_insert_attachment( $attachment, $wp_dest_file_path );
					
					//Error check
					if( $attach_id !== 0 ) {
						
						//Generate wp attachment meta data
						if( file_exists(ABSPATH . 'wp-admin/includes/image.php') && file_exists(ABSPATH . 'wp-admin/includes/media.php') ) {
							require_once(ABSPATH . 'wp-admin/includes/image.php');
							require_once(ABSPATH . 'wp-admin/includes/media.php');
							
							$attach_data = wp_generate_attachment_metadata( $attach_id, $wp_dest_file_path );
							wp_update_attachment_metadata( $attach_id, $attach_data );
						}
						
					} else {
						$attach_id = FALSE;
					}
					
				}
				
			}			
			
		}
		
		//Error detected with file attachment, delete file upload from server
		if( $attach_id === FALSE ) {
			if( file_exists($uploaded_file_path) ) {
				unlink( $uploaded_file_path );
			} elseif( file_exists($wp_dest_file_path) ) {
				unlink( $wp_dest_file_path );
			}
		}
		
		return $attach_id;
	}
	
	/**
	* move_file
	* 
	* Helper to move a file from one path to another
	* Paths are full paths to a file including filename and ext
	* 
	* @access 	private
	* @author	Ben Moody
	*/
	private function move_file( $current_path = NULL, $destination_path = NULL ) {
		
		//Init vars
		$result = FALSE;
		
		if( isset($current_path) && file_exists($current_path) ) {
			
			//First check if destination dir exists if not make it
			if( !file_exists(dirname($destination_path)) ) {		
		        mkdir( dirname($destination_path) );
	        }
			
			if( file_exists(dirname($destination_path)) ) {
			        
		        //Move file into dir
		        if( copy($current_path, $destination_path) ) {
			        unlink($current_path);
			        
			        if( file_exists($destination_path) ) {
				        $result = TRUE;
			        }
			        
		        }
		        
	        }
			
		}
		
		return $result;
	}
	
	/**
	* frm_update_entry_details
	* 
	* Called by $this->process_uploads()
	* Updates the frm details with a serilized array of all the files uploaded
	* into this form entry.
	* Note that the serialized array is inserted into both the lead_details_table and lead_details_long_table
	* It's ok that the string may get truncated in the std details table as frm will then grab the full string
	* from the long details table.
	* 
	* @access 	private
	* @author	Ben Moody
	*/
	private function frm_update_entry_details( $entry_id = NULL, $field_id = NULL, $value = NULL ) {
		
		//Init vars
		global $wpdb;
		$results = array();
		
		if ( isset($entry_id, $field_id, $value) ) {
			
			$frm_entry_meta = new FrmEntryMeta();
    		$results = $frm_entry_meta->get_entry_meta_by_field($entry_id, $field_id);
	        
	        //Insert file upload data
	        if ( empty($results) ) {
		        $frm_entry_meta->add_entry_meta($entry_id, $field_id, $meta_key = null, $value);
	        } else { //Update upload details
	            $value = array_merge((array) $value, (array) $results);
		        $frm_entry_meta->update_entry_meta($entry_id, $field_id, $meta_key = null, $value);
	        }
			
			return TRUE;
		} 
		
		return FALSE;
	}
	
	/**
	* pluploader_display_type
	* 
	* Called by 'frm_display_value_atts' formidable filter.
	* Detects a pluploader field, and sets is to a "file" type
	* so Formidable will display is as media
	* 
	* @access 	public
	* @author	Ben Moody
	*/
    public function pluploader_display_type($atts, $field) {
        if ( $atts['type'] == self::$field_type ) {
            $atts['type'] = 'file';
        }
        
        return $atts;
    }
	
	/**
	* pluploader_delete_entry
	* 
	* Called by 'frm_before_destroy_entry' formidable action.
	* Called when a formidable entry is deleted.
	* Detects if the form contains any plupload fields, gets the wp attachment post id's
	* for each upload. then calls wp_delete_attachment to remove file from media library & server
	* 
	* @access 	public
	* @author	Ben Moody
	*/
	public function pluploader_delete_entry( $entry_id, $entry ) {
		
		//Init vars
		$attachments			= array();
		$file_attachment_ids 	= array();
		$form_id				= $entry->form_id;
		$form 					= array();
		$field_ids				= array();
			
		//First check if we should delete entry files
		$delete_files = $this->delete_files_check( $entry );
		
		if ( $delete_files !== TRUE ) {
		    return;
		}
	    
	    $frm_field = new FrmField();
	    
	    // get all pluploader fields for this form
		$fields = $frm_field->getAll(array('fi.form_id' => $form_id, 'type' => self::$field_type));
		
		if ( empty($fields) ) {
			return;
		}
		
		//Loop any pluploader fields and get any uploads, then delete the wp attachements for each
		foreach( $fields as $field ) {
			$attachments[] = $this->get_entry_detail_long_value( $entry, $field );
		}
		
		//Now loop through each file attachment id and force delete them
		if ( ! empty($attachments) ) {
			foreach ( $attachments as $attachment ) {
				foreach ( (array) $attachment as $attachment_id ) {
				    if ( is_numeric($attachment_id) ){
					    wp_delete_attachment( $attachment_id, TRUE );
					}
				}
			}
		}
		
	}
	
	/**
	* delete_files_check
	* 
	* Helper to check if plupload files should be deleted for
	* an entry
	* 
	* @access 	public
	* @author	Ben Moody
	*/
	private function delete_files_check( $entry ) {
		
		//Init vars
		$delete_files = FALSE;
        
		//Get entry meta for delete files option
		$delete_files = 'checked'; //TODO: Where to save this? gform_get_meta( $entry_id, self::$delete_files_meta_key );
		
		if( $delete_files === 'checked' ) {
			return TRUE;
		}
		
		return FALSE;
	}
	
	/**
	* get_entry_detail_long_value
	* 
	* Helper to get the 'long value' for a specific entry field
	* 
	* @param	object			$entry	    Entry object
	* @param	object			$field	    Field object
	* @return	string		    $results	Attachment ids
	* @access 	public
	* @author	Ben Moody
	*/
	private function get_entry_detail_long_value( $entry, $field ) {
		
		if ( isset($entry->metas) && isset($entry->metas[$field->id]) ) {
		    return $entry->metas[$field->id];
		}
		
		$frm_entry_meta = new FrmEntryMeta();
		$results = $frm_entry_meta->get_entry_meta_by_field($entry->id, $field->id);

		return $results;
	}
	
	/**
	* localize_script_prso_pluploader_entries
	* 
	* Called by $this->enqueue_scripts().
	* Localizes some variables for use in a js script that adds a file delete option
	* to the entry post edit page and warns users of file deletion when sending an entry to the trash
	* 
	* @access 	public
	* @author	Ben Moody
	*/
	private function localize_script_prso_pluploader_entries() {
		
		//Init vars
		$input_field	= NULL;
		$message		= __("NOT deleting uploaded files. To delete any uploaded files, be sure to check Delete Uploads", "prso-frm-plupload");	
		$delete_files 	= NULL;
		$entry_id 		= NULL;
		
		//Set the checkbox input field html
		$input_field = '<div style="padding:10px 10px 10px 0;"><input id="prso_pluploader_delete_uploads" type="checkbox" onclick="" name="prso_pluploader_delete_uploads"><label for="prso_fineup_delete_uploads">&nbsp;'. __("Delete Plupload Uploaded Files", "prso-frm-plupload") .'</label></div>';
		
		//Get entry meta for delete files option
		if( isset($_GET['lid']) ) {
			$entry_id = (int) $_GET['lid'];
		}
		
		$delete_files = 'checked'; //TODO: Where to save this? gform_get_meta( $entry_id, self::$delete_files_meta_key );
		
		wp_localize_script( 
			'prso-pluploader-entries', 
			'prso_frm_pluploader', 
			array('file_delete_message' => $message, 'file_delete_meta' => esc_attr($delete_files), 'input_field_html' => $input_field) 
		);
		
	}
	
	/**
	* name_encrypt
	* 
	* Called by $this->pluploader_ajax_submit()
	* Used to encrypt the file name before sending back to DOM to be stored in input field
	* 
	* @access 	public
	* @author	Ben Moody
	*/
	private function name_encrypt( $file_name = NULL ) {
		
		$key = self::$encrypt_key;
		
		if( isset($file_name, $key) && function_exists('mcrypt_encrypt') ) {
			$file_name = trim(base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $key, $file_name, MCRYPT_MODE_ECB, mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND))));
		}
		
		return $file_name;
	}
	
	/**
	* name_decrypt
	* 
	* Decrytps file names that have been store in form input fields
	* 
	* @access 	public
	* @author	Ben Moody
	*/
	private function name_decrypt( $file_name = NULL ) {
		
		$key = self::$encrypt_key;
		
		if( isset($file_name, $key) && function_exists('mcrypt_decrypt') ) {
			$file_name = trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, base64_decode($file_name), MCRYPT_MODE_ECB, mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND)));
		}
		
		return $file_name;
	}
	
	/**
     * Converts a given size with units to bytes.
     * @param string $str
     */
    private function toBytes($str){
        $val = trim($str);
        $last = strtolower($str[strlen($str)-1]);
        switch($last) {
            case 'g': $val *= 1024;
            case 'm': $val *= 1024;
            case 'k': $val *= 1024;
        }
        return $val;
    }
	
}