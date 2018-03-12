<?php

namespace Retailcrm\Retailcrm\Block\Adminhtml\System\Config\Form\Field;

use Magento\Framework\Data\Form\Element\AbstractElement;
 
class Attributes extends \Magento\Config\Block\System\Config\Form\Field
{
    protected function _getElementHtml(AbstractElement $element)
    {
        $values = $element->getValues();
        $html = '<table id="' . $element->getId() . '_table" class="ui_select_table" cellspacing="0">';
        $html .= '<tbody><tr>';
        $html .= '<td><ul id="' . $element->getId() . '_selected" class="ui_select selected sortable">';

        $selected = explode(',', $element->getValue());

        foreach ($selected as $value) {
            if ($key = array_search($value, array_column($values, 'value'))) {
                $html .= '<li value="' . $value . '" title="' . $values[$key]['title'] . '">';
                $html .= isset($values[$key]['label'])?$values[$key]['label']:'n/a';
                $html .= '</li>';
                $values[$key]['selected'] = TRUE;
            }
        }

        $html .= '</ul></td><td>';
        $html .= '<ul id="' . $element->getId() . '_source" class="ui_select source sortable">';

        if ($values) {
            foreach ($values as $option) {
                if (!isset($option['selected'])) {
                    $html .= '<li value="' . $option['value'] . '" title="' . $option['title'] . '">';
                    $html .= isset($option['label'])?$option['label']:'n/a';
                    $html .= '</li>';
                }
            }
        }

        $html .= '</ul></td></tr></tbody></table>';
        $html .= '<div style="display:none;">' . $element->getElementHtml() . '</div>';
        $html .= '<script type="text/javascript">
        require(["jquery"], function(jQuery){
            require(["BelVG_Pricelist/js/verpage", "ui/1.11.4"], function(){
                jQuery(document).ready( function() {
                    jQuery("#' . $element->getId() . '_selected, #' . $element->getId() . '_source").sortable({
                        connectWith: ".sortable",
                        stop: function(event, ui) {
                            var vals = [];
                            jQuery("#' . $element->getId() . '_selected").find("li").each(function(index, element){
                                vals.push(jQuery(element).val());
                            });
                            jQuery("#' . $element->getId() . '").val(vals);
                        }
                    }).disableSelection();
                });
            })
        })
        </script>';

        return $html;
    }
}
