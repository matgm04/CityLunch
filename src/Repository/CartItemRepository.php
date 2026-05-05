<?php

namespace App\Repository;

use App\Entity\CartItem;
use App\Entity\Customer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CartItem>
 */
class CartItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CartItem::class);
    }

    public function findCartForCustomer(Customer $customer): array
    {
        return $this->createQueryBuilder('ci')
            ->join('ci.product', 'p')
            ->addSelect('p')
            ->where('ci.customer = :customer')
            ->setParameter('customer', $customer)
            ->orderBy('ci.addedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getTotalForCustomer(Customer $customer): float
    {
        $result = $this->createQueryBuilder('ci')
            ->join('ci.product', 'p')
            ->select('SUM(p.price * ci.quantity) as total')
            ->where('ci.customer = :customer')
            ->setParameter('customer', $customer)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    public function clearCartForCustomer(Customer $customer): int
    {
        return $this->createQueryBuilder('ci')
            ->delete()
            ->where('ci.customer = :customer')
            ->setParameter('customer', $customer)
            ->getQuery()
            ->execute();
    }
}
