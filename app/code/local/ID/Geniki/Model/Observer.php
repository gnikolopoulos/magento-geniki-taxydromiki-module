<?php

class ID_Geniki_Model_Observer
{

	public function addButtonVoucher($observer)
	{
	    $container = $observer->getBlock();
	    $order = Mage::app()->getRequest()->getParams();

	    if( $container instanceof Mage_Adminhtml_Block_Sales_Order_View ) {
	    	$order_obj = Mage::getModel('sales/order')->load($order['order_id']);
	    	if( !$order_obj->isCanceled() && $order_obj->canShip() ) {
		        $data = array(
		            'label'     => Mage::helper('geniki')->__('Create Voucher'),
		            'class'     => 'go',
		            'onclick'   => 'setLocation(\''  . Mage::helper('adminhtml')->getUrl('*/geniki/create', array('order' => $order['order_id'])) . '\')',
		        );
		        $container->addButton('create_voucher', $data);
		    }

		    if( !$order_obj->isCanceled() && $order_obj->getStatus() == 'complete' ) {
		        $data = array(
		            'label'     => Mage::helper('geniki')->__('Print Voucher'),
		            'class'     => 'go',
		            'onclick'   => 'setLocation(\''.Mage::helper('adminhtml')->getUrl('*/geniki/reprintVoucher', array('order' => $order['order_id'])) . '\')',
		        );
		        $container->addButton('print_voucher', $data);

		        $data = array(
		            'label'     => Mage::helper('geniki')->__('Delete Voucher'),
		            'class'     => 'go',
		            'onclick'	=> "confirmSetLocation('".Mage::helper('geniki')->__('Are you sure you want to delete this voucher?')."', '".Mage::helper('adminhtml')->getUrl('*/geniki/deleteVoucher', array('order' => $order['order_id']))."')"
		        );
		        $container->addButton('delete_voucher', $data);
		    }
	    }

	    return $this;
	}

	public function addActions($observer)
	{
		$block = $observer->getEvent()->getBlock();
	    if(get_class($block) =='Mage_Adminhtml_Block_Widget_Grid_Massaction' && $block->getRequest()->getControllerName() == 'sales_order')
	    {
	      $block->addItem('createvouchers', array(
	        'label' => Mage::helper('geniki')->__('Create Vouchers'),
	        'url' => Mage::app()->getStore()->getUrl('*/geniki/massVouchers'),
	      ));

	      $block->addItem('printvouchers', array(
	        'label' => Mage::helper('geniki')->__('Print Vouchers'),
	        'url' => Mage::app()->getStore()->getUrl('*/geniki/massReprint'),
	      ));
	    }

	  return $this;
	}

	public function saveCustomData($event)
	{
		$quote = $event->getSession()->getQuote();
		$quote->setData('field_custom_price', $event->getRequestModel()->getPost('field_custom_price'));

		return $this;
	}

}