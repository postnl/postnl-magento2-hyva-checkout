<?php

namespace PostNL\HyvaCheckout\Magewire;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\LocalizedException;
use Magewirephp\Magewire\Component;
use PostNL\HyvaCheckout\Api\CheckoutFieldsApi;
use PostNL\HyvaCheckout\Magewire\Helper\AddressRequest;
use PostNL\HyvaCheckout\Model\QuoteOrderRepository;
use PostNL\HyvaCheckout\Model\Shipping\Pickup\Location;
use TIG\PostNL\Service\Action\OrderSave;
use TIG\PostNL\Service\Shipment\PickupValidator;
use TIG\PostNL\Service\Shipping\LetterboxPackage;
use TIG\PostNL\Service\Shipping\PickupLocations;

class SelectPickup extends Component
{
    use AddressRequest;

    private const LOCATIONS_LIMIT = 5;
    public bool $pickupSelected = false;
    public string $editMode = '1';

    public string $locationId = '';

    public ?Location $location = null;

    protected $listeners = [
        'postnl_select_delivery_type' => 'init',
        'shipping_address_saved' => 'refresh',
        'postnl_pickup_selected' => 'refresh',
        'postnl_delivery_selected' => 'resetStoredData'
    ];

    protected $loader = [
        'updatedPickupSelected' => 'Saving selected option...',
    ];

    private CheckoutSession $checkoutSession;
    private QuoteOrderRepository $postnlOrderRepository;
    private OrderSave $orderSave;
    private LetterboxPackage $letterboxPackage;
    private PickupLocations $pickupLocations;
    private PickupValidator $pickupValidator;

    public function __construct(
        CheckoutSession $checkoutSession,
        QuoteOrderRepository $postnlOrderRepository,
        OrderSave $orderSave,
        LetterboxPackage $letterboxPackage,
        PickupLocations $pickupLocations,
        PickupValidator $pickupValidator
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->postnlOrderRepository = $postnlOrderRepository;
        $this->orderSave = $orderSave;
        $this->letterboxPackage = $letterboxPackage;
        $this->pickupLocations = $pickupLocations;
        $this->pickupValidator = $pickupValidator;
    }

    public function boot(): void
    {
        $quote = $this->checkoutSession->getQuote();
        $this->checkShippingSelected($quote);
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

    public function resetStoredData(): void
    {
        $this->pickupSelected = false;
        $this->locationId = '';
    }

    public function isOpen(): bool
    {
        return $this->pickupSelected;
    }

    public function isEditMode(): bool
    {
        return $this->editMode !== '0';
    }

    public function isLetterboxPackage(): bool
    {
        $quote = $this->checkoutSession->getQuote();
        $products = $quote->getAllItems();
        $country = $quote->getShippingAddress()->getCountryId();
        return $country === 'NL' && $this->letterboxPackage->isLetterboxPackage($products, false);
    }

    public function canSearch(): bool
    {
        $shippingAddress = $this->checkoutSession->getQuote()->getShippingAddress();
        $requestData = $this->getRequestData($shippingAddress, true);

        if(!is_array($requestData)){
            return false;
        }

        return true;
    }

    /**
     * @return Delivery\Day[]
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getLocations(): array
    {
        $shippingAddress = $this->checkoutSession->getQuote()->getShippingAddress();
        $requestData = $this->getRequestData($shippingAddress, true);

        if(!is_array($requestData)){
            return [];
        }

        try {
            $locations = $this->pickupLocations->get($requestData);
        } catch (\TIG\PostNL\Webservices\Api\Exception $e) {
            return [];
        }
        return $this->convertResponse($locations);
    }

    public function getPickupDate(): string
    {
        return $this->pickupLocations->getLastDeliveryDate();
    }

    public function getSelectedLocation(): ?Location
    {
        if ($this->location !== null) {
            return $this->location;
        }
        if (!$this->locationId) {
            return null;
        }
        return $this->getLocationById($this->locationId);
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
                if ($postnlOrder->getIsPakjegemak()) {
                    $this->pickupSelected = true;
                }
            } else {
                // Default display - check if pickup should be selected first
                $countryId = $shipping->getAddress()->getCountryId();
                if ($this->pickupValidator->isDefaultPickupActive($countryId)) {
                    // Pickup is default - do not update anything
                    $this->pickupSelected = true;
                }
            }
        }
        return true;
    }

    public function updatedEditMode($value): string
    {
        if ((int)$value > 0) {
            //$this->emit('postnl_pickup_selected');
        }
        return $value;
    }

    public function updatedLocationId($value): string
    {
        $value = (int)$value;
        if (!$value) {
            return '';
        }
        $pickupLocation = $this->getLocationById($value);
        if (!$pickupLocation) {
            return '';
        }
        $quote = $this->checkoutSession->getQuote();

        // Simulate request data from Magento checkout
        $shipping = $quote->getShippingAddress();
        $street = $shipping->getStreet();
        $request = [
            'type' => CheckoutFieldsApi::DELIVERY_TYPE_PICKUP,
            'option' => 'PG', // Always
            'from' => '15:00:00', // Also always
            'name' => $pickupLocation->getName(),
            'LocationCode' => $value,
            'RetailNetworkID' => $pickupLocation->getNetworkId(),
            'country' => $shipping->getCountryId(),
            'quote_id' => $quote->getId(),
            'address' => $pickupLocation->getAddressArray(),
            'customerData' => [
                'country' => $shipping->getCountryId(),
                'street' => $shipping->getStreet(),
                'postcode' => $shipping->getPostcode(),
                'housenumber' => $street[1] ?? '',
                'firstname' => $shipping->getFirstname(),
                'lastname' => $shipping->getLastname(),
                'telephone' => $shipping->getTelephone()
            ]
        ];

        $postnlOrder = $this->postnlOrderRepository->getByQuoteId($quote->getId());
        try {
            $this->orderSave->saveDeliveryOption($postnlOrder, $request);
        } catch (LocalizedException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new LocalizedException(__('Failed to save postnl order information.'));
        }
        //$this->location = $pickupLocation;
        $this->editMode = 0;
        // Trigger updates on related blocks
        $this->emit('shipping_method_selected');
        $this->emit('postnl_pickup_selected');

        return (string)$value;
    }

    private function getLocationById(int $locationId): ?Location
    {
        $locations = $this->getLocations();
        return $locations[$locationId] ?? null;
    }

    /**
     * @param \stdClass[] $locations
     * @return Location[]
     */
    private function convertResponse(array $locations): array
    {
        $result = [];
        $count = 0;
        foreach ($locations as $location) {
            $location = new Location($location);
            $result[$location->getValue()] = $location;
            $count ++;
            if ($count === self::LOCATIONS_LIMIT) {
                break;
            }
        }
        return $result;
    }

    private function checkOptionSelected(\Magento\Quote\Api\Data\CartInterface $quote)
    {
        $postnlOrder = $this->postnlOrderRepository->getByQuoteId($quote->getId());
        if (!$this->locationId
            && $postnlOrder->getEntityId()
            && $postnlOrder->getIsPakjegemak()
            && $postnlOrder->getPgLocationCode()
        ) {
            $this->locationId = $postnlOrder->getPgLocationCode();
            $this->editMode = '0';
        }
    }

}
