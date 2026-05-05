<?php

namespace App\Controller;

use App\Entity\CartItem;
use App\Repository\CartItemRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/cart', name: 'app_cart_')]
#[IsGranted('ROLE_USER')]
class CartController extends AbstractController
{
    #[Route('', name: 'index')]
    public function index(CartItemRepository $cartItemRepository): Response
    {
        /** @var \App\Entity\Customer $customer */
        $customer  = $this->getUser();
        $cartItems = $cartItemRepository->findCartForCustomer($customer);
        $total     = $cartItemRepository->getTotalForCustomer($customer);

        return $this->render('cart/index.html.twig', [
            'cartItems' => $cartItems,
            'total'     => $total,
        ]);
    }

    #[Route('/add/{id}', name: 'add', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function add(
        int $id,
        Request $request,
        ProductRepository $productRepository,
        CartItemRepository $cartItemRepository,
        EntityManagerInterface $em,
    ): Response {
        if (!$this->isCsrfTokenValid('cart_add_' . $id, $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_product_index');
        }

        $product = $productRepository->find($id);
        if (!$product || !$product->isAvailable()) {
            $this->addFlash('danger', 'Produit introuvable.');
            return $this->redirectToRoute('app_product_index');
        }

        /** @var \App\Entity\Customer $customer */
        $customer = $this->getUser();

        // Cherche si le produit est déjà dans le panier
        $cartItem = $cartItemRepository->findOneBy([
            'customer' => $customer,
            'product'  => $product,
        ]);

        if ($cartItem) {
            $cartItem->setQuantity($cartItem->getQuantity() + 1);
        } else {
            $cartItem = new CartItem();
            $cartItem->setCustomer($customer);
            $cartItem->setProduct($product);
            $cartItem->setQuantity(1);
            $em->persist($cartItem);
        }

        $em->flush();
        $this->addFlash('success', sprintf('"%s" ajouté au panier.', $product->getName()));

        return $this->redirectToRoute('app_product_index');
    }

    #[Route('/update/{id}', name: 'update', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function update(
        int $id,
        Request $request,
        CartItemRepository $cartItemRepository,
        EntityManagerInterface $em,
    ): Response {
        if (!$this->isCsrfTokenValid('cart_update_' . $id, $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_cart_index');
        }

        /** @var \App\Entity\Customer $customer */
        $customer = $this->getUser();
        $cartItem = $cartItemRepository->findOneBy(['id' => $id, 'customer' => $customer]);

        if (!$cartItem) {
            throw $this->createNotFoundException();
        }

        $qty = (int) $request->request->get('quantity', 1);

        if ($qty < 1) {
            $em->remove($cartItem);
            $this->addFlash('info', 'Article retiré du panier.');
        } else {
            $cartItem->setQuantity(min($qty, 99));
            $this->addFlash('success', 'Quantité mise à jour.');
        }

        $em->flush();
        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/remove/{id}', name: 'remove', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function remove(
        int $id,
        Request $request,
        CartItemRepository $cartItemRepository,
        EntityManagerInterface $em,
    ): Response {
        if (!$this->isCsrfTokenValid('cart_remove_' . $id, $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_cart_index');
        }

        /** @var \App\Entity\Customer $customer */
        $customer = $this->getUser();
        $cartItem = $cartItemRepository->findOneBy(['id' => $id, 'customer' => $customer]);

        if ($cartItem) {
            $em->remove($cartItem);
            $em->flush();
            $this->addFlash('info', 'Article retiré du panier.');
        }

        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/clear', name: 'clear', methods: ['POST'])]
    public function clear(
        Request $request,
        CartItemRepository $cartItemRepository,
        EntityManagerInterface $em,
    ): Response {
        if (!$this->isCsrfTokenValid('cart_clear', $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_cart_index');
        }

        /** @var \App\Entity\Customer $customer */
        $customer = $this->getUser();
        $cartItemRepository->clearCartForCustomer($customer);

        $this->addFlash('info', 'Panier vidé.');
        return $this->redirectToRoute('app_product_index');
    }
}
