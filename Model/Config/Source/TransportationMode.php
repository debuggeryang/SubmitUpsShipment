<?php

namespace BiFang\SubmitUpsShipment\Model\Config\Source;

class TransportationMode implements \Magento\Framework\Option\ArrayInterface {
  public function toOptionArray() {
    return [
      ['value' => 'Highway', 'label' => __('Highway')],
      ['value' => 'Ground', 'label' => __('Ground')],
      ['value' => 'PAX', 'label' => __('Passenger Aircraft')],
      ['value' => 'CAO', 'label' => __('Cargo Aircraft Only')]
    ];
  }
}
