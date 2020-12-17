<?php

namespace Retailcrm\Retailcrm\Model\Service;

use Retailcrm\Retailcrm\Api\CustomerManagerInterface;

class Customer implements CustomerManagerInterface
{
    private $helper;

    /**
     * Customer constructor.
     *
     * @param \Retailcrm\Retailcrm\Helper\Data $helper
     */
    public function __construct(
        \Retailcrm\Retailcrm\Helper\Data $helper
    ) {
        $this->helper = $helper;
    }

    /**
     * Process customer
     *
     * @param \Magento\Customer\Model\Customer $customer
     *
     * @return array $preparedCustomer
     */
    public function process(\Magento\Customer\Model\Customer $customer)
    {
        $preparedCustomer = [
            'externalId' => $customer->getId(),
            'email' => $customer->getEmail(),
            'firstName' => $customer->getFirstname(),
            'patronymic' => $customer->getMiddlename(),
            'lastName' => $customer->getLastname(),
            'createdAt' => $customer->getCreatedAt()
        ];

        $address = $this->getAddress($customer);
        $phones = $this->getPhones($customer);

        if ($address) {
            $preparedCustomer['address'] = $address;
        }

        if ($phones) {
            $preparedCustomer['phones'] = $this->getPhones($customer);
        }

        if ($this->helper->getGeneralSettings('api_version') == 'v5') {
            if ($customer->getGender()) {
                $preparedCustomer['sex'] = $customer->getGender() == 1 ? 'male' : 'female';
            }

            $preparedCustomer['birthday'] = $customer->getDob();
        }

        return $preparedCustomer;
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     *
     * @return array
     */
    public function prepareCustomerFromOrder(\Magento\Sales\Model\Order $order)
    {
        $billing = $order->getBillingAddress();

        $preparedCustomer = [
            'email' => $billing->getEmail(),
            'firstName' => $billing->getFirstname(),
            'patronymic' => $billing->getMiddlename(),
            'lastName' => $billing->getLastname(),
            'createdAt' => $order->getCreatedAt(),
            'address' => [
                'countryIso' => $billing->getCountryId(),
                'index' => $billing->getPostcode(),
                'region' => $billing->getRegion(),
                'city' => $billing->getCity(),
                'street' => is_array($billing->getStreet())
                    ? implode(', ', $billing->getStreet())
                    : $billing->getStreet(),
                'text' => sprintf(
                    '%s %s %s %s',
                    $billing->getPostcode(),
                    $billing->getRegion(),
                    $billing->getCity(),
                    is_array($billing->getStreet())
                        ? implode(', ', $billing->getStreet())
                        : $billing->getStreet()
                )
            ]
        ];

        if ($billing->getTelephone()) {
            $preparedCustomer['phones'] = [
                [
                    'number' => $billing->getTelephone()
                ]
            ];
        }

        return $preparedCustomer;
    }

    private function getAddress(\Magento\Customer\Model\Customer $customer)
    {
        $billingAddress = $customer->getDefaultBillingAddress();

        if ($billingAddress) {
            $address = [
                'index' => $billingAddress->getData('postcode'),
                'countryIso' => $billingAddress->getData('country_id'),
                'region' => $billingAddress->getData('region'),
                'city' => $billingAddress->getData('city'),
                'street' => $billingAddress->getData('street'),
                'text' => sprintf(
                    '%s %s %s %s',
                    $billingAddress->getData('postcode'),
                    $billingAddress->getData('region'),
                    $billingAddress->getData('city'),
                    $billingAddress->getData('street')
                )
            ];

            return $address;
        }

        return false;
    }

    /**
     * Set customer phones
     *
     * @param \Magento\Customer\Model\Customer $customer
     *
     * @return array $phones
     */
    private function getPhones(\Magento\Customer\Model\Customer $customer)
    {
        $addresses = $customer->getAddressesCollection();
        $phones = [];

        if (!empty($addresses)) {
            foreach ($addresses as $address) {
                $phone = [];
                $phone['number'] = $address->getTelephone();
                $phones[] = $phone;
            }
        }

        return $phones;
    }
}
