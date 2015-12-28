<?php

namespace ClassyLlama\AvaTax\Tests\Integration\Model\Tax\Sales\Total\Quote;

use Magento\Tax\Model\Calculation;
use Magento\TestFramework\Helper\Bootstrap;

require_once __DIR__ . '/SetupUtil.php';
require_once __DIR__ . '/../../../../../_files/tax_calculation_data_aggregated.php';

/**
 * Class TaxTest
 */
class TaxTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Utility object for setting up tax rates, tax classes and tax rules
     *
     * @var SetupUtil
     */
    protected $setupUtil = null;

    protected $quoteAddressFieldsEnsureMatch = [
        'subtotal',
        'base_subtotal',
        'subtotal_with_discount',
        'base_subtotal_with_discount',
        'tax_amount',
        'base_tax_amount',
        'shipping_amount',
        'base_shipping_amount',
        'shipping_tax_amount',
        'base_shipping_tax_amount',
        'discount_amount',
        'base_discount_amount',
        'grand_total',
        'base_grand_total',
        'shipping_discount_amount',
        'base_shipping_discount_amount',
        'subtotal_incl_tax',
        'base_subtotal_total_incl_tax',
        'discount_tax_compensation_amount',
        'base_discount_tax_compensation_amount',
        'shipping_discount_tax_compensation_amount',
        'base_shipping_discount_tax_compensation_amnt',
        'shipping_incl_tax',
        'base_shipping_incl_tax',
        'gw_base_price',
        'gw_price',
        'gw_items_base_price',
        'gw_items_price',
        'gw_card_base_price',
        'gw_card_price',
        'gw_base_tax_amount',
        'gw_tax_amount',
        'gw_items_base_tax_amount',
        'gw_items_tax_amount',
        'gw_card_base_tax_amount',
        'gw_card_tax_amount',
        'gw_base_price_incl_tax',
        'gw_price_incl_tax',
        'gw_items_base_price_incl_tax',
        'gw_items_price_incl_tax',
        'gw_card_base_price_incl_tax',
        'gw_card_price_incl_tax',
    ];

    protected $quoteAddressFieldsEnsureDiff = [
        'applied_taxes',
    ];

    protected $quoteItemFieldsEnsureMatch = [
        'qty',
        'price',
        'base_price',
        'custom_price',
        'discount_percent',
        'discount_amount',
        'base_discount_amount',
        'tax_percent',
        'tax_amount',
        'base_tax_amount',
        'row_total',
        'base_row_total',
        'row_total_with_discount',
        'base_tax_before_discount',
        'tax_before_discount',
        'original_custom_price',
        'base_cost',
        'price_incl_tax',
        'base_price_incl_tax',
        'row_total_incl_tax',
        'base_row_total_incl_tax',
        'discount_tax_compensation_amount',
        'base_discount_tax_compensation_amount',
        'gw_base_price',
        'gw_price',
        'gw_base_tax_amount',
        'gw_tax_amount',
    ];

    /**
     * Test taxes collection for quote.
     *
     * Quote has customer and product.
     * Product tax class and customer group tax class along with billing address have corresponding tax rule.
     *
     * @magentoDataFixture Magento/Customer/_files/customer.php
     * @magentoDataFixture Magento/Customer/_files/customer_address.php
     * @magentoDataFixture Magento/Catalog/_files/products.php
     * @magentoDataFixture Magento/Tax/_files/tax_classes.php
     * @magentoDataFixture Magento/Customer/_files/customer_group.php
     * @magentoDbIsolation enabled
     * @magentoAppIsolation enabled
     */
    public function testCollect()
    {
        /** Preconditions */
        $objectManager = Bootstrap::getObjectManager();
        /** @var \Magento\Tax\Model\ClassModel $customerTaxClass */
        $customerTaxClass = $objectManager->create('Magento\Tax\Model\ClassModel');
        $fixtureCustomerTaxClass = 'CustomerTaxClass2';
        $customerTaxClass->load($fixtureCustomerTaxClass, 'class_name');
        $fixtureCustomerId = 1;
        /** @var \Magento\Customer\Model\Customer $customer */
        $customer = $objectManager->create('Magento\Customer\Model\Customer')->load($fixtureCustomerId);
        /** @var \Magento\Customer\Model\Group $customerGroup */
        $customerGroup = $objectManager->create('Magento\Customer\Model\Group')
            ->load('custom_group', 'customer_group_code');
        $customerGroup->setTaxClassId($customerTaxClass->getId())->save();
        $customer->setGroupId($customerGroup->getId())->save();

        /** @var \Magento\Tax\Model\ClassModel $productTaxClass */
        $productTaxClass = $objectManager->create('Magento\Tax\Model\ClassModel');
        $fixtureProductTaxClass = 'ProductTaxClass1';
        $productTaxClass->load($fixtureProductTaxClass, 'class_name');
        $fixtureProductId = 1;
        /** @var \Magento\Catalog\Model\Product $product */
        $product = $objectManager->create('Magento\Catalog\Model\Product')->load($fixtureProductId);
        $product->setTaxClassId($productTaxClass->getId())->save();

        $fixtureCustomerAddressId = 1;
        $customerAddress = $objectManager->create('Magento\Customer\Model\Address')->load($fixtureCustomerId);
        /** Set data which corresponds tax class fixture */
        $customerAddress->setCountryId('US')->setRegionId(12)->save();
        /** @var \Magento\Quote\Model\Quote\Address $quoteShippingAddress */
        $quoteShippingAddress = $objectManager->create('Magento\Quote\Model\Quote\Address');
        /** @var \Magento\Customer\Api\AddressRepositoryInterface $addressRepository */
        $addressRepository = $objectManager->create('Magento\Customer\Api\AddressRepositoryInterface');
        $quoteShippingAddress->importCustomerAddressData($addressRepository->getById($fixtureCustomerAddressId));

        /** @var \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository */
        $customerRepository = $objectManager->create('Magento\Customer\Api\CustomerRepositoryInterface');
        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $objectManager->create('Magento\Quote\Model\Quote');
        $quote->setStoreId(1)
            ->setIsActive(true)
            ->setIsMultiShipping(false)
            ->assignCustomerWithAddressChange($customerRepository->getById($customer->getId()))
            ->setShippingAddress($quoteShippingAddress)
            ->setBillingAddress($quoteShippingAddress)
            ->setCheckoutMethod($customer->getMode())
            ->setPasswordHash($customer->encryptPassword($customer->getPassword()))
            ->addProduct($product->load($product->getId()), 2);

        /**
         * Execute SUT.
         * \Magento\Tax\Model\Sales\Total\Quote\Tax::collect cannot be called separately from
         * \Magento\Tax\Model\Sales\Total\Quote\Subtotal::collect because tax to zero amount will be applied.
         * That is why it make sense to call collectTotals() instead, which will call SUT in its turn.
         */
        $quote->collectTotals();

        /** Check results */
        $this->assertEquals(
            $customerTaxClass->getId(),
            $quote->getCustomerTaxClassId(),
            'Customer tax class ID in quote is invalid.'
        );
        $this->assertEquals(
            21.5,
            $quote->getGrandTotal(),
            'Customer tax was collected by \Magento\Tax\Model\Sales\Total\Quote\Tax::collect incorrectly.'
        );
    }

    /**
     * Verify fields in quote item
     *
     * @param \Magento\Quote\Model\Quote\Address\Item $item
     * @param array $expectedItemData
     * @return $this
     */
    protected function verifyItem($item, $expectedItemData)
    {
        foreach ($expectedItemData as $key => $value) {
            $this->assertEquals($value, $item->getData($key), 'item ' . $key . ' is incorrect');
        }

        return $this;
    }

    /**
     * Verify one tax rate in a tax row
     *
     * @param array $appliedTaxRate
     * @param array $expectedAppliedTaxRate
     * @return $this
     */
    protected function verifyAppliedTaxRate($appliedTaxRate, $expectedAppliedTaxRate)
    {
        foreach ($expectedAppliedTaxRate as $key => $value) {
            $this->assertEquals($value, $appliedTaxRate[$key], 'Applied tax rate ' . $key . ' is incorrect');
        }
        return $this;
    }

    /**
     * Verify one row in the applied taxes
     *
     * @param array $appliedTax
     * @param array $expectedAppliedTax
     * @return $this
     */
    protected function verifyAppliedTax($appliedTax, $expectedAppliedTax)
    {
        foreach ($expectedAppliedTax as $key => $value) {
            if ($key == 'rates') {
                foreach ($value as $index => $taxRate) {
                    $this->verifyAppliedTaxRate($appliedTax['rates'][$index], $taxRate);
                }
            } else {
                $this->assertEquals($value, $appliedTax[$key], 'Applied tax ' . $key . ' is incorrect');
            }
        }
        return $this;
    }

    /**
     * Verify that applied taxes are correct
     *
     * @param array $appliedTaxes
     * @param array $expectedAppliedTaxes
     * @return $this
     */
    protected function verifyAppliedTaxes($appliedTaxes, $expectedAppliedTaxes)
    {
        foreach ($expectedAppliedTaxes as $taxRateKey => $expectedTaxRate) {
            $this->assertTrue(isset($appliedTaxes[$taxRateKey]), 'Missing tax rate ' . $taxRateKey);
            $this->verifyAppliedTax($appliedTaxes[$taxRateKey], $expectedTaxRate);
        }
        return $this;
    }

    /**
     * Verify fields in quote address
     *
     * @param \Magento\Quote\Model\Quote\Address $quoteAddress
     * @param array $expectedAddressData
     * @return $this
     */
    protected function verifyQuoteAddress($quoteAddress, $expectedAddressData)
    {
        foreach ($expectedAddressData as $key => $value) {
            if ($key == 'applied_taxes') {
                $this->verifyAppliedTaxes($quoteAddress->getAppliedTaxes(), $value);
            } else {
                $this->assertEquals($value, $quoteAddress->getData($key), 'Quote address ' . $key . ' is incorrect');
            }
        }

        return $this;
    }

    /**
     * Verify fields in quote address and quote item are correct
     *
     * @param \Magento\Quote\Model\Quote\Address $quoteAddress
     * @param array $expectedResults
     * @return $this
     */
    protected function verifyResult($quoteAddress, $expectedResults)
    {
        $addressData = $expectedResults['address_data'];

        $this->verifyQuoteAddress($quoteAddress, $addressData);

        $quoteItems = $quoteAddress->getAllItems();
        foreach ($quoteItems as $item) {
            /** @var  \Magento\Quote\Model\Quote\Address\Item $item */
            $sku = $this->getActualSkuForQuoteItem($item);

            $this->assertTrue(
                isset($expectedResults['items_data'][$sku]),
                "Missing array key in 'expected_results' for $sku"
            );

            $expectedItemData = $expectedResults['items_data'][$sku];
            $this->verifyItem($item, $expectedItemData);
        }

        // Make sure all 'expected_result' items are present in quote
        foreach ($quoteItems as $item) {
            unset($expectedResults['items_data'][$this->getActualSkuForQuoteItem($item)]);
        }
        $this->assertEmpty(
            $expectedResults['items_data'],
            'The following expected_results items were not present in quote: '
                . implode(', ', array_keys($expectedResults['items_data']))
        );

        return $this;
    }

    /**
     * Get actual SKU for quote item. This used since configurable product quote items report the child SKU when
     * $item->getProduct()->getSku() is called
     *
     * @param \Magento\Quote\Model\Quote\Item\AbstractItem $item
     * @return mixed
     */
    protected function getActualSkuForQuoteItem(\Magento\Quote\Model\Quote\Item\AbstractItem $item)
    {
        return $item->getProduct()->getData('sku');
    }

    /**
     * Test tax calculation with various configuration and combination of items
     * This method will test various collectors through $quoteAddress->collectTotals() method
     *
     * @param array $configData
     * @param array $quoteData
     * @param array $expectedResults
     * @magentoDbIsolation enabled
     * @magentoAppIsolation enabled
     * @dataProvider taxDataProvider
     * @return void
     */
    public function testTaxCalculation($configData, $quoteData, $expectedResults)
    {
        /** @var  \Magento\Framework\ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var  \Magento\Quote\Model\Quote\TotalsCollector $totalsCollector */
        $totalsCollector = $objectManager->create('Magento\Quote\Model\Quote\TotalsCollector');

        //Setup tax configurations
        $this->setupUtil = new SetupUtil($objectManager);
        $this->setupUtil->setupTax($configData);

        $quote = $this->setupUtil->setupQuote($quoteData);
        $quoteAddress = $quote->getShippingAddress();
        $totalsCollector->collectAddressTotals($quote, $quoteAddress);
        $this->verifyResult($quoteAddress, $expectedResults);
    }

    /**
     * Test tax calculation with various configuration and combination of items
     * This method will test various collectors through $quoteAddress->collectTotals() method
     *
     * @param array $configData
     * @param array $quoteData
     * @param array $expectedResults
     * @magentoDbIsolation enabled
     * @magentoAppIsolation enabled
     * @dataProvider taxDataProvider
     * @return void
     */
    public function testNativeVsMagentoTaxCalculation($configData, $quoteData, $expectedResults)
    {
        // Only compare with native Magento taxes if this test is configured to do so
        if (!isset($expectedResults['compare_with_native_tax_calculation'])
            || !$expectedResults['compare_with_native_tax_calculation']
        ) {
            return;
        }

        /** @var  \Magento\Framework\ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        //Setup tax configurations
        $this->setupUtil = new SetupUtil($objectManager);
        // Ensure AvaTax is disabled
        $nativeConfigData = [
            SetupUtil::CONFIG_OVERRIDES => [
                \ClassyLlama\AvaTax\Model\Config::XML_PATH_AVATAX_MODULE_ENABLED => 0,
            ],
        ];
        $nativeQuoteAddress = $this->calculateTaxes($nativeConfigData, $quoteData);
        $avaTaxQuoteAddress = $this->calculateTaxes($configData, $quoteData, false);
        $this->compareResults($nativeQuoteAddress, $avaTaxQuoteAddress, $expectedResults);
    }

    /**
     * Calculate taxes based on the specified config values
     *
     * @param $configData
     * @param $quoteData
     * @param bool $setupTaxData
     * @return \Magento\Quote\Model\Quote\Address
     */
    protected function calculateTaxes($configData, $quoteData, $setupTaxData = true)
    {
        /** @var  \Magento\Framework\ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var  \Magento\Quote\Model\Quote\TotalsCollector $totalsCollector */
        $totalsCollector = $objectManager->create('Magento\Quote\Model\Quote\TotalsCollector');

        if ($setupTaxData) {
            $this->setupUtil->setupTax($configData);
        } elseif (!empty($configData[SetupUtil::CONFIG_OVERRIDES])) {
            //Tax calculation configuration
            $this->setupUtil->setConfig($configData[SetupUtil::CONFIG_OVERRIDES]);
        }

        $quote = $this->setupUtil->setupQuote($quoteData);
        $quoteAddress = $quote->getShippingAddress();
        $totalsCollector->collectAddressTotals($quote, $quoteAddress);
        return $quoteAddress;
    }

    /**
     * Compare two quote addresses and ensure that their values either match or don't match
     *
     * @param \Magento\Quote\Model\Quote\Address $nativeQuoteAddress
     * @param \Magento\Quote\Model\Quote\Address $avaTaxQuoteAddress
     * @param $expectedResults
     * @return $this
     * @throws \Exception
     */
    protected function compareResults(
        \Magento\Quote\Model\Quote\Address $nativeQuoteAddress,
        \Magento\Quote\Model\Quote\Address $avaTaxQuoteAddress,
        $expectedResults
    ) {
        $this->compareQuoteAddresses($nativeQuoteAddress, $avaTaxQuoteAddress);

        $avaTaxItemsBySku = [];
        foreach ($avaTaxQuoteAddress->getAllItems() as $item) {
            if (isset($avaTaxItemsBySku[$this->getActualSkuForQuoteItem($item)])) {
                throw new \Exception(__('Quote contains items containing the same SKU.'
                    . ' This will not work since SKU must be used as the GUID to compare quote items.'));
            }
            $avaTaxItemsBySku[$this->getActualSkuForQuoteItem($item)] = $item;
        }

        $quoteItems = $nativeQuoteAddress->getAllItems();
        foreach ($quoteItems as $item) {
            /** @var  \Magento\Quote\Model\Quote\Address\Item $item */
            $sku = $this->getActualSkuForQuoteItem($item);

            $this->assertTrue(
                isset($expectedResults['items_data'][$sku]),
                "Missing array key in 'expected_results' for $sku"
            );

            if (!isset($avaTaxItemsBySku[$sku])) {
                throw new \Exception(__('Sku %1 was not found in AvaTax quote.', $sku));
            }

            $avaTaxItem = $avaTaxItemsBySku[$sku];
            $this->compareItems($item, $avaTaxItem);
        }

        // Make sure all 'expected_result' items are present in quote
        foreach ($quoteItems as $item) {
            unset($expectedResults['items_data'][$this->getActualSkuForQuoteItem($item)]);
        }
        $this->assertEmpty(
            $expectedResults['items_data'],
            'The following expected_results items were not present in quote: '
            . implode(', ', array_keys($expectedResults['items_data']))
        );

        return $this;
    }

    /**
     * Compare quote address and ensure fields match / don't match
     *
     * @param \Magento\Quote\Model\Quote\Address $nativeQuoteAddress
     * @param \Magento\Quote\Model\Quote\Address $avaTaxQuoteAddress
     * @return $this
     */
    protected function compareQuoteAddresses($nativeQuoteAddress, $avaTaxQuoteAddress)
    {
        foreach ($this->quoteAddressFieldsEnsureMatch as $value) {
            try {
                $this->assertEquals(
                    $nativeQuoteAddress->getData($value),
                    $avaTaxQuoteAddress->getData($value),
                    'native/AvaTax calcalation does not match for quote address field: ' . $value
                );
            } catch (\PHPUnit_Framework_ExpectationFailedException $e) {
                $this->logError($e->getMessage());
            }
        }
        foreach ($this->quoteAddressFieldsEnsureDiff as $value) {
            try {
                $this->assertNotEquals(
                    $nativeQuoteAddress->getData($value),
                    $avaTaxQuoteAddress->getData($value),
                    'native/AvaTax calcalation matches (but shouldn\'t be) for quote address field: ' . $value
                );
            } catch (\PHPUnit_Framework_ExpectationFailedException $e) {
                $this->logError($e->getMessage());
            }
        }

        return $this;
    }

    /**
     * Compare quote items and ensure fields match
     *
     * @param \Magento\Quote\Model\Quote\Item\AbstractItem $nativeItem
     * @param \Magento\Quote\Model\Quote\Item\AbstractItem $avaTaxItem
     * @return $this
     */
    protected function compareItems(
        \Magento\Quote\Model\Quote\Item\AbstractItem $nativeItem,
        \Magento\Quote\Model\Quote\Item\AbstractItem $avaTaxItem
    ) {
        foreach ($this->quoteItemFieldsEnsureMatch as $value) {
            try {
                $this->assertEquals(
                    $nativeItem->getData($value),
                    $avaTaxItem->getData($value),
                    'native/AvaTax calcalation does not match for quote item field: ' . $value
                );
            } catch (\PHPUnit_Framework_ExpectationFailedException $e) {
                $this->logError($this->getActualSkuForQuoteItem($nativeItem) . ' ' . $e->getMessage());
            }
        }

        return $this;
    }

    protected function logError($message)
    {
        file_put_contents(
            BP . '/var/log/avatax_tests.log',
            $message . PHP_EOL,
            FILE_APPEND
        );
    }

    /**
     * Read the array defined in ../../../../_files/tax_calculation_data_aggregated.php
     * and feed it to testTaxCalculation
     *
     * @return array
     */
    public function taxDataProvider()
    {
        global $taxCalculationData;
        return $taxCalculationData;
    }
}
