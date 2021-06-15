<?php
/**
 * @author wilson chung <wilson@lpm.hk>
 */
declare(strict_types=1);

namespace Lpm\DisableStockReservation\Plugin;

use Magento\CatalogInventory\Api\StockStateInterface;

/**
 * disable the around plugin in \Magento\InventorySales\Plugin\StockState\CheckQuoteItemQtyPlugin
 * to check quoteItemQty with stock item instead of stock by sales channel
 * so Lpm\DisableStockReservation\Plugin\StockStateProvider is able to kick in
 */
class CheckQuoteItemQtyPlugin extends \Magento\InventorySales\Plugin\StockState\CheckQuoteItemQtyPlugin
{

    /**
     * Replace legacy quote item check
     *
     * @param StockStateInterface $subject
     * @param \Closure $proceed
     * @param int $productId
     * @param float $itemQty
     * @param float $qtyToCheck
     * @param float $origQty
     * @param int|null $scopeId
     *
     * @return DataObject
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundCheckQuoteItemQty(
        StockStateInterface $subject,
        \Closure $proceed,
        $productId,
        $itemQty,
        $qtyToCheck,
        $origQty,
        $scopeId = null
    ) {
        return $proceed($productId, $itemQty, $qtyToCheck, $origQty, $scopeId);
    }

}
