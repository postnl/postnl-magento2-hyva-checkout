<?php
namespace PostNL\HyvaCheckout\ViewModel;

use Hyva\Checkout\ViewModel\Checkout\Formatter;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use TIG\PostNL\Helper\DeliveryOptions\PickupAddress;
use TIG\PostNL\Service\Carrier\Price\Calculator;
use TIG\PostNL\Service\Carrier\QuoteToRateRequest;

class ShippingView implements ArgumentInterface
{
    private $cache = [];
    private CheckoutSession $checkoutSession;
    private Calculator $calculator;
    private QuoteToRateRequest $quoteToRateRequest;
    private Formatter $formatter;

    public function __construct(
        CheckoutSession $checkoutSession,
        Calculator $calculator,
        QuoteToRateRequest $quoteToRateRequest,
        Formatter $formatter
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->calculator = $calculator;
        $this->quoteToRateRequest = $quoteToRateRequest;
        $this->formatter = $formatter;
    }

    public function getPriceFromAddressRequest(string $parcel = null): array
    {
        $shipping = $this->checkoutSession->getQuote()->getShippingAddress();
        $key = $shipping->getCountryId() . '_' . $shipping->getPostcode() . $parcel;
        if (!array_key_exists($key, $this->cache)) {
            $request = $this->quoteToRateRequest->getByUpdatedAddress($shipping->getCountryId(), $shipping->getPostcode());
            $value = $this->calculator->getPriceWithTax($request, $parcel);
            // Format return types
            if (!is_array($value)) {
                $value = [];
            }
            $this->cache[$key] = $value;
        }
        return $this->cache[$key];
    }

    public function formatPrice(?float $price): string
    {
        if (!$price) {
            return '';
        }
        return $this->formatter->currency($price);
    }

    public function getDeliveryPrice(): ?string
    {
        $price = $this->getPriceFromAddressRequest();
        return $price['price'] ?? null;
    }

    public function getPickupPrice(): ?string
    {
        $price = $this->getPriceFromAddressRequest(PickupAddress::PG_ADDRESS_TYPE);
        return $price['price'] ?? null;
    }

}
