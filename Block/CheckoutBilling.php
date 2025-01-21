<?php

namespace PostNL\HyvaCheckout\Block;

use Magento\Checkout\Model\Session;
use Magento\Framework\View\Element\Template;
use PostNL\HyvaCheckout\Model\QuoteOrderRepository;

class CheckoutBilling extends Template
{
    private Session $checkoutSession;
    private QuoteOrderRepository $quoteOrderRepository;

    public function __construct(
        Template\Context $context,
        Session $checkoutSession,
        QuoteOrderRepository $quoteOrderRepository,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->checkoutSession = $checkoutSession;
        $this->quoteOrderRepository = $quoteOrderRepository;
    }

    public function getTemplate()
    {
        $quote = $this->checkoutSession->getQuote();
        $postnlOrder = $this->quoteOrderRepository->getByQuoteId($quote->getId());
        if ($postnlOrder->getEntityId() && $postnlOrder->getIsPakjegemak()
            && $postnlOrder->getPgLocationCode()
        ) {
            return 'PostNL_HyvaCheckout::checkout/address-view/pickup-billing-details.phtml';
        }
        return parent::getTemplate();
    }
}
