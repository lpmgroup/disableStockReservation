<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 *
 * ref Magento\InventorySourceSelectionApi\Model\GetSourceItemQtyAvailableService
 *
 * what's changed: return negetive source item available, include minQty in the availability calculation
 */
declare(strict_types=1);

namespace Lpm\DisableStockReservation\Model;

use Magento\InventoryApi\Api\Data\SourceItemInterface;

/**
 * Get source item qty available for usage in SSA
 * Default implementation that returns source item qty without any modifications
 */
class GetSourceItemQtyAvailableService implements \Magento\InventorySourceSelectionApi\Model\GetSourceItemQtyAvailableInterface
{
    public function __construct(\Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry)
    {
        $this->stockRegistry = $stockRegistry;
    }
    /**
     * @inheritDoc
     */
    public function execute(SourceItemInterface $sourceItem): float
    {
        $stockItem = $this->stockRegistry->getStockItemBySku($sourceItem->getSku());
        $minQty = $stockItem->getMinQty();
        return $stockItem->getQty() - $minQty;
        //return $sourceItem->getQuantity() - $minQty;
    }
}
