# Lpm/magento2-disable-stock-reservation

Extending [AmpersandHQ/magento2-disable-stock-reservation](https://github.com/AmpersandHQ/magento2-disable-stock-reservation)

to resolve a mixture of the below problems

[disable stock reservation](https://github.com/magento/inventory/issues/2269)
[Can't set products' Out of Stock Threshold to a negative value](https://github.com/magento/magento2/issues/25158)
[Unable to set negative product's quantity](https://github.com/magento/magento2/issues/9139)

caused by the introduction of MSI since Magento2.3. 

*Test the use cases described below after upgrading magento*

1. Ability to save negetive product qty
2. Ability to save negetive OOS threhold (must set backorder to "Allow qty below 0" / "Allow qty below 0 and notify customer")
3. Stock status is correct and Salesable Qty is calculated correctly after the following actions:
    - save product
    - place order
    - credit memo & refund
    - update qty by external source (eg an ERP system)

## Troubleshoot
Require `truncate inventory_reservation;` as stated in Ampersand_DisableStockReservation

## Extra referencing:
https://github.com/magento/magento2/commit/6f4df9be6a8f2a43b08b3831821d01fe6f1e4c2f#diff-1555bd96a37cc3d7a70869442e07a012