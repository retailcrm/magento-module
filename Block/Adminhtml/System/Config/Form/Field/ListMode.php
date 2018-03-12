<?php

namespace Retailcrm\Retailcrm\Block\Adminhtml\System\Config\Form\ListMode;

class ListMode implements \Magento\Framework\Option\ArrayInterface
{
 public function toOptionArray()
 {
  return [
    ['value' => 'grid', 'label' => __('Grid Only')],
    ['value' => 'list', 'label' => __('List Only')],
    ['value' => 'grid-list', 'label' => __('Grid (default) / List')],
    ['value' => 'list-grid', 'label' => __('List (default) / Grid')]
  ];
 }
}