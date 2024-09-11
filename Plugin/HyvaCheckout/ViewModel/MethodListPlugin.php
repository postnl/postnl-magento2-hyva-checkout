<?php

namespace PostNL\HyvaCheckout\Plugin\HyvaCheckout\ViewModel;

use Hyva\Checkout\ViewModel\Checkout\Shipping\MethodList as SubjectClass;
use PostNL\HyvaCheckout\Api\CheckoutFieldsApi;
use PostNL\HyvaCheckout\Model\QuoteOrderRepository;

class MethodListPlugin
{
    private QuoteOrderRepository $quoteOrderRepository;
    private \Magento\Checkout\Model\Session $checkoutSession;

    public function __construct(
        QuoteOrderRepository $quoteOrderRepository,
        \Magento\Checkout\Model\Session $checkoutSession
    ) {
        $this->quoteOrderRepository = $quoteOrderRepository;
        $this->checkoutSession = $checkoutSession;
    }

    public function afterGetList(
        SubjectClass $subject,
        ?array $result
    ): ?array {
        if (is_array($result)) {
            /** @var \Magento\Quote\Model\Cart\ShippingMethod $method */
            foreach ($result as $key => $method) {
                if ($method->getCarrierCode() !== CheckoutFieldsApi::CARRIER_CODE) {
                    continue;
                }
                $quoteId = $this->checkoutSession->getQuoteId();
                $order = $this->quoteOrderRepository->getByQuoteId($quoteId);
                $fee = $order->getFee();
                if ($fee && ($fee < -PHP_FLOAT_EPSILON || $fee > PHP_FLOAT_EPSILON)) {
                    // Fee set, update shipping
                    $method->setAmount($method->getAmount() + $fee);
                    $method->setBaseAmount($method->getBaseAmount() + $fee);
                    $method->setPriceInclTax($method->getPriceInclTax() + $fee);
                    $method->setPriceExclTax($method->getPriceExclTax() + $fee);
                }
                break;
            }
        }
        return $result;
    }
}
