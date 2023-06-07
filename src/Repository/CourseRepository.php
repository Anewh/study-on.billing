<?php

namespace App\Repository;

use App\Entity\Course;
use DateInterval;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Course>
 *
 * @method Course|null find($id, $lockMode = null, $lockVersion = null)
 * @method Course|null findOneBy(array $criteria, array $orderBy = null)
 * @method Course[]    findAll()
 * @method Course[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CourseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Course::class);
    }

    public function save(Course $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Course $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findMainInfo()
    {
        return $this->createQueryBuilder('c')
            ->select('c.code', 'c.type', 'c.price')
            ->getQuery()
            ->getArrayResult();
    }

    public function findOneAsArrayByCode(string $code): ?array
    {
        try {
            return $this->createQueryBuilder('c')
                ->select('c.code', 'c.type', 'c.price')
                ->where('c.code = :code')
                ->setParameter('code', $code, ParameterType::STRING)
                ->getQuery()
                ->getSingleResult();
        } catch (NoResultException|NonUniqueResultException $e) {
            return null;
        }
    }

    /**
     * @throws Exception
     */
    public function findExpireInForUsers(string $period): array
    {
        $now = new DateTimeImmutable();
        return $this->createQueryBuilder('c')
            ->select('u.email AS email', 'c.name AS name', 't.expires AS expires_at')
            ->innerJoin('c.transactions', 't')
            ->innerJoin('t.customer', 'u')
            ->where('t.expires >= :now')
                ->setParameter('now', $now, Types::DATETIME_IMMUTABLE)
            ->andWhere('t.expires <= :last_time')
                ->setParameter('last_time', $now->add(new DateInterval($period)), Types::DATETIME_IMMUTABLE)
            ->getQuery()
            ->getArrayResult();
    }
    
//    /**
//     * @return Course[] Returns an array of Course objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('c.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Course
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
