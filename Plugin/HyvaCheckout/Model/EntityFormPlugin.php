<?php
namespace PostNL\HyvaCheckout\Plugin\HyvaCheckout\Model;

use Hyva\Checkout\Model\Form\AbstractEntityForm as SubjectClass;
use PostNL\HyvaCheckout\Api\CheckoutFieldsApi;

class EntityFormPlugin
{
    public function afterToArray(
        SubjectClass $entityForm,
        array $result
    ): array {
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
}
