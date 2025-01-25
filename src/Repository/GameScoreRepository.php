<?php

namespace App\Repository;

use App\Entity\GameScore;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GameScore>
 */
class GameScoreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GameScore::class);
    }

    public function totalScores(): array {
        $res = [];
        $res['canard'] = $this->createQueryBuilder('score')
            ->select('sum(score.score)')
            ->where('score.team = \'canard\'')
            ->getQuery()
            ->getOneOrNullResult();
        $res['abeille'] = $this->createQueryBuilder('score')
            ->select('sum(score.score)')
            ->where('score.team = \'abeille\'')
            ->getQuery()
            ->getOneOrNullResult();

        $canard_first = $res['canard'] >= $res['abeille'];
        return [
            'team_first' => [
                'name' => $canard_first ? 'Canard' : 'Abeille',
                'score' => $canard_first ? $res['canard'][1] : $res['abeille'][1],
            ],
            'team_second' => [
                'name' => !$canard_first ? 'Canard' : 'Abeille',
                'score' => !$canard_first ? $res['canard'][1] : $res['abeille'][1],
            ],
        ];
    }
//    /**
//     * @return GameScore[] Returns an array of GameScore objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('g')
//            ->andWhere('g.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('g.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?GameScore
//    {
//        return $this->createQueryBuilder('g')
//            ->andWhere('g.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
