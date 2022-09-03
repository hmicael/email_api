<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

abstract class AbstractRepository extends ServiceEntityRepository
{
    /**
     * Return paginnated items
     *
     * @param [int] $page
     * @param [int] $limit
     * @return mixed
     */
    public function findAllWithPagination($page, $limit)
    {
        $qb = $this->createQueryBuilder('e')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Get the total of element inside a table
     *
     * @return int
     */
    public function getTotalElement()
    {
        $qb = $this->createQueryBuilder('e')
            ->select('count(e.id)');

        return $qb->getQuery()->getSingleScalarResult();
    }
}