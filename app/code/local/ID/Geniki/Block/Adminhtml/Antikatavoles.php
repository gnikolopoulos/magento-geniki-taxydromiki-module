<?php

class ID_Geniki_Block_Adminhtml_Antikatavoles extends Mage_Adminhtml_Block_Widget_Grid_Container
{

    public function __construct()
    {
        $this->_controller         = 'adminhtml_antikatavoles';
        $this->_blockGroup         = 'id_geniki';
        parent::__construct();
        $this->_headerText         = Mage::helper('geniki')->__('Antikatavoles');

        $this->_addButton('uploadxls', array(
	        'label' 		=> Mage::helper('geniki')->__('Upload XLS'),
	        'onclick'       => 'document.getElementById(\'uploadTarget\').click();',
	        'class' 		    => 'add',
	        'after_html'    => '<form method="POST" action="'.$this->getUrl('*/geniki/uploadxls', array()).'" id="uploadForm" enctype="multipart/form-data">
									<input name="form_key" type="hidden" value="'.Mage::getSingleton('core/session')->getFormKey().'" />
									<input type="file" name="filename" style="display:none;" id="uploadTarget"/>
								</form>
								<script type="text/javascript">
								document.getElementById(\'uploadTarget\').addEventListener(\'change\', function(){
									document.getElementById(\'uploadForm\').submit();
								}, false);
								</script>',
	    ));

	    $this->_addButton('checkorders', array(
	        'label' 	=> Mage::helper('geniki')->__('Check Orders'),
	        'onclick' => "setLocation('" . $this->getUrl('*/geniki/checkorders', array()) . "')",
	        'class' 	=> 'go',
	    ));

        $this->_removeButton('add');
    }
}