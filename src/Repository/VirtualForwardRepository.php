<?php

namespace App\Repository;

use App\Entity\VirtualForward;
use App\Repository\AbstractRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends AbstractRepository<VirtualForward>
 *
 * @method VirtualForward|null find($id, $lockMode = null, $lockVersion = null)
 * @method VirtualForward|null findOneBy(array $criteria, array $orderBy = null)
 * @method VirtualForward[]    findAll()
 * @method VirtualForward[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VirtualForwardRepository extends AbstractRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VirtualForward::class);
    }

    public function add(VirtualForward $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(VirtualForward $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function search($domainId, $keyword)
    {
        $qb = $this
            ->createQueryBuilder('v')
            ->select('v')            
            ->andWhere('v.source like :keyword')
            ->andWhere('v.domainName = :domainId')
            ->setParameter('domainId', $domainId)
            ->setParameter('keyword', '%'. $keyword .'%')
            ->orderBy('v.source', 'asc');

        return $qb->getQuery()->getResult();
    }

//    /**
//     * @return VirtualForward[] Returns an array of VirtualForward objects
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

//    public function findOneBySomeField($value): ?VirtualForward
//    {
//        return $this->createQueryBuilder('v')
//            ->andWhere('v.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
