<?php

//require Mage::getModuleDir('', 'ID_Geniki') . '/lib/PHPExcel.php';

class ID_Geniki_Helper_Antikatavoles extends Mage_Core_Helper_Abstract
{
	private $file;
	private $flag;

	public function _processFile($filename)
  	{
		$this->readCSV($filename);
		$this->flag = true;

		while ( ($data = fgetcsv($this->file, 0, "\t")) !== FALSE) {

			if($this->flag) { $this->flag = false; continue; } // Skip 1st line - Just headers
			/*
			Columns:
				0: Date sent
				1: Voucher No
				2: Customer ID / Order ID
				3: Destination
				4: Recepient
				5: Date delivered
				6: Amount
			*/
			//$voucher = Mage::getModel('id_acs/voucher')->load($data[1], 'pod');
			//Mage::log( 'Voucher No:');
			//Mage::log( $voucher->orderno );
			$order = Mage::getModel('sales/order')->loadByIncrementId( $data[2] );
			if( $order->getId() ) {

				$date_string = str_replace("πμ", "am", str_replace("μμ", 'pm', $data[5])) . "\r\n";
				$date = DateTime::createFromFormat('d/m/y H:iA', substr($date_string, 0, 8).substr($date_string, 15, 8) );
				// Check total
				if( $order->getGrandTotal() == (float)str_replace(',', '.', $data[6]) ) {
					$status = 'OK';
				} elseif( $order->getFieldCustomPrice() == (float)str_replace(',', '.', $data[6]) ) {
					$status = 'OK';
				} else {
					$status = 'Διαφορά ποσού';
				}

				// Check if pod exists before adding
				if( !Mage::getModel('id_geniki/antikatavoles')->load( $data[1], 'pod' )->getEntityId() ) {
					$data = array(
						'pod'			=> $data[1],
						'customer_name' => $data[4],
						'order' 		=> $order->getIncrementId(),
						'value' 		=> (float)str_replace(',', '.', $data[6]),
						'date'			=> $date->getTimestamp(),
						'status'		=> $status,
					);
					Mage::getModel('id_geniki/antikatavoles')->setData($data)->save();
				} else {
					Mage::getSingleton('adminhtml/session')->addError( $this->__('Skipping Voucher %s since it already exists', $data[1]) );
				}
			}
		}

    return true;
  	}

  	private function readCSV($file)
	{
		if (!file_exists( Mage::getBaseDir('tmp'). DS . 'antikatavoles' . DS . $file )) {
			exit("File does not exist." . EOL);
		} else {
			try {
				$this->convert_file_to_utf8( Mage::getBaseDir('tmp'). DS . 'antikatavoles' . DS . $file );
			  	$this->file = fopen( Mage::getBaseDir('tmp'). DS . 'antikatavoles' . DS . $file, 'r' );
			} catch(Exception $e) {
			  die('Error loading file "'.$e->getMessage());
			}
		}
	}

	private function utf16_to_utf8($str) {
	    $c0 = ord($str[0]);
	    $c1 = ord($str[1]);
	 
	    if ($c0 == 0xFE && $c1 == 0xFF) {
	        $be = true;
	    } else if ($c0 == 0xFF && $c1 == 0xFE) {
	        $be = false;
	    } else {
	        return $str;
	    }
	 
	    $str = substr($str, 2);
	    $len = strlen($str);
	    $dec = '';
	    for ($i = 0; $i < $len; $i += 2) {
	        $c = ($be) ? ord($str[$i]) << 8 | ord($str[$i + 1]) :
	                ord($str[$i + 1]) << 8 | ord($str[$i]);
	        if ($c >= 0x0001 && $c <= 0x007F) {
	            $dec .= chr($c);
	        } else if ($c > 0x07FF) {
	            $dec .= chr(0xE0 | (($c >> 12) & 0x0F));
	            $dec .= chr(0x80 | (($c >>  6) & 0x3F));
	            $dec .= chr(0x80 | (($c >>  0) & 0x3F));
	        } else {
	            $dec .= chr(0xC0 | (($c >>  6) & 0x1F));
	            $dec .= chr(0x80 | (($c >>  0) & 0x3F));
	        }
	    }
	    return $dec;
	}
	 
	private function convert_file_to_utf8($csvfile) {
	    $utfcheck = file_get_contents($csvfile);
	    $utfcheck = utf16_to_utf8($utfcheck);
	    file_put_contents($csvfile,$utfcheck);
	}

	public function _check()
	{
		// Get orders that have been completed at least 1 day before and have no antikatavoles yet
		$orders = $this->getOrders();

		Mage::getSingleton('adminhtml/session')->addSuccess( 'Orders: '.count($orders) );
	}

	private function getExisting()
	{
		$data = Mage::getModel('id_geniki/antikatavoles')->getCollection()->addFieldToSelect('order');
		return $data;
	}

	private function getOrders()
	{
		$existing = $this->getExisting();

		$data = Mage::getModel('sales/order')->getCollection()
			->join( array('payment' => 'sales/order_payment'), 'main_table.entity_id=payment.parent_id', array('payment_method' => 'payment.method') )
			->addAttributeToFilter('main_table.created_at', array( 'to'=>date("Y-m-d", strtotime("yesterday")) ))
			->addAttributeToFilter('main_table.status', array( 'in' => array('delivered') ))
			->addAttributeToFilter('main_table.increment_id', array( 'nin' => $existing->getColumnValues('order') ))
			->addAttributeToFilter('main_table.shipping_method', array( 'in' => array('id_geniki_standand','id_geniki_return','id_geniki_reception') ))
			->addAttributeToFilter('payment.method', array( 'in' => array('phoenix_cashondelivery')) );
		return $data;
	}

	public function _reset($pod)
	{
		$antikatavoli = Mage::getModel('id_geniki/antikatavoles')->load($pod, 'pod');
		if( $antikatavoli ) {
			$data = array(
				'status' => 'OK',
			);
			if( $antikatavoli->addData($data)->save() ) {
				Mage::getSingleton('adminhtml/session')->addSuccess( $this->__('Updated') );
			} else {
				Mage::getSingleton('adminhtml/session')->addError( $this->__('Could not update Record') );
			}
		} else {
			Mage::getSingleton('adminhtml/session')->addError( $this->__('Record not found') );
		}
	}
}