<?php

class FrmMeprProfileController{
    public static $min_version = '1.07.02';
    
    function __construct(){
        add_action('mepr_account_home', array(__CLASS__, 'replace_profile_form'));
    }
    
    public static function replace_profile_form() {
        $frm_form = new FrmForm;
        if ( ! $frm_form->getIdByKey( 'memberpress-account' ) ) {
            self::_add_form_templates();
        }
        
        // TODO: hide the MemberPress form more gracefully
        echo '<style type="text/css">#mepr-account-table, #mepr-member-account-wrapper p small{display:none;}</style>';
        
        echo FrmFormsController::get_form_shortcode(array('id' => 'memberpress-account'));
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