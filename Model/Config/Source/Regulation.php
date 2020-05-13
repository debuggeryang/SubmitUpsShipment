<?php

namespace BiFang\SubmitUpsShipment\Model\Config\Source;

class Regulation implements \Magento\Framework\Option\ArrayInterface {
  public function toOptionArray() {
    return [
      ['value' => 'ADR', 'label' => 'ADR'],
      ['value' => 'CFR', 'label' => 'CFR'],
      ['value' => 'IATA', 'label' => 'IATA'],
      ['value' => 'TDG', 'label' => 'TDG']
    ];
  }
}
