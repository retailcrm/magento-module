<?php

namespace Retailcrm\Retailcrm\Model\Setting;

class ApiVersions implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'v4', 'label' => 'v4'],
            ['value' => 'v5', 'label' => 'v5']
        ];
    }
}
