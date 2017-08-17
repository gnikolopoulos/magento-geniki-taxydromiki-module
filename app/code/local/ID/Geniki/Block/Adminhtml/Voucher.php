<?php

class ID_Geniki_Block_Adminhtml_Voucher extends Mage_Adminhtml_Block_Widget_Grid_Container
{

    public function __construct()
    {
        $this->_controller         = 'adminhtml_voucher';
        $this->_blockGroup         = 'id_geniki';
        parent::__construct();
        $this->_headerText         = Mage::helper('geniki')->__('Vouchers');

        $this->_removeButton('add');
    }
}