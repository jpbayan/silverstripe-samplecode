<?php

require BASE_PATH . '/xero/lib/XeroOAuth.php';

/*
 * Developed by LittleMonkey Ltd.
 */

/**
 * Description of CreateInvoicesTask
 *
 * @author Joy
 */
class CreateInvoicesTask extends BuildTask {
	
	protected $title = 'Create Xero Draft Invoices';
	
	protected $description = 'Create draft invoices in Xero for jobs.';

	public function run($request) {
            
                $jobs = Job::get()->where(" \"XeroInvoiceID\" IS NULL and RetailerCompanyID > 0 ");
                
                foreach ($jobs as $job) {
                    
                    $consumerKey = null;
                    $config = null;
                    
                    if ($job->RetailerCompany()) {
                        
                        $config = $job->RetailerCompany();

                        $appType = $config->ApplicationType;
                        $oauthCallback = null;
                        $userAgent = $config->ApplicationName;
                        $consumerKey = $config->ConsumerKey;                        
                    }
                    

                    
                    // if no xero consumer key then skip
                    if (!$consumerKey) {
                        continue;
                    }
                    
                    $sharedSecret = $config->ConsumerSecret;
                    $privateKeyPath = $config->PrivateKey()->URL;
                    $publicKeyPath = $config->PublicKey()->URL;
                    
                    /**
                    *  use the commented code below if your dev is running on docker
                    * 
                    * $privateKeyPath = "/var/www/html". $config->PrivateKey()->URL;
                    * $publicKeyPath = "/var/www/html". $config->PublicKey()->URL;                    
                    * 
                    */                    
                    
                    $signatures = array (
                                    'consumer_key' => $consumerKey,
                                    'shared_secret' => $sharedSecret,
                                    // API versions
                                    'core_version' => '2.0',
                                    'payroll_version' => '1.0' 
                    );

                    if ($appType == "Private" || $appType == "Partner") {
                            $signatures ['rsa_private_key'] = $privateKeyPath;
                            $signatures ['rsa_public_key'] = $publicKeyPath;
                    }

                    $XeroOAuth = new XeroOAuth ( array_merge ( array (
                            'application_type' => $appType,
                            'oauth_callback' => $oauthCallback,
                            'user_agent' => $userAgent 
                    ), $signatures ) );
                    
                    $initialCheck = $XeroOAuth->diagnostics ();
                    $checkErrors = count ( $initialCheck );
                    if ($checkErrors > 0) {
                            // you could handle any config errors here, or keep on truckin if you like to live dangerously
                            foreach ( $initialCheck as $check ) {
                                    echo 'Error: ' . $check . PHP_EOL;
                            }
                    } 
                    else {

                            Session::set('Xero', array (
                                    'oauth_token' => $XeroOAuth->config ['consumer_key'],
                                    'oauth_token_secret' => $XeroOAuth->config ['shared_secret'],
                                    'oauth_session_handle' => '' 
                            ));

                            $oauthSession['oauth_token'] = Session::get('Xero.oauth_token');
                            $oauthSession['oauth_token_secret'] = Session::get('Xero.oauth_token_secret');
                            $oauthSession['oauth_session_handle'] = Session::get('Xero.oauth_session_handle');

                            if (isset ( $oauthSession ['oauth_token'] )) {
                                    $XeroOAuth->config ['access_token'] = $oauthSession ['oauth_token'];
                                    $XeroOAuth->config ['access_token_secret'] = $oauthSession ['oauth_token_secret'];
                                    
                                    $this->createInvoices($XeroOAuth, $job, $config);
                            }
                    }           
                    
                    
                }
                

	}

	/**
	 * Create invoices by generating XML and sending to Xero.
	 * 
	 * @param  XeroOAuth $XeroOAuth Connection to Xero
         * @param  job $job Dataobject Job
         * @param  config $config Dataobject RetailerCompany Xero oath details 
	 */
	private function createInvoices($XeroOAuth, $job, $config) {
            
		$xeroConnection = clone $XeroOAuth;
                
		$invoicePrefix = $config->invoicePrefix;
		$defaultAccountCode = $config->defaultAccountCode;
                
		$invoices = array();
                
                $cusname = "Job-" . $job->ID;
                
                if ($job->Customer()) {
                    $cusname = $job->Customer()->Name;
                    
                    if (!$cusname) {
                        $cusname = "Job-" . $job->ID;
                    }
                }
                
                $i = 0;
                $invoices[$i]['Invoice'] = array(
                        'Type' => 'ACCREC',
                        'InvoiceNumber' => $invoicePrefix . $job->ID,
                        'Contact' => array(
                                'Name' => $cusname
                        ),
                        'Date' => $job->Created,
                        'DueDate' => $job->LastEdited,
                        'Status' => 'DRAFT',
                        'LineAmountTypes' => 'Exclusive',
                        'CurrencyCode' => 'NZD'
                );                                                
                
                
                $description = $job->Product ." ". $job->ReferenceNumber ;
                
                $invoices[$i]['Invoice']['LineItems'][]['LineItem'] = array(
                        'Description' => $description,
                        'Quantity' => 1 ,
                        'UnitAmount' => 1,
                        'AccountCode' => $defaultAccountCode,
                        'TaxType' => 'OUTPUT2'
                );                
                
                
		// If no data do not send to Xero
		if (empty($invoices)) {
			return;
		}
                
		$invoicesXML = new SimpleXMLElement("<Invoices></Invoices>");
                
		$this->arrayToXML($invoices, $invoicesXML);
		$xml = $invoicesXML->asXML();
                
		$response = $xeroConnection->request('POST', $xeroConnection->url('Invoices', 'core'), array(), $xml);
		if ($xeroConnection->response['code'] == 200) {

			$invoices = $xeroConnection->parseResponse($xeroConnection->response['response'], $xeroConnection->response['format']);
			echo count($invoices->Invoices[0]). " invoice(s) created in this Xero organisation.";

			// Update Jobs that have been pushed to Xero so that they are not sent again
			foreach ($invoices->Invoices->Invoice as $invoice) {

				$jobinvoice = Job::get()
					->filter('ID', str_replace($invoicePrefix, '', $invoice->InvoiceNumber->__toString()))
					->first();

				if ($jobinvoice && $jobinvoice->exists()) {
					$jobinvoice->XeroInvoiceID = $invoice->InvoiceID->__toString();
                                        $jobinvoice->XeroStatus = "DRAFT";
					$jobinvoice->write();
				}
			}
		}
		else {
			echo 'Error: ' . $xeroConnection->response['response'] . PHP_EOL;
			SS_Log::log(new Exception(print_r($xeroConnection, true)), SS_Log::NOTICE);
		}

	}


	/**
	 * Helper to generate XML from an array of data.
	 * 
	 * @param  Array $data 
	 * @param  SimpleXMLElement $xml 
	 */
	private function arrayToXML($data, &$xml) {

		foreach($data as $key => $value) {
			if(is_array($value)) {
				if(!is_numeric($key)){
					$subnode = $xml->addChild("$key");
					$this->arrayToXML($value, $subnode);
				}
				else{
					$this->arrayToXML($value, $xml);
				}
			}
			else {
				$xml->addChild("$key", "$value");
			}
		}
	}

	/**
	 * Helper to print formatted XML, useful for debugging.
	 * 
	 * @param  String $xml
	 * @return String      Nicely formatted XML
	 */
	private function prettyPrintXML($xml) {

		$domxml = new DOMDocument('1.0');
		$domxml->preserveWhiteSpace = false;
		$domxml->formatOutput = true;
		$domxml->loadXML($xml);
		return $domxml->saveXML();
	}
}
