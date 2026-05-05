<?php

namespace App\DataFixtures;

use App\Entity\Customer;
use App\Entity\Product;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
    ) {}

    public function load(ObjectManager $manager): void
    {
        // ── Clients ──────────────────────────────────────────────────────────
        $customers = [
            ['Alice', 'Martin',  'alice@citylunch.fr',  'password123'],
            ['Bob',   'Dupont',  'bob@citylunch.fr',    'password123'],
            ['Admin', 'CityLunch', 'admin@citylunch.fr', 'admin1234'],
        ];

        foreach ($customers as [$first, $last, $email, $plain]) {
            $customer = new Customer();
            $customer->setFirstName($first)
                     ->setLastName($last)
                     ->setEmail($email)
                     ->setPassword($this->hasher->hashPassword($customer, $plain));

            if ($email === 'admin@citylunch.fr') {
                $customer->setRoles(['ROLE_ADMIN']);
            }

            $manager->persist($customer);
        }

        // ── Produits du jour ──────────────────────────────────────────────────
        $products = [
            // Plats
            ['Poulet rôti aux herbes de Provence',
             'Cuisse de poulet fermier rôtie lentement avec romarin, thym et ail confit. Accompagnée de légumes de saison.',
             12.50, Product::TYPE_DISH],

            ['Saumon grillé sauce citron-câpres',
             'Pavé de saumon Atlantique grillé, sauce légère citron et câpres, purée de pommes de terre maison.',
             13.90, Product::TYPE_DISH],

            ['Risotto aux champignons des bois',
             'Risotto crémeux au parmesan avec mélange de champignons sauvages (cèpes, girolles, trompettes).',
             11.50, Product::TYPE_DISH],

            // Desserts
            ['Tarte tatin aux pommes',
             'Tarte tatin maison avec des pommes caramélisées fondantes, servie tiède avec crème fraîche.',
             5.50, Product::TYPE_DESSERT],

            ['Mousse au chocolat noir',
             'Mousse légère au chocolat noir 70% cacao, préparée avec des œufs frais du jour.',
             4.80, Product::TYPE_DESSERT],
        ];

        foreach ($products as [$name, $desc, $price, $type]) {
            $product = new Product();
            $product->setName($name)
                    ->setDescription($desc)
                    ->setPrice((string) $price)
                    ->setType($type)
                    ->setAvailable(true);

            $manager->persist($product);
        }

        $manager->flush();
    }
}
