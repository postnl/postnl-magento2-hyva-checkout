<?php

namespace PostNL\HyvaCheckout\Magewire\Helper;

trait AddressRequest
{
    public function getRequestData($address, bool $includeHousenumber = false): ?array
    {
        $requestData = $this->getAddressData($address, $includeHousenumber);
        if ($this->addressIsFilled($requestData)) {
            return $requestData;
        }

        return null;
    }


    private function addressIsFilled(array $addressData): bool
    {
        $postcode = $addressData['postcode'] ?? '';
        if (empty($postcode) || strlen($postcode) < 3) {
            return false;
        }

        $street = $addressData['street'] ?? [];
        if (is_array($street)) {
            if (empty($street) || empty($street[0]) || strlen($street[0]) < 3) {
                return false;
            }
        } else {
            if (empty($street) || strlen($street) < 3) {
                return false;
            }
        }

        if (isset($addressData['housenumber'])) {
            $housenumber = $addressData['housenumber'];
            if (empty($housenumber)) {
                return false;
            }
        }

        return true;
    }

    private function getAddressData($address, bool $includeHousenumber = false): array
    {
        if (!$address) {
            return [];
        }

        $street = $address->getStreet();
        $data = [
            'country'  => $address->getCountryId(),
            'street'   => $street,
            'postcode' => $address->getPostcode(),
            'city'     => $address->getCity(),
        ];

        if ($includeHousenumber) {
            $data['housenumber'] = $street[1] ?? '';
        }

        return $data;
    }
}