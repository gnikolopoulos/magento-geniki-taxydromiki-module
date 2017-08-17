<?php

class ID_Geniki_Helper_Sms extends Mage_Core_Helper_Abstract
{

	private function init() {
		$this->send_sms = Mage::getStoreConfig('geniki/sms/send_sms');
		$this->sms_url = Mage::getStoreConfig('geniki/sms/sms_url');
		$this->sms_user = Mage::getStoreConfig('geniki/sms/sms_user');
		$this->sms_pass = Mage::getStoreConfig('geniki/sms/sms_pass');
	}

}