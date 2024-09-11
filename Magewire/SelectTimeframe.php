<?php

namespace PostNL\HyvaCheckout\Magewire;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\LocalizedException;
use Magewirephp\Magewire\Component;
use PostNL\HyvaCheckout\Api\CheckoutFieldsApi;
use PostNL\HyvaCheckout\Model\QuoteOrderRepository;
use PostNL\HyvaCheckout\Model\Shipping\Delivery;
use TIG\PostNL\Service\Action\OrderSave;
use TIG\PostNL\Service\Order\FeeCalculator;
use TIG\PostNL\Service\Timeframe\Resolver;

class SelectTimeframe extends Component
{
    public bool $deliverySelected = false;

    public string $deliveryTimeframe = '';

    protected $listeners = [
        'postnl_select_delivery_type' => 'init'
    ];

    protected $loader = [
        'updatedDeliveryTimeframe' => 'Saving selected option...',
    ];

    private CheckoutSession $checkoutSession;
    private Resolver $timeframeResolver;
    private FeeCalculator $feeCalculator;
    private QuoteOrderRepository $postnlOrderRepository;
    private OrderSave $orderSave;
    private \Magento\Framework\Pricing\Helper\Data $priceHelper;

    public function __construct(
        CheckoutSession $checkoutSession,
        Resolver $timeframeResolver,
        FeeCalculator $feeCalculator,
        QuoteOrderRepository $postnlOrderRepository,
        OrderSave $orderSave,
        \Magento\Framework\Pricing\Helper\Data $priceHelper
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->timeframeResolver = $timeframeResolver;
        $this->feeCalculator = $feeCalculator;
        $this->postnlOrderRepository = $postnlOrderRepository;
        $this->orderSave = $orderSave;
        $this->priceHelper = $priceHelper;
    }

    public function boot(): void
    {
        $quote = $this->checkoutSession->getQuote();
        $this->checkShippingSelected($quote);
        $this->checkOptionSelected($quote);
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
            $this->deliverySelected = true;
        }
        return true;
    }

    public function updatedDeliveryTimeframe($value)
    {
        $quote = $this->checkoutSession->getQuote();
        if (!$this->checkShippingSelected($quote)) {
            return $value;
        }

        $shippingPoint = explode('_', $value);

        // Simulate request data from Magento checkout
        $shipping = $quote->getShippingAddress();
        $street = $shipping->getStreet();
        $request = [
            'type' => CheckoutFieldsApi::DELIVERY_TYPE_DELIVERY,
            'option' => $shippingPoint[0],
            'country' => $shipping->getCountryId(),
            'quote_id' => $quote->getId(),
            'address' => [
                'country' => $shipping->getCountryId(),
                'street' => $shipping->getStreet(),
                'postcode' => $shipping->getPostcode(),
                'housenumber' => $street[1] ?? '',
            ]
        ];

        if (isset($shippingPoint[3])) {
            $request['date'] = $shippingPoint[1];
            $request['from'] = $shippingPoint[2];
            $request['to'] = $shippingPoint[3];
        }
        if (!$request['date']) {
            $request['date'] = $this->checkoutSession->getPostNLDeliveryDate();
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
        if (!$this->deliveryTimeframe && $postnlOrder->getEntityId() && $postnlOrder->getType()) {
            $key[] = $postnlOrder->getType();
            if ($postnlOrder->getDeliveryDate()) {
                // CHange format from database Y-m-d to d-m-Y that is response from PostNL
                $date = new \DateTime($postnlOrder->getDeliveryDate());
                array_push($key,
                    $date->format('d-m-Y'),
                    $postnlOrder->getExpectedDeliveryTimeStart(),
                    $postnlOrder->getExpectedDeliveryTimeEnd()
                );
            }
            $this->deliveryTimeframe = implode('_', $key);
        }
    }

}
