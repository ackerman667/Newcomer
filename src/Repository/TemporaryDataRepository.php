<?php

namespace App\Repository;

use App\Entity\TemporaryData;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TemporaryData>
 *
 * @method TemporaryData|null find($id, $lockMode = null, $lockVersion = null)
 * @method TemporaryData|null findOneBy(array $criteria, array $orderBy = null)
 * @method TemporaryData[]    findAll()
 * @method TemporaryData[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TemporaryDataRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TemporaryData::class);
    }

    public function deleteExpiredData(): int
    {
        $qb = $this->createQueryBuilder('td')
            ->delete()
            ->where('td.expiration <= :now')
            ->setParameter('now', new \DateTime());

        return $qb->getQuery()->execute(); // Retourne le nombre de lignes supprimées
    }





//    /**
//     * @return TemporaryData[] Returns an array of TemporaryData objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('t.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?TemporaryData
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
