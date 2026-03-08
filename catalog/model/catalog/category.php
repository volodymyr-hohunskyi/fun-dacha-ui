<?php
namespace Opencart\Catalog\Model\Catalog;
/**
 * Class Category
 *
 * Can be called using $this->load->model('catalog/category');
 *
 * @package Opencart\Catalog\Model\Catalog
 */
class Category extends \Opencart\System\Engine\Model {
	/**
	 * Get Category
	 *
	 * Get the record of the category record in the database.
	 *
	 * @param int $category_id primary key of the category record
	 *
	 * @return array<string, mixed> category record that has category ID
	 *
	 * @example
	 *
	 * $this->load->model('catalog/category');
	 *
	 * $category_info = $this->model_catalog_category->getCategory($category_id);
	 */
	public function getCategory(int $category_id): array {
		$query = $this->db->query("SELECT DISTINCT * FROM `" . DB_PREFIX . "category` `c` LEFT JOIN `" . DB_PREFIX . "category_description` `cd` ON (`c`.`category_id` = `cd`.`category_id`) LEFT JOIN `" . DB_PREFIX . "category_to_store` `c2s` ON (`c`.`category_id` = `c2s`.`category_id`) WHERE `c`.`category_id` = '" . (int)$category_id . "' AND `cd`.`language_id` = '" . (int)$this->config->get('config_language_id') . "' AND `c2s`.`store_id` = '" . (int)$this->config->get('config_store_id') . "' AND `c`.`status` = '1'");

		return $query->row;
	}

	/**
	 * Get Categories
	 *
	 * Get the record of the category records in the database.
	 *
	 * @param int $parent_id primary key of the parent category record
	 *
	 * @return array<int, array<string, mixed>> category records that have parent ID
	 *
	 * @example
	 *
	 * $this->load->model('catalog/category');
	 *
	 * $categories = $this->model_catalog_category->getCategories();
	 */
	public function getCategories(int $parent_id = 0): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "category` `c` LEFT JOIN `" . DB_PREFIX . "category_description` `cd` ON (`c`.`category_id` = `cd`.`category_id`) LEFT JOIN `" . DB_PREFIX . "category_to_store` `c2s` ON (`c`.`category_id` = `c2s`.`category_id`) WHERE `c`.`parent_id` = '" . (int)$parent_id . "' AND `cd`.`language_id` = '" . (int)$this->config->get('config_language_id') . "' AND `c2s`.`store_id` = '" . (int)$this->config->get('config_store_id') . "' AND `c`.`status` = '1' ORDER BY `c`.`sort_order`, LCASE(`cd`.`name`)");

		return $query->rows;
	}

	/**
	 * Get attribute filters for category - collected from product attributes.
	 * Returns attribute groups with attributes and their distinct values from products in category.
	 *
	 * @param int $category_id primary key of the category record
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getAttributeFiltersForCategory(int $category_id): array {
		$lang = (int)$this->config->get('config_language_id');
		$store = (int)$this->config->get('config_store_id');
		$query = $this->db->query("SELECT DISTINCT ag.`attribute_group_id`, agd.`name` AS group_name, ag.`sort_order`
			FROM `" . DB_PREFIX . "product_attribute` pa
			INNER JOIN `" . DB_PREFIX . "product_to_category` p2c ON pa.`product_id` = p2c.`product_id` AND p2c.`category_id` = '" . (int)$category_id . "'
			INNER JOIN `" . DB_PREFIX . "product` p ON pa.`product_id` = p.`product_id` AND p.`status` = '1' AND p.`date_available` <= NOW()
			INNER JOIN `" . DB_PREFIX . "product_to_store` p2s ON pa.`product_id` = p2s.`product_id` AND p2s.`store_id` = '" . $store . "'
			INNER JOIN `" . DB_PREFIX . "attribute` a ON pa.`attribute_id` = a.`attribute_id`
			INNER JOIN `" . DB_PREFIX . "attribute_group` ag ON a.`attribute_group_id` = ag.`attribute_group_id`
			LEFT JOIN `" . DB_PREFIX . "attribute_group_description` agd ON ag.`attribute_group_id` = agd.`attribute_group_id` AND agd.`language_id` = '" . $lang . "'
			WHERE pa.`language_id` = '" . $lang . "'
			GROUP BY ag.`attribute_group_id` ORDER BY ag.`sort_order`, LCASE(agd.`name`)");
		$filter_group_data = [];
		foreach ($query->rows as $row) {
			$attr_query = $this->db->query("SELECT DISTINCT a.`attribute_id`, ad.`name` AS attribute_name, pa.`text` AS attribute_value
				FROM `" . DB_PREFIX . "product_attribute` pa
				INNER JOIN `" . DB_PREFIX . "product_to_category` p2c ON pa.`product_id` = p2c.`product_id` AND p2c.`category_id` = '" . (int)$category_id . "'
				INNER JOIN `" . DB_PREFIX . "product` p ON pa.`product_id` = p.`product_id` AND p.`status` = '1' AND p.`date_available` <= NOW()
				INNER JOIN `" . DB_PREFIX . "product_to_store` p2s ON pa.`product_id` = p2s.`product_id` AND p2s.`store_id` = '" . $store . "'
				INNER JOIN `" . DB_PREFIX . "attribute` a ON pa.`attribute_id` = a.`attribute_id` AND a.`attribute_group_id` = '" . (int)$row['attribute_group_id'] . "'
				LEFT JOIN `" . DB_PREFIX . "attribute_description` ad ON a.`attribute_id` = ad.`attribute_id` AND ad.`language_id` = '" . $lang . "'
				WHERE pa.`language_id` = '" . $lang . "'
				ORDER BY a.`sort_order`, LCASE(ad.`name`), pa.`text`");
			$filters = [];
			foreach ($attr_query->rows as $av) {
				$val = trim((string)$av['attribute_value']);
				if ($val !== '') {
					$filters[] = [
						'attribute_id'   => (int)$av['attribute_id'],
						'attribute_name' => $av['attribute_name'],
						'attribute_value' => $val,
					];
				}
			}
			if (!empty($filters)) {
				$filter_group_data[] = [
					'name'    => $row['group_name'] ?? '',
					'filters' => $filters,
				];
			}
		}
		return $filter_group_data;
	}

	/**
	 * Get Layout ID
	 *
	 * Get the record of the category layout record in the database.
	 *
	 * @param int $category_id primary key of the category record
	 *
	 * @return int layout record that has category ID
	 *
	 * @example
	 *
	 * $this->load->model('catalog/category');
	 *
	 * $layout_id = $this->model_catalog_category->getLayoutId($category_id);
	 */
	public function getLayoutId(int $category_id): int {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "category_to_layout` WHERE `category_id` = '" . (int)$category_id . "' AND `store_id` = '" . (int)$this->config->get('config_store_id') . "'");

		if ($query->num_rows) {
			return (int)$query->row['layout_id'];
		} else {
			return 0;
		}
	}
}
