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
- 12-May-2020 0.0.2
  - Fixed issues:
    - UPS weight unit config can be applied correctly now.
    - All existing package information will be removed once you close the packaging modal
  - Known issues:
    - UPS does not have checkboxes for "Dangerous Goods", "Authority to Leave", "Signature Required", ...
    - UPS cannot get dangerous goods labels
    - Only have English language
- 13-May-2020 0.0.3
  - Fixed issues:
    - We can send dangerous goods with UPS now
    - Booking UPS dangerous goods can get the dangerous goods paper, it comes with the shipping labels
    - We add German language support
- 31-May-2020 0.04
  - Fixed issues:
    - We can book multi shipments for one order now
