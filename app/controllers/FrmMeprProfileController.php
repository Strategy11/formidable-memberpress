<?php

class FrmMeprProfileController{
    public static $min_version = '1.07.02';
    
    function __construct(){
        add_action('mepr_account_home', array(__CLASS__, 'replace_profile_form'));
        add_filter('the_content', array(__CLASS__, 'temp_insert_signup'), 11);
        add_action('frm_validate_entry', array(__CLASS__, 'maybe_trigger_mepr_validation'), 20, 2);
        add_action('frm_after_create_entry', array(__CLASS__, 'process_signup_form'), 45, 2);
    }
    
    public static function replace_profile_form() {
        $form_key = 'memberpress-account';
        $frm_form = new FrmForm;
        if ( ! $frm_form->getIdByKey( $form_key ) ) {
            self::_add_form_templates();
        }
        
        // TODO: hide the MemberPress form more gracefully
        echo '<style type="text/css">#mepr-account-table, #mepr-member-account-wrapper p small{display:none;}</style>';
        
        echo FrmFormsController::get_form_shortcode(array('id' => $form_key));
    }
    
    public static function temp_insert_signup($content) {
        if ( strpos($content, '<form id="mepr_logged_in_purchase"') ) {
            $new_form = self::replace_signup_form();
            $pattern = "/<form id=\"mepr_logged_in_purchase\"(.*?)<\/form>/is";
            $content = preg_replace($pattern, $new_form, $content);
        }
        
        return $content;
    }
    
    public static function replace_signup_form() {
        $form_key = 'mepr-signup';
        $frm_form = new FrmForm;
        if ( ! $frm_form->getIdByKey( $form_key ) ) {
            self::_add_form_templates();
        }
        
        $post = MeprUtils::get_current_post();
        $product = new MeprProduct($post->ID);
        
        add_filter('frm_field_type', array(__CLASS__, 'hide_signup_fields'), 10, 2);
        add_filter('frm_get_default_value', array(__CLASS__, 'set_product_id'), 10, 2);
        add_filter('frm_setup_new_fields_vars', array(__CLASS__, 'populate_payment_opts'), 20, 2);
        add_action('frm_validate_entry', array(__CLASS__, 'trigger_mepr_validation'), 20, 2);
        
        return FrmFormsController::get_form_shortcode(array('id' => $form_key, 'mepr-product' => $product->ID));
    }
    
    // hide fields conditionally based on settings
    public static function hide_signup_fields($type, $field) {
        if ( in_array($field->field_key, array('mepr-fname', 'mepr-lname', 'mepr-coupon')) ) {
            $mepr_options = MeprOptions::fetch();
            
            $show_it = array(
                'mepr-fname' => $mepr_options->show_fname_lname,
                'mepr-lname' => $mepr_options->show_fname_lname,
                'mepr-coupon' => ! $mepr_options->coupon_field_enabled,
            );
            
            if ( ! $show_it[$field->field_key] ) {
                $type = 'hidden';
            } else if ( 'mepr-coupon' == $field->field_key ) {
                $product = new MeprProduct($_GET['mepr-product']);
                if ( $product->adjusted_price() <= 0.00 ) {
                    $type = 'hidden';
                }
            }
        }
        
        return $type;
    }
    
    // set the product id value in the hidden field
    public static function set_product_id($value, $field) {
        if ( 'mepr-product' == $field->field_key ) {
            $value = $_GET['mepr-product'];
        }
        
        return $value;
    }
    
    public static function populate_payment_opts($values, $field) {
        if ( 'mepr-pay' != $field->field_key ) {
            return $values;
        }
        
        $product = new MeprProduct($_GET['mepr-product']);
        $mepr_options = MeprOptions::fetch();
        $values['value'] = '';

        $active_pms = $pms = $product->payment_methods();
        if ( count($active_pms) > 1 ) {
            $values['use_key'] = true;
            
            foreach ( $pms as $pm_id ) {
                $obj = $mepr_options->payment_method($pm_id);
                $values['value'] = array();
                if ( $obj instanceof MeprBaseRealGateway ) {
                    $values['value'][$obj->id] = $obj->label; 
                }
            }
        } else {
            $values['type'] = 'hidden';
            if ( $pm = $mepr_options->payment_method(array_shift($active_pms)) ) {
                $values['value'] = $pm->id; 
            }
        }
        
        return $values;
    }
    
    public static function maybe_trigger_mepr_validation($errors, $values) {
        $frm_form = new FrmForm();
        $id = $frm_form->getIdByKey( 'mepr-signup' );
        
        // check if the signup form is being validated
        if ( $id != $values['form_id'] ) {
            return $errors;
        }
        
        return self::trigger_mepr_validation($errors, $values);
    }
    
    public static function trigger_mepr_validation($errors, $values) {
        self::map_to_mepr();
        
        $errors = MeprUser::validate_signup($_POST, $errors);
        $errors = apply_filters('mepr-validate-signup', $errors);
        
        return $errors;
    }
    
    public static function process_signup_form($entry_id, $form_id) {
        if ( $_POST && isset($_POST['mepr_process_signup_form']) ) {
            $frm_form = new FrmForm();
            $form = $frm_form->getOne($form_id);
            FrmEntriesController::delete_entry_before_redirect('', $form, array('id' => $entry_id));
            
            MeprUsersController::process_signup_form();
        }
    }
    
    private static function map_to_mepr() {
        // field key => mp names
        $map = array(
            'mepr-product'  => 'mepr_product_id',
            'mepr-fname'    => 'user_first_name',
            'mepr-lname'    => 'user_last_name',
            'mepr-email'    => 'user_email',
            'mepr-coupon'   => 'mepr_coupon_code',
            'mepr-pay'      => 'mepr_payment_method',
        );
        
        $frm_fields = new FrmField();
        $fields = $frm_fields->getAll(array('fi.form_id' => $_POST['form_id']));
        foreach ( $fields as $field ) {
            if ( isset($map[$field->field_key]) && isset($_POST['item_meta'][$field->id]) ) {
                $_POST[$map[$field->field_key]] = $_POST['item_meta'][$field->id];
            } else if ( isset($_POST['item_meta'][$field->id]) ) {
                // TODO: What do do with extra fields?
                $_POST[$field->field_key] = $_POST['item_meta'][$field->id];
            }
            unset($field);
        }
        
        if ( is_user_logged_in() ) {
            $_POST['logged_in_purchase'] = 1;
        }
        
        $_POST['mepr_process_signup_form'] = 'Y';
        
        // add missing vars to REQUEST
        $_REQUEST = array_merge($_POST, $_REQUEST);
    }
    
    private static function _add_form_templates() {
        if ( !function_exists( 'libxml_disable_entity_loader' ) ){
    		// XML import is not enabled on your server
    		return;
    	}

        include_once(FrmAppHelper::plugin_path() .'/classes/helpers/FrmXMLHelper.php');

        $set_err = libxml_use_internal_errors(true);
        $loader = libxml_disable_entity_loader( true );

        $file = FrmMeprAppHelper::plugin_path() .'/app/forms/form_templates.xml';
        $result = FrmXMLHelper::import_xml($file);
        unset($file);

        libxml_use_internal_errors( $set_err );
    	libxml_disable_entity_loader( $loader );
    }
}