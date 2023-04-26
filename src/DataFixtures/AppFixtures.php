<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $userPasswordHashed;

    public function __construct(
        UserPasswordHasherInterface $userPasswordHashed,
    ) {
        $this->userPasswordHashed = $userPasswordHashed;
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

        $manager->flush();
    }
}