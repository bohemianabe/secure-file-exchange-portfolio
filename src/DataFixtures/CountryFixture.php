<?php

namespace App\DataFixtures;

use App\Entity\Countries;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CountryFixture extends Fixture
{
    public const COUNTRY_REFERENCE_PREFIX = 'country_';

    public function load(ObjectManager $manager): void
    {
        $countries = [
            ['US', 'United States'],
            ['CA', 'Canada'],
            ['MX', 'Mexico'],
            ['GB', 'United Kingdom'],
            ['DE', 'Germany'],
            ['FR', 'France'],
            ['ES', 'Spain'],
            ['IT', 'Italy'],
            ['AU', 'Australia'],
            ['JP', 'Japan'],
        ];

        foreach ($countries as [$code, $name]) {
            $country = new Countries();
            $country->setCode($code);
            $country->setName($name);
            $manager->persist($country);

            // ag: add reference for later use in FirmFixture
            $this->addReference(self::COUNTRY_REFERENCE_PREFIX . $code, $country);
        }

        $manager->flush();
    }
}
