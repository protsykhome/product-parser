<?php

namespace App\Command;

use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use RuntimeException;
use DOMDocument;
use DOMXPath;

#[AsCommand(
	name: 'ParseAutoRia',
	description: 'Parse products from auto.ria.com and save to database.',
)]
class ParseAutoRiaCommand extends Command
{
	private EntityManagerInterface $entityManager;

	public function __construct(EntityManagerInterface $entityManager)
	{
		parent::__construct();
		$this->entityManager = $entityManager;
	}

	protected function configure(): void
	{
		$this
			->setDescription('Parse products from auto.ria.com and save to database.')
			->addOption('pages', null, InputOption::VALUE_OPTIONAL, 'Number of pages to parse', 3);
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);
		$pages = $input->getOption('pages');
		$client = HttpClient::create();
		$baseUrl = 'https://auto.ria.com/uk/legkovie/subaru/?page=';
		$productsData = [];

		try {
			for ($i = 1; $i <= $pages; $i++) {
				$url = $baseUrl . $i;
				try {
					$response = $client->request('GET', $url);
					$html = $response->getContent();
				} catch (ClientExceptionInterface | RedirectionExceptionInterface | ServerExceptionInterface $e) {
					$io->error("HTTP error occurred while fetching page $i: " . $e->getMessage());
					continue;
				}

				$dom = new DOMDocument();
				@$dom->loadHTML($html);
				$xpath = new DOMXPath($dom);
				$products = $xpath->query('//section[contains(@class, "ticket-item")]');

				foreach ($products as $productNode) {
					$nameNode = $xpath->query('.//div[contains(@class, "item ticket-title")]//a', $productNode);
					$priceNode = $xpath->query('.//span[contains(@class, "bold") and contains(@class, "size22") and contains(@class, "green")]', $productNode);
					$imageUrlNode = $xpath->query('.//a//picture//img/@src', $productNode);
					$productUrlNode = $xpath->query('.//div[contains(@class, "item ticket-title")]//a/@href', $productNode);

					if ($nameNode->length > 0 && $priceNode->length > 0 && $imageUrlNode->length > 0 && $productUrlNode->length > 0) {
						$product = new Product();
						$product->setName(trim($nameNode->item(0)->textContent));
						$product->setPrice(floatval(str_replace(' ', '', $priceNode->item(0)->textContent)));
						$product->setImageUrl(trim($imageUrlNode->item(0)->textContent));
						$product->setProductUrl(trim($productUrlNode->item(0)->textContent));

						$this->entityManager->persist($product);

						$productsData[] = [
							'name' => trim($nameNode->item(0)->textContent),
							'price' => floatval(str_replace(' ', '', $priceNode->item(0)->textContent)),
							'imageUrl' => trim($imageUrlNode->item(0)->textContent),
							'productUrl' => trim($productUrlNode->item(0)->textContent),
						];
					} else {
						$io->warning("Missing data for a product on page $i.");
					}
				}

				$io->success("Parsed page $i");
			}

			$this->entityManager->flush();
			$io->success('All products have been successfully parsed and saved.');

			$this->saveToCsv($productsData, $io);

		} catch (TransportExceptionInterface $e) {
			$io->error("An error occurred during HTTP transport: " . $e->getMessage());
			return Command::FAILURE;
		} catch (Exception $e) {
			$io->error("An error occurred: " . $e->getMessage());
			return Command::FAILURE;
		}

		return Command::SUCCESS;
	}

	/**
	 * @throws RuntimeException
	 */
	private function saveToCsv(array $productsData, SymfonyStyle $io): void
	{
		$csvFile = 'products.csv';
		try {
			$csvHandle = fopen($csvFile, 'w');
			if ($csvHandle === false) {
				throw new RuntimeException("Unable to open file for writing: $csvFile");
			}

			fputcsv($csvHandle, ['Name', 'Price', 'Image URL', 'Product URL']);
			foreach ($productsData as $product) {
				fputcsv($csvHandle, $product);
			}
			fclose($csvHandle);

			$io->success('Products have been saved to CSV file.');
		} catch (RuntimeException $e) {
			$io->error("An error occurred while saving to CSV: " . $e->getMessage());
		}
	}
}
