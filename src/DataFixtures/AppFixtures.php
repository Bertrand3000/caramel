<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Utilisateur;
use App\Enum\ProfilUtilisateur;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $users = [
            ['login' => 'admin@caramel.local', 'password' => 'Admin1234!', 'roles' => ['ROLE_ADMIN'], 'profil' => ProfilUtilisateur::DMAX],
            ['login' => 'dmax@caramel.local', 'password' => 'Dmax1234!', 'roles' => ['ROLE_DMAX'], 'profil' => ProfilUtilisateur::DMAX],
            ['login' => 'teletravailleur@caramel.local', 'password' => 'Tele1234!', 'roles' => ['ROLE_TELETRAVAILLEUR'], 'profil' => ProfilUtilisateur::TELETRAVAILLEUR],
            ['login' => 'partenaire@caramel.local', 'password' => 'Part1234!', 'roles' => ['ROLE_PARTENAIRE'], 'profil' => ProfilUtilisateur::PARTENAIRE],
            ['login' => 'public@caramel.local', 'password' => 'Publ1234!', 'roles' => ['ROLE_AGENT'], 'profil' => ProfilUtilisateur::PUBLIC],
        ];

        foreach ($users as $data) {
            $user = new Utilisateur();
            $user->setLogin($data['login']);
            $user->setRoles($data['roles']);
            $user->setActif(true);
            $user->setProfil($data['profil']);
            $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));
            $manager->persist($user);
        }

        $manager->flush();
    }
}
