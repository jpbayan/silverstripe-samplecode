<?php

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Email\Email;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\Debug;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\EmailField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TreeDropdownField;
use SilverStripe\Omnipay\Exception\Exception;
use SilverStripe\Omnipay\GatewayInfo;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Omnipay\Service\ServiceFactory;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\HTMLEditor\HTMLEditorField;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Control\Session;
/*
 * Developed by LittleMonkey Ltd.
 */

/**
 * Page for collecting a business registration
 *
 * @author Joy
 */
class RegistrationPage extends Page {

    private static $singular_name = 'Business Registration page';
    private static $description = "A page where visitors can register a new business";
    
    private static $db = [
        "PlanHeader"=>"HTMLText",
        "PlanFooter"=>"HTMLText"
    ];        
    
    
    private static $has_one = [
        "SuccessPage" => Page::class,
        "MoreInformationPage" => Page::class
    ];
    
    private static $has_many = [
        'Plans' => RegistrationPlan::class
    ];        
    
    private static $username = "";
    private static $account_id = "";
    private static $password = "";
    private static $testMode = true;

    /**
     * Update CMS fields
     */
    public function getCMSFields() {
        $fields = parent::getCMSFields();

        $fields->addFieldToTab('Root.Main', TreeDropdownField::create('SuccessPageID', "Success Page", SiteTree::class)->setDescription("Choose Page to Connect"));
        $fields->addFieldToTab('Root.Main', TreeDropdownField::create('MoreInformationPageID', "More Information Page", SiteTree::class)->setDescription("Choose Page to Connect"));
        
        $fields->addFieldToTab('Root.Plans', new HTMLEditorField('PlanHeader'));
        
        // Plans
        $cfg = GridFieldConfig_RecordEditor::create(30);
        $fields->addFieldToTab('Root.Plans', new GridField('Plans', 'Plans', $this->Plans(), $cfg) );        
        
        $fields->addFieldToTab('Root.Plans', new HTMLEditorField('PlanFooter'));

        return $fields;
    }
    

}

class RegistrationPageController extends PageController {

    private static $allowed_actions = [
        "Form",
        "success",
        "failed",
        "PlusForm"
    ];
    
    public function selectedPlan()
    {
        return $this->getRequest()->getVar('p');
    }    

    public function Form() {
        $fields = FieldList::create();
        $fields->add(HeaderField::create("BusinessHeader", "Business details"));
        $fields->add(TextField::create("Name", "")->setAttribute("placeholder", "Business / Organization Name"));
        $fields->add($typeDropdown = DropdownField::create("BusinessTypeID", "", BusinessType::get()));
        $typeDropdown->setEmptyString("Business Type");

        $fields->add(NumericField::create("NumberOfEmployees", "")->setAttribute("placeholder", "Number of users"));
        
        $dropdownplan = RegistrationPlan::get()->map('ID', 'Title')->toArray();
        
        $fields->add($planDropdown = DropdownField::create("Plan", "", array('Standard' =>'Free Trial', 'Plus' =>'Standard')));
        
        if ($this->getRequest()->getVar('p')) {
            $planDropdown->setValue("Plus");
        }

        $fields->add(LiteralField::create("PersonalHeaderSpacing", "&nbsp;"));
        $fields->add(HeaderField::create("PersonalHeader", "Your details"));
        $fields->add(TextField::create("FirstName", "")->setAttribute("placeholder", "Name"));
        $fields->add(TextField::create("Surname", "")->setAttribute("placeholder", "Surname"));
        $fields->add(TextField::create("Title", "")->setAttribute("placeholder", "Title"));

        $fields->add(TextField::create("Mobile", "")->setAttribute("placeholder", "Mob"));
        $fields->add(TextField::create("Phone", "")->setAttribute("placeholder", "Tel"));
        $fields->add(EmailField::create("Email", "")->setAttribute("placeholder", "Email"));
        $fields->add(TextareaField::create("Description", "")->setAttribute("placeholder", "Additional Comments"));
        $fields->add(HiddenField::create('AcceptedTermsAndConditions','AcceptedTermsAndConditions'));
        $actions = FieldList::create();
        $actions->add(FormAction::create('doRegister', 'Register'));
        $actions->add(FormAction::create('doTerms', 'Terms & Conditions'));
        $required = RequiredFields::create([
                    'Name',
                    'BusinessTypeID',
                    'NumberOfEmployees',
                    'FirstName',
                    'Surname',
                    'Email',
                    'Mobile'
                    
        ]);
        $form = Form::create($this, 'Form', $fields, $actions, $required);
        
        $form->getSessionData();
        
        return $form;
    }
    
    public function PlusForm() {
        $form = $this->Form();
        $form->Fields()->add(HiddenField::create('Plan','Plan','Plus'));
        
        return $form;
    }
    
    public function doTerms() {
        
    }

    public function doRegister($data, $form, $request) {
        
        if (!$data['AcceptedTermsAndConditions']) {
            $form->sessionMessage("You have to read and agree to the terms and conditions before continuing", "bad");
            $form->setSessionData($data);
            return $this->redirectBack();            
        }
        
        
        $existingMember = Member::get()->filter(["Email" => $data['Email']])->first();
        if ($existingMember) {
            $form->sessionMessage("This user is already registered", "bad");
            $form->setSessionData($data);
            return $this->redirectBack();
        }
        $business = new Business();
        $business->update($data);


        $businessMember = Member::create();
        $businessMember->update($data);
        $businessMember->MemberType = 'Business Owner';
        $businessMember->write();


        $business->BusinessOwnerID = $businessMember->ID;
        $business->write();
        $businessMember->BusinessID = $business->ID;
        $businessMember->write();
        
        $autoLoginToken = $businessMember->generateAutologinTokenAndStoreHash();
        $token = Security::getPasswordResetLink($businessMember, $autoLoginToken);        
        
        if ($data['NumberOfEmployees'] > 50) {
            $this->sendEmail($businessMember, $token);
            if ($this->MoreInformationPageID != 0) {
                return $this->redirect($this->MoreInformationPage()->URLSegment);
            } else {
                return $this->redirectBack();
            }
        }
        
        // use payment gateway if plus plan
        if (isset($data['Plan']) && $data['Plan'] == 'Plus') {
            
            $gateway = GatewayInfo::getSupportedGateways();

            $paymentMethod = 'Click';

            $config = SiteConfig::current_site_config();

            $money = null;
            if ($data['NumberOfEmployees'] >= 20) {
                $money = $config->BigBusinessPrice;
            } else {
                $money = $config->SmallBusinessPrice;
            }
            $payment = Payment::create()->init($paymentMethod, round($money->getAmount() * 1.15, 2), $money->getCurrency())
                    ->setSuccessUrl($this->Link() . 'success/' . $business->ID)
                    ->setFailureUrl($this->Link() . 'failed/' . $business->ID);
            $payment->write();
            
            //set recurring payment per month
            $payment->setRecurring(round($money->getAmount() * 1.15, 2),1,'Month');
            
            // Initiate payment, get the result back
            try {
                $service = ServiceFactory::create()->getService($payment, ServiceFactory::INTENT_PAYMENT)
                        ->initiate(array(
                    "reference" => "GrowTool Subscription Ref". $business->ID,
                    "particular" => $business->Name,
                            "username"=> Config::forClass("Click")->get("username"),
                            "account_id"=>Config::forClass("Click")->get("account_id"),
                            "password"=>Config::forClass("Click")->get("password"),
                            "testMode"=>Config::forClass("Click")->get("testMode")


                ));
            } catch (Exception $ex) {
                // error out when an exception occurs
                Debug::dump($ex->getMessage());
            }

            return $service->redirectOrRespond();            
            
        }
        
        // this is for standard plan
        $this->sendEmail($businessMember, $token);
        if ($this->SuccessPageID != 0) {
            return $this->redirect($this->SuccessPage()->URLSegment);
        } else {
            return $this->redirect("/");
        }        


    }

    /**
     * Send the email to the member that requested a reset link
     * @param Member $member
     * @param string $token
     * @return bool
     */
    protected function sendEmail($member, $token) {
        /** @var Email $email */
        $email = Email::create()
                ->setHTMLTemplate('email\\RegistrationEmail.ss')
                ->setData($member)
                ->setSubject("Thank you for registering")
                ->addData('PasswordResetLink', Security::getPasswordResetLink($member, $token))
                ->setTo($member->Email)
                ->setCC("growtool@peopleandbeanz.com");
        return $email->send();
    }

    public function success($data) {
        $business = Business::get()->byID($this->urlParams['ID']);
        $business->Paid = true;
        $business->write();

        $businessMember = Member::get()->byID($business->BusinessOwnerID);

        $autoLoginToken = $businessMember->generateAutologinTokenAndStoreHash();
        $token = Security::getPasswordResetLink($businessMember, $autoLoginToken);

        $this->sendEmail($businessMember, $token);
        if ($this->SuccessPageID != 0) {
            return $this->redirect($this->SuccessPage()->URLSegment);
        } else {
            return $this->redirect("/");
        }
    }

    public function failed($data) {
        
    }

}
