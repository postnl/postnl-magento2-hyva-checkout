<?php

namespace PostNL\HyvaCheckout\Model;

class StateContainer
{
    public const STATE_SAVE = 1;
    public const STATE_DEFAULT = 0;

    private int $state = self::STATE_DEFAULT;

    public function setSaveState(int $value): void
    {
        $this->state = $value;
    }

    public function isStateSave(): bool
    {
        return $this->state === self::STATE_SAVE;
    }
}
