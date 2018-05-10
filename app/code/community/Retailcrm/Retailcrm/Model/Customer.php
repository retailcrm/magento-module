<?php
/**
 * Customer class
 *
 * @category Model
 * @package  RetailCrm\Model
 * @author   RetailDriver LLC <integration@retailcrm.ru>
 * @license  http://opensource.org/licenses/MIT MIT License
 * @link     http://www.magentocommerce.com/magento-connect/retailcrm-1.html
 *
 * @SuppressWarnings(PHPMD.CamelCaseClassName)
 */
class Retailcrm_Retailcrm_Model_Customer extends Retailcrm_Retailcrm_Model_Exchange
{
    /**
     * Customer create
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     * @SuppressWarnings(PHPMD.ElseExpression)
     * @param mixed $data
     *
     * @return bool
     */
    public function customerRegister($data)
    {
        $customer = array(
            'externalId' => $data->getId(),
            'email' => $data->getEmail(),
            'firstName' => $data->getFirstname(),
            'patronymic' => $data->getMiddlename(),
            'lastName' => $data->getLastname(),
            'createdAt' => Mage::getSingleton('core/date')->date()
        );
        $this->_api->setSite(Mage::helper('retailcrm')->getSite($data->getStore()));
        $this->_api->customersEdit($customer);
    }

     /**
    * Customers export
    *
    * @SuppressWarnings(PHPMD.StaticAccess)
    * @SuppressWarnings(PHPMD.ElseExpression)
    *
    * @return bool
    */
    public function customersExport()
    {
        $customersSites = array();
        $customerCollection = Mage::getModel('customer/customer')
        ->getCollection()
        ->addAttributeToSelect('email')
        ->addAttributeToSelect('firstname')
        ->addAttributeToSelect('lastname');
        foreach ($customerCollection as $customerData) {
            $customer = array(
                'externalId' => $customerData->getId(),
                'email' => $customerData->getData('email'),
                'firstName' => $customerData->getData('firstname'),
                'lastName' => $customerData->getData('lastname')
            );

            $customersSites[$customerData->getStore()->getId()][] = $customer;
        }

        unset($customerCollection);

        foreach ($customersSites as $storeId => $customers) {
            $chunked = array_chunk($customers, 50);
            unset($customers);
            foreach ($chunked as $chunk) {
                $this->_api->customersUpload($chunk, Mage::helper('retailcrm')->getSite($storeId));
                time_nanosleep(0, 250000000);
            }

            unset($chunked);
        }

        return true;
    }
}
