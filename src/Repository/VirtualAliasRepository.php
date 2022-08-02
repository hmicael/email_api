<?php

namespace App\Repository;

use App\Entity\VirtualAlias;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VirtualAlias>
 *
 * @method VirtualAlias|null find($id, $lockMode = null, $lockVersion = null)
 * @method VirtualAlias|null findOneBy(array $criteria, array $orderBy = null)
 * @method VirtualAlias[]    findAll()
 * @method VirtualAlias[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VirtualAliasRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VirtualAlias::class);
    }

    public function add(VirtualAlias $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(VirtualAlias $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findAllWithPagination($page, $limit)
    {
        $qb = $this->createQueryBuilder('v')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);
        return $qb->getQuery()->getResult();
    }

//    /**
//     * @return VirtualAlias[] Returns an array of VirtualAlias objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('v')
//            ->andWhere('v.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('v.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?VirtualAlias
//    {
//        return $this->createQueryBuilder('v')
//            ->andWhere('v.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
