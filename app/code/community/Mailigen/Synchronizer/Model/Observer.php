<?php 

class Mailigen_Synchronizer_Model_Observer {

	public function newsletter_subscriber_create_after( Varien_Event_Observer $observer ){
	
		$enabled 			= Mage::getStoreConfig('mailigen_settings/mailigen_general_group/mailigen_general_status');
		$delete_on_unsubscribe 		= Mage::getStoreConfig('mailigen_settings/mailigen_unsubscribe_group/mailigen_unsubscribe_delete');



		$subscriber 	= $observer->getDataObject();
		$data           = $subscriber->getData();
		$statusChange 	= $subscriber->getIsStatusChanged();

		if( $enabled && $statusChange == true ) {

			$mgapi 	= Mage::getModuleDir('','Mailigen_Synchronizer') . DS . 'api' . DS . 'MGAPI.class.php';
			$apikey	= Mage::getStoreConfig('mailigen_settings/mailigen_general_group/mailigen_general_api_key');
			$listid	= Mage::getStoreConfig('mailigen_settings/mailigen_general_group/mailigen_general_list');
		
			require_once( $mgapi ); 
			$api = new MGAPI($apikey);

			$email_address 		= $observer['subscriber']->getSubscriberEmail();
			$merge_vars 		= array('EMAIL'=>$email_address); // or $merge_vars = array();
			$email_type 		= 'html';
			$double_optin 		= false;
			$update_existing 	= true;
                        
                        
                        //If mailigen transational emails are set from admin.
                        $send_flag = Mage::getStoreConfig('mailigen_settings/mailigen_general_group/mailigen_default_emails');
                        
                        if( $send_flag ){
                            $send_welcome = true;
                            $send_goodbye = true;
                        }else{
                            $send_welcome = false;
                            $send_goodbye = false;
                        }
            
                        //if is a customer we also grab firstname and lastname
                        if( $observer['subscriber']->getCustomerId() ){
                            $customer = Mage::getModel("customer/customer"); 
                            $customer->load( $observer['subscriber']->getCustomerId() );

                            $merge_vars['FNAME'] = $customer->getFirstname(); 
                            $merge_vars['LNAME'] = $customer->getLastname(); 

                        }
                        
                        Mage::log( "Subscribe: " . $send_flag );

			if ($data['subscriber_status'] === 1) {
				$retval = $api->listSubscribe($listid, $email_address, $merge_vars, $email_type, $double_optin, $update_existing, $send_welcome);
			}else{
				$retval = $api->listUnsubscribe($listid, $email_address, $delete_member, $send_goodbye, $send_notify);
			}



			if ($api->errorCode){
				Mage::log( "Mailigen API Error: " . "Code=".$api->errorCode. " Msg=".$api->errorMessage );
			} else{
				Mage::log("Returned: ".$retval);
			}
		}
		
		return $observer;
	}
    
    public function daily_sync( Varien_Event_Observer $observer ){
        $autosync = Mage::getStoreConfig('mailigen_settings/mailigen_general_group/mailigen_default_emails');
        if( $autosync == 'yes' ) {
            $mailigen = Mage::getModel('mailigen_synchronizer/mailigen');
            $mailigen->sync();
        }
    }
    
    public function admin_system_config_changed_section_mailigen_settings ( Varien_Event_Observer $observer ){
        
        $new_list_name  = Mage::getStoreConfig('mailigen_settings/mailigen_general_group/mailigen_general_new_list');
        $notify_to      = Mage::getStoreConfig('trans_email/ident_general/email');
        
        if( $new_list_name ) {
            
            //Get the list with current lists
            $lists   = Mage::getModel('mailigen_synchronizer/list')->toOptionArray();
            
            //We need this later on
            $config_model = new Mage_Core_Model_Config();
            
            //Check if a similar list name doesn't exists already.
            $continue = true;
            foreach($lists as $list){
                if($list['label'] == $new_list_name){
                    $continue = false;
                    Mage::getSingleton('adminhtml/session')->addError( "A list with a simiar name already exists" );
                    break;
                }
            }
            
            //Only if a list with a similar name is not doesn't exists we move further.
            if( $continue ) {
            
                $options = array(
                    'permission_reminder'       => ' ',
                    'notify_to'                 => $notify_to,
                    'subscription_notify'       => true,
                    'unsubscription_notify'     => true,
                    'has_email_type_option'     => true
                );

                $mgapi 	= Mage::getModuleDir('','Mailigen_Synchronizer') . DS . 'api' . DS . 'MGAPI.class.php';
                $apikey	= Mage::getStoreConfig('mailigen_settings/mailigen_general_group/mailigen_general_api_key');
                require_once( $mgapi ); 
                $api = new MGAPI($apikey);

                $retval = $api->listCreate($new_list_name, $options);

                if ($api->errorCode){
                    Mage::log( "Mailigen API Error: " . "Code=".$api->errorCode. " Msg=".$api->errorMessage );
                } else{
                    Mage::log("Returned: ".$retval);
                }
                
                //We grab the list one more time
                $lists   = Mage::getModel('mailigen_synchronizer/list')->toOptionArray();
                foreach($lists as $list){
                    if($list['label'] == $new_list_name){    
                        //We make the new submitted list default
                        $config_model ->saveConfig('mailigen_settings/mailigen_general_group/mailigen_general_list', $list['value'], 'default', 0);
                        continue;
                    }
                }
            }
            
            $config_model ->saveConfig('mailigen_settings/mailigen_general_group/mailigen_general_new_list', "", 'default', 0);
            
            
        }
        
        
        
    }
    
}