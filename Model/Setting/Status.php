<?php

namespace Retailcrm\Retailcrm\Model\Setting;

class Status extends \Magento\Config\Model\Config\Backend\Serialized\ArraySerialized
{
    public function beforeSave()
    {
        // For value validations
        $exceptions = $this->getValue();
 
        // Validations 
        $this->setValue($exceptions);
 
        return parent::beforeSave();
    }
}
