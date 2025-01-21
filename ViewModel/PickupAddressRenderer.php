<?php

namespace PostNL\HyvaCheckout\ViewModel;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Checkout\Model\Session as SessionCheckout;
use Magento\Customer\Helper\Address as CustomerAddressHelper;
use Magento\Customer\Model\Address\Mapper as CustomerAddressMapper;
use TIG\PostNL\Helper\DeliveryOptions\PickupAddress;

class PickupAddressRenderer extends \Hyva\Checkout\ViewModel\Checkout\AddressRenderer
{
    private PickupAddress $pickupAddressHelper;

    public function __construct(
        SessionCheckout $sessionCheckout,
        CustomerAddressHelper $customerAddressHelper,
        CustomerAddressMapper $customerAddressMapper,
        PickupAddress $pickupAddressHelper
    ) {
        parent::__construct($sessionCheckout, $customerAddressHelper, $customerAddressMapper);
        $this->pickupAddressHelper = $pickupAddressHelper;
    }

    public function renderPickupAddress(string $code = self::BILLING_RENDERER): string
    {
        try {
            $quote = $this->sessionCheckout->getQuote();

            $pickupAddress = $this->pickupAddressHelper->getPakjeGemakAddressInQuote($quote->getId());
            return $this->renderCustomerAddress($pickupAddress->exportCustomerAddress(), $code);
        } catch (LocalizedException | NoSuchEntityException $exception) {
            return __('%1 address cannot be shown due to a technical malfunction.', 'Billing')->render();
        }
    }
}
