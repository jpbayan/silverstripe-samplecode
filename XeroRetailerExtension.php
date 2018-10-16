<?php

/*
 * Developed by LittleMonkey Ltd.
 */

/**
 * Description of XeroRetailerExtension
 *
 * @author Joy
 */
class XeroRetailerExtension extends DataExtension {

    private static $db = array(
        "ApplicationName"=>"Varchar(255)",
        "ConsumerKey" => "Varchar(30)",
        "ConsumerSecret" => "Varchar(30)",
        "ApiEndpoint" => "Varchar(255)",
        "ApplicationType" => "Enum('Private,Public,Partner','Private')",
        "invoicePrefix"=>"Varchar(255)",
        "defaultAccountCode"=>"Varchar(255)"
    );
    
    private static $has_one = array(
        "PublicKey"=>"File",
        "PrivateKey"=>"File"
    );
    
    public function updateCMSFields(\FieldList $fields) {
        $fields->addFieldToTab("Root.Xero", new TextField("ApplicationName"));
        $fields->addFieldToTab("Root.Xero", new TextField("ConsumerKey"));
        $fields->addFieldToTab("Root.Xero", new TextField("ConsumerSecret"));
        $fields->addFieldToTab("Root.Xero", new TextField("ApiEndpoint"));
        $fields->addFieldToTab("Root.Xero", new DropdownField("ApplicationType","Application Type",singleton('RetailerCompany')->dbObject('ApplicationType')->enumValues()));
        $fields->addFieldToTab("Root.Xero", new TextField("invoicePrefix"));
        $fields->addFieldToTab("Root.Xero", new TextField("defaultAccountCode"));
        
        $fields->addFieldToTab("Root.Xero", $public = UploadField::create("PublicKey")->setAllowedExtensions(array("cer")));
        $fields->addFieldToTab("Root.Xero", $private = new UploadField("PrivateKey"));
        //$public->setAllowedExtensions(array("cer"));
        $public->setRightTitle(".cer file generated from openssl");
        $private->setAllowedExtensions(array("pem"));
        $private->setRightTitle(".pem file generated from openssl and matching the public key above");
        
    }

}
