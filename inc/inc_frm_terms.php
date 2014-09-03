<?php

class PrsoFrmTermsFunctions {
	public static $field_type = 'tos';
	
	//*** PRSO PLUGIN FRAMEWORK METHODS - Edit at your own risk (go nuts if you just want to add to them) ***//
	
	function __construct() {
 		
 		//Hook into WP admin_init
 		$this->admin_init();
 		
	}
	
	/**
	* admin_init
	* 
	* Called in __construct() to fire any methods for
	* WP Action Hook 'admin_init'
	* 
	* @access 	private
	* @author	Ben Moody
	*/
	private function admin_init() {
		
		//*** PRSO PLUGIN CORE ACTIONS ***//
		
		//Add any custom actions
		add_action( 'init', array( $this, 'add_actions' ) );
		
		//Add any custom filter
		add_action( 'after_setup_theme', array( $this, 'add_filters' ) );
		
		
		//*** ADD CUSTOM ACTIONS HERE ***//

		
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
		
		// Adds the input area to the external side
		add_action( 'frm_form_fields', array($this, 'wps_tos_field_input'), 10, 2 );
		
		// Add a custom setting to the tos advanced field
		add_action( 'frm_field_options_form', array($this, 'wps_tos_settings'), 9, 3 );
		
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
		
		// Add a custom field button to the advanced to the field editor
		add_filter( 'frm_pro_available_fields', array($this, 'wps_add_tos_field') );
		
		// save custom settings in a field
		add_filter( 'frm_update_field_options', array($this, 'wps_update_field_settings'), 10, 3 );
		
		// Add a custom class to the field container
		add_filter( 'frm_before_replace_shortcodes', array($this, 'custom_class'), 11, 2 );
		
	}
	
	
	//*** CUSTOM METHODS SPECIFIC TO THIS PLUGIN ***//
	
	public function wps_add_tos_field( $fields ) {
        
        $fields[self::$field_type] = __('Terms of Service');
	
	    return $fields;
	
	}
	
	function wps_tos_field_input ( $field, $field_name, $atts = array() ){
	    
	    if ( $field['type'] != self::$field_type ) {
	        return;
	    }
		
		//Init vars
		$default_tos_value = "Terms of Service -- Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum. Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum. Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.";
		
	    
	    // Add a script to the display of the particular form only if tos field is being used
	    if ( isset($field['field_tos']) && !empty($field['field_tos']) ) {
		    $this->wps_frm_enqueue_scripts();
		}
		
		$value = $field['description'];
		if( empty($field['description']) ) {
			$value = $default_tos_value;
		}
        
        $input = sprintf("<div id='frm-tos-container' class='frminput_container'><textarea readonly name='item_meta[%s]' id='%s' class='textarea frm_tos %s' rows='10' cols='50'>%s</textarea></div>", $field['id'], 'field_'. $field['field_key'] , $field['type'] . ' ', esc_html($value));
        
        //Apply filters to allow devs to move the tos conainter to another location in the dom - e.g. modal box
        $input = apply_filters( 'prso_frm_tos_container', $input, $field);
	
	    echo $input;
	}
	
	public function get_default_field_settings() {
	    return array(
		    'field_tos' => '',
		);
	}

	function wps_tos_settings( $field, $display, $values ){

    	if ( self::$field_type != $field['type'] ) {
    		return;
    	}
        
        $field = array_merge($this->get_default_field_settings(), $field);
        
	    ?>
		<tr>
		    <td>Submit Button</td>
		    <td>
                <input type="checkbox" id="field_tos" name="field_options[field_tos_<?php echo $field['id'] ?>]" value="1" <?php checked($field['field_tos'], 1) ?> />
                
                <label for="field_tos" class="inline">
    	            <?php _e('Disable Submit Button'); ?>
    	        </label>
            </td>
        </tr>
        
	    <?php
	
	}
	
	/*
	* Update the options in a field
	*/
	public function wps_update_field_settings($field_options, $field, $values) {
        if ( $field->type != self::$field_type ) {
            return $field_options;
        }
            
        $defaults = self::get_default_field_settings();

        
        foreach ( $defaults as $opt => $default ) {
            $field_options[$opt] = isset($values['field_options'][$opt .'_'. $field->id]) ? $values['field_options'][$opt .'_'. $field->id] : $default;
        }
            
        return $field_options;
    }
	
	function wps_frm_enqueue_scripts( ) {
		
		$url = plugins_url( '/js/frm_tos.js' , __FILE__ );
	    
	    //Filter script url allowing devs to override the tos behaviour
	    $url = apply_filters( 'prso_frm_tos_script_url', $url );
	    
	    wp_enqueue_script( 'frm_tos_script', $url , array(), '1.0', TRUE );
	
	}

    function custom_class($html, $field){
        if ( $field['type'] == self::$field_type ) {
            $html = str_replace('[required_class]', '[required_class] frm_tos', $html);
        }
        
        return $html;
    }
	
}