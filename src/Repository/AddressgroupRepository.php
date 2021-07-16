<?php

namespace App\Repository;

use App\Entity\Addressgroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Addressgroup|null find($id, $lockMode = null, $lockVersion = null)
 * @method Addressgroup|null findOneBy(array $criteria, array $orderBy = null)
 * @method Addressgroup[]    findAll()
 * @method Addressgroup[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AddressgroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Addressgroup::class);
    }

    // /**
    //  * @return Addressgroup[] Returns an array of Addressgroup objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('a.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Addressgroup
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
