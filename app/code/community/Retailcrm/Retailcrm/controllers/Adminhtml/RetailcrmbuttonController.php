<?php
class Retailcrm_Retailcrm_Adminhtml_RetailcrmbuttonController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Return some checking result
     *
     * @return void
     */
    public function checkAction()
    {
        $orders = Mage::getModel('retailcrm/order');
        $orders ->ordersExportNumber();
    }
    
    /**
     * Check is allowed access to action
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('system/config/retailcrm');
    }
}