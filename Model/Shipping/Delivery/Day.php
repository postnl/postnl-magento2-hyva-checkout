<?php
namespace PostNL\HyvaCheckout\Model\Shipping\Delivery;
class Day
{
    /**
     * @var array|Timeframe[]
     */
    private array $options;
    private ?string $date;
    private ?string $weekDay;

    /**
     * @param Timeframe[] $options
     * @param string|null $date
     * @param string|null $weekDay
     */
    public function __construct(
        array $options,
        string $date = null,
        string $weekDay = null
    ) {
        $this->options = $options;
        $this->date = $date;
        $this->weekDay = $weekDay;
    }

    public function getDate()
    {
        return $this->date;
    }

    public function getOptions()
    {
        return $this->options;
    }

    public function getWeekDay()
    {
        return $this->weekDay;
    }
}
