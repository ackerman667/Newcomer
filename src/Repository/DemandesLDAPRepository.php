<?php

namespace App\Repository;

use App\Entity\DemandesLDAP;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DemandesLDAP>
 *
 * @method DemandesLDAP|null find($id, $lockMode = null, $lockVersion = null)
 * @method DemandesLDAP|null findOneBy(array $criteria, array $orderBy = null)
 * @method DemandesLDAP[]    findAll()
 * @method DemandesLDAP[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DemandesLDAPRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DemandesLDAP::class);
    }

//    /**
//     * @return DemandesLDAP[] Returns an array of DemandesLDAP objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('d')
//            ->andWhere('d.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('d.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?DemandesLDAP
//    {
//        return $this->createQueryBuilder('d')
//            ->andWhere('d.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
