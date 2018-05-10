<?php
/**
 * Observer
 *
 * @author RetailDriver LLC
 */
class Retailcrm_Retailcrm_Model_Observer
{
    /**
     * Event after order created
     *
     * @param Varien_Event_Observer $observer
     * @return bool
     */
    public function orderCreate(Varien_Event_Observer $observer)
    {
        if (Mage::registry('sales_order_place_after') != 1){//do nothing if the event was dispatched
            $order = $observer->getEvent()->getOrder();
            Mage::getModel('retailcrm/order')->orderCreate($order);
        }

        return true;
    }
    
    public function orderUpdate(Varien_Event_Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        Mage::getModel('retailcrm/order')->orderUpdate($order);
        return true;
    }
    
    public function orderStatusHistoryCheck(Varien_Event_Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        Mage::getModel('retailcrm/order')->orderStatusHistoryCheck($order);
        
        return true;
    }
    
    /**
     * Event after customer created
     *
     * @param Varien_Event_Observer $observer
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     *
     * @return bool
     */
    public function customerRegister(Varien_Event_Observer $observer)
    {
        if (Mage::registry('customer_save_after') != 1) {
            $customer = $observer->getEvent()->getCustomer();
            Mage::getModel('retailcrm/customer')->customerRegister($customer);
        }

        return true;
    }
    
    public function exportCatalog()
    {
        foreach (Mage::app()->getWebsites() as $website) {
            foreach ($website->getStores() as $store) {
                Mage::getModel('retailcrm/icml')->generate($store);
            }
        }
    }

    public function importHistory()
    {
        Mage::getModel('retailcrm/exchange')->ordersHistory();
    }
}
