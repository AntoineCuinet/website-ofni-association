<?php

namespace App\Repository;

use App\Entity\AssoEventInstance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AssoEventInstance>
 */
class AssoEventInstanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AssoEventInstance::class);
    }

    public function history(): array
    {
        return $this->createQueryBuilder('i')
            ->orderBy('i.date', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function prev(int $limit = 5): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.date < :now')
            ->setParameter('now', new \DateTime())
            ->orderBy('i.date', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    public function next(int $limit = 5): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.date >= :now')
            ->setParameter('now', new \DateTime()->setTime(0, 0, 0))
            ->orderBy('i.date', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

//    /**
//     * @return AssoEventInstance[] Returns an array of AssoEventInstance objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('a')
//            ->andWhere('a.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('a.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?AssoEventInstance
//    {
//        return $this->createQueryBuilder('a')
//            ->andWhere('a.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
