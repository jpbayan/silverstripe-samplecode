<?php

/*
 * Developed by LittleMonkey Ltd.
 */

/**
 * Description of XeroJobExtension
 *
 * @author Joy
 */
class XeroJobExtension extends DataExtension {
    
    private static $db = array(
        "XeroInvoiceID" => "Varchar(255)",
        "XeroStatus" => "Varchar(255)"
    );    
    
    public function updateCMSFields(\FieldList $fields) {
        $invoice = new TextField("XeroInvoiceID");
        
        $invoiceStat = new TextField("XeroStatus");
        
        $fields->addFieldToTab("Root.Main", $invoice->performReadonlyTransformation());
        $fields->addFieldToTab("Root.Main", $invoiceStat->performReadonlyTransformation());
    }    


}
