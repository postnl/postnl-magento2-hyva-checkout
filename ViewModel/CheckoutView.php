<?php

namespace PostNL\HyvaCheckout\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;

class CheckoutView implements ArgumentInterface
{
    private \TIG\PostNL\Config\CheckoutConfiguration\Urls $checkoutUrls;

    public function __construct(
        \TIG\PostNL\Config\CheckoutConfiguration\Urls $checkoutUrls
    ) {
        $this->checkoutUrls = $checkoutUrls;
    }

    public function getCheckoutUrl(string $key): string
    {
        $urls = $this->checkoutUrls->getValue();
        return $urls[$key] ?? '';
    }
}
