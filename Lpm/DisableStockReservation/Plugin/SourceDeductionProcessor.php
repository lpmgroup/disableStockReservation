<?php
/**
 * @author  wilson chung <wilson@lpm.hk>
 * override Ampersand\DisableStockReservation\Plugin
 * to update stock item after order is placed 
 */
declare(strict_types=1);

namespace Lpm\DisableStockReservation\Plugin;

use Ampersand\DisableStockReservation\Model\GetSourceSelectionResultFromOrder;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\InventorySalesApi\Api\Data\SalesEventInterface;
use Magento\InventorySalesApi\Api\Data\SalesEventInterfaceFactory;
use Magento\InventorySourceDeductionApi\Model\SourceDeductionRequestInterface;
use Magento\InventorySourceDeductionApi\Model\SourceDeductionServiceInterface;
use Magento\InventoryShipping\Model\SourceDeductionRequestsFromSourceSelectionFactory;
use Magento\InventorySalesApi\Api\Data\ItemToSellInterfaceFactory;
use Magento\InventorySalesApi\Api\PlaceReservationsForSalesEventInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Service\OrderService;

class SourceDeductionProcessor
{
    /**
     * @var GetSourceSelectionResultFromOrder
     */
    private $getSourceSelectionResultFromOrder;

    /**
     * @var SourceDeductionServiceInterface
     */
    private $sourceDeductionService;

    /**
     * @var SourceDeductionRequestsFromSourceSelectionFactory
     */
    private $sourceDeductionRequestsFromSourceSelectionFactory;

    /**
     * @var SalesEventInterfaceFactory
     */
    private $salesEventFactory;

    /**
     * @var ItemToSellInterfaceFactory
     */
    private $itemToSellFactory;

    /**
     * @var PlaceReservationsForSalesEventInterface
     */
    private $placeReservationsForSalesEvent;

    /**
     * @param GetSourceSelectionResultFromOrder $getSourceSelectionResultFromOrder
     * @param SourceDeductionServiceInterface $sourceDeductionService
     * @param SourceDeductionRequestsFromSourceSelectionFactory $sourceDeductionRequestsFromSourceSelectionFactory
     * @param SalesEventInterfaceFactory $salesEventFactory
     * @param ItemToSellInterfaceFactory $itemToSellFactory
     * @param PlaceReservationsForSalesEventInterface $placeReservationsForSalesEvent
     */
    public function __construct(
        GetSourceSelectionResultFromOrder $getSourceSelectionResultFromOrder,
        StockRegistryInterface $stockRegistry,
        SourceDeductionServiceInterface $sourceDeductionService,
        SourceDeductionRequestsFromSourceSelectionFactory $sourceDeductionRequestsFromSourceSelectionFactory,
        SalesEventInterfaceFactory $salesEventFactory,
        ItemToSellInterfaceFactory $itemToSellFactory,
        PlaceReservationsForSalesEventInterface $placeReservationsForSalesEvent
    ) {
        $this->getSourceSelectionResultFromOrder = $getSourceSelectionResultFromOrder;
        $this->stockRegistry = $stockRegistry;
        $this->sourceDeductionService = $sourceDeductionService;
        $this->sourceDeductionRequestsFromSourceSelectionFactory = $sourceDeductionRequestsFromSourceSelectionFactory;
        $this->salesEventFactory = $salesEventFactory;
        $this->itemToSellFactory = $itemToSellFactory;
        $this->placeReservationsForSalesEvent = $placeReservationsForSalesEvent;
    }

    /**
     * @param OrderService $subject
     * @param OrderInterface $result
     *
     * @return OrderInterface|void
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws LocalizedException
     * @see OrderService::place
     */
    public function afterPlace(OrderService $subject, OrderInterface $result)
    {
        /** @var Order $order */
        $order = $result;
        if ($order->getId() === null) {
            return;
        }

        $sourceSelectionResult = $this->getSourceSelectionResultFromOrder->execute($order);

        /** @var SalesEventInterface $salesEvent */
        $salesEvent = $this->salesEventFactory->create([
            'type' => SalesEventInterface::EVENT_ORDER_PLACED,
            'objectType' => SalesEventInterface::OBJECT_TYPE_ORDER,
            'objectId' => $order->getEntityId(),
        ]);

        $sourceDeductionRequests = $this->sourceDeductionRequestsFromSourceSelectionFactory->create(
            $sourceSelectionResult,
            $salesEvent,
            (int)$order->getStore()->getWebsiteId()
        );

        foreach ($sourceDeductionRequests as $sourceDeductionRequest) {
            $this->sourceDeductionService->execute($sourceDeductionRequest);
            $this->placeCompensatingReservation($sourceDeductionRequest);
        }

        $items = $order->getAllItems();

        foreach($items as $item) {
            $sku = $item->getSku();
            $stockItem = $this->stockRegistry->getStockItemBySku($sku);
            $newQty = $stockItem->getQty() - $item->getQtyOrdered();
            $stockItem->setQty($newQty);
            $stockItem->save();

            // $stockStatus = $this->stockRegistry->getStockStatusBySku($sku);
            // $newQty = $stockStatus->getQty() - $item->getQty();
            // $stockStatus->setQty($newQty);
            // $stockStatus->save();
        }


        return $result;
    }

    /**
     * Place compensating reservation after source deduction
     *
     * @param SourceDeductionRequestInterface $sourceDeductionRequest
     *
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws LocalizedException
     */
    private function placeCompensatingReservation(SourceDeductionRequestInterface $sourceDeductionRequest): void
    {
        $items = [];
        foreach ($sourceDeductionRequest->getItems() as $item) {
            $items[] = $this->itemToSellFactory->create([
                'sku' => $item->getSku(),
                'qty' => $item->getQty()
            ]);
        }
        $this->placeReservationsForSalesEvent->execute(
            $items,
            $sourceDeductionRequest->getSalesChannel(),
            $sourceDeductionRequest->getSalesEvent()
        );
    }
}
