## BiFang Submit UPS Shipment Extension
#### developerlily@hotmail.com
======================

#### Description

This extension brings UPS_Shipping the function to create consignments and shipment labels when we create the shipment for an order in Magento2.

The function was created based on the Dhl_Shipping , so when using this extension, please make sure it is enabled.

This extension may have compatibility issues if Dhl_Shipping version is not 0.10.3.


#### Update Log:

- 11-May-2020 0.0.1
  - Now we can create UPS labels when creating new shipment.
  - Known issues:
    - UPS does not have checkboxes for "Dangerous Goods", "Authority to Leave", "Signature Required", ...
    - UPS weight unit configuration cannot be applied
    - Once you close the packaging modal and switch to another carrier and open the modal again, the packaging template for the previous carrier is still there
