<?php

use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Control\Email\Email;
use SilverStripe\Security\Member;
/**
 * OmniPay Extension
 * This class extends omnipay to have CardToken saved into payment model
 *
 * @author Joy
 */
class OmniPayExtension extends DataExtension {
    
    private static $db = array(
        'CardToken'=> 'Varchar'
    );    

    public function onCaptured($response){
        $data = $response->getOmnipayResponse()->getData();
        
        $particular = null;
        $businessid = null;
        $receiptNumber = null;
        $status = null;
        $amount = null;
        $trandate = null;
        $tranId = null;
        
        if (isset($data['ReceiptNumber'])) {
            $receiptNumber = $data['ReceiptNumber'];
        }        
        if (isset($data['Status'])) {
            $status = $data['Status'];
        }                
        if (isset($data['Amount'])) {
            $amount = $data['Amount'];
        }                        
        if (isset($data['TransactionDate'])) {
            $trandate = $data['TransactionDate'];
        }                               
        if (isset($data['TransactionId'])) {
            $tranId = $data['TransactionId'];
        }                                       
        if (isset($data['Particular'])) {
            $particular = $data['Particular'];
        }
        if (isset($data['Reference'])) {
            $ref = str_replace("GrowTool Subscription Ref","",$data['Reference']);
            
            if ($ref) {
                $business = Business::get_by_id("Business", $ref);
                $businessid = $business->ID;
                
                $businessMember = Member::get()->byID($business->BusinessOwnerID);
            }
        }        

        if (isset($data['CardToken'])) {
            $this->owner->CardToken = $data['CardToken'];
            $this->owner->write();
        }

        $recurring = RecurringPayment::get()->filter(array('InitialPaymentID' => $this->owner->ID))->last();

        if ($recurring) {
            $recurring->CardToken = $this->owner->CardToken;
            $recurring->Reference = $this->owner->TransactionReference;
            $recurring->Particular = $particular;
            $recurring->BusinessID = $businessid;
            $recurring->write();            
        }
        
        if ($businessMember) {
            $email = Email::create()
                    ->setHTMLTemplate('email\\SubscriptionReceipt.ss')
                    ->setData($businessMember)
                    ->setSubject("GrowTool Subscription Receipt")
                    ->addData('ReceiptNumber', $receiptNumber)
                    ->addData('Status', $status)
                    ->addData('Amount', $amount)
                    ->addData('TranDate', $trandate)
                    ->addData('TransactionId', $tranId)
                    ->setTo($businessMember->Email)
                    ->setBCC("growtool@peopleandbeanz.com");
            $email->send();                    
        }
        
    }
    
    public function setRecurring($amount, $unit, $frequency) {

        $recurring = new RecurringPayment();
        $recurring->CardToken = $this->owner->CardToken;
        $recurring->StartDate = DBDatetime::now()->value;
        $recurring->Amount = $amount;
        $recurring->ScheduleValue = $unit;
        $recurring->ScheduleFrequency = $frequency;
        $recurring->InitialPaymentID = $this->owner->ID;
        $recurring->Reference = $this->owner->TransactionReference;
        $recurring->write();
        
        
    }

    
}
