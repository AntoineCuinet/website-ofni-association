<?php

namespace App\Repository;

use App\Entity\Actu;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Actu>
 */
class ActuRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Actu::class);
    }

    /**
     * Get the last actus
     *
     * @param int $limit The number of actus to get, 0 for all
     * @return Actu[] The last $limit actus or all actus
     */
    public function history(int $limit = 5): array
    {
        $builder = $this->createQueryBuilder('a')
                        ->orderBy('a.postedAt', 'DESC')
        ;
        if ($limit > 0) {
            $builder->setMaxResults($limit);
        }
        return $builder->getQuery()->getResult();
    }

    /**
     * Get the actus older than a given date
     *
     * @param \DateTimeImmutable $date The date to compare
     * @return Actu[] The actus older than $date
     */
    public function olderThan(\DateTimeImmutable $date): array
    {
        return $this->createQueryBuilder('a')
                    ->where('a.postedAt < :date')
                    ->setParameter('date', $date)
                    ->getQuery()
                    ->getResult()
        ;
    }
}
