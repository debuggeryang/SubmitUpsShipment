<?php

namespace BiFang\SubmitUpsShipment\Model\Config\Source;

class DeclarationLevel implements \Magento\Framework\Option\ArrayInterface {
  public function toOptionArray() {
    return [
      ['value' => '01', 'label' => __('Package Level')],
      ['value' => '02', 'label' => __('Shipment Level')]
    ];
  }
}
