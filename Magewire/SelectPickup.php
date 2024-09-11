<?php

namespace PostNL\HyvaCheckout\Magewire;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\LocalizedException;
use Magewirephp\Magewire\Component;
use PostNL\HyvaCheckout\Api\CheckoutFieldsApi;
use PostNL\HyvaCheckout\Model\QuoteOrderRepository;
use PostNL\HyvaCheckout\Model\Shipping\Delivery;
use PostNL\HyvaCheckout\ViewModel\ShippingView;
use TIG\PostNL\Service\Action\OrderSave;
use TIG\PostNL\Service\Order\FeeCalculator;
use TIG\PostNL\Service\Shipping\LetterboxPackage;
use TIG\PostNL\Service\Timeframe\Resolver;

class SelectPickup extends Component
{
    public bool $pickupSelected = false;

    public string $locationId = '';

    protected $listeners = [
        'postnl_select_delivery_type' => 'init'
    ];

    protected $loader = [
        'updatedPickupSelected' => 'Saving selected option...',
    ];

    private CheckoutSession $checkoutSession;
    private QuoteOrderRepository $postnlOrderRepository;
    private OrderSave $orderSave;
    private \Magento\Framework\Pricing\Helper\Data $priceHelper;
    private LetterboxPackage $letterboxPackage;
    private ShippingView $shippingView;

    public function __construct(
        CheckoutSession $checkoutSession,
        QuoteOrderRepository $postnlOrderRepository,
        OrderSave $orderSave,
        \Magento\Framework\Pricing\Helper\Data $priceHelper,
        LetterboxPackage $letterboxPackage,
        ShippingView $shippingView
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->postnlOrderRepository = $postnlOrderRepository;
        $this->orderSave = $orderSave;
        $this->priceHelper = $priceHelper;
        $this->letterboxPackage = $letterboxPackage;
        $this->shippingView = $shippingView;
    }

    public function boot(): void
    {
        $quote = $this->checkoutSession->getQuote();
        //$this->checkShippingSelected($quote);
        $this->checkOptionSelected($quote);
    }

    public function init($data): void
    {
        if (!is_array($data)) {
            return;
        }

        $value = $data['value'] ?? null;
        $this->pickupSelected = $value === CheckoutFieldsApi::DELIVERY_TYPE_PICKUP;
    }

    public function isOpen(): bool
    {
        return $this->pickupSelected;
    }

    public function isLetterboxPackage(): bool
    {
        $products = $this->checkoutSession->getQuote()->getAllItems();
        return $this->letterboxPackage->isLetterboxPackage($products);
    }

    /**
     * @return Delivery\Day[]
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getLocations(): array
    {
        $shippingAddress = $this->checkoutSession->getQuote()->getShippingAddress();
        $data = [
            'country' => $shippingAddress->getCountryId(),
            'street' => $shippingAddress->getStreet(),
            'postcode' => $shippingAddress->getPostcode(),
            'city' => $shippingAddress->getCity(),
        ];
        return $timeframes;
    }

    private function checkShippingSelected(\Magento\Quote\Api\Data\CartInterface $quote): bool
    {
        $extAtributes = $quote->getExtensionAttributes();
        if (!$extAtributes) {
            return false;
        }
        $assignments = $extAtributes->getShippingAssignments();
        if (!$assignments || !isset($assignments[0])) {
            return false;
        }
        $shipping = $assignments[0]->getShipping();
        if ($shipping && $shipping->getMethod() === CheckoutFieldsApi::SHIPPING_CODE) {
            // @todo sometimes pickup can be default
            $this->pickupSelected = false;
        }
        return true;
    }

    public function updatedLocationId($value)
    {
        $quote = $this->checkoutSession->getQuote();
        if (!$this->checkShippingSelected($quote)) {
            return $value;
        }

        $postnlOrder = $this->postnlOrderRepository->getByQuoteId($quote->getId());
        $originalFee = (float)$postnlOrder->getFee();
        try {
            $this->orderSave->saveDeliveryOption($postnlOrder, $request);
        } catch (LocalizedException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new LocalizedException(__('Failed to save postnl order information.'));
        }
        // Trigger updates on related blocks
        $this->emit('shipping_method_selected');

        return $value;
    }


    /**
     * @param array $timeframes
     * @return Delivery\Day[]
     */
    private function convertResponse(array $timeframes): array
    {
        $timeframes = $timeframes['timeframes'] ?? [];
        $types = $timeframes[0][0] ?? [];
        // Check if it's one of the fallbacks responses
        if (!empty($types) && count($timeframes) === 1 && count($types) === 1) {
            $type = current($types);
            $key = key($types);
            $timeframe = new Delivery\Timeframe($key, $type);
            $day = new Delivery\Day([$timeframe]);
            return [$day];
        }
        $result = [];
        foreach ($timeframes as $dayData) {
            $options = [];
            foreach ($dayData as $dayInfo) {
                $key = [
                    $dayInfo['option'],
                    $dayInfo['date'],
                    $dayInfo['from'],
                    $dayInfo['to'],
                ];
                $fee = $this->feeCalculator->get($dayInfo);
                $timeframe = new Delivery\Timeframe(
                    implode('_', $key),
                    $dayInfo['from_friendly'] . ' - ' . $dayInfo['to_friendly'],
                    $dayInfo['option'] ?? null,
                    $fee > 0 ? $this->priceHelper->currency($fee,true,false) : null
                );
                $options[] = $timeframe;
            }
            $day = new Delivery\Day($options, $dayInfo['date'] ?? '', $dayInfo['day'] ?? '');
            $result[] = $day;
        }
        return $result;
    }

    private function checkOptionSelected(\Magento\Quote\Api\Data\CartInterface $quote)
    {
        $postnlOrder = $this->postnlOrderRepository->getByQuoteId($quote->getId());
        if (!$this->locationId && $postnlOrder->getEntityId() && $postnlOrder->getType()) {
        }
    }

}
