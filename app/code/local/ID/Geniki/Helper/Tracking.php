<?php

class ID_Geniki_Helper_Tracking extends Mage_Core_Helper_Abstract
{

    private $username;
    private $password;
    private $appkey;

    private $api_url;
    private $soap;
    private $auth_key;

    private function init()
    {
        $this->username = Mage::getStoreConfig('geniki/login/username');
        $this->password = Mage::getStoreConfig('geniki/login/password');
        $this->appkey = Mage::getStoreConfig('geniki/login/appkey');
        $this->api_url = Mage::getStoreConfig('geniki/login/api_url');
        $this->soap = new SoapClient( $this->api_url );

        if( !$this->auth_key ) {
            $this->authenticate();
        }
    }

    private function authenticate()
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

    public function _trace($voucher)
    {
        $this->init();

        $params = array (
            'authKey' => $this->auth_key,
            'voucherNo' => $voucher,
            'language' => 'el'
        );
        $response = $this->soap->TrackAndTrace($params)->TrackAndTraceResult;

        return array(
            'checkpoints' => $response->Checkpoints->Checkpoint,
            'status'      => $response->Status,
            'date'        => $response->DeliveryDate,
            'signed'      => $response->Consignee,
        );
    }

}