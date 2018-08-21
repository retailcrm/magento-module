<?php

namespace Retailcrm\Retailcrm\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Magento\Customer\Model\ResourceModel\Customer\Collection;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory;

class CustomersExport extends Command
{
    /**
     * @var CollectionFactory
     */
    private $customerCollectionFactory;

    /**
     * @var Collection
     */
    private $collection;
    private $appState;
    private $api;
    private $helper;
    private $serviceCustomer;

    public function __construct(
        CollectionFactory $customerCollectionFactory,
        \Magento\Framework\App\State $appState,
        \Retailcrm\Retailcrm\Helper\Proxy $api,
        \Retailcrm\Retailcrm\Helper\Data $helper,
        \Retailcrm\Retailcrm\Model\Service\Customer $serviceCustomer
    ) {
        $this->appState = $appState;
        $this->api = $api;
        $this->helper = $helper;
        $this->customerCollectionFactory = $customerCollectionFactory;
        $this->serviceCustomer = $serviceCustomer;

        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('retailcrm:customers:export')
            ->setDescription('Upload archive customers to retailCRM from Magento')
            ->addArgument('from', InputArgument::OPTIONAL, 'Beginning order number')
            ->addArgument('to', InputArgument::OPTIONAL, 'End order number');

        parent::configure();
    }

    /**
     * Upload customers to retailCRM
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @throws \Exception
     *
     * @return boolean
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->appState->setAreaCode(\Magento\Framework\App\Area::AREA_GLOBAL);

        $arguments = $input->getArguments();
        $this->collection = $this->customerCollectionFactory->create();
        $this->collection->addAttributeToSelect('*');

        if ($arguments['from'] !== null && $arguments['to'] !== null) {
            $this->collection->addAttributeToFilter(
                [
                    [
                        'attribute' => 'entity_id',
                        'from' => $arguments['from']
                    ],
                    [
                        'attribute' => 'entity_id',
                        'to' => $arguments['to']
                    ]
                ]
            );
        }

        $customers = $this->collection->getItems();

        if (empty($customers)) {
            $output->writeln('<comment>Customers not found</comment>');

            return false;
        }

        /** @var  \Magento\Customer\Model\Customer $customer */
        foreach ($customers as $customer) {
            $ordersToCrm[$customer->getStore()->getId()][] = $this->serviceCustomer->process($customer);
        }

        foreach ($ordersToCrm as $storeId => $ordersStore) {
            $chunked = array_chunk($ordersStore, 50);
            unset($ordersStore);

            foreach ($chunked as $chunk) {
                $this->api->setSite($this->helper->getSite($storeId));
                $this->api->customersUpload($chunk);
                time_nanosleep(0, 250000000);
            }

            unset($chunked);
        }

        $output->writeln('<info>Uploading customers finished</info>');

        return true;
    }
}
