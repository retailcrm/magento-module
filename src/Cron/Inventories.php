<?php

namespace Retailcrm\Retailcrm\Cron;

class Inventories
{
    private $inventoriesUpload;
    private $helper;

    public function __construct(
        \Retailcrm\Retailcrm\Helper\Data $helper,
        \Retailcrm\Retailcrm\Model\Service\InventoriesUpload $InventoriesUpload
    ) {
        $this->helper = $helper;
        $this->inventoriesUpload = $InventoriesUpload;
    }

    public function execute()
    {
        if ($this->helper->getInventoriesUpload() == true) {
            $this->inventoriesUpload->uploadInventory();
        }
    }
}
