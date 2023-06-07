<?php

namespace App\DataFixtures;

use App\Entity\Course;
use App\Entity\User;
use App\Service\PaymentService;
use DateInterval;
use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private const COURSES_DATA = [
        [
            'code' => 'nympydata',
            'type' => 0,
            'name' => 'Numpy и анализ данных'
        ], [
            'code' => 'figmadesign',
            'type' => 1,
            'price' => 10,
            'name' => 'Веб-дизайн в Figma 2023. Основы UI/UX дизайна на практике.'
        ], [
            'code' => 'molecularphysics',
            'type' => 2,
            'price' => 20,
            'name' => 'Молекулярная физика и термодинамика',
        ]
    ];

    private UserPasswordHasherInterface $userPasswordHashed;
    private PaymentService $paymentService;

    public function __construct(UserPasswordHasherInterface $passwordHasher, PaymentService $paymentService)
    {
        $this->userPasswordHashed = $passwordHasher;
        $this->paymentService = $paymentService;
    }


    public function load(ObjectManager $manager): void
    {
        $user = new User();
        $password = $this->userPasswordHashed->hashPassword(
            $user,
            'password'
        );
        $user
            ->setEmail('user@example.com')
            ->setPassword($password)
            ->setBalance(1000.0);

        $manager->persist($user);

        $admin = new User();
        $password = $this->userPasswordHashed->hashPassword(
            $admin,
            'password'
        );
        $admin
            ->setEmail('admin@example.com')
            ->setPassword($password)
            ->setRoles(['ROLE_SUPER_ADMIN'])
            ->setBalance(1000.0);

        $manager->persist($admin);

        $coursesByCode = $this->createCourses($manager);

        $this->paymentService->deposit($user, 90.45);
        $this->paymentService->deposit($admin, 450.50);

        $transaction = $this->paymentService->pay($user, $coursesByCode['figmadesign']);
        $transaction->setCreated((new DateTime())->sub(new DateInterval('P2D')));
        $transaction->setExpires((new DateTime())->sub(new DateInterval('P1D')));

        $transaction = $this->paymentService->pay($user, $coursesByCode['molecularphysics']);

        $transaction = $this->paymentService->pay($user, $coursesByCode['figmadesign']);
        $transaction->setExpires((new DateTime())->add(new DateInterval('PT23H')));

        $manager->persist($transaction);

        $this->paymentService->deposit($admin, 1000);
        $transaction = $this->paymentService->pay($admin, $coursesByCode['molecularphysics']);
        $transaction = $this->paymentService->pay($admin, $coursesByCode['figmadesign']);


        $manager->flush();
    }

    public function createCourses(ObjectManager $manager): array
    {
        $coursesByCode = [];

        foreach (self::COURSES_DATA as $courseData) {
            $course = (new Course())
                ->setCode($courseData['code'])
                ->setName($courseData['name'])
                ->setType($courseData['type']);
            if (isset($courseData['price'])) {
                $course->setPrice($courseData['price']);
            }

            $coursesByCode[$courseData['code']] = $course;
            $manager->persist($course);
        }
        return $coursesByCode;
    }
}