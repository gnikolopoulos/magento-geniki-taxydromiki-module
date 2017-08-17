<?php

class ID_Geniki_Block_Adminhtml_Antikatavoles_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    /**
     * constructor
     * @access public
     */
    public function __construct()
    {
        parent::__construct();
        $this->setId('id_geniki_grid');
        $this->setDefaultSort('date');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);
    }

    /**
     * prepare collection
     *
     * @access protected
     * @return ID_Geniki_Block_Adminhtml_Antikatavoles_Grid
     */
    protected function _prepareCollection()
    {
        $collection = Mage::getModel('id_geniki/antikatavoles')->getCollection();
        $collection->join(
            array('orders' => 'sales/order'), // alias => model
            'orders.increment_id = main_table.order', // join on
            array('grand_total','field_custom_price') // fields
        );
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    /**
     * prepare grid collection
     *
     * @access protected
     * @return ID_Geniki_Block_Adminhtml_Antikatavoles_Grid
     */
    protected function _prepareColumns()
    {
        $this->addColumn(
            'entity_id',
            array(
                'header'        => Mage::helper('geniki')->__('#'),
                'align'         => 'center',
                'field_name'    => 'entity_id',
                'index'         => 'entity_id',
                'width'         => '20px',
                'type'          => 'checkbox',
                'values'        => $this->getEntityId(),
            )
        );
        $this->addColumn(
            'pod',
            array(
                'header'    => Mage::helper('geniki')->__('Voucher'),
                'align'     => 'left',
                'width'     => '50px',
                'index'     => 'pod',
            )
        );
        $this->addColumn(
            'customer_name',
            array(
                'header'    => Mage::helper('geniki')->__('Customer Name'),
                'align'     => 'left',
                'width'     => '200px',
                'index'     => 'customer_name',
            )
        );
        $this->addColumn(
            'order',
            array(
                'header'    => Mage::helper('geniki')->__('Order'),
                'align'     => 'left',
                'width'     => '100px',
                'index'     => 'order',
            )
        );
        $this->addColumn(
            'value',
            array(
                'header'    => Mage::helper('geniki')->__('Voucher Value'),
                'align'     => 'left',
                'width'     => '50px',
                'index'     => 'value',
            )
        );
        $this->addColumn(
            'grand_total',
            array(
                'header'    => Mage::helper('geniki')->__('Order Total'),
                'align'     => 'left',
                'width'     => '50px',
                'index'     => 'grand_total',
            )
        );
        $this->addColumn(
            'field_custom_price',
            array(
                'header'    => Mage::helper('geniki')->__('Geniki Amount'),
                'align'     => 'left',
                'width'     => '20px',
                'index'     => 'field_custom_price',
            )
        );
        $this->addColumn(
            'status',
            array(
                'header'        => Mage::helper('geniki')->__('Status'),
                'align'         => 'left',
                'width'         => '80px',
                'index'         => 'status',
                'filter_index'  => 'main_table.status',
                'type'          => 'options',
                'options'       => $this->_getUniqueStatus(),
            )
        );
        $this->addColumn(
            'date',
            array(
                'header' => Mage::helper('geniki')->__('Delivered at'),
                'index'  => 'date',
                'width'  => '150px',
                'type'   => 'datetime',
            )
        );
        $this->addColumn(
            'action',
            array(
                'header'  =>  Mage::helper('geniki')->__('Action'),
                'width'   => '50px',
                'type'    => 'action',
                'getter'  => 'getPod',
                'actions' => array(
                    array(
                        'caption' => Mage::helper('geniki')->__('Reset'),
                        'url'     => array('base'=> '*/*/resetAntikatavoli'),
                        'field'   => 'pod',
                    )
                ),
                'filter'    => false,
                'is_system' => true,
                'sortable'  => false,
            )
        );
        return parent::_prepareColumns();
    }

    /**
     * get unique statuses from model
     *
     * @access protected
     * @param ID_Geniki_Model_Antikatavoles
     * @return string
     */
    protected function _getUniqueStatus()
    {
        $collection = Mage::getModel('id_geniki/antikatavoles')
                        ->getCollection()
                        ->distinct(true)
                        ->addFieldToSelect('status')
                        ->load();

        $status = array();
        foreach($collection as $c){
            $status[$c->getStatus()] = $c->getStatus();
        }

        return $status;
    }

    /**
     * get the row url
     *
     * @access public
     * @param ID_Geniki_Model_Antikatavoles
     * @return string
     */
    public function getRowUrl($row)
    {
        return $this->getUrl('*/sales_order/view', array('order_id' => Mage::getModel('sales/order')->load($row->getOrder(), 'increment_id')->getId()));
    }

    /**
     * get the grid url
     *
     * @access public
     * @return string
     */
    public function getGridUrl()
    {
        return $this->getUrl('*/*/grid_antikatavoles', array('_current'=>true));
    }

    /**
     * after collection load
     *
     * @access protected
     * @return ID_Geniki_Block_Adminhtml_Antikatavoles_Grid
     */
    protected function _afterLoadCollection()
    {
        $this->getCollection()->walk('afterLoad');
        parent::_afterLoadCollection();
    }
}
