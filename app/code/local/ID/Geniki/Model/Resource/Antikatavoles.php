<?php

class ID_Geniki_Model_Resource_Antikatavoles extends Mage_Core_Model_Resource_Db_Abstract
{
    public function _construct()
    {
        $this->_init('id_geniki/antikatavoles', 'entity_id');
    }
}