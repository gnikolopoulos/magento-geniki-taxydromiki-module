<?php

$this->startSetup();
$table_list = $this->getConnection()
    ->newTable($this->getTable('id_geniki/list'))
    ->addColumn(
        'entity_id',
        Varien_Db_Ddl_Table::TYPE_INTEGER,
        null,
        array(
            'identity'  => true,
            'nullable'  => false,
            'primary'   => true,
        ),
        'Entity ID'
    )
    ->addColumn(
        'massnumber',
        Varien_Db_Ddl_Table::TYPE_TEXT,
        255,
        array(
            'nullable'  => false,
        ),
        'Mass Number'
    )
    ->addColumn(
        'created_at',
        Varien_Db_Ddl_Table::TYPE_TIMESTAMP,
        null,
        array(),
        'List Creation Time'
    )
    ->setComment('List Table');

$this->getConnection()->createTable($table_list);

$table_voucher = $this->getConnection()
    ->newTable($this->getTable('id_geniki/voucher'))
    ->addColumn(
        'entity_id',
        Varien_Db_Ddl_Table::TYPE_INTEGER,
        null,
        array(
            'identity'  => true,
            'nullable'  => false,
            'primary'   => true,
        ),
        'Entity ID'
    )
    ->addColumn(
        'pod',
        Varien_Db_Ddl_Table::TYPE_TEXT,
        255,
        array(
            'nullable'  => false,
        ),
        'POD No'
    )
    ->addColumn(
        'jobid',
        Varien_Db_Ddl_Table::TYPE_TEXT,
        255,
        array(
            'nullable'  => false,
        ),
        'Job ID'
    )
    ->addColumn(
        'orderno',
        Varien_Db_Ddl_Table::TYPE_TEXT,
        255,
        array(
            'nullable'  => false,
        ),
        'Order No'
    )
    ->addColumn(
        'created_at',
        Varien_Db_Ddl_Table::TYPE_TIMESTAMP,
        null,
        array(),
        'POD Creation Time'
    )
    ->addColumn(
        'status',
        Varien_Db_Ddl_Table::TYPE_TEXT,
        255,
        array(
            'nullable'  => false,
        ),
        'POD Status'
    )
    ->addColumn(
        'is_printed',
        Varien_Db_Ddl_Table::TYPE_INTEGER,
        null,
        array(
            'nullable'  => false,
        ),
        'Print Status'
    )
    ->setComment('Voucher Table');

$this->getConnection()->createTable($table_voucher);

$table_antik = $this->getConnection()
    ->newTable($this->getTable('id_geniki/antikatavoles'))
    ->addColumn(
        'entity_id',
        Varien_Db_Ddl_Table::TYPE_INTEGER,
        null,
        array(
            'identity'  => true,
            'nullable'  => false,
            'primary'   => true,
        ),
        'Entity ID'
    )
    ->addColumn(
        'pod',
        Varien_Db_Ddl_Table::TYPE_TEXT,
        255,
        array(
            'nullable'  => false,
        ),
        'POD No'
    )
    ->addColumn(
        'customer_name',
        Varien_Db_Ddl_Table::TYPE_TEXT,
        255,
        array(),
        'Customer Name'
    )
    ->addColumn(
        'order',
        Varien_Db_Ddl_Table::TYPE_TEXT,
        255,
        array(
            'nullable'  => false,
        ),
        'Order Increment ID'
    )
    ->addColumn(
        'value',
        Varien_Db_Ddl_Table::TYPE_DECIMAL,
        null,
        array(
            'nullable'  => false,
            'scale'     => 2,
            'precision' => 9,
        ),
        'POD Value'
    )
    ->addColumn(
        'status',
        Varien_Db_Ddl_Table::TYPE_TEXT,
        255,
        array(
            'nullable'  => false,
        ),
        'POD Status'
    )
    ->addColumn(
        'date',
        Varien_Db_Ddl_Table::TYPE_TIMESTAMP,
        null,
        array(),
        'Date Delivered'
    )
    ->setComment('Voucher Table');

$this->getConnection()->createTable($table_antik);

$this->getConnection()
    ->addColumn($this->getTable('sales/order'),'field_custom_price', array(
        'type'      => Varien_Db_Ddl_Table::TYPE_TEXT,
        'nullable'  => true,
        'length'    => 255,
        'after'     => null, // column name to insert new column after
        'comment'   => 'Custom Price'
        ));

$this->getConnection()
    ->addColumn($this->getTable('sales/quote'),'field_custom_price', array(
        'type'      => Varien_Db_Ddl_Table::TYPE_TEXT,
        'nullable'  => true,
        'length'    => 255,
        'after'     => null, // column name to insert new column after
        'comment'   => 'Custom Price'
        ));

$this->endSetup();