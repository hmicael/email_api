<?php

namespace App\DataFixtures;

use App\Entity\DomainName;
use App\Entity\VirtualUser;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private $userPasswordHasher;
    
    public function __construct(UserPasswordHasherInterface $userPasswordHasher)
    {
        $this->userPasswordHasher = $userPasswordHasher;
    }
    
    public function load(ObjectManager $manager): void
    {
        // $product = new Product();
        // $manager->persist($product);
        // Création d'un user "normal"
        $user = new User();
        $user->setEmail("user@bookapi.com");
        $user->setRoles(["ROLE_USER"]);
        $user->setPassword($this->userPasswordHasher->hashPassword($user, "password"));
        $manager->persist($user);
        
        // Création d'un user admin
        $userAdmin = new User();
        $userAdmin->setEmail("admin@email.com");
        $userAdmin->setRoles(["ROLE_ADMIN"]);
        $userAdmin->setPassword($this->userPasswordHasher->hashPassword($userAdmin, "admin"));
        $manager->persist($userAdmin);

        $domains = [];
        for($i = 0; $i < 3; $i++) {
            $domains[$i] = new DomainName();
            $domains[$i]->setName("domain-" . (int)($i+1) . ".com");
            $manager->persist($domains[$i]);
        }

        $users = [];
        for($i = 0; $i < 3; $i++) {
            $users[$i] = new VirtualUser();
            $users[$i]->setName("user-" . (int)($i+1));
            $users[$i]->setFirstName("user-firstname" . (int)($i+1));
            $users[$i]->setEmail("email-" . (int)($i+1) . "@domain.com");
            $users[$i]->setDomainName($domains[0]);
            $users[$i]->setMaildir("maildir-" . (int)($i+1));
            $users[$i]->setPassword("1698@Igy");
            $manager->persist($users[$i]);
        }

        $manager->flush();
    }
}
