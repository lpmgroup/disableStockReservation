<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 *
 * ref Magento\InventorySourceDeductionApi\Model\SourceDeductionService
 *
 * what's changed: removed the qty >=0 check to allow negetive qty in source
 */
declare(strict_types=1);

namespace Lpm\DisableStockReservation\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\InventoryConfigurationApi\Api\Data\StockItemConfigurationInterface;
use Magento\InventoryConfigurationApi\Api\GetStockItemConfigurationInterface;
use Magento\InventorySalesApi\Api\GetStockBySalesChannelInterface;

/**
 * @inheritdoc
 */
class SourceDeductionService implements \Magento\InventorySourceDeductionApi\Model\SourceDeductionServiceInterface
{
    /**
     * Constant for zero stock quantity value.
     */
    private const ZERO_STOCK_QUANTITY = 0.0;

    /**
     * @var SourceItemsSaveInterface
     */
    private $sourceItemsSave;

    /**
     * @var GetSourceItemBySourceCodeAndSku
     */
    private $getSourceItemBySourceCodeAndSku;

    /**
     * @var GetStockItemConfigurationInterface
     */
    private $getStockItemConfiguration;

    /**
     * @var GetStockBySalesChannelInterface
     */
    private $getStockBySalesChannel;

    /**
     * @param SourceItemsSaveInterface $sourceItemsSave
     * @param GetSourceItemBySourceCodeAndSku $getSourceItemBySourceCodeAndSku
     * @param GetStockItemConfigurationInterface $getStockItemConfiguration
     * @param GetStockBySalesChannelInterface $getStockBySalesChannel
     */
    public function __construct(
        SourceItemsSaveInterface $sourceItemsSave,
        \Magento\InventorySourceDeductionApi\Model\GetSourceItemBySourceCodeAndSku $getSourceItemBySourceCodeAndSku,
        GetStockItemConfigurationInterface $getStockItemConfiguration,
        GetStockBySalesChannelInterface $getStockBySalesChannel
    ) {
        $this->sourceItemsSave = $sourceItemsSave;
        $this->getSourceItemBySourceCodeAndSku = $getSourceItemBySourceCodeAndSku;
        $this->getStockItemConfiguration = $getStockItemConfiguration;
        $this->getStockBySalesChannel = $getStockBySalesChannel;
    }

    /**
     * @inheritdoc
     */
    public function execute(\Magento\InventorySourceDeductionApi\Model\SourceDeductionRequestInterface $sourceDeductionRequest): void
    {
        $sourceItems = [];
        $sourceCode = $sourceDeductionRequest->getSourceCode();
        $salesChannel = $sourceDeductionRequest->getSalesChannel();

        $stockId = $this->getStockBySalesChannel->execute($salesChannel)->getStockId();
        foreach ($sourceDeductionRequest->getItems() as $item) {
            $itemSku = $item->getSku();
            $qty = $item->getQty();
            $stockItemConfiguration = $this->getStockItemConfiguration->execute(
                $itemSku,
                $stockId
            );

            if (!$stockItemConfiguration->isManageStock()) {
                //We don't need to Manage Stock
                continue;
            }

            $sourceItem = $this->getSourceItemBySourceCodeAndSku->execute($sourceCode, $itemSku);
            //if (($sourceItem->getQuantity() - $qty) >= 0) {
                $sourceItem->setQuantity($sourceItem->getQuantity() - $qty);
                $stockStatus = $this->getSourceStockStatus(
                    $stockItemConfiguration,
                    $sourceItem
                );
                $sourceItem->setStatus($stockStatus);
                $sourceItems[] = $sourceItem;
            // } else {
            //     throw new LocalizedException(
            //         __('Not all of your products are available in the requested quantity.')
            //     );
            // }
        }

        if (!empty($sourceItems)) {
            $this->sourceItemsSave->execute($sourceItems);
        }
    }

    /**
     * Get source item stock status after quantity deduction.
     *
     * @param StockItemConfigurationInterface $stockItemConfiguration
     * @param SourceItemInterface $sourceItem
     * @return int
     */
    private function getSourceStockStatus(
        StockItemConfigurationInterface $stockItemConfiguration,
        SourceItemInterface $sourceItem
    ): int {
        $sourceItemQty = $sourceItem->getQuantity() ?: self::ZERO_STOCK_QUANTITY;
        return $sourceItemQty === $stockItemConfiguration->getMinQty() && !$stockItemConfiguration->getBackorders()
            ? SourceItemInterface::STATUS_OUT_OF_STOCK
            : SourceItemInterface::STATUS_IN_STOCK;
    }
}
