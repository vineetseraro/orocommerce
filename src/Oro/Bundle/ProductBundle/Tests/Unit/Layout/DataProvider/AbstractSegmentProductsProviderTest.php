<?php

namespace Oro\Bundle\ProductBundle\Tests\Unit\Layout\DataProvider;

use Doctrine\Common\Cache\CacheProvider;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Parameter;
use Doctrine\ORM\QueryBuilder;
use Oro\Bundle\ConfigBundle\Config\ConfigManager;
use Oro\Bundle\ProductBundle\Entity\Manager\ProductManager;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\Layout\DataProvider\AbstractSegmentProductsProvider;
use Oro\Bundle\ProductBundle\Provider\Segment\ProductSegmentProviderInterface;
use Oro\Bundle\SecurityBundle\Encoder\SymmetricCrypterInterface;
use Oro\Bundle\SegmentBundle\Entity\Manager\SegmentManager;
use Oro\Bundle\SegmentBundle\Entity\Segment;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

abstract class AbstractSegmentProductsProviderTest extends \PHPUnit\Framework\TestCase
{
    /** @var SegmentManager|\PHPUnit\Framework\MockObject\MockObject */
    protected $segmentManager;

    /** @var ProductSegmentProviderInterface|\PHPUnit\Framework\MockObject\MockObject */
    protected $productSegmentProvider;

    /** @var ProductManager|\PHPUnit\Framework\MockObject\MockObject */
    protected $productManager;

    /** @var ConfigManager|\PHPUnit\Framework\MockObject\MockObject */
    protected $configManager;

    /** @var EntityManagerInterface|\PHPUnit\Framework\MockObject\MockObject */
    protected $em;

    /** @var TokenStorageInterface|\PHPUnit\Framework\MockObject\MockObject */
    protected $tokenStorage;

    /** @var CacheProvider|\PHPUnit\Framework\MockObject\MockObject */
    protected $cache;

    /** @var SymmetricCrypterInterface|\PHPUnit\Framework\MockObject\MockObject */
    protected $crypter;

    /** @var AbstractSegmentProductsProvider */
    protected $segmentProductsProvider;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->segmentManager = $this->createMock(SegmentManager::class);
        $this->productSegmentProvider = $this->createMock(ProductSegmentProviderInterface::class);
        $this->productManager = $this->createMock(ProductManager::class);
        $this->configManager = $this->createMock(ConfigManager::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $registry = $this->createMock(RegistryInterface::class);
        $registry->expects($this->any())
            ->method('getEntityManager')
            ->willReturn($this->em);
        $this->tokenStorage = $this->createMock(TokenStorageInterface::class);
        $this->cache = $this->createMock(CacheProvider::class);
        $this->crypter = $this->createMock(SymmetricCrypterInterface::class);

        $this->createSegmentProvider($registry);

        $this->segmentProductsProvider->setCache($this->cache, 3600);
    }

    /**
     * @param RegistryInterface $registry
     */
    abstract protected function createSegmentProvider(RegistryInterface $registry);

    /**
     * @return string
     */
    abstract protected function getCacheKey();

    /**
     * @param QueryBuilder|\PHPUnit\Framework\MockObject\MockObject $queryBuilder
     */
    protected function getProducts(QueryBuilder $queryBuilder)
    {
        $result = [new Product()];
        $dql = 'DQL SELECT';
        $qbParameters = new ArrayCollection([new Parameter('parameter', 1)]);
        $hash = $this->getHashData($dql, ['parameter' => 1]);

        $segment = new Segment();
        $this->productSegmentProvider
            ->expects($this->once())
            ->method('getProductSegmentById')
            ->with(1)
            ->willReturn($segment);

        $this->cache
            ->expects($this->at(0))
            ->method('fetch')
            ->with($this->getCacheKey())
            ->willReturn(null);

        $queryBuilder->expects($this->once())
            ->method('getDQL')
            ->willReturn($dql);

        $queryBuilder->expects($this->once())
            ->method('getParameters')
            ->willReturn($qbParameters);

        $this->cache
            ->expects($this->at(1))
            ->method('fetch')
            ->with('_keyBunch');

        $this->cache
            ->expects($this->at(2))
            ->method('save')
            ->with('_keyBunch', sprintf('["%s"]', $this->getCacheKey()));

        $this->cache
            ->expects($this->at(3))
            ->method('save')
            ->with(
                $this->getCacheKey(),
                [
                    'dql' => $dql,
                    'parameters' => ['parameter' => 1],
                    'hash' => sprintf('encrypt_%s', $hash),
                ],
                3600
            );

        $this->crypter->expects($this->any())
            ->method('encryptData')
            ->with($hash)
            ->willReturn(sprintf('encrypt_%s', $hash));

        /** @var Query|\PHPUnit\Framework\MockObject\MockObject $query */
        $query = $this->createMock(AbstractQuery::class);
        $this->em
            ->expects($this->once())
            ->method('createQuery')
            ->with($dql)
            ->willReturn($query);

        $query->expects($this->once())
            ->method('execute')
            ->with(['parameter' => 1])
            ->willReturn($result);

        $this->assertEquals($result, $this->segmentProductsProvider->getProducts());
    }

    protected function getProductsWithCache()
    {
        $result = [new Product()];
        $dql = 'DQL SELECT';
        $hash = $this->getHashData($dql, ['parameter' => 1]);

        $segment = new Segment();
        $this->productSegmentProvider
            ->expects($this->once())
            ->method('getProductSegmentById')
            ->with(1)
            ->willReturn($segment);

        $this->cache
            ->expects($this->once())
            ->method('fetch')
            ->with($this->getCacheKey())
            ->willReturn(['dql' => $dql, 'parameters' => ['parameter' => 1], 'hash' =>  sprintf('encrypt_%s', $hash)]);

        $this->crypter->expects($this->any())
            ->method('encryptData')
            ->with($dql)
            ->willReturn($hash);

        $this->crypter->expects($this->any())
            ->method('decryptData')
            ->with(sprintf('encrypt_%s', $hash))
            ->willReturn($hash);

        /** @var Query|\PHPUnit\Framework\MockObject\MockObject $query */
        $query = $this->createMock(AbstractQuery::class);
        $this->em
            ->expects($this->once())
            ->method('createQuery')
            ->with($dql)
            ->willReturn($query);

        $query->expects($this->once())
            ->method('execute')
            ->with(['parameter' => 1])
            ->willReturn($result);

        $this->assertEquals($result, $this->segmentProductsProvider->getProducts());
    }

    protected function getProductsWithInvalidCache(QueryBuilder $queryBuilder)
    {
        $result = [new Product()];
        $dql = 'DQL SELECT';
        $invalidDql = 'INVALID DQL SELECT';
        $qbParameters = new ArrayCollection([new Parameter('parameter', 1)]);
        $hash = $this->getHashData($dql, ['parameter' => 1]);

        $segment = new Segment();
        $this->productSegmentProvider
            ->expects($this->once())
            ->method('getProductSegmentById')
            ->with(1)
            ->willReturn($segment);

        $this->cache
            ->expects($this->exactly(2))
            ->method('fetch')
            ->willReturnOnConsecutiveCalls(
                ['dql' => $invalidDql, 'parameters' => ['parameter' => 1], 'hash' =>  sprintf('encrypt_%s', $hash)],
                false
            );

        $this->cache
            ->expects($this->at(2))
            ->method('save')
            ->with('_keyBunch', sprintf('["%s"]', $this->getCacheKey()));

        $this->cache
            ->expects($this->at(3))
            ->method('save')
            ->with(
                $this->getCacheKey(),
                [
                    'dql' => $dql,
                    'parameters' => ['parameter' => 1],
                    'hash' => sprintf('encrypt_%s', $hash),
                ],
                3600
            );

        $queryBuilder->expects($this->once())
            ->method('getDQL')
            ->willReturn($dql);

        $queryBuilder->expects($this->once())
            ->method('getParameters')
            ->willReturn($qbParameters);

        $this->crypter->expects($this->once())
            ->method('encryptData')
            ->with($hash)
            ->willReturn(sprintf('encrypt_%s', $hash));

        $this->crypter->expects($this->once())
            ->method('decryptData')
            ->with(sprintf('encrypt_%s', $hash))
            ->willReturn($hash);

        /** @var Query|\PHPUnit\Framework\MockObject\MockObject $query */
        $query = $this->createMock(AbstractQuery::class);
        $this->em
            ->expects($this->once())
            ->method('createQuery')
            ->with($dql)
            ->willReturn($query);

        $query->expects($this->once())
            ->method('execute')
            ->with(['parameter' => 1])
            ->willReturn($result);

        $this->assertEquals($result, $this->segmentProductsProvider->getProducts());
    }

    protected function getProductsWithBrokenCache()
    {
        $result = [new Product()];
        $dql = 'DQL SELECT';
        $hash = 'hash';

        $segment = new Segment();
        $this->productSegmentProvider
            ->expects($this->once())
            ->method('getProductSegmentById')
            ->with(1)
            ->willReturn($segment);

        $this->cache
            ->expects($this->once())
            ->method('fetch')
            ->with($this->getCacheKey())
            ->willReturn(['dql' => $dql, 'parameters' => ['parameter' => 1], 'hash' => md5('invalid')]);

        $this->crypter->expects($this->any())
            ->method('encryptData')
            ->with($dql)
            ->willReturn($hash);

        /** @var Query|\PHPUnit\Framework\MockObject\MockObject $query */
        $query = $this->createMock(AbstractQuery::class);
        $this->em
            ->expects($this->once())
            ->method('createQuery')
            ->with($dql)
            ->willReturn($query);

        $query->expects($this->once())
            ->method('execute')
            ->with(['parameter' => 1])
            ->willReturn($result);

        $this->assertEquals($result, $this->segmentProductsProvider->getProducts());
    }

    /**
     * @param QueryBuilder|\PHPUnit\Framework\MockObject\MockObject $queryBuilder
     */
    protected function getProductsWithDisabledCache(QueryBuilder $queryBuilder)
    {
        $result = [new Product()];
        $dql = 'DQL SELECT';

        $this->segmentProductsProvider->disableCache();

        $segment = new Segment();
        $this->productSegmentProvider
            ->expects($this->once())
            ->method('getProductSegmentById')
            ->with(1)
            ->willReturn($segment);

        $this->cache
            ->expects($this->never())
            ->method('fetch');

        $this->cache
            ->expects($this->never())
            ->method('save');

        $queryBuilder->expects($this->once())
            ->method('getDQL')
            ->willReturn($dql);

        $parameters = new ArrayCollection([new Parameter('parameter', 1)]);
        $queryBuilder->expects($this->once())
            ->method('getParameters')
            ->willReturn($parameters);

        /** @var Query|\PHPUnit\Framework\MockObject\MockObject $query */
        $query = $this->createMock(AbstractQuery::class);
        $this->em
            ->expects($this->once())
            ->method('createQuery')
            ->with($dql)
            ->willReturn($query);

        $query->expects($this->once())
            ->method('execute')
            ->with(['parameter' => 1])
            ->willReturn($result);

        $this->assertEquals($result, $this->segmentProductsProvider->getProducts());
    }

    protected function getProductsWithoutSegment()
    {
        $this->productSegmentProvider
            ->expects($this->once())
            ->method('getProductSegmentById')
            ->with(1)
            ->willReturn(null);

        $this->assertEquals([], $this->segmentProductsProvider->getProducts());
    }

    protected function getProductsQueryBuilderIsNull()
    {
        $segment = new Segment();
        $this->productSegmentProvider
            ->expects($this->once())
            ->method('getProductSegmentById')
            ->with(1)
            ->willReturn($segment);

        $this->cache
            ->expects($this->at(0))
            ->method('fetch')
            ->with($this->getCacheKey())
            ->willReturn(null);

        $this->cache
            ->expects($this->never())
            ->method('save');

        $this->em
            ->expects($this->never())
            ->method('createQuery');

        $this->assertEquals([], $this->segmentProductsProvider->getProducts());
    }

    /**
     * @return QueryBuilder|\PHPUnit\Framework\MockObject\MockObject
     */
    protected function getQueryBuilder()
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $this->segmentManager->expects($this->once())
            ->method('getEntityQueryBuilder')
            ->willReturn($queryBuilder);
        $this->productManager->expects($this->once())
            ->method('restrictQueryBuilder')
            ->with($queryBuilder, [])
            ->willReturn($queryBuilder);

        return $queryBuilder;
    }

    /**
     * @param $dql
     * @param $parameters
     *
     * @return string
     */
    private function getHashData($dql, $parameters): string
    {
        return md5(serialize([
            AbstractSegmentProductsProvider::DQL => $dql,
            AbstractSegmentProductsProvider::PARAMETERS => $parameters,
        ]));
    }
}
