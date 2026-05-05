<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/products', name: 'app_product_')]
class ProductController extends AbstractController
{
    #[Route('', name: 'index')]
    public function index(ProductRepository $productRepository): Response
    {
        $products = $productRepository->findAvailable();

        $dishes   = array_filter($products, fn($p) => $p->isDish());
        $desserts = array_filter($products, fn($p) => $p->isDessert());

        return $this->render('product/index.html.twig', [
            'dishes'   => $dishes,
            'desserts' => $desserts,
        ]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'])]
    public function show(int $id, ProductRepository $productRepository): Response
    {
        $product = $productRepository->find($id);

        if (!$product || !$product->isAvailable()) {
            throw $this->createNotFoundException('Produit introuvable.');
        }

        return $this->render('product/show.html.twig', ['product' => $product]);
    }
}
