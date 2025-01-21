<?php

namespace PostNL\HyvaCheckout\Plugin\HyvaCheckout\Model;

use Hyva\Checkout\Model\Form\EntityFormSaveServiceInterface as SubjectClass;
use PostNL\HyvaCheckout\Model\StateContainer;

class EntityFormSaveServicePlugin
{
    private StateContainer $stateContainer;

    public function __construct(
        StateContainer $stateContainer
    ) {
        $this->stateContainer = $stateContainer;
    }

    public function beforeSave(): void
    {
        $this->stateContainer->setSaveState(StateContainer::STATE_SAVE);
    }

    public function afterSave(
        SubjectClass $subject,
        $result
    ) {
        $this->stateContainer->setSaveState(StateContainer::STATE_DEFAULT);
        return $result;
    }
}
