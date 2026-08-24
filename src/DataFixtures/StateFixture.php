<?php
// src/DataFixtures/StateFixtures.php
namespace App\DataFixtures;

use App\Entity\Countries;
use App\Entity\States;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class StateFixture extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $states = [
            ['code' => 'AL', 'name' => 'Alabama'],
            ['code' => 'AK', 'name' => 'Alaska'],
            ['code' => 'AZ', 'name' => 'Arizona'],
            ['code' => 'AR', 'name' => 'Arkansas'],
            ['code' => 'CA', 'name' => 'California'],
            ['code' => 'CO', 'name' => 'Colorado'],
            ['code' => 'CT', 'name' => 'Connecticut'],
            ['code' => 'DE', 'name' => 'Delaware'],
            ['code' => 'DC', 'name' => 'District of Columbia'],
            ['code' => 'FL', 'name' => 'Florida'],
            ['code' => 'GA', 'name' => 'Georgia'],
            ['code' => 'HI', 'name' => 'Hawaii'],
            ['code' => 'ID', 'name' => 'Idaho'],
            ['code' => 'IL', 'name' => 'Illinois'],
            ['code' => 'IN', 'name' => 'Indiana'],
            ['code' => 'IA', 'name' => 'Iowa'],
            ['code' => 'KS', 'name' => 'Kansas'],
            ['code' => 'KY', 'name' => 'Kentucky'],
            ['code' => 'LA', 'name' => 'Louisiana'],
            ['code' => 'ME', 'name' => 'Maine'],
            ['code' => 'MD', 'name' => 'Maryland'],
            ['code' => 'MA', 'name' => 'Massachusetts'],
            ['code' => 'MI', 'name' => 'Michigan'],
            ['code' => 'MN', 'name' => 'Minnesota'],
            ['code' => 'MS', 'name' => 'Mississippi'],
            ['code' => 'MO', 'name' => 'Missouri'],
            ['code' => 'MT', 'name' => 'Montana'],
            ['code' => 'NE', 'name' => 'Nebraska'],
            ['code' => 'NV', 'name' => 'Nevada'],
            ['code' => 'NH', 'name' => 'New Hampshire'],
            ['code' => 'NJ', 'name' => 'New Jersey'],
            ['code' => 'NM', 'name' => 'New Mexico'],
            ['code' => 'NY', 'name' => 'New York'],
            ['code' => 'NC', 'name' => 'North Carolina'],
            ['code' => 'ND', 'name' => 'North Dakota'],
            ['code' => 'OH', 'name' => 'Ohio'],
            ['code' => 'OK', 'name' => 'Oklahoma'],
            ['code' => 'OR', 'name' => 'Oregon'],
            ['code' => 'PA', 'name' => 'Pennsylvania'],
            ['code' => 'RI', 'name' => 'Rhode Island'],
            ['code' => 'SC', 'name' => 'South Carolina'],
            ['code' => 'SD', 'name' => 'South Dakota'],
            ['code' => 'TN', 'name' => 'Tennessee'],
            ['code' => 'TX', 'name' => 'Texas'],
            ['code' => 'UT', 'name' => 'Utah'],
            ['code' => 'VT', 'name' => 'Vermont'],
            ['code' => 'VA', 'name' => 'Virginia'],
            ['code' => 'WA', 'name' => 'Washington'],
            ['code' => 'WV', 'name' => 'West Virginia'],
            ['code' => 'WI', 'name' => 'Wisconsin'],
            ['code' => 'WY', 'name' => 'Wyoming'],
            ['code' => 'AB', 'name' => 'Alberta'],
            ['code' => 'BC', 'name' => 'British Columbia'],
            ['code' => 'MB', 'name' => 'Manitoba'],
            ['code' => 'NB', 'name' => 'New Brunswick'],
            ['code' => 'NL', 'name' => 'Newfoundland and Labrador'],
            ['code' => 'NT', 'name' => 'Northwest Territories'],
            ['code' => 'NS', 'name' => 'Nova Scotia'],
            ['code' => 'NU', 'name' => 'Nunavut'],
            ['code' => 'ON', 'name' => 'Ontario'],
            ['code' => 'PE', 'name' => 'Prince Edward Island'],
            ['code' => 'QC', 'name' => 'Quebec'],
            ['code' => 'SK', 'name' => 'Saskatchewan'],
            ['code' => 'YT', 'name' => 'Yukon'],
            ['code' => 'AGU', 'name' => 'Aguascalientes'],
            ['code' => 'BCN', 'name' => 'Baja California'],
            ['code' => 'BCS', 'name' => 'Baja California Sur'],
            ['code' => 'CAM', 'name' => 'Campeche'],
            ['code' => 'CHP', 'name' => 'Chiapas'],
            ['code' => 'CHH', 'name' => 'Chihuahua'],
            ['code' => 'CMX', 'name' => 'Ciudad de México'],
            ['code' => 'COA', 'name' => 'Coahuila'],
            ['code' => 'COL', 'name' => 'Colima'],
            ['code' => 'DUR', 'name' => 'Durango'],
            ['code' => 'GUA', 'name' => 'Guanajuato'],
            ['code' => 'GRO', 'name' => 'Guerrero'],
            ['code' => 'HID', 'name' => 'Hidalgo'],
            ['code' => 'JAL', 'name' => 'Jalisco'],
            ['code' => 'MEX', 'name' => 'México'],
            ['code' => 'MIC', 'name' => 'Michoacán'],
            ['code' => 'MOR', 'name' => 'Morelos'],
            ['code' => 'NAY', 'name' => 'Nayarit'],
            ['code' => 'NLE', 'name' => 'Nuevo León'],
            ['code' => 'OAX', 'name' => 'Oaxaca'],
            ['code' => 'PUE', 'name' => 'Puebla'],
            ['code' => 'QUE', 'name' => 'Querétaro'],
            ['code' => 'ROO', 'name' => 'Quintana Roo'],
            ['code' => 'SLP', 'name' => 'San Luis Potosí '],
            ['code' => 'SIN', 'name' => 'Sinaloa'],
            ['code' => 'SON', 'name' => 'Sonora'],
            ['code' => 'TAB', 'name' => 'Tabasco'],
            ['code' => 'TAM', 'name' => 'Tamaulipas'],
            ['code' => 'TLA', 'name' => 'Tlaxcala'],
            ['code' => 'VER', 'name' => 'Veracruz'],
            ['code' => 'YUC', 'name' => 'Yucatán'],
            ['code' => 'ZAC', 'name' => 'Zacatecas'],
        ];

        foreach ($states as $s) {
            $state = new States();
            $state->setCode($s['code']);
            $state->setName($s['name']);
            if (strlen($s['code'] == 3)) {
                $state->setCountry($this->getReference('country_MX', Countries::class));
            } elseif (in_array($s['code'], ['AB', 'BC', 'MB', 'NB', 'NL', 'NT', 'NS', 'NU', 'ON', 'PE', 'QC', 'SK', 'YT'])) {
                $state->setCountry($this->getReference('country_CA', Countries::class));
            } else {
                $state->setCountry($this->getReference('country_US', Countries::class));
            }
            $manager->persist($state);



            // ag: add reference for later use in FirmFixture
            $this->addReference('state_' . $s['code'], $state);
        }

        $manager->flush();
    }
}
