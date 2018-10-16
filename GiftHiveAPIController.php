<?php

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\Email\Email;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\RestfulServer\DataFormatter\JSONDataFormatter;

/*
 * Developed by LittleMonkey Ltd.
 */

/**
 * The API controller for communication between the SilverStripe backend
 * and the frontend
*To get friends list
*http://martine/api/friendlist/

 * @author Joy
 */
class GiftHiveAPIController extends Controller {

    private static $allowed_actions = [
        "login",
        "register",
        "friendlist",
        "isemailexist",
        "checkauthentication",
        "allinvitations",
        "acceptrejectinvitation",
        "allcategories",
        "showproducts",
        "createinvitation",
        "updatefriend",
        "invitationlist",
        "showproductsforfriend",
        "setfirebasetoken",
        "othersinvitationlist",
        "testPushNotification",
        "addfriend",
        "fetchmydetails",
        "updatemydetails",
        "shareme"
    ];
    
    private static $hidden_fields = [
        "ClassName",
        "LastEdited",
        "Created",
        "RecordClassName",
        "_SortColumn0",
        "ProductImageID",
        "ID",
        "Active",
        "SupplierID",
        "CountryCode",
        "BackAccountName",
        "BackAccountNumber",
        "Currency",
        "IconID"
    ];    
    
    private function tidyMap(&$map)
    {
        foreach (static::$hidden_fields as $field) {
            unset($map[$field]);
        }
    }    

    /**
     *If you need to login use 
    * parameter requires username
    * parameter requires password
    * 
    * {
    *   "credential":{
    *   "username": "a@a.a",
    *   "password": "test"
    *   }
    * }
    * 
    * after login the newly created uniqe token must be written in member
    *  return ok
    */    
    public function login() {
        
        $string = $this->getRequest()->getBody();
        
        $jsn = json_decode($string, true);
        
        $credentials = $jsn['credential'];
        $username = null;
        $password = null;
        if (isset($credentials['username'])) {
            $username = $credentials['username'];
        }
        if (isset($credentials['password'])) {
            $password = $credentials['password'];
        }        
        
        if (!$username) {
            return "username required";
        }
        
        if (!$password) {
            return "password required";
        }        
        
        $member = Member::get()->filter(array("Email" => $username))->First();
        
        if (!$member) {
            return "Unauthorized";
        } 
        
        $checkResult = $member->checkPassword($password);

        if (!$checkResult->isValid()) {
            return "invalid password";
        }         
        
        $member->logIn(true);
        $token = uniqid();
        $member->Token = $token;
        $member->write();
        
        $response = $this->getResponse();
        $response->setBody($token);
        
        return $this->getResponse();                 
    }
    
    /**
     *If you need share and add you as friend without sending invites
    * parameter requires username
    * parameter requires password
    * 
    * {
    *   "credential":{
    *   "username": "a@a.a",
    *   "password": "test"
    *   }
    * }
    * 
    * after login the newly created uniqe token must be written in member
    *  return ok
    */        
    public function shareme() {
        
        $member = $this->checkauthentication();
        
        if (!$member) {
            return "Unauthorized";
        }             
        
        $pageAddMe = AddMePage::get()->first();
        
        $link = $pageAddMe->absoluteLink() ."?st=". $member->ShareToken;
        
        $response = $this->getResponse();
        $response->setBody($link);
        
        return $this->getResponse();    
        
    }
    
    /**
    * To register gifthive
    * parameter requires username
    * parameter requires password
    * parameter string firstname
    * parameter string lastname
    * parameter date birthdate
    * parameter string homeaddress
    * parameter string workaddress
    * parameter string gender (Male/Female)
    * {
    *   "credential":{
    *   "username": "a@a.a",
    *   "password": "test",
    *   "firstname": "a",
    *   "lastname": "b",
    *   "birthdate": "2000-12-20",
    *   "homeaddress": "test test",
    *   "workaddress": "test",
    *   "gender": "Male"
    *  }
    * }     
    *  success returns ok
    */        
    public function register() {
     
        $string = $this->getRequest()->getBody();
        
        $jsn = json_decode($string, true);
        
        $credentials = $jsn['credential'];
        $username = null;
        $password = null;
        $firstname = null;
        $lastname = null;
        $bdate= null;
        $homeaddress = null;
        $workaddress = null;
        $gender = null;
        
        if (isset($credentials['username'])) {
            $username = $credentials['username'];
        }
        if (isset($credentials['password'])) {
            $password = $credentials['password'];
        }        
        if (isset($credentials['firstname'])) {
            $firstname = $credentials['firstname'];
        }
        if (isset($credentials['lastname'])) {
            $lastname = $credentials['lastname'];
        }                
        if (isset($credentials['birthdate'])) {
            $bdate = $credentials['birthdate'];
        }                
        if (isset($credentials['homeaddress'])) {
            $homeaddress = $credentials['homeaddress'];
        }
        if (isset($credentials['workaddress'])) {
            $workaddress = $credentials['workaddress'];
        }
        if (isset($credentials['gender'])) {
            $gender = $credentials['gender'];
        }        
        
        if (!$username) {
            return "username required";
        }
        
        if (!$password) {
            return "password required";
        }        
        
        $member = Member::get()->filter(array("Email" => $username))->First();
        
        if ($member) {
            if ($member->BirthDate || $member->HomeAddress || $member->WorkAddress || $member->Gender || $member->Token) {
                return "username already used";
            }
            else {
                $newmember = $member;
            }
        } 
        else {
            $newmember = new Member();
            $newmember->Email = $username;
        }
        
        $newmember->Password = $password;  
        $newmember->FirstName = $firstname;  
        $newmember->Surname = $lastname;  
        $newmember->BirthDate = $bdate;  
        $newmember->HomeAddress = $homeaddress;  
        $newmember->WorkAddress = $workaddress;  
        $newmember->Gender = $gender;  
        
        $token = uniqid();
        $newmember->Token = $token;
        
        $sharetoken = uniqid();
        $newmember->ShareToken = $sharetoken;        
        
        $newmember->write();
        
        $newmember->logIn(true);
        
        $response = $this->getResponse();
        $response->setBody($token);
        
        return $this->getResponse();                         
        
    }
    
    /**
    * To check if email exist
    * parameter requires email
    * {
        "email": "valid@email.address"
      }
    * 
    *  success returns true / 1
    */        
    public function isemailexist() {
        $string = $this->getRequest()->getBody();
        
        $jsonstring = json_decode($string, true);    
        
        if (isset($jsonstring['email'])) {
            $email = $jsonstring['email'];
        }    
        else {
             return "email required";
        }
        
        $member = Member::get()->filter(array("Email" => $email))->First();
        
        if ($member) {
            if ($member->BirthDate || $member->HomeAddress || $member->WorkAddress || $member->Gender || $member->Token) {
                return true;
            }
            else {
                return false;
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
    * To fetch friends list
    * parameter requires token in header x-auth-token
    *  success returns friend object in json format
    */        
    public function friendlist() {
        
        $member = $this->checkauthentication();
        
        if (!$member) {
            return "Unauthorized";
        }     
        
        $result = new stdClass();
        
        $friendArray = array();
        foreach ($member->Friends() as $friend){
            
            $friendObj = $friend->Friend();
            
            $friendArray[] = array('Name'=>$friendObj->Name, 'Email'=>$friendObj->Email, 'BirthDate'=>$friendObj->BirthDate, 'HomeAddress'=>$friendObj->HomeAddress, 'WorkAddress'=>$friendObj->WorkAddress, 'Gender'=>$friendObj->Gender, 'Budget'=>$friend->Budget, 'Currency'=>$friend->Currency);
            
        }
        
        $result->Friends = $friendArray;
        
        return json_encode($result);
        
        
    }    
    
    /**
    * To fetch invitation list
    * parameter requires token in header x-auth-token
    *  success returns friend object in json format
    */        
    public function invitationlist() {
        
        $member = $this->checkauthentication();
        
        if (!$member) {
            return "Unauthorized";
        }     
        
        $result = new stdClass();
        
        $friendArray = array();
        $PendingArray = array();
        $AcceptedArray = array();
        $DeclineArray = array();
        foreach ($member->Invitations() as $invitation){
            
            $inviteObj = $invitation->Invited();
            
            $friendArray[] = array('Name'=>$inviteObj->Name, 'Email'=>$inviteObj->Email, 'Status'=>$invitation->Status);
            
            if ($invitation->Status == 'Pending') {
                $PendingArray[] = $inviteObj->Email;
            }
            if ($invitation->Status == 'Accepted') {
                $AcceptedArray[] = $inviteObj->Email;
            }            
            if ($invitation->Status == 'Decline') {
                $DeclineArray[] = $inviteObj->Email;
            }                        
            
        }
        
        $result->Pending = $PendingArray;
        $result->Accepted = $AcceptedArray;
        $result->Decline = $DeclineArray;
        
        return json_encode($result);
        
        
    }        
    
    /**
    * To fetch other's invitation list
    * parameter requires token in header x-auth-token
    *  success returns friend object in json format
    */        
    public function othersinvitationlist() {
        
        $member = $this->checkauthentication();
        
        if (!$member) {
            return "Unauthorized";
        }     
        
        $result = new stdClass();
        
        $PendingArray = array();
        $AcceptedArray = array();
        $DeclineArray = array();
        
        $invitationObj = Invitation::get()->filter(array("Invited.Email" => $member->Email));
        
        //foreach ($member->Invitations() as $invitation){
        foreach ($invitationObj as $invitation){
            
            $inviteObj = $invitation->Sender();
            
            if ($invitation->Status == 'Pending') {
                $PendingArray[] = $inviteObj->Email;
            }
            if ($invitation->Status == 'Accepted') {
                $AcceptedArray[] = $inviteObj->Email;
            }            
            if ($invitation->Status == 'Decline') {
                $DeclineArray[] = $inviteObj->Email;
            }                        
            
        }
        
        $result->Pending = $PendingArray;
        $result->Accepted = $AcceptedArray;
        $result->Decline = $DeclineArray;
        
        return json_encode($result);
        
        
    }            
    
    /**
    * To check authentication 
    * parameter requires token in header x-auth-token
    *  success returns member object
    * fail returns null
    */            
    public function checkauthentication() {
        $token = $this->getRequest()->getHeader('x-auth-token');
        
        if (!$token) {
            return null;
        }
        
        $member = null;
        if ($token) {
            $member = Member::get()->filter(array("Token" => $token))->First();
        }        
        
        if (!$member) {
            return null;
        }             
        
        if (!$member->ShareToken) {
            $sharetoken = uniqid();
            $member->ShareToken = $sharetoken;            
        }
        
        $member->write();        
        
        return $member;
    }
    
    /**
    * To fetch my details
    * parameter requires token in header x-auth-token
    * success returns object [FirstName,Surname, BirthDate, HomeAddress, WorkAddress, Gender, Email, sharetoken]in json format
    */            
    public function fetchmydetails() {
        
        $member = $this->checkauthentication();
        
        if (!$member) {
            return "Unauthorized";
        }         
        
        $result = new stdClass();
    
        $result->firstname = $member->FirstName;
        $result->lastname = $member->Surname;
        $result->username = $member->Email;
        $result->birthdate = $member->BirthDate;
        $result->homeaddress = $member->HomeAddress;
        $result->workaddress = $member->WorkAddress;
        $result->gender = $member->Gender;
        $result->sharetoken = $member->ShareToken;
        
        return json_encode($result);
    }        
    
    /**
    * To update my details
    * parameter requires token in header x-auth-token
    * 
    * {
    *  "firstname": "XXXXXXXXX",
    *  "lastname": "YYYYYYYY",
    *  "username": "a@a.a",
    *  "birthdate": "yyyy-mm-dd",
    *  "homeaddress": "aaa bbbb sssss dddd",
    *  "workaddress": "eee ffff hhhh dddd",
    *  "gender": "Male/Female"
    * }
    * 
    *  return ok
    */    

    public function updatemydetails() {
        
        $member = $this->checkauthentication();
        
        if (!$member) {
            return "Unauthorized";
        }      
        
        $string = $this->getRequest()->getBody();
        
        $jsn = json_decode($string, true);
        
        $firstname = null;
        $surname = null;
        $email = null;
        $birthdate = null;
        $homeaddress = null;
        $workaddress = null;
        $gender = null;
        if (isset($jsn['firstname'])) {
            $firstname = $jsn['firstname'];
        }
        if (isset($jsn['lastname'])) {
            $surname = $jsn['lastname'];
        }        
        
        if (!$firstname && !$surname) {
            return "name is required";
        }                                
        
        if (isset($jsn['username'])) {
            $email = $jsn['username'];
        }                        
        
        if (!$email) {
            return "username is required";
        }                                
        
        $isexist = Member::get()->filter(array("Email" => $email))->First();
        
        if (($email != $member->Email) && $isexist) {
            return "email already exist";
        }
        
        if (isset($jsn['birthdate'])) {
            $birthdate = $jsn['birthdate'];
        }                        
        
        if (!$birthdate) {
            return "birth date is required";
        }                        
        
        if (isset($jsn['homeaddress'])) {
            $homeaddress = $jsn['homeaddress'];
        }
        if (isset($jsn['workaddress'])) {
            $workaddress = $jsn['workaddress'];
        }
        
        if (!$homeaddress && !$workaddress) {
            return "address is required";
        }
        
        if (isset($jsn['gender'])) {
            $gender = $jsn['gender'];
        }

        if (!$gender) {
            return "gender is required";
        }        
        
        $member->FirstName = $firstname;
        $member->Surname = $surname;
        $member->Email = $email;        
        $member->BirthDate = $birthdate;       
        $member->HomeAddress = $homeaddress;       
        $member->WorkAddress = $workaddress;       
        $member->Gender = $gender;       
        $member->write();
        
        $response = $this->getResponse();
        $response->setBody('ok');
        
        return $this->getResponse();    
    }            
    
    /**
    * To fetch invitation status
    * parameter requires token in header x-auth-token
    *  success returns total number of invitation, pending, accepted and declined in json format
    */            
    public function allinvitations() {
        
        $member = $this->checkauthentication();
        
        if (!$member) {
            return "Unauthorized";
        }         
        
        $result = new stdClass();
        
        $result->TotalInvitation = $member->Invitations()->Count();
        $result->Pending = $member->Invitations()->filter(array("Status" => "Pending"))->Count();
        $result->Accepted = $member->Invitations()->filter(array("Status" => "Accepted"))->Count();
        $result->Decline = $member->Invitations()->filter(array("Status" => "Decline"))->Count();
        
        return json_encode($result);
        

    }    
    
    /**
    * Accepting or Reject invitation for exisitng members
    * parameter requires token in header x-auth-token
    * parameter requires invitationtoken
    * parameter requires string action either Accepted or Decline
    * 
    * {
    *  "invitationtoken": "XXXXXXXXX",
    *  "email": "a@a.a",
    *  "action": "Accepted"
    * }
    * 
    *  return ok
    */    
    public function acceptrejectinvitation() {
        
        $member = $this->checkauthentication();
        
        if (!$member) {
            return "Unauthorized";
        }         
        
        $string = $this->getRequest()->getBody();
        
        $jsn = json_decode($string, true);
        
        $invitationtoken = null;
        $action = null;
        $email = null;
        if (isset($jsn['invitationtoken'])) {
            $invitationtoken = $jsn['invitationtoken'];
        }
        if (isset($jsn['email'])) {
            $email = $jsn['email'];
        }        
        if (isset($jsn['action'])) {
            $action = $jsn['action'];
        }                
        
        if (!$invitationtoken && !$email) {
            return "invitation token or email is required";
        }        
        
        if (!$action) {
            return "action required";
        }                
        
        if ($invitationtoken) {
            $invitationObj = Invitation::get()->filter(array("InvitationToken" => $invitationtoken))->first();
        }
        else {
            $invitationObj = Invitation::get()->filter(array("Invited.ID" => $member->ID, "Sender.Email" => $email))->first();
        }
        
        
        if (!$invitationObj) {
            return "invitation not found";
        }                
        
        $invitationObj->Status = $action;
        $invitationObj->write();
        
        $sendermember = $invitationObj->Sender();
        
        if ($action == 'Accepted') {
            //create friend relationship from the sender
            $friendObj = new FriendList();
            $friendObj->FriendID = $member->ID;
            $friendObj->HostID = $sendermember->ID;
            $friendObj->write();    
            
            $createrelationship = $sendermember->Friends();
            $createrelationship->add($friendObj);            
            
            //create friend relationship to whom accept the invitation
//            $friendObj = new FriendList();
//            $friendObj->FriendID = $sendermember->ID;
//            $friendObj->HostID = $member->ID;
//            $friendObj->write();    
//            
//            $createrelationship = $member->Friends();
//            $createrelationship->add($friendObj);                        
        }
        
        if ($invitationtoken) {
                $message = "Youre invitation has been ". lcfirst($action)  ;
                $this->ProcessPushNotification($sendermember, "Gifthive Invitation", $message, $invitationtoken);
        }        
        
        $response = $this->getResponse();
        $response->setBody('ok');
        
        return $this->getResponse();         
        
    }   
    
    /**
    * Add friend relationship, this api is best called when after you accepted invite
    * parameter requires token in header x-auth-token
    * 
    * {
    *  "email": "a@a.a",
    * }
    * 
    *  return list of friends
    */        
    public function addfriend() {

        $member = $this->checkauthentication();
        
        if (!$member) {
            return "Unauthorized";
        }         
        
        $string = $this->getRequest()->getBody();
        
        $jsn = json_decode($string, true);
        
        $email = null;
        if (isset($jsn['email'])) {
            $email = $jsn['email'];
        }        
        
        if (!$email) {
            return "friend's email is required";
        }        
        
        $acceptafriend = Member::get()->filter(array("Email" => $email))->First();
        
        if (!$acceptafriend) {
            return "friend's email does not exist";
        }
        
        $friendObj = new FriendList();
        $friendObj->FriendID = $acceptafriend->ID;
        $friendObj->HostID = $member->ID;
        $friendObj->write();    

        $createrelationship = $member->Friends();
        $createrelationship->add($friendObj);
        
        $result = new stdClass();
        
        $friendArray = array();
        foreach ($member->Friends() as $friend){
            
            $friendObj = $friend->Friend();
            
            $friendArray[] = array('Name'=>$friendObj->Name, 'Email'=>$friendObj->Email, 'BirthDate'=>$friendObj->BirthDate, 'HomeAddress'=>$friendObj->HomeAddress, 'WorkAddress'=>$friendObj->WorkAddress, 'Gender'=>$friendObj->Gender, 'Budget'=>$friend->Budget, 'Currency'=>$friend->Currency);
            
        }
        
        $result->Friends = $friendArray;
        
        return json_encode($result);        

        
    }
    
    /**
    * create invitation 
    * parameter requires token in header x-auth-token
    * parameter array invited
    * 
    * {
    *   "newinvitation":{
    *   "invited":["g@g.g","c@c.c","d@d.d"]
    *   }
    * }
    * 
    *  return ok
    */    
    public function createinvitation() {
        
        $member = $this->checkauthentication();
        
        if (!$member) {
            return "Unauthorized";
        }         
        
        $string = $this->getRequest()->getBody();
        
        $jsn = json_decode($string, true);
        
        $invitation = $jsn['newinvitation'];
        $invidtedids = array();

        if (isset($invitation['invited'])) {
            $invidtedids = $invitation['invited'];
        }
        
        if (!$invitation) {
            return "parameter required";
        }
        
        if (!$invidtedids) {
            return "invitation emails required";
        }                
        
        foreach ($invidtedids as $email) {
            
            if (!$email || $email == '' || $email == ' ') {
                continue;
            }
            
            $memObj = Member::get()->filter(array("Email" => $email))->First();
            
            // if member not exist create new record
            if (!$memObj) {
                $newmember = new Member();
                $newmember->Email = $email;
                
                $newmember->write();                            
                $memObj = $newmember;
            }   
            else {
                // check if the invited person already listed the host as a friend
                $invitedcheckfriend = $memObj->Friends()->filter(array("Friend.Email" => $member->Email))->first();

                if ($invitedcheckfriend) {
                    $friendObj = new FriendList();
                    $friendObj->FriendID = $memObj->ID;
                    $friendObj->HostID = $member->ID;
                    $friendObj->write();    

                    $createrelationship = $member->Friends();
                    $createrelationship->add($friendObj);                    
                    
                    continue;
                }
                
            }
            
            //check if already invited
            $relationshipexist = $member->Invitations()->filter(array("InvitedID" => $memObj->ID))->First();
            $invitationtoken = uniqid();
            
            if ($relationshipexist) {
                //$relationshipexist->InvitationToken = $invitationtoken;
                //$relationshipexist->write();
                continue;
            }
            else {
                //create relationship invited
                $invObj = new Invitation();
                $invObj->InvitedID = $memObj->ID;
                $invObj->SenderID = $member->ID;
                $invObj->InvitationToken = $invitationtoken;
                $invObj->write();    

                $createrelationship = $member->Invitations();
                $createrelationship->add($invObj);                
            }
            
            $message = "You have an invitation from ". $member->Name ;
            
            $this->sendEmail($memObj, "You have an invitation", $message, $invitationtoken);
            
            if ($invitationtoken) {
                $this->ProcessPushNotification($memObj, "Gifthive Invitation", $message, $invitationtoken);
            }
            
        }   
        
        $pendinginvitation = new stdClass();

        $member->Invitations()->filter(array("Status" => 'Pending'));
        
        $pendingObj = $member->Invitations()->filter(array("Status" => 'Pending'));
        $pendingArray = array();
        
        foreach ($pendingObj as $pendinginvite) {
            $pendingArray[] = $pendinginvite->Invited()->Email;
        }
        
        $pendinginvitation->Pending = $pendingArray;
        
        return json_encode($pendinginvitation);                
        
    }       
    
    /**
    * To fetch all categories
    * parameter requires token in header x-auth-token
    *  success returns category object
    */            
    public function allcategories() {
        
        $member = $this->checkauthentication();
        
        if (!$member) {
            return "Unauthorized";
        }         
        
        $result = new stdClass();
        
        $categoriesArray = Category::get()->map("ID","Name")->toArray();
        
        $result->Categories = $categoriesArray;
        
        return json_encode($result);        
        

    }    
    
    /**
    * To fetch products
    * parameter requires token in header x-auth-token
    * parameter array categoryids
    * parameter int limit
    * parameter boolean random 1 or 0
    * parameter numeric minprice
    * parameter numeric maxprice
    * 
    * {
    *   "products":{
    *   "categories":["ID":"name","ID":"name","ID":"name"],
    *   "limit": "3",
    *   "random": "1",
    *   "minprice": "10.00",
    *   "maxprice": "100.00"
    *   }
    * }
    * 
    *  return product list
    */    
    public function showproducts() {
        
        $member = $this->checkauthentication();
        
        if (!$member) {
            return "Unauthorized";
        }         
        
        $string = $this->getRequest()->getBody();
        
        $products = json_decode($string, true);
        
        $productrequest = $products['products'];
        $categoryids = array();
        $limit = 3;
        $random = true;
        $pricerange = array();
        $minprice = null;
        $maxprice = null;
        if (isset($productrequest['categories'])) {
            $categoryids = $productrequest['categories'];
            $ids = array_filter(array_keys($categoryids), 'is_numeric');
        }
        if (isset($productrequest['limit'])) {
            if (is_numeric($productrequest['limit'])) {
                $limit = $productrequest['limit'];
            }
        }        
        if (isset($productrequest['random'])) {
            $random = $productrequest['random'];
        }                
        if (isset($productrequest['minprice'])) {
            if (is_numeric($productrequest['minprice'])) {
                $minprice = $productrequest['minprice'];
            }
            
        }                        
        if (isset($productrequest['maxprice'])) {
            if (is_numeric($productrequest['maxprice'])) {
                $maxprice = $productrequest['maxprice'];
            }            
        }                                
        
        
        $result = new stdClass();
        
        $productArray = Product::get()->where("Active = 1"); 

        if ($categoryids) {
            $productArray = $productArray->filter(array('Categories.ID'=>$ids)); 
        }
        
        if ($minprice && !$maxprice) {
            $productArray = $productArray->where("Price >= '$minprice'"); 
        }        
        if (!$minprice && $maxprice) {
            $productArray = $productArray->where("Price <= '$maxprice'"); 
        }                
        if ($minprice && $maxprice) {
            $productArray = $productArray->where("Price between '$minprice' and '$maxprice'"); 
        }                        
        
        if ($random) {
            $productArray = $productArray->sort('RAND()');
        }
        
        $productArray = $productArray->limit($limit);
        
        $productArray = $productArray->toNestedArray();
        
        $result->Products = $productArray;
        
        return json_encode($result);                                
        
    }        
    
    /**
    * To fetch products that is suitable for a friend
    * parameter requires token in header x-auth-token
    * parameter string email
    * 
    * {
    *  "email":"a@a.a"
    * }
    * 
    *  return product list
    */    
    public function showproductsforfriend() {
        
        $member = $this->checkauthentication();
        
        if (!$member) {
            return "Unauthorized";
        }         
        
        $string = $this->getRequest()->getBody();
        
        $jsnobj = json_decode($string, true);
        
        $friendEmail = null;
        
        if (isset($jsnobj['Email'])) {
            $friendEmail = $jsnobj['Email'];
        }
        else {
            if (isset($jsnobj['email'])) {
                $friendEmail = $jsnobj['email'];
            }            
        }
        
        if (!$friendEmail) {
            return "friend's Email is required";
        }                 
        
        $friendObj = $member->Friends()->filter(array("Friend.Email" => $friendEmail))->first();
        
        if (!$friendObj) {
            return "You are not yet in friend zone";
        }
        
        $gender = $friendObj->Friend()->Gender;
        
        $catID = null;
        $catArr = array();
        if ($gender) {
            $catObj = Category::get()->filter(array('Name'=>$gender))->first();
            $catArr[] =  $catObj->ID;
            
            $catObj = Category::get()->filter(array('Name'=>"Unisex"))->first();
            $catArr[] =  $catObj->ID;            
        }
        
        $budget = $friendObj->Budget;
        // compute the +-20% based on budget
        $minprice  = $budget - (20 / 100) * $budget;
        $maxprice = $budget + (20 / 100) * $budget;
        $nzmaxrate = $maxprice;
        $nzminrate = $minprice;
        $nzbudgetrate = $budget;
        
        $currency = "NZD";
        if ($member->Currency) {
            $currency = $member->Currency;
        }
        if (!$member->Currency && $friendObj->Currency) {
            $currency = $friendObj->Currency;
        }
        
        //convert min and max to nzrate
        if ($currency != "NZD") {
            $nzmaxrate = \Vulcan\CurrencyConversion\CurrencyConversion::convert($maxprice, $currency, 'NZD'); 
            $nzminrate = \Vulcan\CurrencyConversion\CurrencyConversion::convert($minprice, $currency, 'NZD'); 
            $nzbudgetrate = \Vulcan\CurrencyConversion\CurrencyConversion::convert($budget, $currency, 'NZD'); 
        }
   
        $result = new stdClass();
        
        $productArray = Product::get()->where("Active = 1"); 
        
        if ($catArr) {
            $prodcatarray = $productArray->filter(array('Categories.ID'=>$catArr)); 
            
            if ($prodcatarray->Count() > 0) {
                $productArray = $prodcatarray;
            }
        }
        
        // check first number for products with the same price as with budget
        $budgetArray = $productArray->where("NZDPrice = '$nzbudgetrate'"); 

        
        if ($budgetArray->Count() < 3) {
            
            if ($minprice && $maxprice) {
                
                $productArray = $productArray->where("NZDPrice between '$nzminrate' and '$nzmaxrate'"); 
                
            }                                    
            
        }
        else {
            $productArray = $budgetArray;
        }
        
        $random = 1;
        if ($random) {
            $productArray = $productArray->sort('RAND()');
        }
        
        $productArray = $productArray->limit(3);
        
        $prodObj = [];
        
        foreach ($productArray as $proditem) {
            
            $supplierObj = [];
            
            $map = $proditem->toMap();
            $this->tidyMap($map);
            $map['ID'] = $proditem->ID;
            $map['Image'] = $proditem->ProductImage()->URL;
            
            $supplier = null;
            $supplier = $proditem->Supplier()->toMap();
            
            if ($supplier) {
                $this->tidyMap($supplier);
                $supplier['Icon'] = $proditem->Supplier()->Icon()->URL;                
            }
            
            $map['Supplier'] = $supplier;
            
            // convert nzrate to the currency in budget object
            $nzdprice = $map['NZDPrice'];
            $price = $map['Price'];
            
            //if ($proditem->Supplier()->Currency != "NZD") {
            //    $price = \Vulcan\CurrencyConversion\CurrencyConversion::convert($nzdprice, "NZD", $proditem->Supplier()->Currency); 
            //}
            if ($currency != $proditem->Supplier()->Currency ) {
                $price = \Vulcan\CurrencyConversion\CurrencyConversion::convert($nzdprice, "NZD", $currency); 
            }
            
            $map['Price'] = round($price, 2);
            //$map['Currency'] = $proditem->Supplier()->Currency;
            $map['Currency'] = $currency;
            
            $prodObj[] = $map;
        }        
        
        //cmmment here
        $data = new stdClass();
        $data->products = $prodObj;
        $this->response->setBody(json_encode($data));
        return $this->response;
        
    }
    
    
    /**
    * Update friend details such as budget and currency
    * parameter requires token in header x-auth-token
    * parameter requires friend email
    * 
    * {
    *   "updatefriend":{
    *   "email": "a@a.a",
    *   "currency": "NZD",
    *   "budget": "20.00"
    *   }
    * }
    * 
    *  return ok
    */    
    public function updatefriend() {
        
        $member = $this->checkauthentication();
        
        if (!$member) {
            return "Unauthorized";
        }         
        
        $string = $this->getRequest()->getBody();
        
        $jsn = json_decode($string, true);
        
        $updatefriend = $jsn['updatefriend'];
        $email = null;
        $currency = "NZD";
        $budget = 0;
        if (isset($updatefriend['email'])) {
            $email = $updatefriend['email'];
        }
        if (isset($updatefriend['currency'])) {
            $currency = $updatefriend['currency'];
        }
        if (isset($updatefriend['budget'])) {
            $budget = $updatefriend['budget'];
        }        
        
        if (!$updatefriend) {
            return "parameter required";
        }
        
        if (!$email) {
            return "friend's email is required";
        }        
        
        if (!$budget) {
            return "budget is required";
        }                
        
        $selectedfriend = $member->Friends()->filter(array("Friend.Email" => $email))->first();
        
        if (!$selectedfriend) {
            return $email. " is not on your friend's list";
        }
        
        $selectedfriend->Currency = $currency;
        $selectedfriend->Budget = $budget;
        $selectedfriend->write();        
        
        $response = $this->getResponse();
        $response->setBody('ok');
        
        return $this->getResponse();         
        
    }       
    
    protected function sendEmail($member, $subject, $body, $invitationtoken = null)
    {
        $email = Email::create()
            ->setHTMLTemplate('email\\HiveEmail')
            ->setData($member)
            ->setSubject($subject)
            ->addData('Content', $body)
            ->addData('Token', $invitationtoken)
            ->addData('Base', Director::absoluteBaseURL())    
            ->setTo($member->Email);
        
        return $email->send();
    }    
    
    public function testPushNotification()
    {

        // API access key from Google API's Console
        $apikey = "AIzaSyBrFqLidjgYKnovu3xzNR3HPgJRxmZlAAM";
        $url = 'https://fcm.googleapis.com/fcm/send';
        $subject = 'test';
        $body = 'this is test';
        
        $firebasetoken = 'eP78HgBm0qQ:APA91bFZxLJFriMMOX8ZjOjhZdP-8tIn58sv6d7JntULhJ5YcqHlZrGRGkYVNuY104dN8PcF_lIegb66HVBPJoApQFgfxNFloDmP4Uhb1tQZOwJjUg5GA-1wuM_V_37bUC7zsYgMSI7M';
        
        $registrationIds = array($firebasetoken);
        
        // prepare the message
        $notificationBody = array( 
                'title'     => $subject,
                'body'      => $body
        );    
    
    
        $data = array( 
                'token'     => $firebasetoken,
                'vibrate' => 1,
                'sound' => 1,
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
        );
        
        $fields = array( 
                'data'             => $data,
                'priority' => 'high',
                'notification' => array(
                    'title' => $subject,
                    'body' => $body
                ),
            'to' =>$firebasetoken
        );  
        
        $headers = array( 
                'Authorization: key='. $apikey, 
                'Content-Type: application/json'
        );        
        
        $ch = curl_init();
        curl_setopt( $ch,CURLOPT_URL,$url);
        curl_setopt( $ch,CURLOPT_POST,true);
        curl_setopt( $ch,CURLOPT_HTTPHEADER,$headers);
        curl_setopt( $ch,CURLOPT_RETURNTRANSFER,true);
        curl_setopt( $ch,CURLOPT_SSL_VERIFYPEER,false);
        
        curl_setopt( $ch,CURLOPT_POSTFIELDS,json_encode($fields));
        $result = curl_exec($ch);
        curl_close($ch);        
        
        return $result;        
        
    }    
    
    protected function ProcessPushNotification($member, $subject, $body, $invitationtoken)
    {
        // API access key from Google API's Console
        $apikey = "AIzaSyBrFqLidjgYKnovu3xzNR3HPgJRxmZlAAM";
        $url = 'https://fcm.googleapis.com/fcm/send';
        
        if (!$member->FirebaseToken) {
            return 'no firebase token';
        }
        
        
        // prepare the message
        $notificationBody = array( 
                'title'     => $subject,
                'body'      => $body
        );    
    
    
        $data = array( 
                'token'     => $member->FirebaseToken,
                'invitationtoken'      => $invitationtoken,
                'vibrate' => 1,
                'sound' => 1,
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
        );
        
        $fields = array( 
                'registration_ids' => array($member->FirebaseToken), 
                'data'             => $data,
                'priority' => 'high',
                'notification' => array(
                    'title' => $subject,
                    'body' => $body
                )
        );   
        
        $headers = array( 
                'Authorization: key='. $apikey, 
                'Content-Type: application/json'
        );        
        
        $ch = curl_init();
        curl_setopt( $ch,CURLOPT_URL,$url);
        curl_setopt( $ch,CURLOPT_POST,true);
        curl_setopt( $ch,CURLOPT_HTTPHEADER,$headers);
        curl_setopt( $ch,CURLOPT_RETURNTRANSFER,true);
        curl_setopt( $ch,CURLOPT_SSL_VERIFYPEER,false);
        curl_setopt( $ch,CURLOPT_POSTFIELDS,json_encode($fields));
        $result = curl_exec($ch);
        curl_close($ch);        
        
        return $result;
    }        
    
    /**
    * To set firebase
    * parameter requires token in header x-auth-token
    * parameter string email
    * 
    * {
    *  "token":"AAAAGAugXwg:APA91bFKs25YJsF6iMxpTI819sDZnbfM3lzgCwzrUqVZ1Sa2Z2mh1yCM4UZwBJw82EjlTT82_zEZt_NGotwwr9Su1uEjRdcVP8MrFjKS57VZgYULZMgEXzjPdc9iDJTl3Yhq2i5j67yc"
    * }
    * 
    *  return ok
    */            
    
    public function setfirebasetoken() {
        
        $member = $this->checkauthentication();
        
        if (!$member) {
            return "Unauthorized";
        }         
        
        
        $string = $this->getRequest()->getBody();
        
        $jsnobj = json_decode($string, true);
        
        if (!isset($jsnobj['token'])) {
            return "No firebased token passed";
        }
        
        $firebase = $jsnobj['token'];        
        
        
        $member->FirebaseToken = $firebase;
        $member->write();
        
        $response = $this->getResponse();
        $response->setBody('ok');
        
        return $this->getResponse();                 

    }        
}

