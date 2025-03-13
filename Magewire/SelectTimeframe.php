<?php

namespace PostNL\HyvaCheckout\Magewire;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Pricing\Helper\Data;
use Magewirephp\Magewire\Component;
use PostNL\HyvaCheckout\Api\CheckoutFieldsApi;
use PostNL\HyvaCheckout\Model\QuoteOrderRepository;
use PostNL\HyvaCheckout\Model\Shipping\Delivery;
use TIG\PostNL\Config\Provider\ShippingOptions;
use TIG\PostNL\Service\Action\OrderSave;
use TIG\PostNL\Service\Order\FeeCalculator;
use TIG\PostNL\Service\Shipment\PickupValidator;
use TIG\PostNL\Service\Timeframe\Resolver;

class SelectTimeframe extends Component
{
    public bool $deliverySelected = false;

    public string $deliveryTimeframe = '';
    public $statedOnly = '';

    protected $listeners = [
        'postnl_select_delivery_type' => 'init',
        'shipping_address_saved' => 'refresh',
        'postnl_delivery_selected' => 'refresh',
        'postnl_pickup_selected' => 'resetStoredData'
    ];

    protected $loader = [
        'updatedDeliveryTimeframe' => 'Saving selected option...',
    ];

    private CheckoutSession $checkoutSession;
    private Resolver $timeframeResolver;
    private FeeCalculator $feeCalculator;
    private QuoteOrderRepository $postnlOrderRepository;
    private OrderSave $orderSave;
    private Data $priceHelper;
    private ShippingOptions $shippingOptions;
    private PickupValidator $pickupValidator;

    public function __construct(
        CheckoutSession $checkoutSession,
        Resolver $timeframeResolver,
        FeeCalculator $feeCalculator,
        QuoteOrderRepository $postnlOrderRepository,
        OrderSave $orderSave,
        Data $priceHelper,
        ShippingOptions $shippingOptions,
        PickupValidator $pickupValidator
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->timeframeResolver = $timeframeResolver;
        $this->feeCalculator = $feeCalculator;
        $this->postnlOrderRepository = $postnlOrderRepository;
        $this->orderSave = $orderSave;
        $this->priceHelper = $priceHelper;
        $this->shippingOptions = $shippingOptions;
        $this->pickupValidator = $pickupValidator;
    }

    public function boot(): void
    {
        $quote = $this->checkoutSession->getQuote();
        $this->checkShippingSelected($quote);
        if ($this->deliverySelected) {
            $this->checkOptionSelected($quote);
        }
    }

    public function init($data)
    {
        if (!is_array($data)) {
            return;
        }

        $value = $data['value'] ?? null;
        if ($value !== CheckoutFieldsApi::DELIVERY_TYPE_DELIVERY) {
            $this->deliverySelected = false;
            //$this->reset(['pickupPointId', 'pickupPoints']);
            return;
        }

        $this->deliverySelected = true;
    }

    public function resetStoredData(): void
    {
        $this->deliverySelected = false;
        $this->deliveryTimeframe = '';
    }

    public function isOpen(): bool
    {
        return $this->deliverySelected;
    }

    /**
     * @return Delivery\Day[]
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getTimeframes(): array
    {
        $shippingAddress = $this->checkoutSession->getQuote()->getShippingAddress();
        $data = [
            'country' => $shippingAddress->getCountryId(),
            'street' => $shippingAddress->getStreet(),
            'postcode' => $shippingAddress->getPostcode(),
            'city' => $shippingAddress->getCity(),
        ];
        $timeframes = $this->convertResponse($this->timeframeResolver->processTimeframes($data));
        return $timeframes;
    }

    private function checkShippingSelected(\Magento\Quote\Api\Data\CartInterface $quote): bool
    {
        $extAttributes = $quote->getExtensionAttributes();
        if (!$extAttributes) {
            return false;
        }
        $assignments = $extAttributes->getShippingAssignments();
        if (!$assignments || !isset($assignments[0])) {
            return false;
        }
        $shipping = $assignments[0]->getShipping();
        if ($shipping && $shipping->getMethod() === CheckoutFieldsApi::SHIPPING_CODE) {
            // Check if postnl order exists and selected
            $postnlOrder = $this->postnlOrderRepository->getByQuoteId($quote->getId());
            if ($postnlOrder->getEntityId()) {
                if (!$postnlOrder->getIsPakjegemak()) {
                    $this->deliverySelected = true;
                }
            } else {
                // Default display - check if pickup should be selected first
                $countryId = $shipping->getAddress()->getCountryId();
                if ($this->pickupValidator->isDefaultPickupActive($countryId)) {
                    // Pickup is default - do not update anything
                } else {
                    $this->deliverySelected = true;
                }
            }
        }
        return true;
    }

    public function updatedStatedOnly($value)
    {
        $this->updatedDeliveryTimeframe($this->deliveryTimeframe);
        return $value;
    }

    public function updatedDeliveryTimeframe($value)
    {
        if ($this->saveDeliveryTimeframe($value)) {
            $this->emit('shipping_method_selected');
            $this->emit('postnl_delivery_selected');
        }
        return $value;
        // Trigger updates on related blocks
    }

    public function saveDeliveryTimeframe($value): bool
    {
        $quote = $this->checkoutSession->getQuote();
        if (!$value || !$this->checkShippingSelected($quote)) {
            return false;
        }

        $shippingPoint = explode('__', $value);

        // Simulate request data from Magento checkout
        $shipping = $quote->getShippingAddress();
        $street = $shipping->getStreet();
        $request = [
            'type' => CheckoutFieldsApi::DELIVERY_TYPE_DELIVERY,
            'country' => $shipping->getCountryId(),
            'quote_id' => $quote->getId(),
            'address' => [
                'country' => $shipping->getCountryId(),
                'street' => $shipping->getStreet(),
                'postcode' => $shipping->getPostcode(),
                'housenumber' => $street[1] ?? '',
            ],
            'stated_address_only' => (int)$this->statedOnly
        ];

        if (isset($shippingPoint[3])) {
            $request['option'] = $shippingPoint[0];
            $request['date'] = $shippingPoint[1];
            $request['from'] = $shippingPoint[2];
            $request['to'] = $shippingPoint[3];
        } else {
            // Replace type with the value from input
            $request['type'] = $shippingPoint[0];
        }
        // Update request type
        $request['type'] = $this->handleRequestType($request['type']);
        if (!isset($request['date'])) {
            $request['date'] = $this->checkoutSession->getPostNLDeliveryDate();
        }
        $postnlOrder = $this->postnlOrderRepository->getByQuoteId($quote->getId());
        try {
            $this->orderSave->saveDeliveryOption($postnlOrder, $request);
        } catch (LocalizedException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new LocalizedException(__('Failed to save postnl order information.'));
        }

        return true;
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
                    implode('__', $key),
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

    private function checkOptionSelected(\Magento\Quote\Api\Data\CartInterface $quote): void
    {
        $postnlOrder = $this->postnlOrderRepository->getByQuoteId($quote->getId());
        if ($postnlOrder->getEntityId() && $postnlOrder->getType()) {
            if (!$this->deliveryTimeframe && !$postnlOrder->getIsPakjegemak()) {
                $key[] = $postnlOrder->getType();
                if ($postnlOrder->getExpectedDeliveryTimeStart()) {
                    // Change format from database Y-m-d to d-m-Y that is response from PostNL
                    $date = new \DateTime($postnlOrder->getDeliveryDate());
                    array_push($key,
                        $date->format('d-m-Y'),
                        $postnlOrder->getExpectedDeliveryTimeStart(),
                        $postnlOrder->getExpectedDeliveryTimeEnd()
                    );
                } else {
                    // Seems like if a non-day delivery option or a fallback one - get data from timeframes
                    $timeframes = $this->getTimeframes();
                    if (isset($timeframes[0]) && !$timeframes[0]->getDate()) {
                        $key = [$timeframes[0]->getOptions()[0]->getValue()];
                    }
                }
                $this->deliveryTimeframe = implode('__', $key);
            }
            if ($postnlOrder->getIsStatedAddressOnly() > 0) {
                $this->statedOnly = 1;
            }
        } else {
            // Select first delivery
            $timeframes = $this->getTimeframes();
            // In case this is a delivery day, not a fall-back option of some sort
            if (isset($timeframes[0]) && $timeframes[0]->getDate()) {
                $this->deliveryTimeframe = $timeframes[0]->getOptions()[0]->getValue();
                $this->saveDeliveryTimeframe($this->deliveryTimeframe);
            }
        }
    }

    public function canUseStatedAddressOnly(): bool
    {
        $isActive = $this->shippingOptions->isStatedAddressOnlyActive();
        $isInternationalPacketsActive = $this->shippingOptions->canUsePriority();

        $address = $this->checkoutSession->getQuote()->getShippingAddress();
        $countryId = $address ? $address->getCountryId() : '';
        $isNL = $countryId === 'NL' || ($countryId === 'BE' && $isInternationalPacketsActive === false);

        return $isActive && $isNL;
    }

    public function statedAddressOnlyFee(): ?string
    {
        $fee = $this->shippingOptions->getStatedAddressOnlyFee();
        return $fee > 0 ? $this->priceHelper->currency($fee) : null;
    }

    /**
     * This is a deprecation that goes from Tig js files where type is changed to other string.
     * Probably need to handle original extension more correctly, but will leave it for the next iteration, when
     * such functionality will be updated. OrderParams should be changed as well.
     */
    private function handleRequestType(string $type): string
    {
        switch ($type) {
            case 'gp':
                return 'GP';
            case 'eps':
                return 'EPS';
            case 'letterbox_package':
                return 'Letterbox Package';
            case 'boxable_packets':
                return 'Boxable Packet';
            case 'international_packet':
                return 'International Packet';
        }
        // fallback, default
        return $type;
    }

}
