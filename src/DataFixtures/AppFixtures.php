<?php

namespace App\DataFixtures;

use App\Entity\DomainName;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // $product = new Product();
        // $manager->persist($product);
        $domains = [];
        for($i = 0; $i < 3; $i++) {
            $domains[$i] = new DomainName();
            $domains[$i]->setName("domain-" . (int)($i+1));
            $manager->persist($domains[$i]);
        }

        $manager->flush();
    }
}
