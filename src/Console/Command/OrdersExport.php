<?php

namespace Retailcrm\Retailcrm\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

class OrdersExport extends Command
{
    private $orderRepository;
    private $searchCriteriaBuilder;
    private $appState;
    private $serviceOrder;
    private $api;
    private $helper;

    public function __construct(
        \Magento\Sales\Model\OrderRepository $orderRepository,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Framework\App\State $appState,
        \Retailcrm\Retailcrm\Model\Service\Order $serviceOrder,
        \Retailcrm\Retailcrm\Helper\Proxy $api,
        \Retailcrm\Retailcrm\Helper\Data $helper
    ) {
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->appState = $appState;
        $this->serviceOrder = $serviceOrder;
        $this->api = $api;
        $this->helper = $helper;

        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('retailcrm:orders:export')
            ->setDescription('Upload archive orders to retailCRM from Magento')
            ->addArgument('from', InputArgument::OPTIONAL, 'Beginning order number')
            ->addArgument('to', InputArgument::OPTIONAL, 'End order number');

        parent::configure();
    }

    /**
     * Upload orders to retailCRM
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

        if ($arguments['from'] !== null && $arguments['to'] !== null) {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('increment_id', $arguments['from'], 'from')
                ->addFilter('increment_id', $arguments['to'], 'to')
                ->create();
        } else {
            $searchCriteria = $this->searchCriteriaBuilder->create();
        }

        $resultSearch = $this->orderRepository->getList($searchCriteria);
        $orders = $resultSearch->getItems();

        if (empty($orders)) {
            $output->writeln('<comment>Orders not found</comment>');

            return false;
        }

        /** @var \Magento\Sales\Model\Order $order */
        foreach ($orders as $order) {
            $ordersToCrm[$order->getStore()->getId()][] = $this->serviceOrder->process($order);
        }

        foreach ($ordersToCrm as $storeId => $ordersStore) {
            $chunked = array_chunk($ordersStore, 50);
            unset($ordersStore);

            foreach ($chunked as $chunk) {
                $this->api->setSite($this->helper->getSite($storeId));
                $this->api->ordersUpload($chunk);
                time_nanosleep(0, 250000000);
            }

            unset($chunked);
        }

        $output->writeln('<info>Uploading orders finished</info>');

        return true;
    }
}
