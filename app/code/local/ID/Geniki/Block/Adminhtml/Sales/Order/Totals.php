<?php

class ID_Geniki_Block_Adminhtml_Sales_Order_Totals extends Mage_Adminhtml_Block_Sales_Order_Totals
{
    /**
     * Initialize order totals array
     *
     * @return Mage_Sales_Block_Order_Totals
     */
    protected function _initTotals()
    {
        parent::_initTotals();
        $order = $this->getOrder();
        if ($order->getFieldCustomPrice() !== NULL ) {
            $this->addTotalBefore(new Varien_Object(array(
                'code'      => 'geniki',
                'value'     => $order->getFieldCustomPrice(),
                'base_value'=> $order->getFieldCustomPrice(),
                'label'     => Mage::helper('geniki')->__('Αντικαταβολή (Μόνο για αλλαγές)'),
            )));
        }

        return $this;
    }

}