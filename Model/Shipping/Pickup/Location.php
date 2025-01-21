<?php

namespace PostNL\HyvaCheckout\Model\Shipping\Pickup;

class Location
{
    private \stdClass $object;

    private array $days = [
        'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'
    ];

    public function __construct(
        \stdClass $object
    ) {
        $this->object = $object;
    }

    public function getAddressArray(): array
    {
        return (array)$this->object->Address;
    }

    public function getName(): string
    {
        return (string)$this->object->Name;
    }

    public function getDistance(): string
    {
        $distance = (int)$this->object->Distance;
        switch (true) {
            case $distance > 0 && $distance < 1000:
                return $distance . ' m';
            case $distance >= 1000:
                return round($distance/1000, 1) . ' km';
            default:
                return '';
        }
    }

    public function getValue(): string
    {
        return $this->object->LocationCode;
    }

    public function getNetworkId()
    {
        return $this->object->RetailNetworkID;
    }

    public function getStreet(): string
    {
        return $this->object->Address->Street;
    }

    public function getHouseNumber(): string
    {
        return $this->object->Address->HouseNr;
    }

    public function getHouseNumberExt(): string
    {
        return $this->object->Address->HouseNrExt ?? '';
    }
    public function getCity(): string
    {
        return $this->object->Address->City;
    }

    public function getPostcode(): string
    {
        return $this->object->Address->Zipcode;
    }

    public function getCountry(): string
    {
        return $this->object->Address->Countrycode;
    }

    public function getHours(): array
    {
        $result = [];
        foreach ($this->days as $dayKey) {
            if (isset($this->object->OpeningHours->$dayKey)) {
                $day = [
                    'day' => __($dayKey),
                    'hours' => []
                ];
                foreach ($this->object->OpeningHours->$dayKey->string as $hours) {
                    $day['hours'][] = $hours;
                }
                $result[] = $day;
            }
        }
        return $result;
    }
}
