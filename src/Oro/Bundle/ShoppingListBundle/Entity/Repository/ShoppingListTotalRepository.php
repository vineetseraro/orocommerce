<?php

namespace Oro\Bundle\ShoppingListBundle\Entity\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Oro\Bundle\CustomerBundle\Entity\CustomerVisitor;
use Oro\Component\DoctrineUtils\ORM\ResultSetMappingUtil;
use Oro\Component\DoctrineUtils\ORM\SqlQueryBuilder;

/**
 * Doctrine repository for Oro\Bundle\ShoppingListBundle\Entity\ShoppingListTotal entity
 */
class ShoppingListTotalRepository extends EntityRepository
{
    /**
     * Invalidate ShoppingList subtotals by given Combined Price List ids
     *
     * @param array $combinedPriceListIds
     */
    public function invalidateByCombinedPriceList(array $combinedPriceListIds)
    {
        if (!$combinedPriceListIds) {
            return;
        }

        $subQuery = $this->getEntityManager()->createQueryBuilder();
        $subQuery->select('1')
            ->from('OroShoppingListBundle:LineItem', 'lineItem')
            ->join(
                'OroPricingBundle:CombinedProductPrice',
                'productPrice',
                Join::WITH,
                $subQuery->expr()->eq('lineItem.product', 'productPrice.product')
            )
            ->where(
                $subQuery->expr()->eq('total.shoppingList', 'lineItem.shoppingList'),
                $subQuery->expr()->in('productPrice.priceList', ':priceLists')
            );

        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->update($this->getEntityName(), 'total')
            ->set('total.valid', ':newIsValid')
            ->where(
                $qb->expr()->eq('total.valid', ':isValid'),
                $qb->expr()->exists($subQuery)
            )
            ->setParameter('newIsValid', false, Type::BOOLEAN)
            ->setParameter('priceLists', $combinedPriceListIds, Connection::PARAM_INT_ARRAY)
            ->setParameter('isValid', true, Type::BOOLEAN);

        $qb->getQuery()->execute();
    }

    /**
     * @param array $customerIds
     * @param int $websiteId
     */
    public function invalidateByCustomers(array $customerIds, $websiteId)
    {
        if (empty($customerIds)) {
            return;
        }

        $expr = $this->getEntityManager()->getExpressionBuilder();
        $updateQB = $this->getBaseInvalidateQb($websiteId);
        $updateQB->andWhere($expr->in('sl.customer_id', ':customerIds'));
        $updateQB->setParameter('customerIds', $customerIds, Connection::PARAM_INT_ARRAY);

        $updateQB->execute();
    }

    /**
     * @param int $websiteId
     */
    public function invalidateGuestShoppingLists($websiteId)
    {
        $visitorMetadata = $this->getEntityManager()->getClassMetadata(CustomerVisitor::class);
        $customerVisitorsJoinTable = $visitorMetadata->getAssociationMapping('shoppingLists')['joinTable']['name'];

        $expr = $this->getEntityManager()->getExpressionBuilder();
        $visitorSubQB = $this->getEntityManager()->getConnection()->createQueryBuilder();
        $visitorSubQB->select($visitorSubQB->expr()->literal(1, Type::INTEGER))
            ->from($customerVisitorsJoinTable, 'slv')
            ->where($expr->eq('slv.shoppinglist_id', 'sl.id'));

        $updateQB = $this->getBaseInvalidateQb($websiteId);
        $updateQB->andWhere($expr->exists($visitorSubQB->getSQL()));

        $updateQB->execute();
    }

    /**
     * @param int $websiteId
     * @return SqlQueryBuilder
     */
    protected function getBaseInvalidateQb($websiteId): SqlQueryBuilder
    {
        $expr = $this->getEntityManager()->getExpressionBuilder();
        $rsm = ResultSetMappingUtil::createResultSetMapping(
            $this->getEntityManager()->getConnection()->getDatabasePlatform()
        );
        $updateQB = new SqlQueryBuilder($this->getEntityManager(), $rsm);
        $updateQB->update('oro_shopping_list_total', 'st')
            ->innerJoin('st', 'oro_shopping_list', 'sl', $expr->eq('st.shopping_list_id', 'sl.id'))
            ->set('is_valid', ':newIsValid')
            ->where(
                $expr->andX(
                    $expr->eq('st.is_valid', ':isValid'),
                    $expr->eq('sl.website_id', ':websiteId')
                )
            )
            ->setParameter('newIsValid', false, Type::BOOLEAN)
            ->setParameter('isValid', true, Type::BOOLEAN)
            ->setParameter('websiteId', $websiteId, Type::INTEGER);

        return $updateQB;
    }
}
