<?php

namespace Oro\Bundle\ShoppingListBundle\Tests\Unit\Manager;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManager;
use Oro\Bundle\ConfigBundle\Config\ConfigManager;
use Oro\Bundle\CustomerBundle\Entity\Customer;
use Oro\Bundle\CustomerBundle\Entity\CustomerUser;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\ProductBundle\DependencyInjection\Configuration;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\Entity\ProductUnit;
use Oro\Bundle\ProductBundle\Entity\ProductUnitPrecision;
use Oro\Bundle\ProductBundle\Provider\ProductMatrixAvailabilityProvider;
use Oro\Bundle\ProductBundle\Provider\ProductVariantAvailabilityProvider;
use Oro\Bundle\ProductBundle\Rounding\QuantityRoundingService;
use Oro\Bundle\SecurityBundle\Authentication\TokenAccessorInterface;
use Oro\Bundle\ShoppingListBundle\Entity\LineItem;
use Oro\Bundle\ShoppingListBundle\Entity\Repository\LineItemRepository;
use Oro\Bundle\ShoppingListBundle\Entity\ShoppingList;
use Oro\Bundle\ShoppingListBundle\Manager\ShoppingListManager;
use Oro\Bundle\ShoppingListBundle\Manager\ShoppingListTotalManager;
use Oro\Bundle\WebsiteBundle\Entity\Website;
use Oro\Bundle\WebsiteBundle\Manager\WebsiteManager;
use Oro\Component\Testing\Unit\EntityTrait;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * @SuppressWarnings(PHPMD.TooManyMethods)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class ShoppingListManagerTest extends \PHPUnit\Framework\TestCase
{
    use EntityTrait;

    /** @var ShoppingListManager */
    private $manager;

    /** @var TokenAccessorInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $tokenAccessor;

    /** @var WebsiteManager|\PHPUnit\Framework\MockObject\MockObject */
    private $websiteManager;

    /** @var ShoppingListTotalManager|\PHPUnit\Framework\MockObject\MockObject */
    private $totalManager;

    /** @var ProductVariantAvailabilityProvider|\PHPUnit\Framework\MockObject\MockObject */
    private $productVariantProvider;

    /** @var EntityManager|\PHPUnit\Framework\MockObject\MockObject */
    private $em;

    /** @var LineItemRepository|\PHPUnit\Framework\MockObject\MockObject */
    private $lineItemRepository;

    /** @var \PHPUnit\Framework\MockObject\MockObject */
    private $productMatrixAvailabilityProvider;

    /** @var \PHPUnit\Framework\MockObject\MockObject */
    private $configManager;

    protected function setUp()
    {
        $this->lineItemRepository = $this->createMock(LineItemRepository::class);
        $this->lineItemRepository
            ->expects($this->any())
            ->method('findDuplicate')
            ->willReturnCallback(function (LineItem $lineItem) {
                /** @var ArrayCollection $shoppingListLineItems */
                $shoppingListLineItems = $lineItem->getShoppingList()->getLineItems();
                if ($lineItem->getShoppingList()->getId() === 1
                    && $shoppingListLineItems->count() > 0
                    && $shoppingListLineItems->current()->getUnit() === $lineItem->getUnit()
                ) {
                    return $lineItem->getShoppingList()->getLineItems()->current();
                }

                return null;
            });

        $this->em = $this->createMock(EntityManager::class);
        $this->em->expects($this->any())
            ->method('getRepository')
            ->with(LineItem::class)
            ->willReturn($this->lineItemRepository);

        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->expects($this->any())
            ->method('getManagerForClass')
            ->willReturn($this->em);

        $this->tokenAccessor = $this->createMock(TokenAccessorInterface::class);
        $this->websiteManager = $this->createMock(WebsiteManager::class);
        $this->totalManager = $this->createMock(ShoppingListTotalManager::class);
        $this->productVariantProvider = $this->createMock(ProductVariantAvailabilityProvider::class);
        $translator = $this->createMock(TranslatorInterface::class);
        $roundingService = $this->createMock(QuantityRoundingService::class);
        $roundingService->expects($this->any())
            ->method('roundQuantity')
            ->willReturnCallback(function ($value) {
                return round($value);
            });

        $this->productMatrixAvailabilityProvider = $this->createMock(ProductMatrixAvailabilityProvider::class);
        $this->configManager = $this->createMock(ConfigManager::class);

        $this->manager = new ShoppingListManager(
            $doctrine,
            $this->tokenAccessor,
            $translator,
            $roundingService,
            $this->websiteManager,
            $this->totalManager,
            $this->productVariantProvider,
            $this->productMatrixAvailabilityProvider,
            $this->configManager
        );
    }

    /**
     * @param int  $id
     * @param bool $isCurrent
     *
     * @return ShoppingList
     */
    private function getShoppingList($id, $isCurrent = false)
    {
        return $this->getEntity(ShoppingList::class, ['id' => $id, 'current' => $isCurrent]);
    }

    /**
     * @param int $id
     *
     * @return LineItem
     */
    private function getLineItem($id)
    {
        return $this->getEntity(LineItem::class, ['id' => $id]);
    }

    /**
     * @param int         $id
     * @param string|null $type
     *
     * @return Product
     */
    private function getProduct($id, $type = null)
    {
        $properties = ['id' => $id];
        if ($type) {
            $properties['type'] = $type;
        }

        return $this->getEntity(Product::class, $properties);
    }

    /**
     * @param string $code
     * @param int    $defaultPrecision
     *
     * @return ProductUnit
     */
    private function getProductUnit($code, $defaultPrecision)
    {
        $productUnit = new ProductUnit();
        $productUnit->setCode($code);
        $productUnit->setDefaultPrecision($defaultPrecision);

        return $productUnit;
    }

    public function testCreate()
    {
        $customerUser = new CustomerUser();
        $customerUser->setCustomer(new Customer());
        $customerUser->setOrganization(new Organization());
        $this->tokenAccessor->expects($this->once())
            ->method('getUser')
            ->willReturn($customerUser);

        $website = $this->createMock(Website::class);
        $this->websiteManager->expects($this->once())
            ->method('getCurrentWebsite')
            ->willReturn($website);

        $this->em->expects($this->never())
            ->method('persist');
        $this->em->expects($this->never())
            ->method('flush');

        $shoppingList = $this->manager->create();

        $this->assertSame($customerUser, $shoppingList->getCustomerUser());
        $this->assertSame($customerUser->getCustomer(), $shoppingList->getCustomer());
        $this->assertSame($customerUser->getOrganization(), $shoppingList->getOrganization());
        $this->assertSame($website, $shoppingList->getWebsite());
    }

    public function testCreateWithFlushAndLabel()
    {
        $label = 'test label';

        $customerUser = new CustomerUser();
        $customerUser->setCustomer(new Customer());
        $customerUser->setOrganization(new Organization());
        $this->tokenAccessor->expects($this->once())
            ->method('getUser')
            ->willReturn($customerUser);

        $website = $this->createMock(Website::class);
        $this->websiteManager->expects($this->once())
            ->method('getCurrentWebsite')
            ->willReturn($website);

        $this->em->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(ShoppingList::class));
        $this->em->expects($this->once())
            ->method('flush');

        $shoppingList = $this->manager->create(true, $label);

        $this->assertEquals($label, $shoppingList->getLabel());
        $this->assertSame($customerUser, $shoppingList->getCustomerUser());
        $this->assertSame($customerUser->getCustomer(), $shoppingList->getCustomer());
        $this->assertSame($customerUser->getOrganization(), $shoppingList->getOrganization());
        $this->assertSame($website, $shoppingList->getWebsite());
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage The customer user does not exist in the security context.
     */
    public function testCreateWhenNoCustomerUser()
    {
        $this->tokenAccessor->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        $this->manager->create();
    }

    public function testAddLineItem()
    {
        $shoppingList = new ShoppingList();
        $lineItem = new LineItem();

        $this->manager->addLineItem($lineItem, $shoppingList);
        $this->assertCount(1, $shoppingList->getLineItems());
        $this->assertNull($lineItem->getCustomerUser());
        $this->assertNull($lineItem->getOrganization());
    }

    public function testAddLineItemWithShoppingListData()
    {
        $shoppingList = new ShoppingList();
        $shoppingList->setCustomerUser(new CustomerUser());
        $shoppingList->setOrganization(new Organization());
        $lineItem = new LineItem();

        $this->manager->addLineItem($lineItem, $shoppingList);
        $this->assertCount(1, $shoppingList->getLineItems());
        $this->assertSame($shoppingList->getCustomerUser(), $lineItem->getCustomerUser());
        $this->assertSame($shoppingList->getOrganization(), $lineItem->getOrganization());
    }

    public function testAddLineItemDuplicate()
    {
        $persistedLineItems = [];
        $this->em->expects($this->any())
            ->method('persist')
            ->willReturnCallback(function ($obj) use (&$persistedLineItems) {
                if ($obj instanceof LineItem) {
                    $persistedLineItems[] = $obj;
                }
            });

        $shoppingList = $this->getShoppingList(1);

        $lineItem = (new LineItem())
            ->setUnit($this->getProductUnit('test', 1))
            ->setQuantity(10);

        $this->manager->addLineItem($lineItem, $shoppingList);
        $this->assertCount(1, $shoppingList->getLineItems());
        $this->assertCount(1, $persistedLineItems);
        $lineItemDuplicate = clone $lineItem;
        $lineItemDuplicate->setQuantity(5);
        $this->manager->addLineItem($lineItemDuplicate, $shoppingList);
        $this->assertCount(1, $shoppingList->getLineItems());
        /** @var LineItem $resultingItem */
        $resultingItem = array_shift($persistedLineItems);
        $this->assertEquals(15, $resultingItem->getQuantity());
    }

    public function testAddLineItemDuplicateAndConcatNotes()
    {
        $persistedLineItems = [];
        $this->em->expects($this->any())
            ->method('persist')
            ->willReturnCallback(function ($obj) use (&$persistedLineItems) {
                if ($obj instanceof LineItem) {
                    $persistedLineItems[] = $obj;
                }
            });

        $shoppingList = $this->getShoppingList(1);

        $lineItem = (new LineItem())
            ->setUnit($this->getProductUnit('test', 1))
            ->setNotes('Notes');

        $this->manager->addLineItem($lineItem, $shoppingList);

        $lineItemDuplicate = clone $lineItem;
        $lineItemDuplicate->setNotes('Duplicated Notes');

        $this->manager->addLineItem($lineItemDuplicate, $shoppingList, true, true);

        $this->assertCount(1, $shoppingList->getLineItems());

        /** @var LineItem $resultingItem */
        $resultingItem = array_shift($persistedLineItems);
        $this->assertSame('Notes Duplicated Notes', $resultingItem->getNotes());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Can not save not simple product
     */
    public function testAddLineItemNotAllowedProductType()
    {
        $shoppingList = new ShoppingList();
        $lineItem = new LineItem();
        $configurableProduct = new Product();
        $configurableProduct->setType(Product::TYPE_CONFIGURABLE);
        $lineItem->setProduct($configurableProduct);

        $this->manager->addLineItem($lineItem, $shoppingList);
    }

    public function testGetLineItemExistingItem()
    {
        $shoppingList = new ShoppingList();
        $lineItem = $this->getLineItem(1);
        $lineItem->setNotes('123');
        $this->manager->addLineItem($lineItem, $shoppingList);
        $returnedLineItem = $this->manager->getLineItem(1, $shoppingList);
        $this->assertEquals($returnedLineItem->getNotes(), $lineItem->getNotes());
    }

    public function testGetLineItemNotExistingItem()
    {
        $shoppingList = new ShoppingList();
        $returnedLineItem = $this->manager->getLineItem(1, $shoppingList);
        $this->assertNull($returnedLineItem);
    }

    /**
     * @dataProvider removeProductDataProvider
     *
     * @param array $lineItems
     * @param array $relatedLineItems
     * @param bool $flush
     * @param bool $expectedFlush
     */
    public function testRemoveProduct(array $lineItems, array $relatedLineItems, $flush, $expectedFlush)
    {
        $shoppingList = $this->getShoppingList(1, true);
        foreach ($lineItems as $lineItem) {
            $shoppingList->addLineItem($lineItem);
        }

        $product = $this->getProduct(42);

        $removedLineItems = [];
        $this->em->expects($this->exactly(count($relatedLineItems)))
            ->method('remove')
            ->willReturnCallback(function (LineItem $item) use (&$removedLineItems) {
                $removedLineItems[] = $item;
            });
        $this->em->expects($expectedFlush ? $this->once() : $this->never())
            ->method('flush');

        $this->lineItemRepository->expects($this->once())
            ->method('getItemsByShoppingListAndProducts')
            ->with($shoppingList, [$product])
            ->willReturn($relatedLineItems);

        $result = $this->manager->removeProduct($shoppingList, $product, $flush);

        $this->assertEquals(count($relatedLineItems), $result);

        foreach ($relatedLineItems as $lineItem) {
            $this->assertContains($lineItem, $removedLineItems);
            $this->assertNotContains($lineItem, $shoppingList->getLineItems());
        }

        $this->assertCount(
            count($lineItems) - count($relatedLineItems),
            $shoppingList->getLineItems()
        );
    }

    /**
     * @return array
     */
    public function removeProductDataProvider()
    {
        $lineItem1 = $this->getLineItem(35);
        $lineItem2 = $this->getLineItem(36);
        $lineItem3 = $this->getLineItem(37);

        return [
            [
                'lineItems' => [$lineItem1, $lineItem2, $lineItem3],
                'relatedLineItems' => [$lineItem1, $lineItem3],
                'flush' => true,
                'expectedFlush' => true
            ],
            [
                'lineItems' => [$lineItem1, $lineItem2, $lineItem3],
                'relatedLineItems' => [],
                'flush' => true,
                'expectedFlush' => false
            ],
            [
                'lineItems' => [$lineItem1, $lineItem2, $lineItem3],
                'relatedLineItems' => [$lineItem2],
                'flush' => false,
                'expectedFlush' => false
            ]
        ];
    }

    /**
     * @param array           $simpleProducts
     * @param ArrayCollection $lineItems
     *
     * @dataProvider getSimpleProductsProvider
     */
    public function testRemoveConfigurableProduct($simpleProducts, ArrayCollection $lineItems)
    {
        $product = $this->getProduct(43, Product::TYPE_CONFIGURABLE);
        $shoppingList = $this->getShoppingList(1);
        foreach ($lineItems as $item) {
            $shoppingList->addLineItem($item);
        }

        $this->productVariantProvider->expects($this->once())
            ->method('getSimpleProductsByVariantFields')
            ->with($product)
            ->willReturn($simpleProducts);

        $this->em->expects($this->exactly(count($lineItems)))
            ->method('remove');

        $products = $simpleProducts;
        $products[] = $product;

        $this->lineItemRepository->expects($this->once())
            ->method('getItemsByShoppingListAndProducts')
            ->with($shoppingList, $products)
            ->willReturn($lineItems);

        $result = $this->manager->removeProduct($shoppingList, $product, true);
        $this->assertEquals(count($lineItems), $result);
        $this->assertTrue($shoppingList->getLineItems()->isEmpty());
    }

    /**
     * @return array
     */
    public function getSimpleProductsProvider()
    {
        return [
            [
                [],
                new ArrayCollection()
            ],
            [
                [
                    $this->getProduct(44, Product::TYPE_SIMPLE),
                    $this->getProduct(45, Product::TYPE_SIMPLE),
                    $this->getProduct(46, Product::TYPE_SIMPLE)
                ],
                new ArrayCollection([
                    $this->getLineItem(38),
                    $this->getLineItem(39),
                    $this->getLineItem(40)
                ])
            ]
        ];
    }

    public function testBulkAddLineItems()
    {
        $shoppingList = new ShoppingList();
        $lineItems = [];
        for ($i = 0; $i < 10; $i++) {
            $lineItems[] = new LineItem();
        }

        $this->manager->bulkAddLineItems($lineItems, $shoppingList, 10);
        $this->assertCount(10, $shoppingList->getLineItems());
    }

    public function testBulkAddLineItemsWithEmptyLineItems()
    {
        $this->assertEquals(0, $this->manager->bulkAddLineItems([], new ShoppingList(), 10));
    }

    public function testEdit()
    {
        $label = 'test label';

        $customerUser = new CustomerUser();
        $customerUser->setCustomer(new Customer());
        $customerUser->setOrganization(new Organization());
        $this->tokenAccessor->expects($this->once())
            ->method('getUser')
            ->willReturn($customerUser);

        $website = $this->createMock(Website::class);
        $this->websiteManager->expects($this->once())
            ->method('getCurrentWebsite')
            ->willReturn($website);

        $shoppingList = new ShoppingList();

        $this->assertSame($shoppingList, $this->manager->edit($shoppingList, $label));
        $this->assertEquals($label, $shoppingList->getLabel());
        $this->assertSame($customerUser, $shoppingList->getCustomerUser());
        $this->assertSame($customerUser->getCustomer(), $shoppingList->getCustomer());
        $this->assertSame($customerUser->getOrganization(), $shoppingList->getOrganization());
        $this->assertSame($website, $shoppingList->getWebsite());
    }

    public function testRemoveLineItems()
    {
        $shoppingList = new ShoppingList();
        $lineItem1 = new LineItem();
        $this->manager->addLineItem($lineItem1, $shoppingList);
        $lineItem2 = new LineItem();
        $this->manager->addLineItem($lineItem2, $shoppingList);
        $this->assertCount(2, $shoppingList->getLineItems());

        $this->totalManager->expects($this->once())
            ->method('recalculateTotals')
            ->with($shoppingList, false);

        $this->manager->removeLineItems($shoppingList);
        $this->assertCount(0, $shoppingList->getLineItems());
    }

    public function testUpdateLineItem()
    {
        $lineItem = (new LineItem())
            ->setUnit($this->getProductUnit('test', 1))
            ->setQuantity(10);

        $shoppingList = $this->getShoppingList(1);
        $shoppingList->addLineItem($lineItem);

        $lineItemDuplicate = clone $lineItem;
        $lineItemDuplicate->setQuantity(5);
        $this->manager->updateLineItem($lineItemDuplicate, $shoppingList);

        $this->assertCount(1, $shoppingList->getLineItems());
        /** @var LineItem $resultingItem */
        $resultingItem = $shoppingList->getLineItems()->first();
        $this->assertEquals(5, $resultingItem->getQuantity());
    }

    public function testUpdateAndRemoveLineItem()
    {
        $lineItem = (new LineItem())
            ->setUnit($this->getProductUnit('test', 1))
            ->setQuantity(10);

        $shoppingList = $this->getShoppingList(1);
        $shoppingList->addLineItem($lineItem);

        $lineItemDuplicate = clone $lineItem;
        $lineItemDuplicate->setQuantity(0);
        $this->manager->updateLineItem($lineItemDuplicate, $shoppingList);

        $this->assertEmpty($shoppingList->getLineItems());
    }

    public function testRemoveLineItemWithSimpleProductsInItems()
    {
        $lineItem = (new LineItem())
            ->setUnit($this->getProductUnit('test', 1))
            ->setQuantity(10);
        $lineItem1 = (new LineItem())
            ->setUnit($this->getProductUnit('test1', 1))
            ->setQuantity(2);

        $shoppingList = $this->getShoppingList(1);
        $shoppingList->addLineItem($lineItem);
        $shoppingList->addLineItem($lineItem1);

        $countDeletedItems = $this->manager->removeLineItem($lineItem);

        $this->assertEquals(1, $countDeletedItems);

        $lineItems = $shoppingList->getLineItems();

        $this->assertCount(1, $lineItems);
        $this->assertTrue($lineItems->contains($lineItem1));
        $this->assertFalse($lineItems->contains($lineItem));
    }

    public function testRemoveLineItemWithConfigurableProductsAndMatrixMatrixType()
    {
        $productUnitPrecision = new ProductUnitPrecision();
        $productUnitPrecision->setUnit($this->getProductUnit('test', 1));

        $product = $this->getProduct(5, Product::TYPE_CONFIGURABLE);
        $product->setPrimaryUnitPrecision($productUnitPrecision);

        $simpleProduct1 = $this->getProduct(6, Product::TYPE_SIMPLE);
        $simpleProduct2 = $this->getProduct(7, Product::TYPE_SIMPLE);
        $simpleProduct3 = $this->getProduct(8, Product::TYPE_SIMPLE);

        $lineItem3 = new LineItem();
        $lineItem3->setProduct($simpleProduct3);
        $lineItem3->setParentProduct($product);
        $lineItem3->setUnit($this->getProductUnit('test', 1));

        $lineItem1 = new LineItem();
        $lineItem1->setProduct($simpleProduct1);
        $lineItem1->setParentProduct($product);
        $lineItem1->setUnit($this->getProductUnit('test', 1));

        $lineItem2 = new LineItem();
        $lineItem2->setProduct($simpleProduct2);
        $lineItem2->setParentProduct($product);
        $lineItem2->setUnit($this->getProductUnit('test', 1));

        $shoppingList = $this->getShoppingList(1);
        $shoppingList->addLineItem($lineItem1);
        $shoppingList->addLineItem($lineItem2);
        $shoppingList->addLineItem($lineItem3);

        $this->lineItemRepository->expects($this->once())
            ->method('getItemsByShoppingListAndProducts')
            ->with($shoppingList, [$product])
            ->willReturn([$lineItem1, $lineItem2, $lineItem3]);

        $this->configManager->expects($this->once())
            ->method('get')
            ->with('oro_product.matrix_form_on_shopping_list')
            ->willReturn(Configuration::MATRIX_FORM_INLINE);
        $this->productMatrixAvailabilityProvider->expects($this->once())
            ->method('isMatrixFormAvailable')
            ->with($product)
            ->willReturn(true);

        $countDeletedItems = $this->manager->removeLineItem($lineItem1);

        $this->assertEquals(3, $countDeletedItems);
        $this->assertEmpty($shoppingList->getLineItems());
    }

    public function testRemoveLineItemWithConfigurableProductsAndNoneMatrixType()
    {
        $productUnitPrecision = new ProductUnitPrecision();
        $productUnitPrecision->setUnit($this->getProductUnit('test', 1));

        $product = $this->getProduct(43, Product::TYPE_CONFIGURABLE);
        $product->setPrimaryUnitPrecision($productUnitPrecision);

        $simpleProduct1 = $this->getProduct(44, Product::TYPE_SIMPLE);
        $simpleProduct2 = $this->getProduct(45, Product::TYPE_SIMPLE);
        $simpleProduct3 = $this->getProduct(46, Product::TYPE_SIMPLE);

        $lineItem1 = new LineItem();
        $lineItem1->setProduct($simpleProduct1);
        $lineItem1->setParentProduct($product);
        $lineItem1->setUnit($this->getProductUnit('test', 1));

        $lineItem3 = new LineItem();
        $lineItem3->setProduct($simpleProduct3);
        $lineItem3->setParentProduct($product);
        $lineItem3->setUnit($this->getProductUnit('test', 1));

        $lineItem2 = new LineItem();
        $lineItem2->setProduct($simpleProduct2);
        $lineItem2->setParentProduct($product);
        $lineItem2->setUnit($this->getProductUnit('test', 1));

        $shoppingList = $this->getShoppingList(1);
        $shoppingList->addLineItem($lineItem1);
        $shoppingList->addLineItem($lineItem2);
        $shoppingList->addLineItem($lineItem3);

        $this->configManager->expects($this->once())
            ->method('get')
            ->with('oro_product.matrix_form_on_shopping_list')
            ->willReturn(Configuration::MATRIX_FORM_NONE);

        $countDeletedItems = $this->manager->removeLineItem($lineItem1);

        $this->assertEquals(1, $countDeletedItems);

        $resultLineItems = $shoppingList->getLineItems();
        $this->assertCount(2, $resultLineItems);
        $this->assertFalse($resultLineItems->contains($lineItem1));
        $this->assertTrue($resultLineItems->contains($lineItem2));
        $this->assertTrue($resultLineItems->contains($lineItem3));
    }

    public function testRemoveLineItemWithConfigurableProductsAndWithFlagToDeleteOnlyCurrentItem()
    {
        $productUnitPrecision = new ProductUnitPrecision();
        $productUnitPrecision->setUnit($this->getProductUnit('test', 1));

        $product = $this->getProduct(10, Product::TYPE_CONFIGURABLE);
        $product->setPrimaryUnitPrecision($productUnitPrecision);

        $simpleProduct1 = $this->getProduct(11, Product::TYPE_SIMPLE);
        $simpleProduct2 = $this->getProduct(12, Product::TYPE_SIMPLE);
        $simpleProduct3 = $this->getProduct(13, Product::TYPE_SIMPLE);

        $lineItem1 = new LineItem();
        $lineItem1->setProduct($simpleProduct1);
        $lineItem1->setParentProduct($product);
        $lineItem1->setUnit($this->getProductUnit('test', 1));

        $lineItem2 = new LineItem();
        $lineItem2->setProduct($simpleProduct2);
        $lineItem2->setParentProduct($product);
        $lineItem2->setUnit($this->getProductUnit('test', 1));

        $lineItem3 = new LineItem();
        $lineItem3->setProduct($simpleProduct3);
        $lineItem3->setParentProduct($product);
        $lineItem3->setUnit($this->getProductUnit('test', 1));

        $shoppingList = $this->getShoppingList(1);
        $shoppingList->addLineItem($lineItem1);
        $shoppingList->addLineItem($lineItem2);
        $shoppingList->addLineItem($lineItem3);

        $countDeletedItems = $this->manager->removeLineItem($lineItem1, true);

        $this->assertEquals(1, $countDeletedItems);

        $resultItems = $shoppingList->getLineItems();
        $this->assertCount(2, $resultItems);
        $this->assertFalse($resultItems->contains($lineItem1));
        $this->assertTrue($resultItems->contains($lineItem2));
        $this->assertTrue($resultItems->contains($lineItem3));
    }
}
