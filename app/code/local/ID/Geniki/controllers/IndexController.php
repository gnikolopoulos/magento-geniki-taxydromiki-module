<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

class ID_Geniki_IndexController extends Mage_Core_Controller_Front_Action
{

	private $api_url;
	private $appkey;
	private $username;
	private $password;
    private $soap;
    private $auth_key;
    private $send_sms;

	private function init()
	{
		$this->username = Mage::getStoreConfig('geniki/login/username');
        $this->password = Mage::getStoreConfig('geniki/login/password');
        $this->appkey = Mage::getStoreConfig('geniki/login/appkey');
        $this->api_url = Mage::getStoreConfig('geniki/login/api_url');
        $this->send_sms = Mage::getStoreConfig('geniki/sms/send_sms');
        
        $this->sms_url = Mage::getStoreConfig('geniki/sms/sms_url');
		$this->sms_user = Mage::getStoreConfig('geniki/sms/sms_user');
		$this->sms_pass = Mage::getStoreConfig('geniki/sms/sms_pass');

        $this->soap = new SoapClient( $this->api_url );

        if( !$this->auth_key ) {
            $this->authenticate();
        }
	}

    public function authenticate()
    {
        $oAuthResult = $this->soap->Authenticate(
            array(
                'sUsrName'          => $this->username,
                'sUsrPwd'           => $this->password,
                'applicationKey'    => $this->appkey
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

    public function indexAction()
    {
        $this->init();

        $order_collection = Mage::getModel('sales/order')
					->getCollection()
					->addAttributeToSelect('*')
					->addAttributeToFilter('status', array('in' => array('complete')));

		foreach ($order_collection as $order) {
			$xml = array (
                'authKey' => $this->auth_key,
                'voucherNo' => $order->getTracksCollection()->getFirstItem()->getNumber(),
                'language' => 'el'
            );
            $response = $this->soap->TrackAndTrace($xml);
            $checkpoints = $response->TrackAndTraceResult->Checkpoints->Checkpoint;

            if( $response->Result == 0 ) {
    			if( $response->TrackAndTraceResult->Status == 'ΠΑΡΑΔΟΜΕΝΟ' ) {
    				echo $order->getIncrementId() . ' Delivered<br />';
    				$order->setStatus("delivered");
    				$order->save();
    			} else {
                    foreach( $checkpoints as $points ) {
                        if( $point->Status == 'Αδυναμία παράδοσης - Άρνηση Παραλαβής' || $point->Status == 'Επιστροφή στον αρχικό αποστολέα' ) {
                            echo $order->getIncrementId() . ' Denied<br />';
                            $order->setStatus("denied");
                            $order->save();
                            $this->sendDeniedEmail($order);

                            if( $this->send_sms ) {
                                $this->sendSMS($order);
                            }
                            break;
                        }
                    }
    			}
            }
		}
    }

    private function sendDeniedEmail($order)
    {
        // Get order
        $storeId = Mage::app()->getStore()->getStoreId();
        Mage::log('Order for denied:'.$order->getIncrementId());
        if( $order->getStatus() == 'denied' ) {
            // Order has been denied, prepare email
            $previousStore = Mage::app()->getStore();
            Mage::app()->setCurrentStore($order->getStore()->getCode());
            Mage::getDesign()->setArea('frontend');

            $emailTemplate  = Mage::getModel('core/email_template')->loadDefault('id_denied_order_email');
            $emailTemplateVariables['order'] = $order;
            $emailTemplateVariables['store'] = $order->getStore();
            $emailTemplate->getProcessedTemplate($emailTemplateVariables);
            $emailTemplate->setSenderEmail(Mage::getStoreConfig('trans_email/ident_general/email', $storeId));
            $emailTemplate->setSenderName(Mage::getStoreConfig('trans_email/ident_general/name', $storeId));
            $emailTemplate->send( $order->getCustomerEmail() ,'Sportifs.gr', $emailTemplateVariables);
            $emailTemplate->send( 'info@sportifs.gr' ,'Sportifs.gr', $emailTemplateVariables);

            Mage::app()->setCurrentStore($previousStore->getCode());
        }

        return $this;
    }

    private function sendSMS($order)
    {
        $message = 'ΕΝΗΜΕΡΩΘΗΚΑΜΕ ΓΙΑ ΤΗΝ ΑΡΝΗΣΗ ΠΑΡΑΛΑΒΗΣ ΤΗΣ ΠΑΡΑΓΓΕΛΙΑΣ ΣΑΣ #'.$order->getIncrementId().'.ΣΑΣ ΕΧΕΙ ΣΤΑΛΕΙ EMAIL ΣΧΕΤΙΚΑ ΜΕ ΤΗΝ ΟΦΕΙΛΗ ΣΑΣ ΒΑΣΕΙ ΤΩΝ ΟΡΩΝ ΠΟΥ ΕΧΕΤΕ ΑΠΟΔΕΧΘΕΙ.';

        $phone = $order->getShippingAddress()->getTelephone();
        $fax = $order->getShippingAddress()->getFax();

        // Start procedure
        if( $order->getStatus() == 'denied' ) {
            if ( preg_match('#^69#', $phone) === 1 && strlen($phone) == 10 ) {
                // Is valid mobile
                $data = array(
                    'username'      => $this->sms_user,
                    'password'      => $this->sms_pass,
                    'destination'   => '30'.$phone,
                    'sender'        => 'Sportifs.gr',
                    'message'       => $message,
                    'batchuserinfo' => 'OrderDenied',
                    'pricecat'      => 0
                );
                $response = file_get_contents( $this->sms_url.'?'.http_build_query($data) );

                if( preg_match('#^OK ID:[0-9]{1,}#', $response) === 1 ) {
                    return true;
                } else {
                    return false;
                }

            } elseif( preg_match('#^69#', $fax) === 1 && strlen($fax) == 10 ) {
                // Is valid mobile
                $data = array(
                            'username'      => $this->sms_user,
                            'password'      => $this->sms_pass,
                            'destination'   => '30'.$fax,
                            'sender'        => 'Sportifs.gr',
                            'message'       => $message,
                            'batchuserinfo' => 'OrderDenied',
                            'pricecat'      => 0
                        );
                $response = file_get_contents( $this->sms_url.'?'.http_build_query($data) );

                if( preg_match('#^OK ID:[0-9]{1,}#', $response) === 1 ) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        }
    }

}
