<?php

namespace Oro\Bundle\ProductBundle\Layout\DataProvider;

use Doctrine\ORM\Query\Parameter;
use Doctrine\ORM\QueryBuilder;
use Oro\Bundle\ConfigBundle\Config\ConfigManager;
use Oro\Bundle\ProductBundle\Entity\Manager\ProductManager;
use Oro\Bundle\ProductBundle\Provider\ProductsProviderInterface;
use Oro\Bundle\ProductBundle\Provider\Segment\ProductSegmentProviderInterface;
use Oro\Bundle\SecurityBundle\Encoder\SymmetricCrypterInterface;
use Oro\Bundle\SegmentBundle\Entity\Manager\SegmentManager;
use Oro\Bundle\SegmentBundle\Entity\Segment;
use Oro\Component\Cache\Layout\DataProviderCacheTrait;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Responsible for saving, processing and return the results of segment from the cache and
 * checking the integrity of the data coming.
 * Ensures that the cached data is not used in case of substitution by a third party
 */
abstract class AbstractSegmentProductsProvider implements ProductsProviderInterface
{
    const DQL = 'dql';
    const PARAMETERS = 'parameters';
    const HASH = 'hash';

    use DataProviderCacheTrait {
        getFromCache as loadFromCache;
    }

    /** @var SegmentManager */
    private $segmentManager;

    /** @var ProductSegmentProviderInterface */
    private $productSegmentProvider;

    /** @var ProductManager */
    private $productManager;

    /** @var ConfigManager */
    private $configManager;

    /** @var RegistryInterface */
    private $registry;

    /** @var TokenStorageInterface */
    private $tokenStorage;

    /** @var SymmetricCrypterInterface */
    private $crypter;

    /**
     * @param SegmentManager $segmentManager
     * @param ProductSegmentProviderInterface $productSegmentProvider
     * @param ProductManager $productManager
     * @param ConfigManager $configManager
     * @param RegistryInterface $registry
     * @param TokenStorageInterface $tokenStorage
     * @param SymmetricCrypterInterface $crypter
     */
    public function __construct(
        SegmentManager $segmentManager,
        ProductSegmentProviderInterface $productSegmentProvider,
        ProductManager $productManager,
        ConfigManager $configManager,
        RegistryInterface $registry,
        TokenStorageInterface $tokenStorage,
        SymmetricCrypterInterface $crypter
    ) {
        $this->segmentManager = $segmentManager;
        $this->productSegmentProvider = $productSegmentProvider;
        $this->productManager = $productManager;
        $this->configManager = $configManager;
        $this->registry = $registry;
        $this->tokenStorage = $tokenStorage;
        $this->crypter = $crypter;
    }

    /**
     * @return int
     */
    abstract protected function getSegmentId();

    /**
     * @param Segment $segment
     *
     * @return array
     */
    abstract protected function getCacheParts(Segment $segment);

    /**
     * @param Segment $segment
     *
     * @return QueryBuilder|null
     */
    abstract protected function getQueryBuilder(Segment $segment);

    /**
     * {@inheritDoc}
     */
    public function getProducts()
    {
        if (!$this->getSegmentId()) {
            return [];
        }

        $segment = $this->productSegmentProvider->getProductSegmentById($this->getSegmentId());
        if ($segment) {
            $this->initCache($this->getCacheParts($segment));
            $useCache = $this->isCacheUsed();

            if (true === $useCache) {
                $data = $this->getFromCache();
                if ($data) {
                    return $this->getResult($data);
                }
            }

            $qb = $this->getQueryBuilder($segment);
            if ($qb) {
                $data = $this->getCachedData($qb->getDQL(), $qb->getParameters()->toArray());
                if (true === $useCache) {
                    $this->saveToCache($data);
                }

                return $this->getResult($data);
            }
        }

        return [];
    }

    /**
     * @return false|array
     */
    private function getFromCache()
    {
        $data = $this->loadFromCache();

        if (is_array($data) && $this->checkCacheDataConsistency($data)) {
            return $data;
        }

        return false;
    }

    /**
     * @return RegistryInterface
     */
    public function getRegistry()
    {
        return $this->registry;
    }

    /**
     * @return ProductManager
     */
    protected function getProductManager()
    {
        return $this->productManager;
    }

    /**
     * @return SegmentManager
     */
    protected function getSegmentManager()
    {
        return $this->segmentManager;
    }

    /**
     * @return TokenStorageInterface
     */
    protected function getTokenStorage()
    {
        return $this->tokenStorage;
    }

    /**
     * @return ConfigManager
     */
    protected function getConfigManager()
    {
        return $this->configManager;
    }

    /**
     * @param array $data
     * @return \Doctrine\ORM\Query
     */
    protected function restoreQuery(array $data)
    {
        return $this->getRegistry()
            ->getEntityManager()
            ->createQuery($data[self::DQL]);
    }

    /**
     * @param array $data
     *
     * @return array
     */
    protected function getResult(array $data)
    {
        return $this->restoreQuery($data)->execute($data[self::PARAMETERS]);
    }

    /**
     * @param string $dql
     * @param Parameter[] $parameters
     *
     * @return array
     */
    private function getCachedData($dql, array $parameters)
    {
        /** @var Parameter $parameter */
        $resultParameters = [];
        foreach ($parameters as $parameter) {
            $resultParameters[$parameter->getName()] = $parameter->getValue();
        }

        $result = [
            self::DQL => $dql,
            self::PARAMETERS => $resultParameters,
            self::HASH => $this->getEncryptedData($dql, $resultParameters),
        ];

        return $result;
    }

    /**
     * @param array $data
     *
     * @return bool
     */
    private function checkCacheDataConsistency(array $data): bool
    {
        if (!empty($data[self::DQL]) &&
            !empty($data[self::HASH]) &&
            !empty($data[self::PARAMETERS])
        ) {
            $hash = $this->getDecryptedData($data[self::HASH]);

            return $hash === $this->getHashData($data[self::DQL], $data[self::PARAMETERS]);
        }

        return false;
    }

    /**
     * @param string $dql
     * @param array $parameters
     *
     * @return string|null
     */
    private function getEncryptedData(string $dql, array $parameters = []): ?string
    {
        $data = $this->getHashData($dql, $parameters);

        return $this->getEncryptedHash($data);
    }

    /**
     * @param $hash
     *
     * @return string|null
     */
    private function getDecryptedData(string $hash): ?string
    {
        return $this->crypter->decryptData($hash);
    }

    /**
     * @param string $dql
     * @param array $parameters
     *
     * @return string
     */
    private function getHashData(string $dql, array $parameters = []): string
    {
        return md5(serialize([
            self::DQL => $dql,
            self::PARAMETERS => $parameters,
        ]));
    }

    /**
     * @param string $data
     *
     * @return string|null
     */
    private function getEncryptedHash(string $data): ?string
    {
        return $this->crypter->encryptData($data);
    }
}
