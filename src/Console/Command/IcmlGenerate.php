<?php

namespace Retailcrm\Retailcrm\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

class IcmlGenerate extends Command
{
    private $icml;
    private $storeManager;
    private $appState;

    public function __construct(
        \Retailcrm\Retailcrm\Model\Icml\Icml $icml,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\App\State $appState
    ) {
        $this->icml = $icml;
        $this->storeManager = $storeManager;
        $this->appState = $appState;

        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('retailcrm:icml:generate')
            ->setDescription('Generating ICML catalog in root directory')
            ->addArgument('website', InputArgument::OPTIONAL, 'Website id');

        parent::configure();
    }

    /**
     * Generate ICML catalog
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @throws \Exception
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->appState->setAreaCode(\Magento\Framework\App\Area::AREA_GLOBAL);

        $arguments = $input->getArguments();
        $websites = [];

        if (isset($arguments['website'])) {
            $websites[] = $this->storeManager->getWebsite($arguments['website']);
        } else {
            $websites = $this->storeManager->getWebsites();
        }

        if (!$websites) {
            $output->writeln('<comment>Websites not found</comment>');

            return 0;
        }

        foreach ($websites as $website) {
            try {
                $this->icml->generate($website);
            } catch (\Exception $exception) {
                $output->writeln('<comment>' . $exception->getMessage() . '</comment>');

                return 1;
            }

        }

        return 0;
    }
}
