<?php

namespace BiFang\SubmitUpsShipment\Model\Config\Source;

class DeclarationLevel implements \Magento\Framework\Option\ArrayInterface {
  public function toOptionArray() {
    return [
      ['value' => '01', 'label' => 'Package Level'],
      ['value' => '02', 'label' => 'Shipment Lebvel']
    ];
  }
}
