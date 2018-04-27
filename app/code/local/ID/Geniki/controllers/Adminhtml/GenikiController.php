<?php

class ID_Geniki_Adminhtml_GenikiController extends Mage_Adminhtml_Controller_Action
{

	private $order;

	private $username;
	private $password;
	private $appkey;

	private $send_sms;
	private $sms_url;
	private $sms_user;
	private $sms_pass;

	private $massNumbers;

	private $api_url;
	private $soap;
	private $auth_key;

	/*
		Mage::getSingleton('core/session')->addSuccess('Success message');
		Mage::getSingleton('core/session')->addNotice('Notice message');
		Mage::getSingleton('core/session')->addError('Error message');
		// Admin only
		Mage::getSingleton('adminhtml/session')->addWarning('Warning message');

		try{
			/// ...
		} catch (Exception $e) {
			Mage::getSingleton('core/session')->addError('Error ' . $e->getMessage());
		}
	*/

	private function init()
	{
		$this->username = Mage::getStoreConfig('geniki/login/username');
		$this->password = Mage::getStoreConfig('geniki/login/password');
		$this->appkey = Mage::getStoreConfig('geniki/login/appkey');

		$this->send_sms = Mage::getStoreConfig('geniki/sms/send_sms');
		$this->sms_url = Mage::getStoreConfig('geniki/sms/sms_url');
		$this->sms_user = Mage::getStoreConfig('geniki/sms/sms_user');
		$this->sms_pass = Mage::getStoreConfig('geniki/sms/sms_pass');

		$this->api_url = Mage::getStoreConfig('geniki/login/api_url');

		$this->soap = new SoapClient( $this->api_url );

		if( !$this->auth_key ) {
			$this->authenticate();
		}
	}

	public function indexAction()
	{
	    $this->_redirectReferer();
	    Mage::getSingleton('adminhtml/session')->addNotice( $this->__('You cannot access this area directly') );
	    return $this;
	}

	public function authenticate()
	{
	  	$oAuthResult = $this->soap->Authenticate(
			array(
				'sUsrName' 			=> $this->username,
				'sUsrPwd' 			=> $this->password,
				'applicationKey' 	=> $this->appkey
			)
		);

		if ($oAuthResult->AuthenticateResult->Result != 0) {
			$this->_redirectReferer();
			Mage::getSingleton('core/session')->addError('Authentication error!');
			return false;
		}

		$this->auth_key = $oAuthResult->AuthenticateResult->Key;
		return true;
	}

	public function createAction($order = null)
	{
		$this->init();

		if( $this->getRequest()->getParam('order') ) {
			$this->order = Mage::getModel("sales/order")->load( $this->getRequest()->getParam('order') );
		} elseif( $this->getRequest()->getParam('order_ids') ) {
			$this->order = Mage::getModel("sales/order")->load( $order );
		} else {
			$this->_redirectReferer();
			Mage::getSingleton('adminhtml/session')->addError( $this->__('Invalid action. You must select at least 1 order to create vouchers for') );
			return false;
		}

		// Get Order parameters
		if( $this->order->canShip() && (substr($this->order->getShippingMethod(), 0, 10) === 'id_geniki_') ) {
			$order_data = $this->order->getShippingAddress()->getData();
			$extras = array();
			if( $this->order->getPayment()->getMethodInstance()->getCode() == 'cashondelivery' ) {
				// Αντικαταβολή
				if( $this->order->getFieldCustomPrice() !== NULL ) {
					if( $this->order->getFieldCustomPrice() < 0.1 ) {
						$amount = 0;
					} else {
						$amount = $this->order->getFieldCustomPrice();
						$extras[] = 'ΑΜ'; // Ελληνικά
					}
				} else {
					$amount = $this->order->getGrandTotal();
					$extras[] = 'ΑΜ'; // Ελληνικά
				}
			} else {
				// Άλλο
				$amount = 0;
			}

			// Έλεγχος για παράδοση Reception
			if( $this->order->getShippingMethod() == 'id_geniki_reception' ) {
				//$extras[] = 'ΡΣ'; // Ελληνικά TODO
			}

			// Έλεγχος για παράδοση Σαββατο
			if( $this->order->getShippingMethod() == 'id_geniki_saturday' ) {
				$extras[] = '5Σ'; // Ελληνικά
			}

			// Έλεγχος για χέρι με χέρι
			if( $this->order->getShippingMethod() == 'id_geniki_exchange' ) {
				$extras[] = 'ΠΚ'; // Ελληνικά
			}

			try {
				$params = array(
					'ReceivedDate'				=> date('Y-m-d'),
					'Name'						=> $this->order->getShippingAddress()->getName(),
					'Address'					=> trim($order_data['street']),
					'City'						=> $order_data['city'], // Περιοχή
					'Telephone'					=> $order_data['telephone'], // Τηλέφωνο
					'Zip'						=> $order_data['postcode'], // ΤΚ
					'Pieces'					=> count( $this->order->getAllVisibleItems() ),
					'Weight'					=> 0.5, // TODO
					'CodAmount'					=> floatval($amount), // Ποσό
					'Comments'					=> ($this->order->getCustomerNote() ? $this->order->getCustomerNote() : ''),
					'OrderId'					=> $this->order->getIncrementId(), // Αρ. Παραγγελίας
					'Services'					=> implode(',', $extras), // ΑΜ = Αντικαταβολή, ΡΣ = Παράδοση Reception
					'InsAmount'					=> 0,
				);

				$xml = array(
					'sAuthKey' => $this->auth_key,
					'oVoucher' => $params,
					'eType' => "Voucher"
				);

				$response = @$this->soap->CreateJob($xml);

				// Proceed if no errors
				if( $response->CreateJobResult->Result == 0 ) {
					$this->createInvoice();
					$this->createShipment($response->CreateJobResult->Voucher);
					if( $this->send_sms ) {
						//Mage::log('SMS sending triggered');
						if( $this->sendSMS($response->CreateJobResult->Voucher) ) {
							$extra = $this->__('SMS Notification Sent');
						} else {
							$extra = $this->__('SMS Notification not sent');
						}
					} else {
						$extra = null;
					}

					// Add voucher to Vouchers table
					$voucher = array(
						'created_at'		=> date('d-m-Y H:i:s'),
						'pod'				=> $response->CreateJobResult->Voucher,
						'jobid'				=> $response->CreateJobResult->JobId,
						'orderno'			=> $this->order->getIncrementId(),
						'status'			=> 'Active',
						'is_printed'		=> 0,
					);
					Mage::getModel('id_geniki/voucher')->setData($voucher)->save();
					
					// Add subvouchers - if any - to Vourchers table
					if( count($response->CreateJobResult->SubVouchers->Record) > 0 ) {
						foreach( $response->CreateJobResult->SubVouchers->Record as $subvoucher ) {
							$subvoucher = array(
								'created_at'		=> date('d-m-Y H:i:s'),
								'pod'				=> $subvoucher->VoucherNo,
								'jobid'				=> $response->CreateJobResult->JobId,
								'orderno'			=> $this->order->getIncrementId(),
								'status'			=> 'Active',
								'is_printed'		=> 0,
							);
							Mage::getModel('id_geniki/voucher')->setData($subvoucher)->save();
							$subvoucher = array();
						}
					}

					$this->_redirectReferer();
					Mage::getSingleton('adminhtml/session')->addSuccess( $this->__('Created voucher for order #%s.Voucher: %s'.' '.$extra, $this->order->getIncrementId(), $response->CreateJobResult->Voucher) );
				} else {
					$this->_redirectReferer();
					Mage::getSingleton('adminhtml/session')->addError( $this->__('Could not create voucher for order #%s. Error: %s', $this->order->getIncrementId(), $response->CreateJobResult) ); // TODO
				}

			} catch(SoapFault $fault) {
				trigger_error("SOAP Fault: (faultcode: {$fault->faultcode}, faultstring: {$fault->faultstring})", E_USER_ERROR);

				$this->_redirectReferer();
				Mage::getSingleton('adminhtml/session')->addError( $this->__('Could not create voucher for order #%s', $this->order->getIncrementId()) );
			}
		} else {
			$this->_redirectReferer();
			Mage::getSingleton('adminhtml/session')->addError( $this->__('Order #%s cannot be shipped or has already been shipped', $this->order->getIncrementId()) );
		}
		return $this;
	}

	public function massVouchersAction()
	{
		$this->order_arr = $this->getRequest()->getParam('order_ids');
		foreach($this->order_arr as $_order) {
			$this->createAction($_order);
		}
		return $this;
	}

	private function createShipment($voucher)
	{
		if($this->order->canShip()) {
			$customerEmailComments = '';
			// Create shipment and add tracking number
			$shipment = Mage::getModel('sales/service_order', $this->order)->prepareShipment(Mage::helper('geniki/orders')->_getItemQtys($this->order));

		    if( $shipment ) {
			    $arrTracking = array(
	          		'carrier_code' 	=> 'custom',
	          		'title' 		=> 'Γενική Ταχυδρομική',
	          		'number' 		=> $voucher,
	        	);
			    $track = Mage::getModel('sales/order_shipment_track')->addData($arrTracking);
	        	$shipment->addTrack($track);
	        	$shipment->register();
	       		Mage::helper('geniki/orders')->_saveShipment($shipment, $this->order, $customerEmailComments);
	        	Mage::helper('geniki/orders')->_saveOrder($this->order);

		        if( !$shipment->getEmailSent() ) {
		        	// Send Tracking data
		        	$shipment->sendEmail(true);
		        	$shipment->setEmailSent(true);
		        	$shipment->save();
		        }
	        	return true;
	      	}
		} else {
			return false;
		}
	}

	private function createInvoice()
	{
		if( !$this->order->hasInvoices() && $this->order->canInvoice() ) {
	    	// Prepare
	    	$invoice = Mage::getModel('sales/service_order', $this->order)->prepareInvoice();
	    	// Check that are products to be invoiced
	    	if( $invoice->getTotalQty() ) {
	        	// CAPTURE_OFFLINE since CC and PayPal already have invoices
	        	$invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
	        	$invoice->register();
	        	$transactionSave = Mage::getModel('core/resource_transaction')
	          		->addObject($invoice)
	          		->addObject($invoice->getOrder());
	        	$transactionSave->save();
	      	}
  		}
	}

	private function sendSMS($tracking_id) {
		$phone = $this->order->getBillingAddress()->getTelephone();
		$fax = $this->order->getBillingAddress()->getFax();

		if ( preg_match('#^69#', $phone) === 1 && strlen($phone) == 10 ) {
		    // Is valid mobile
		    if( $this->sms($phone, $tracking_id) ) {
		    	return true;
		    } else {
		    	return false;
		    }
		} elseif( preg_match('#^69#', $fax) === 1 && strlen($fax) == 10 ) {
			// Is valid mobile
		    if( $this->sms($fax, $tracking_id) ) {
		    	return true;
		    } else {
		    	return false;
		    }
		} else {
			return false;
		}
		return $this;
	}

	private function sms($number,$voucher)
	{
		$message = 'Η ΠΑΡΑΓΓΕΛΙΑ ΣΑΣ ΜΕ ΑΡΙΘΜΟ #'.$this->order->getIncrementId().' ΑΠΕΣΤΑΛΗ ΜΕ ΓΕΝΙΚΗ ΤΑΧΥΔΡΟΜΙΚΗ.Ο ΑΡΙΘΜΟΣ ΑΠΟΣΤΟΛΗΣ ΕΙΝΑΙ '.$voucher.'. ΕΥΧΑΡΙΣΤΟΥΜΕ.';
		$data = array(
			'username'			=> $this->sms_user,
			'password'			=> $this->sms_pass,
			'destination'		=> '30'.$number,
			'sender'			=> 'Sportifs.gr',
			'message'			=> $message,
			'batchuserinfo' 	=> 'TrackingInfoSMS',
			'pricecat'			=> 0
		);
		$response = file_get_contents( $this->sms_url.'?'.http_build_query($data) );
		if( preg_match('#^OK ID:[0-9]{1,}#', $response) === 1 ) {
			$this->order->addStatusHistoryComment('Tracking Number SMS Sent, '.date('d-m-Y H:i:s'));
			$this->order->save();
			return true;
		} else {
			return false;
		}
		return $this;
	}

	public function unprintedAction()
	{
		$this->init();

		$unprinted_vouchers = Mage::getModel('id_geniki/voucher')->getCollection();
		$unprinted_vouchers->addFieldToFilter('is_printed',0);
		$unprinted_vouchers->addFieldToFilter('status','Active');
		$unprinted_vouchers->addFieldToFilter('created_at',array('gt' => Mage::getModel('core/date')->date('Y-m-d H:i:s', strtotime('today'))));

		$pods = array();
		foreach ($unprinted_vouchers as $pod) {
			$pods[] = $pod->pod;
			$pod->is_printed = 1;
			$pod->save();
		}

		if( count($pods) > 0 ) {
			$sorted_pods = sort($pods);
			$this->getResponse()->setRedirect(str_replace("?WSDL", "", $this->api_url).'/GetVouchersPdf?authKey='.urlencode($this->auth_key).'&voucherNumbers='.implode('&voucherNumbers=', $pods).'&Format=Flyer&extraInfoFormat=None');
		} else {
			$this->_redirectReferer();
			Mage::getSingleton('adminhtml/session')->addWarning( $this->__('No Vouchers to print') );
		}
		return true;
		return $this;
	}

	public function getlistAction()
	{
		$this->init();

		try {
			$this->soap->ClosePendingJobs(
				array('sAuthKey' => $this->auth_key)
			);

			$this->_redirectReferer();
			Mage::getSingleton('core/session')->addSuccess($this->__('Όλες οι εκκρεμείς αποστολές οριστικοποιήθηκαν') );
		} catch(SoapFault $fault) {
			trigger_error("SOAP Fault: (faultcode: {$fault->faultcode}, faultstring: {$fault->faultstring})", E_USER_ERROR);
			return false;
		}
		return $this;
	}

	public function reprintVoucherAction($order = null)
	{
		$this->init();

		if( $this->getRequest()->getParam('order') ) {
			$this->order = Mage::getModel("sales/order")->load( $this->getRequest()->getParam('order') );

			$this->getResponse()->setRedirect(str_replace("?WSDL", "", $this->api_url).'/GetVouchersPdf?authKey='.urlencode($this->auth_key).'&voucherNumbers='.$this->order->getTracksCollection()->getFirstItem()->getNumber().'&Format=Flyer&extraInfoFormat=None');
		} elseif( $this->getRequest()->getParam('order_ids') ) {
			$this->order = Mage::getModel("sales/order")->load( $order );
			return $this->order->getTracksCollection()->getFirstItem()->getNumber();
		} elseif( $this->getRequest()->getParam('pod') ) {
			$this->getResponse()->setRedirect(str_replace("?WSDL", "", $this->api_url).'/GetVouchersPdf?authKey='.urlencode($this->auth_key).'&voucherNumbers='.$this->getRequest()->getParam('pod').'&Format=Flyer&extraInfoFormat=None');
		} else {
			$this->_redirectReferer();
			Mage::getSingleton('adminhtml/session')->addError( $this->__('Invalid action. You must select at least 1 order to print vouchers from') );
			return false;
		}
	}

	public function massReprintAction()
	{
		$this->order_arr = $this->getRequest()->getParam('order_ids');
		$pods = array();
		foreach($this->order_arr as $_order) {
			$pods[] = $this->reprintVoucherAction($_order);
		}
		$this->getResponse()->setRedirect(str_replace("?WSDL", "", $this->api_url).'/GetVouchersPdf?authKey='.urlencode($this->auth_key).'&voucherNumbers='.implode('&voucherNumbers=', $pods).'&Format=Flyer&extraInfoFormat=None');
	}

	public function deleteVoucherAction($order = null)
	{
		$this->init();

		if( $this->getRequest()->getParam('order') ) {
			$this->order = Mage::getModel("sales/order")->load( $this->getRequest()->getParam('order') );

			try {
				$pod = $this->order->getTracksCollection()->getFirstItem()->getNumber();
				$voucher = Mage::getModel('id_geniki/voucher')->load($pod, 'pod');
				$params = array(
					'sAuthKey' 	=> $this->auth_key,
					'nJobId' 	=> $voucher->jobid,
					'bCancel' 	=> true
				);
				$response = $this->soap->CancelJob( $params );

				if( $response->CancelJobResult == 0 ) {
					// Delete Shipment
					$shipments = $this->order->getShipmentsCollection();
					foreach ($shipments as $shipment){
					    $shipment->delete();
					}

					$invoices = $this->order->getInvoiceCollection();

          			foreach ($invoices as $invoice) {
            			$items = $invoice->getAllItems();
			            foreach ($items as $i) {
			               $i->delete();
			            }
            			$invoice->delete();
          			}

					$items = $this->order->getAllVisibleItems();
					foreach($items as $i) {
						$i->setQtyShipped(0);
						$i->setQtyInvoiced(0);
						$i->save();
					}

					//Reset order state
					$this->order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true, 'Undo Shipment');
					$this->order->save();

					$this->_redirectReferer();
					Mage::getSingleton('adminhtml/session')->addSuccess( $this->__('Voucher %s deleted', $this->order->getTracksCollection()->getFirstItem()->getNumber()) );

					$data = array(
						'status' => 'Cancelled',
					);
					$voucher->addData($data)->save();

					return true;
				} else {
					$this->_redirectReferer();
					Mage::getSingleton('adminhtml/session')->addSuccess( $this->__('Could not delete voucher %s', $this->order->getTracksCollection()->getFirstItem()->getNumber()) );
					return false;
				}
			} catch(SoapFault $fault) {
				trigger_error("SOAP Fault: (faultcode: {$fault->faultcode}, faultstring: {$fault->faultstring})", E_USER_ERROR);
				return false;
			}
		} elseif( $this->getRequest()->getParam('pod') ) {
			try {
				$voucher = Mage::getModel('id_geniki/voucher')->load($this->getRequest()->getParam('pod'), 'pod');
				$response = $this->soap->CancelJob( $this->auth_key, $voucher{0}->jobid, true );

				if( $response ) {
					$data = array(
						'status' => 'Cancelled',
					);
					$voucher->addData($data)->save();

					$this->_redirectReferer();
					Mage::getSingleton('adminhtml/session')->addSuccess( $this->__('Voucher %s deleted', $this->getRequest()->getParam('pod')) );
					return true;
				} else {
					$this->_redirectReferer();
					Mage::getSingleton('adminhtml/session')->addSuccess( $this->__('Could not delete voucher %s.', $this->getRequest()->getParam('pod')) );
					return false;
				}
			} catch(SoapFault $fault) {
				trigger_error("SOAP Fault: (faultcode: {$fault->faultcode}, faultstring: {$fault->faultstring})", E_USER_ERROR);
				return false;
			}
		} else {
			$this->_redirectReferer();
			Mage::getSingleton('adminhtml/session')->addError( $this->__('Invalid action') );
			return false;
		}
	}

	// TODO
	public function listsAction()
	{
		$this->_title($this->__('Previous Receipt Lists'));
        $this->loadLayout();
        $this->_setActiveMenu('geniki/list');
        $this->_addContent($this->getLayout()->createBlock('id_geniki/adminhtml_list'));
        $this->renderLayout();
	}

	public function vouchersAction()
	{
		$this->_title($this->__('Vouchers'));
        $this->loadLayout();
        $this->_setActiveMenu('geniki/voucher');
        $this->_addContent($this->getLayout()->createBlock('id_geniki/adminhtml_voucher'));
        $this->renderLayout();
	}

	public function antikatavolesAction()
	{
		$this->_title($this->__('Antikatavoles'));
        $this->loadLayout();
        $this->_setActiveMenu('geniki/antikatavoles');
        $this->_addContent($this->getLayout()->createBlock('id_geniki/adminhtml_antikatavoles'));
        $this->renderLayout();
	}

	public function grid_voucherAction()
    {
        $this->loadLayout();
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock('id_geniki/adminhtml_voucher_grid')->toHtml()
        );
    }

	public function grid_listAction()
    {
        $this->loadLayout();
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock('id_geniki/adminhtml_list_grid')->toHtml()
        );
    }

    public function grid_antikatavolesAction()
    {
        $this->loadLayout();
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock('id_geniki/adminhtml_antikatavoles_grid')->toHtml()
        );
    }

    // TODO
    public function printListAction()
    {
    	$this->init();

    	$list = Mage::getModel('id_geniki/list')->load($this->getRequest()->getParam('massnumber'), 'massnumber');

    	$this->getResponse()->setRedirect( 'http://acs-eud.acscourier.gr/Eshops/getlist.aspx?MainID='.$this->companyId.'&MainPass='.$this->companyPass.'&UserID='.$this->username.'&UserPass='.$this->password.'&MassNumber='.$this->getRequest()->getParam('massnumber').'&DateParal='.date('Y-m-d', strtotime($list->created_at)) );
    }

    public function checkordersAction()
    {
		Mage::helper('geniki/antikatavoles')->_check();
		$this->_redirect('*/geniki/antikatavoles');
    }

    // TODO
    public function uploadxlsAction()
    {
    	if ($data = $this->getRequest()->getParams()) {
            if (isset($_FILES['filename']['name']) && $_FILES['filename']['name'] != '') {
                try {
                    $uploader = new Varien_File_Uploader('filename');
                    $uploader->setAllowedExtensions(array('csv', 'CSV'));
                    $uploader->setAllowRenameFiles(false);
                    $uploader->setFilesDispersion(false);
                    $path = Mage::getBaseDir('tmp') . DS . 'antikatavoles';
                    if (!is_dir($path)) {
                        mkdir($path, 0777, true);
                    }
                    $uploader->save($path, $_FILES['filename']['name']);
                    $filename = $uploader->getUploadedFileName();
                    Mage::helper('geniki/antikatavoles')->_processFile($filename);
					Mage::getSingleton('adminhtml/session')->addSuccess( $this->__('File %s uploaded', $filename) );
                } catch (Exception $e) {
                    Mage::log( $e->getMessage() );
                }
            }
        }
        $this->_redirect('*/geniki/antikatavoles');
    }

    public function resetAntikatavoliAction()
    {
    	Mage::helper('geniki/antikatavoles')->_reset($this->getRequest()->getParam('pod'));
		$this->_redirect('*/geniki/antikatavoles');
    }

    protected function _isAllowed() {
	    return Mage::getSingleton('admin/session')->isAllowed('admin/geniki');
	}

}
