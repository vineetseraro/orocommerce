<?php

namespace Oro\Bundle\ProductBundle\Tests\Unit\Form\Type;

use Doctrine\ORM\EntityManagerInterface;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\EntityBundle\Provider\EntityProvider;
use Oro\Bundle\ProductBundle\ContentVariantType\ProductCollectionContentVariantType;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\Form\Type\ProductCollectionVariantType;
use Oro\Bundle\QueryDesignerBundle\QueryDesigner\Manager;
use Oro\Bundle\SegmentBundle\Form\Type\SegmentFilterBuilderType;
use Oro\Component\Testing\Unit\FormIntegrationTestCase;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class ProductCollectionVariantTypeTest extends FormIntegrationTestCase
{
    /**
     * @var EntityProvider|\PHPUnit_Framework_MockObject_MockObject
     */
    private $entityProvider;

    /**
     * @var Manager|\PHPUnit_Framework_MockObject_MockObject
     */
    private $queryDesignerManager;

    /**
     * @var DoctrineHelper|\PHPUnit_Framework_MockObject_MockObject
     */
    private $doctrineHelper;

    /**
     * @var TokenStorageInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $tokenStorage;

    /**
     * @var ProductCollectionVariantType
     */
    protected $type;

    /**
     * {@inheritDoc}
     */
    protected function setUp()
    {
        $this->entityProvider = $this->createMock(EntityProvider::class);
        $this->queryDesignerManager = $this->createMock(Manager::class);
        $this->doctrineHelper = $this->createMock(DoctrineHelper::class);
        $this->tokenStorage = $this->createMock(TokenStorageInterface::class);

        parent::setUp();
        $this->type = new ProductCollectionVariantType();
    }

    /**
     * @return array
     */
    protected function getExtensions()
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $this->doctrineHelper->expects($this->any())
            ->method('getEntityManagerForClass')
            ->with(Product::class, false)
            ->willReturn($em);

        $segmentFilterBuilderType = new SegmentFilterBuilderType(
            $this->entityProvider,
            $this->queryDesignerManager,
            $this->doctrineHelper,
            $this->tokenStorage
        );

        return [
            new PreloadedExtension(
                [
                    SegmentFilterBuilderType::NAME => $segmentFilterBuilderType
                ],
                []
            ),
            $this->getValidatorExtension(true)
        ];
    }

    public function testBuildForm()
    {
        $form = $this->factory->create($this->type, null);
        $this->assertTrue($form->has('productCollectionSegment'));
        $this->assertEquals(
            ProductCollectionContentVariantType::TYPE,
            $form->getConfig()->getOption('content_variant_type')
        );
    }

    public function testGetName()
    {
        $this->assertEquals(ProductCollectionVariantType::NAME, $this->type->getName());
    }

    public function testGetBlockPrefix()
    {
        $this->assertEquals(ProductCollectionVariantType::NAME, $this->type->getBlockPrefix());
    }
}
