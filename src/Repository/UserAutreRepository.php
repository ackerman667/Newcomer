<?php

namespace App\Repository;

use App\Entity\UserAutre;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserAutre>
 *
 * @method UserAutre|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserAutre|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserAutre[]    findAll()
 * @method UserAutre[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserAutreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserAutre::class);
    }

//    /**
//     * @return UserAutre[] Returns an array of UserAutre objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('u.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?UserAutre
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
