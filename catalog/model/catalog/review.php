<?php
namespace Opencart\Catalog\Model\Catalog;
/**
 * Class Review
 *
 * Can be called using $this->load->model('catalog/review');
 *
 * @package Opencart\Catalog\Model\Catalog
 */
class Review extends \Opencart\System\Engine\Model {
	/**
	 * Add Review
	 *
	 * Create a new review record in the database.
	 *
	 * @param int                  $product_id primary key of the product record
	 * @param array<string, mixed> $data       array of data
	 *
	 * @return int
	 *
	 * @example
	 *
	 * $review_data = [
	 *     'author'      => 'Author Name',
	 *     'customer_id' => 1,
	 *     'product_id'  => 1,
	 *     'text'        => '',
	 *     'rating'      => 4
	 * ];
	 *
	 * $this->load->model('catalog/review');
	 *
	 * $this->model_catalog_review->addReview($product_id, $review_data);
	 */
	public function addReview(int $product_id, array $data): int {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "review` SET `author` = '" . $this->db->escape($data['author']) . "', `customer_id` = '" . (int)$this->customer->getId() . "', `product_id` = '" . (int)$product_id . "', `text` = '" . $this->db->escape($data['text']) . "', `rating` = '" . (int)$data['rating'] . "', `date_added` = NOW(), `date_modified` = NOW()");

		return $this->db->getLastId();
	}

	/**
	 * Get Reviews By Product ID
	 *
	 * Get the record of the reviews by product records in the database.
	 *
	 * @param int $product_id primary key of the product record
	 * @param int $start
	 * @param int $limit
	 *
	 * @return array<int, array<string, mixed>> review records that have product ID
	 *
	 * @example
	 *
	 * $this->load->model('catalog/review');
	 *
	 * $results = $this->model_catalog_review->getReviewsByProductId($product_id, $start, $limit);
	 */
	public function getReviewsByProductId(int $product_id, int $start = 0, int $limit = 20): array {
		if ($start < 0) {
			$start = 0;
		}

		if ($limit < 1) {
			$limit = 20;
		}

		$query = $this->db->query("SELECT `r`.`author`, `r`.`rating`, `r`.`text`, `r`.`date_added` FROM `" . DB_PREFIX . "review` `r` LEFT JOIN `" . DB_PREFIX . "product` `p` ON (`r`.`product_id` = `p`.`product_id`) LEFT JOIN `" . DB_PREFIX . "product_description` `pd` ON (`p`.`product_id` = `pd`.`product_id`) WHERE `r`.`product_id` = '" . (int)$product_id . "' AND `p`.`date_available` <= NOW() AND `p`.`status` = '1' AND `r`.`status` = '1' AND `pd`.`language_id` = '" . (int)$this->config->get('config_language_id') . "' ORDER BY `r`.`date_added` DESC LIMIT " . (int)$start . "," . (int)$limit);

		return $query->rows;
	}

	/**
	 * Get Total Reviews By Product ID
	 *
	 * Get the total number of total review records in the database.
	 *
	 * @param int $product_id primary key of the product record
	 *
	 * @return int total number of review records that have product ID
	 *
	 * @example
	 *
	 * $this->load->model('catalog/review');
	 *
	 * $review_total = $this->model_catalog_review->getTotalReviewsByProductId($product_id);
	 */
	public function getTotalReviewsByProductId(int $product_id): int {
		$query = $this->db->query("SELECT COUNT(*) AS `total` FROM `" . DB_PREFIX . "review` `r` LEFT JOIN `" . DB_PREFIX . "product` `p` ON (`r`.`product_id` = `p`.`product_id`) LEFT JOIN `" . DB_PREFIX . "product_description` `pd` ON (`p`.`product_id` = `pd`.`product_id`) WHERE `p`.`product_id` = '" . (int)$product_id . "' AND `p`.`date_available` <= NOW() AND `p`.`status` = '1' AND `r`.`status` = '1' AND `pd`.`language_id` = '" . (int)$this->config->get('config_language_id') . "'");

		return (int)$query->row['total'];
	}

	/**
	 * Latest approved reviews store-wide (for homepage carousel).
	 *
	 * @param int $limit Max rows (capped)
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getLatestReviews(int $limit = 25): array {
		if ($limit < 1) {
			$limit = 25;
		}
		if ($limit > 100) {
			$limit = 100;
		}

		$query = $this->db->query("SELECT `r`.`review_id`, `r`.`author`, `r`.`rating`, `r`.`text`, `r`.`date_added`, `r`.`product_id`, `p`.`image`, `pd`.`name` AS `product_name` FROM `" . DB_PREFIX . "review` `r` INNER JOIN `" . DB_PREFIX . "product` `p` ON (`r`.`product_id` = `p`.`product_id`) INNER JOIN `" . DB_PREFIX . "product_to_store` `p2s` ON (`p`.`product_id` = `p2s`.`product_id`) INNER JOIN `" . DB_PREFIX . "product_description` `pd` ON (`p`.`product_id` = `pd`.`product_id`) WHERE `r`.`status` = '1' AND `p`.`status` = '1' AND `p`.`date_available` <= NOW() AND `pd`.`language_id` = '" . (int)$this->config->get('config_language_id') . "' AND `p2s`.`store_id` = '" . (int)$this->config->get('config_store_id') . "' ORDER BY `r`.`date_added` DESC LIMIT " . (int)$limit);

		return $query->rows;
	}
}
