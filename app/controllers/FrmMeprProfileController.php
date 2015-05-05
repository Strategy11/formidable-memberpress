<?php

class FrmMeprProfileController{
    public static $min_version = '1.07.02';
    
    function __construct(){
        add_action('mepr_account_home', array(__CLASS__, 'replace_profile_form'));
    }
    
    public static function replace_profile_form() {
        $frm_form = new FrmForm;
        if ( ! $frm_form->getIdByKey( 'memberpress-account' ) ) {
            // TODO: Copy registration settings correctly
            self::_add_form_templates();
        }
        
        // TODO: hide the MemberPress form more gracefully
        echo '<style type="text/css">#mepr_account_form, .mepr_spacer, .mp_wrapper > a, #mepr-account-table, #mepr-member-account-wrapper p small{display:none;}
.mp_wrapper textarea, .mp_wrapper select, .mp_wrapper input[type="text"], .mp_wrapper input[type="url"], .mp_wrapper input[type="email"], .mp_wrapper input[type="tel"], .mp_wrapper input[type="number"], .mp_wrapper input[type="password"] {max-width: 100% !important; padding: 6px 10px !important; width: 100% !important;}
</style>';
        
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

        $file = array( FrmMeprAppHelper::plugin_path() .'/app/forms/form_templates.xml' );
        $result = FrmXMLHelper::import_xml($file);
        unset($file);

        libxml_use_internal_errors( $set_err );
    	libxml_disable_entity_loader( $loader );
    }
}