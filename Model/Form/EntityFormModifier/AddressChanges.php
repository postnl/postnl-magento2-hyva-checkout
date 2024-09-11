<?php

namespace PostNL\HyvaCheckout\Model\Form\EntityFormModifier;

use Hyva\Checkout\Model\Form\AbstractEntityForm;
use Hyva\Checkout\Model\Form\AbstractEntityFormModifier;
use Hyva\Checkout\Model\Form\EntityField\EavEntityAddress\StreetAttributeField;
use Magento\Customer\Api\Data\AddressInterface;
use PostNL\HyvaCheckout\Api\CheckoutFieldsApi;

class AddressChanges extends AbstractEntityFormModifier
{
    public function apply(AbstractEntityForm $form): AbstractEntityForm
    {
        $form->registerModificationListener(
            'init-postnl-address-fields',
            'form:build',
            [$this, 'registerCheckoutFields']
        );

        $form->registerModificationListener(
            'init-postnl-address-fields2',
            'form:field:updated',
            [$this, 'validateUpdatedFields']
        );

        $form->registerModificationListener(
            'postnl.form.boot',
            'form:boot',
            fn (AbstractEntityForm $form) => $this->formBootListenerAction($form)
        );

        return $form;
    }

    public function registerCheckoutFields(AbstractEntityForm $form): void
    {
        $countryField = $form->getField(\Magento\Customer\Api\Data\AddressInterface::COUNTRY_ID);

        if ($countryField?->getValue() === 'NL') {
            $form->getField(CheckoutFieldsApi::POSTNL_ADDRESS)->show();

            $form->getField(AddressInterface::POSTCODE)->setAttribute('disabled');
            $form->getField(CheckoutFieldsApi::POSTNL_HOUSE_NUMBER)?->enable();
            $form->getField(CheckoutFieldsApi::POSTNL_POSTCODE)?->enable();

            $street = $form->getField(AddressInterface::STREET);
            $street->setAttribute('disabled');

            foreach ($street->getRelatives() as $relative) {
                $relative->setAttribute('disabled');
            }

            $form->getField(AddressInterface::CITY)?->setAttribute('disabled');
            $form->getField(AddressInterface::POSTCODE)?->setAttribute('disabled');
        } else {
            $form->getField(CheckoutFieldsApi::POSTNL_ADDRESS)->hide();
        }
    }

    public function validateUpdatedFields($form, $attributeField, $form2, $addressType)
    {
        $address = (array)\json_decode((string)$form->getField(CheckoutFieldsApi::POSTNL_POSTCODE)->getValue());
        if ($address) {
            $streetField = $form->getfield(AddressInterface::STREET);
            $streetField->setValue($address[AddressInterface::STREET] ?? '');
            $streetRelatives = $streetField->getRelatives();

            if (isset($streetRelatives[1]) && $streetField instanceof StreetAttributeField) {
                $streetRelatives[1]->setValue($address['houseNumber']);
            }
            if (isset($streetRelatives[2]) && $streetField instanceof StreetAttributeField) {
                $streetRelatives[2]->setValue($address['houseNumberAddition']);
            }

            $this->updateFormField($form, AddressInterface::POSTCODE, $address, AddressInterface::POSTCODE);
            $this->updateFormField($form, AddressInterface::CITY, $address, AddressInterface::CITY);
            $this->updateFormField($form, AddressInterface::REGION, $address, 'province');
            $this->updateFormField($form, CheckoutFieldsApi::POSTNL_POSTCODE, $address, AddressInterface::POSTCODE);
            $this->updateFormField($form, CheckoutFieldsApi::POSTNL_HOUSE_NUMBER, $address, 'houseNumber');
            $this->updateFormField($form, CheckoutFieldsApi::POSTNL_HOUSE_NUMBER_ADDITION, $address, 'houseNumberAddition');
        } else {
            $postcode = $form->getField('postcode')?->getValue();
            if ($postcode) {
                $form->getField(CheckoutFieldsApi::POSTNL_POSTCODE)?->setValue($postcode);
            }
        }
    }

    private function updateFormField(AbstractEntityForm $form, string $fieldName, array $address, string $fromField): void
    {
        $field = $form->getField($fieldName);
        if ($field && isset($address[$fromField])) {
            $field->setValue($address[$fromField]);
        }
    }

    private function formBootListenerAction (AbstractEntityForm $form): AbstractEntityForm
    {
        // Prevent the regular address fields to show, without removing them, so they can hold an address value.
        $streetField = $form->getfield(\Magento\Customer\Api\Data\AddressInterface::STREET);
        $postcodeField = $form->getfield('postcode');

        // Wrapper field who's responsible for postcode and house number.
        $address = $form->createField(CheckoutFieldsApi::POSTNL_ADDRESS, 'html', [
            'data' => [
                'position' => 11
            ]
        ]);

        // Create two data carrier fields and hide them, so we can render both manually.
        $postcode = $form->createField(CheckoutFieldsApi::POSTNL_POSTCODE, 'text', [
            'data' => [
                'label' => __('Zip/Postal Code')->__toString(),
                'is_auto_save' => false,
                'auto_complete' => 'off',
                'is_required' => true,
                'value' => $postcodeField->getValue()
            ]
        ])
            ->setAttribute('x-ref', CheckoutFieldsApi::POSTNL_POSTCODE)
            ->setAttribute('x-model', 'address.'. CheckoutFieldsApi::POSTNL_POSTCODE)
            ->setValidationRule('validate-postcode')
            ->setAttribute('@change', 'onChangeInput')
            ->hide();

        $houseNumber = $form->createField(CheckoutFieldsApi::POSTNL_HOUSE_NUMBER, 'text', [
            'data' => [
                'label' => __('House Number')->__toString(),
                'is_auto_save' => false,
                'auto_complete' => 'off',
                'is_required' => true
            ]
        ])
            ->setAttribute('x-ref', CheckoutFieldsApi::POSTNL_HOUSE_NUMBER)
            ->setAttribute('x-model', 'address.'. CheckoutFieldsApi::POSTNL_HOUSE_NUMBER)
            ->setValidationRule('validate-house-number')
            ->setAttribute('@change', 'onChangeInput')
            ->hide();

        $houseNumberAddition = $form->createField(CheckoutFieldsApi::POSTNL_HOUSE_NUMBER_ADDITION, 'text', [
            'data' => [
                'label' => __('House Number Addition')->__toString(),
                'is_auto_save' => false,
                'auto_complete' => 'off',
                'is_required' => false
            ]
        ])
            ->setAttribute('x-ref', CheckoutFieldsApi::POSTNL_HOUSE_NUMBER_ADDITION)
            ->setAttribute('x-model', 'address.'. CheckoutFieldsApi::POSTNL_HOUSE_NUMBER_ADDITION)
            ->hide();

        $streetRelatives = $streetField->getRelatives();

        if (isset($streetRelatives[1])) {
            $houseNumber->setValue($streetRelatives[1]->getValue());
        }
        if (isset($streetRelatives[2])) {
            $houseNumberAddition->setValue($streetRelatives[2]->getValue());
        }

        $form->addField($address);
        $form->addField($postcode);
        $form->addField($houseNumber);
        $form->addField($houseNumberAddition);

        return $form;
    }
}
