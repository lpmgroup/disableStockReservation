<?php
/**
 * @author wilson chung <wilson@lpm.hk>
 *
 * ref Magento\CatalogInventory\Model\StockStateProvider
 */

namespace Lpm\DisableStockReservation\Plugin;

use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Model\Spi\StockStateProviderInterface;

class StockStateProvider
{


    /**
     * Check quantity
     *
     * the result of original checkQty() in Magento\CatalogInventory\Model\StockStateProvider 
     * is incorrect in the following use case:
     *     1. when product is BACKORDERS_YES
     *     2. when product has negative qty
     *     3. when product has negative out-of-stock threshold
     *     4. when customer add product to cart which result in the new qty < out-of-stock threshold
     *
     * eg. 
     *     qty = -4, oosth = -10, adding 7 to cart, 
     *     expect: action fail
     *     actual: action success
     *
     * @param StockItemInterface $stockItem
     * @param int|float $qty
     * @exception \Magento\Framework\Exception\LocalizedException
     * @return bool
     */
    public function afterCheckQty(\Magento\CatalogInventory\Model\Spi\StockStateProviderInterface $subject, $result, StockItemInterface $stockItem, $qty)
    {
        if (
            $stockItem->getQty() - $stockItem->getMinQty() - $qty < 0 
            && $stockItem->getMinQty() !== 0
        ) {
            return false;
        }
        return $result;
    }
}
