<?php
class Mailigen_Synchronizer_Model_List extends  Mage_Core_Model_Abstract {

	public function _construct()
	{
		parent::_construct();
		$this->_init('mailigen_synchronizer/list');
	}

	 public function toOptionArray()
     {
	  
		$mgapi 	= Mage::getModuleDir('','Mailigen_Synchronizer') . DS . 'api' . DS . 'MGAPI.class.php';
		$apikey	= Mage::getStoreConfig('mailigen_settings/mailigen_general_group/mailigen_general_api_key');
		require_once( $mgapi ); 
		
		$api = new MGAPI($apikey);

		$lists = $api->lists();

		//print_r($lists);
			
		if( !$api->errorCode && $lists){
                    $array[] = array('label' => '--Create a new list--','value'=>'');
                    foreach($lists as $list){
                            $array[] = array('label'=>$list['name'],'value'=>$list['id']);
                    }
                    return $array;
		
		}
		
	   
     }
}
