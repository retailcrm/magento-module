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
        $order = $observer->getEvent()->getOrder();
        Mage::getModel('retailcrm/exchange')->orderCreate($order);

        return true;
    }

    /**
     * Event after order updated
     *
     * @param Varien_Event_Observer $observer
     * @return bool
     */
    public function orderUpdate(Varien_Event_Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();

        if($order->getExportProcessed()){ //check if flag is already set for prevent triggering twice.
            return;
        }

        Mage::getModel('retailcrm/exchange')->orderEdit($order);

        $order->setExportProcessed(true);

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
}
