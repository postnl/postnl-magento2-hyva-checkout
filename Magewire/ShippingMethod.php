<?php
namespace PostNL\HyvaCheckout\Magewire;

use Hyva\Checkout\Model\Magewire\Component\EvaluationInterface;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultInterface;
use Magewirephp\Magewire\Component;
use Magento\Checkout\Model\Session as CheckoutSession;
use PostNL\HyvaCheckout\Model\QuoteOrderRepository;
use PostNL\HyvaCheckout\Api\CheckoutFieldsApi;
use PostNL\HyvaCheckout\ViewModel\ShippingView;
use TIG\PostNL\Config\Provider\ShippingOptions;
use TIG\PostNL\Service\Shipment\PickupValidator;
use TIG\PostNL\Service\Shipping\BoxablePackets;
use TIG\PostNL\Service\Shipping\InternationalPacket;

class ShippingMethod extends Component implements EvaluationInterface
{
    public $type = null;

    /**
     * @var string[]
     */
    protected $listeners = [
        'shipping_address_submitted' => 'refresh',
        'shipping_address_activated' => 'refresh',
        'shipping_address_saved' => 'refresh',
        'shipping_country_updated' => 'setTypeToDelivery',
        'postnl_delivery_selected' => 'refresh',
        'shipping_method_selected' => 'refresh',
        //'postnl_locations_request_failed' => 'setTypeToDelivery',
        //'postnl_unselect_pickup_point' => 'unselectPickupPoint',
    ];


    private CheckoutSession $checkoutSession;
    private QuoteOrderRepository $postnlOrderRepository;
    private ShippingView $shippingView;
    private ShippingOptions $shippingOptions;
    private BoxablePackets $boxablePackets;
    private InternationalPacket $internationalPacket;
    private PickupValidator $pickupValidator;

    public function __construct(
        CheckoutSession $checkoutSession,
        QuoteOrderRepository $postnlOrderRepository,
        ShippingView $shippingView,
        ShippingOptions $shippingOptions,
        BoxablePackets $boxablePackets,
        InternationalPacket $internationalPacket,
        PickupValidator $pickupValidator
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->postnlOrderRepository = $postnlOrderRepository;
        $this->shippingView = $shippingView;
        $this->shippingOptions = $shippingOptions;
        $this->boxablePackets = $boxablePackets;
        $this->internationalPacket = $internationalPacket;
        $this->pickupValidator = $pickupValidator;
    }

    public function canDisplayPickup(): bool
    {
        $countryId = $this->checkoutSession->getQuote()->getShippingAddress()->getCountryId();
        $result = $this->pickupValidator->isPickupEnabledForCountry($countryId);
        if ($result && $countryId === 'BE') {
            $products = $this->checkoutSession->getQuote()->getAllItems();
            // Disable pickup locations for Packets
            if ($this->internationalPacket->canFixInTheBox($products) || $this->boxablePackets->canFixInTheBox($products)) {
                $result = false;
            }
        }
        return $result;
    }

    public function boot(): void
    {
        $quote = $this->checkoutSession->getQuote();

        // Check if postnl order already exists
        $postnlOrder = $this->postnlOrderRepository->getByQuoteId($quote->getId());
        $defaultType = $postnlOrder->getIsPakjegemak() ? CheckoutFieldsApi::DELIVERY_TYPE_PICKUP
            : CheckoutFieldsApi::DELIVERY_TYPE_DELIVERY;
        // Validate if we can select pickup in case order was not saved
        if (!$postnlOrder->getEntityId()) {
            $countryId = $quote->getShippingAddress()->getCountryId();
            if ($this->pickupValidator->isDefaultPickupActive($countryId)) {
                $defaultType = CheckoutFieldsApi::DELIVERY_TYPE_PICKUP;
            }
        }
        $this->type = $defaultType;
    }

    public function updatedType(mixed $value): mixed
    {
        if (is_string($value)) {
            $this->emit('postnl_select_delivery_type', ['value' => $value]);
        }
        return $value;
    }

    public function evaluateCompletion(EvaluationResultFactory $resultFactory): EvaluationResultInterface
    {
        $quote = $this->checkoutSession->getQuote();

        // Check if postnl order already exists
        $postnlOrder = $this->postnlOrderRepository->getByQuoteId($quote->getId());
        if (!$postnlOrder->getEntityId() || !$postnlOrder->getType()) {
            return $resultFactory->createErrorMessage((string)__('Please choose delivery options.'));
        }

        return $resultFactory->createSuccess();
    }

    public function isDelivery(): bool
    {
        return $this->type === null || $this->type === CheckoutFieldsApi::DELIVERY_TYPE_DELIVERY;
    }

    public function isPickup(): bool
    {
        return $this->type === CheckoutFieldsApi::DELIVERY_TYPE_PICKUP;
    }

    public function getDeliveryPrice(): string
    {
        $price = $this->shippingView->getDeliveryPrice();
        $result = '';
        if ($price !== null) {
            $result = $this->shippingView->formatPrice($price);
        }
        // Don't display additional price in case pickup is selected
        if ($price === null || $this->isPickup()) {
            return $result;
        }
        $result = [$result];
        $quote = $this->checkoutSession->getQuote();
        $statedFee = $this->shippingOptions->getStatedAddressOnlyFee();
        $postnlOrder = $this->postnlOrderRepository->getByQuoteId($quote->getId());
        if ($postnlOrder->getFee() > 0) {
            $deliveryFee = $postnlOrder->getFee();
            if ($deliveryFee > 0 && $statedFee > 0 && $postnlOrder->getIsStatedAddressOnly()) {
                $deliveryFee -= $statedFee;
            }
            if ($deliveryFee > 0) {
                $result[] = $this->shippingView->formatPrice($deliveryFee);
            }
        }
        if ($this->shippingOptions->isStatedAddressOnlyActive()
            && $postnlOrder->getIsStatedAddressOnly()
            && $statedFee
        ) {
            $result[] = $this->shippingView->formatPrice($this->shippingOptions->getStatedAddressOnlyFee());
        }
        return implode(' + ', $result);
    }

    public function getPickupPrice(): string
    {
        $price = $this->shippingView->getPickupPrice();
        $result = '';
        if ($price !== null) {
            $result = $this->shippingView->formatPrice($price);
        }
        return $result;
    }
}
