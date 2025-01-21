<?php

namespace PostNL\HyvaCheckout\Api;

class CheckoutFieldsApi
{
    /**
     * Well, it's hardcoded a lot in the original extension, so this one might be used only in hyva one abstraction.
     */
    public const CARRIER_CODE = 'tig_postnl';
    public const SHIPPING_CODE = 'tig_postnl_regular';

    /**
     * Additional fields added to the Shipping address
     */
    public const POSTNL_ADDRESS = 'postnl_address';
    public const POSTNL_POSTCODE = 'postnl_postcode';
    public const POSTNL_HOUSE_NUMBER = 'postnl_housenumber';
    public const POSTNL_HOUSE_NUMBER_ADDITION = 'postnl_housenumber_addition';

    /**
     * Delivery selection
     */
    public const DELIVERY_TYPE_DELIVERY = 'delivery';
    public const DELIVERY_TYPE_PICKUP = 'pickup';

}
