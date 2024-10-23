<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class ProductController extends AbstractController
{
	private LoggerInterface $logger;

	public function __construct(LoggerInterface $logger)
	{
		$this->logger = $logger;
	}

	#[Route('/api/products', name: 'api_products', methods: ['GET'])]
	public function index(ProductRepository $productRepository): JsonResponse
	{
		try {
			$products = $productRepository->findAll();
			$data = [];

			if (empty($products)) {
				return new JsonResponse(['message' => 'No products found.'], 404);
			}

			foreach ($products as $product) {
				$data[] = [
					'name' => $product->getName(),
					'price' => $product->getPrice(),
					'imageUrl' => $product->getImageUrl(),
					'productUrl' => $product->getProductUrl(),
				];
			}

			return new JsonResponse($data);
		} catch (Exception $e) {
			$this->logger->error('Error fetching products: ' . $e->getMessage());

			return new JsonResponse(['message' => 'An error occurred while fetching products.'], 500);
		}
	}
}
