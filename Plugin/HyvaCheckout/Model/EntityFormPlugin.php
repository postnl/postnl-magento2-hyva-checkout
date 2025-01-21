<?php
namespace PostNL\HyvaCheckout\Plugin\HyvaCheckout\Model;

use Hyva\Checkout\Model\Form\AbstractEntityForm as SubjectClass;
use PostNL\HyvaCheckout\Api\CheckoutFieldsApi;
use PostNL\HyvaCheckout\Model\StateContainer;

class EntityFormPlugin
{
    private StateContainer $stateContainer;

    public function __construct(
        StateContainer $stateContainer
    ) {
        $this->stateContainer = $stateContainer;
    }

    public function afterToArray(
        SubjectClass $entityForm,
        array $result
    ): array {
        if (!$this->stateContainer->isStateSave()) {
            return $result;
        }
        $street = $entityForm->getField('street');
        if (!$street || !isset($result['street'])) {
            return $result;
        }
        $houseNumberField = $entityForm->getField(CheckoutFieldsApi::POSTNL_HOUSE_NUMBER);
        $additionaField = $entityForm->getField(CheckoutFieldsApi::POSTNL_HOUSE_NUMBER_ADDITION);
        $relatives = $street->getRelatives();
        if ($houseNumberField && $houseNumberField->getValue() && !isset($relatives[1])) {
            $additionalValue = $additionaField ? $additionaField->getValue() : '';
            $result['street'] .= "\n" . $houseNumberField->getValue() . "\n" . $additionalValue;
        } else if ($additionaField && $additionaField->getValue() && !isset($relatives[2])) {
            $result['street'] .= "\n" . $additionaField->getValue();
        }
        return $result;
    }

    public function beforeFill(
        SubjectClass $entityForm,
        array $values,
        array $fields = []
    ): array {
        $street = $entityForm->getField('street');
        if ($street && isset($values['street']) && is_array($values['street'])) {
            // Need to check each relative - if it's set or not
            $relativesMap = [
                1 => CheckoutFieldsApi::POSTNL_HOUSE_NUMBER,
                2 => CheckoutFieldsApi::POSTNL_HOUSE_NUMBER_ADDITION
            ];
            // We have different logic for when there are 1 or 2 relatives. In both cases data is saved to the street fields
            // But hyva will fill out parts of this data itself if relatives exists, so we should only walk around when they are not
            $relatives = $street->getRelatives();
            foreach ($relativesMap as $relativeKey => $key) {
                if (!isset($relatives[$relativeKey])) {
                    $street->setData($key, $values['street'][$relativeKey] ?? null);
                    unset($values['street'][$relativeKey]);
                }
            }
        }
        return [$values, $fields];
    }
}
