<?php
namespace PostNL\HyvaCheckout\Magewire;

use Magewirephp\Magewire\Component;
use Magento\Checkout\Model\Session as CheckoutSession;
use PostNL\HyvaCheckout\Model\QuoteOrderRepository;
use PostNL\HyvaCheckout\Api\CheckoutFieldsApi;
use PostNL\HyvaCheckout\ViewModel\ShippingView;
use TIG\PostNL\Config\Provider\ShippingOptions;

class ShippingMethod extends Component
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

    public function __construct(
        CheckoutSession $checkoutSession,
        QuoteOrderRepository $postnlOrderRepository,
        ShippingView $shippingView,
        ShippingOptions $shippingOptions
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->postnlOrderRepository = $postnlOrderRepository;
        $this->shippingView = $shippingView;
        $this->shippingOptions = $shippingOptions;
    }

    public function canDisplayPickup(): bool
    {
        $countryId = $this->checkoutSession->getQuote()->getShippingAddress()->getCountryId();
        return ($countryId === 'NL' || $countryId === 'BE') && $this->shippingOptions->isPakjegemakActive($countryId);
    }

    public function boot(): void
    {
        $quote = $this->checkoutSession->getQuote();

        // Check if postnl order already exists
        $postnlOrder = $this->postnlOrderRepository->getByQuoteId($quote->getId());
        $this->type = $postnlOrder->getIsPakjegemak() ? CheckoutFieldsApi::DELIVERY_TYPE_PICKUP
            : CheckoutFieldsApi::DELIVERY_TYPE_DELIVERY;
    }

    public function updatedType(mixed $value): mixed
    {
        if (is_string($value)) {
            $this->emit('postnl_select_delivery_type', ['value' => $value]);
        }
        return $value;
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
        if ($price) {
            $result = $this->shippingView->formatPrice($price);
        }
        // Don't display additional price in case pickup is selected
        if (!$result || $this->isPickup()) {
            return $result;
        }
        $quote = $this->checkoutSession->getQuote();
        $postnlOrder = $this->postnlOrderRepository->getByQuoteId($quote->getId());
        if ($postnlOrder->getFee() > 0) {
            $result .= ' + ' . $this->shippingView->formatPrice($postnlOrder->getFee());
        }
        return $result;
/**
 * <!-- ko if: shipmentType() == 'delivery' && statedDeliveryFee() -->
 * + <span data-bind="text: formatPrice(statedDeliveryFee())"></span>
 * <!-- /ko -->
 */
    }

    public function getPickupPrice(): string
    {
        $price = $this->shippingView->getPickupPrice();
        $result = '';
        if ($price) {
            $result = $this->shippingView->formatPrice($price);
        }
        return $result;
    }
}
