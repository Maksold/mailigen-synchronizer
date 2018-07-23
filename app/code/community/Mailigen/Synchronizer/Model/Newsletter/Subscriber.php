<?php

class Mailigen_Synchronizer_Model_Newsletter_Subscriber extends Mailigen_Synchronizer_Model_Newsletter_SubscriberBase
{
    /**
     * @return $this|Mage_Newsletter_Model_Subscriber
     */
    public function sendUnsubscriptionEmail()
    {
        if (Mage::helper('mailigen_synchronizer')->canHandleDefaultEmails()) {
            return $this;
        } else {
            return parent::sendUnsubscriptionEmail();
        }
    }

    /**
     * @return $this|Mage_Newsletter_Model_Subscriber
     */
    public function sendConfirmationRequestEmail()
    {
        if (Mage::helper('mailigen_synchronizer')->canHandleDefaultEmails()) {
            return $this;
        } else {
            return parent::sendConfirmationRequestEmail();
        }
    }

    /**
     * @return $this|Mage_Newsletter_Model_Subscriber
     */
    public function sendConfirmationSuccessEmail()
    {
        if (Mage::helper('mailigen_synchronizer')->canHandleDefaultEmails()) {
            return $this;
        } else {
            return parent::sendConfirmationSuccessEmail();
        }
    }
}
