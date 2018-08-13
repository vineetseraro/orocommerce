<?php

namespace Oro\Bundle\ProductBundle\Tests\Functional\Model;

use Doctrine\ORM\QueryBuilder;
use Oro\Bundle\ProductBundle\Model\RestrictProductVariationsBuilderModifier;
use Oro\Bundle\ProductBundle\Tests\Functional\DataFixtures\LoadProductData;
use Oro\Bundle\ProductBundle\Tests\Functional\DataFixtures\LoadProductVariants;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;

class RestrictProductVariationsBuilderModifierTest extends WebTestCase
{
    /** @var RestrictProductVariationsBuilderModifier */
    protected $modifier;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->initClient([], $this->generateBasicAuthHeader());
        $this->client->useHashNavigation(true);

        $this->loadFixtures([LoadProductVariants::class]);

        $doctrineHelper = $this->getContainer()->get('oro_entity.doctrine_helper');
        $this->modifier = new RestrictProductVariationsBuilderModifier($doctrineHelper);
    }

    public function testModify()
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = $this->getContainer()->get('doctrine')
            ->getRepository('OroProductBundle:Product')
            ->createQueryBuilder('p')
            ->select('p.id')
            ->orderBy('p.id');

        $this->modifier->modify($queryBuilder);

        $actualResult = $queryBuilder->getQuery()->getScalarResult();

        $this->assertSame(
            [
                $this->getReference(LoadProductData::PRODUCT_4)->getId(),
                $this->getReference(LoadProductData::PRODUCT_5)->getId(),
                $this->getReference(LoadProductData::PRODUCT_6)->getId(),
                $this->getReference(LoadProductData::PRODUCT_7)->getId(),
                $this->getReference(LoadProductData::PRODUCT_8)->getId(),
                $this->getReference(LoadProductData::PRODUCT_9)->getId(),
            ],
            array_map('intval', array_column($actualResult, 'id'))
        );
    }
}
