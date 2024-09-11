<?php

namespace PostNL\HyvaCheckout\Model;

use TIG\PostNL\Api\Data\OrderInterface;
use TIG\PostNL\Api\OrderRepositoryInterface;

class QuoteOrderRepository
{
    private array $cache = [];

    private OrderRepositoryInterface $orderRepository;

    public function __construct(
        OrderRepositoryInterface $orderRepository
    ) {
        $this->orderRepository = $orderRepository;
    }

    public function getByQuoteId(int $quoteId): OrderInterface
    {
        if (!$quoteId) {
            return $this->orderRepository->create();
        }
        if (!array_key_exists($quoteId, $this->cache)) {
            $this->cache[$quoteId] = $this->orderRepository->getByQuoteId($quoteId);
            if (!$this->cache[$quoteId]) {
                $this->cache[$quoteId] = $this->orderRepository->create();
            }
            // Re-check that this order wasn't created an saved, in this case we need a new one.
            if ($this->cache[$quoteId]->getOrderId()) {
                $this->cache[$quoteId] = $this->orderRepository->create();
            }
            // Be sure to set quote id in the new model
            $this->cache[$quoteId]->setQuoteId($quoteId);
        }
        return $this->cache[$quoteId];
    }
}
