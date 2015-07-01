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
            Mage::getModel('retailcrm/exchange')->ordersCreate($order);
        }

        return true;
    }

    public function exportCatalog()
    {
        foreach (Mage::app()->getWebsites() as $website) {
            foreach ($website->getGroups() as $group) {
                Mage::getModel('retailcrm/icml')->generate((int)$group->getId());
            }
        }
    }

    public function importHistory()
    {
        Mage::getModel('retailcrm/exchange')->ordersHistory();
    }
}
