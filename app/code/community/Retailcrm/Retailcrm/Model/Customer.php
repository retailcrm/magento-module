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
            'createdAt' => date('Y-m-d H:i:s', strtotime($data->getCreatedAt()))
        );

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
        $customers = array();
        $customerCollection = Mage::getModel('customer/customer')
        ->getCollection()
        ->addAttributeToSelect('email')
        ->addAttributeToSelect('firstname')
        ->addAttributeToSelect('lastname');
        foreach ($customerCollection as $customerData)
        {
            $customer = array(
                'externalId' => $customerData->getId(),
                'email' => $customerData->getData('email'),
                'firstName' => $customerData->getData('firstname'),
                'lastName' => $customerData->getData('lastname')
            );
            $customers[] = $customer;
        }
        unset($customerCollection);
        $chunked = array_chunk($customers, 50);
        unset($customers);
        foreach ($chunked as $chunk) {
//file_put_contents('/var/www/konzeptual/data/www/konzeptual.ru/tempC.txt', var_export($chunk,true)); die();            
            $this->_api->customersUpload($chunk);
            time_nanosleep(0, 250000000);
        }
        unset($chunked);
        return true;
    }
}
