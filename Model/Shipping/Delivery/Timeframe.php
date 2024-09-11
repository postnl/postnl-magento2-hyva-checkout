<?php

namespace PostNL\HyvaCheckout\Model\Shipping\Delivery;

class Timeframe
{
    private string $value;
    private string $label;
    private ?string $rightLabel;
    private ?string $fee;

    public function __construct(
        string $value,
        string $label,
        string $rightLabel = null,
        string $fee = null
    ) {
        $this->value = $value;
        $this->label = $label;
        $this->rightLabel = $rightLabel;
        $this->fee = $fee;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getRightLabel(): string
    {
        $label = strtolower($this->rightLabel);
        switch ($label) {
            case 'daytime':
                return __('Daytime');
            case 'evening':
                return __('Evening');
            case 'sunday':
                return __('Sunday');
            case 'today':
                return __('Fast Delivery');
            default:
                return '';
        }
    }

    public function getFee(): ?string
    {
        return $this->fee;
    }
}
