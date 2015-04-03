<?php 

class Mailigen_Synchronizer_Adminhtml_MailigenController extends Mage_Adminhtml_Controller_Action {
    
    public function syncAction(){
        
        $mailigen = Mage::getModel('mailigen_synchronizer/mailigen');
        $mailigen->sync();
        
        $this->_redirect('*/newsletter_subscriber/index');
    }
    
}