<?php

namespace App\Repository;

use App\Entity\Transaction;
use App\Enum\CourseType;
use DateTime;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transaction>
 *
 * @method Transaction|null find($id, $lockMode = null, $lockVersion = null)
 * @method Transaction|null findOneBy(array $criteria, array $orderBy = null)
 * @method Transaction[]    findAll()
 * @method Transaction[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    public function save(Transaction $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Transaction $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByQueryParamsAndUserEmail(
        string  $userEmail,
        ?int    $transactionType = null,
        ?string $courseCode = null,
        bool    $skipExpired = false
    ) {
        $qb = $this->createQueryBuilder('t')
            ->select(
                't.id',
                't.created AS created_at',
                't.expires AS expires_at',
                't.type',
                'c.code AS course_code',
                't.amount'
            )
            ->leftJoin('t.course', 'c')
            ->innerJoin('t.customer', 'u', Join::WITH, 'u.email = :userEmail')
            ->setParameter('userEmail', $userEmail)
        ;

        if (null !== $transactionType) {
            $qb->andWhere('t.type = :transactionType')
                ->setParameter('transactionType', $transactionType, Types::SMALLINT);
        }
        if (null !== $courseCode) {
            $qb->andWhere('c.code = :code')
                ->setParameter('code', $courseCode);
        }
        if (!$skipExpired) {
            return $qb
                ->getQuery()
                ->getArrayResult();
        }

        $previousQb = clone $qb;
        $q1 = $qb->andWhere('c.type != :courseType OR c.type IS NULL')
            ->setParameter('courseType', CourseType::RENT, Types::SMALLINT)
            ->getQuery();
        $q2 = $previousQb
            ->andWhere('c.type = :courseType')
            ->setParameter('courseType', CourseType::RENT, Types::SMALLINT)
            ->andWhere('t.expires > :now')
            ->setParameter('now', new DateTime(), Types::DATETIME_MUTABLE)
            ->getQuery();

        return array_merge(
            $q1->getArrayResult(),
            $q2->getArrayResult()
        );
    }

    public function countActiveCourses(int $userId, int $courseId, int $courseType): int
    {
        $qb = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->innerJoin('t.course', 'c')
            ->where('c.id = :courseId')
            ->setParameter('courseId', $courseId, Types::INTEGER)

            ->innerJoin('t.customer', 'u')
            ->andWhere('u.id = :userId')
            ->setParameter('userId', $userId, Types::INTEGER)
        ;

        if ($courseType === CourseType::RENT) {
            $qb->andWhere('t.expires > :now')
                ->setParameter('now', new DateTime(), Types::DATETIME_MUTABLE);
        }

        return $qb
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findPeriodTotalPaid(DateTimeImmutable $from, DateTimeImmutable $to)
    {
        return $this->createQueryBuilder('t')
            ->select(
                'u.email AS email',
                'c.name AS name',
                'c.type AS type',
                'COUNT(t.id) AS transactions_count',
                'SUM(t.amount) AS course_amount'
            )
            ->innerJoin('t.course', 'c')
            ->innerJoin('t.customer', 'u')
            ->where('t.created >= :from')
            ->setParameter('from', $from, Types::DATETIME_IMMUTABLE)
            ->andWhere('t.created <= :to')
            ->setParameter('to', $to, Types::DATETIME_IMMUTABLE)
            ->groupBy('email', 'c.id', 'name', 'type')
            ->getQuery()
            ->getArrayResult();
    }

//    /**
//     * @return Transaction[] Returns an array of Transaction objects
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

//    public function findOneBySomeField($value): ?Transaction
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
