<?php

namespace App\Repository;

use App\Entity\VirtualUser;
use App\Repository\AbstractRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends AbstractRepository<VirtualUser>
 *
 * @method VirtualUser|null find($id, $lockMode = null, $lockVersion = null)
 * @method VirtualUser|null findOneBy(array $criteria, array $orderBy = null)
 * @method VirtualUser[]    findAll()
 * @method VirtualUser[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VirtualUserRepository extends AbstractRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VirtualUser::class);
    }

    public function add(VirtualUser $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(VirtualUser $entity, bool $flush = false): void
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
            ->andWhere('v.name like :keyword')
            ->orWhere('v.firstname like :keyword')
            ->orWhere('v.email like :keyword')
            ->andWhere('v.domainName = :domainId')
            ->setParameter('domainId', $domainId)
            ->setParameter('keyword', '%'. $keyword .'%')
            ->orderBy('v.name', 'asc');

        return $qb->getQuery()->getResult();
    }

//    /**
//     * @return VirtualUser[] Returns an array of VirtualUser objects
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

//    public function findOneBySomeField($value): ?VirtualUser
//    {
//        return $this->createQueryBuilder('v')
//            ->andWhere('v.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
