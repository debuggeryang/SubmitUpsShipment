<?php

namespace BiFang\SubmitUpsShipment\Model\Config\Source;

class TransportationMode implements \Magento\Framework\Option\ArrayInterface {
  public function toOptionArray() {
    return [
      ['value' => 'Highway', 'label' => 'Highway'],
      ['value' => 'Ground', 'label' => 'Ground'],
      ['value' => 'PAX', 'label' => 'Passenger Aircraft'],
      ['value' => 'CAO', 'label' => 'Cargo Aircraft Only']
    ];
  }
}
