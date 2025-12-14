<?php
namespace Opencart\Catalog\Controller\Information;

class Sitemap extends \Opencart\System\Engine\Controller {
    
    public function index(): void {
        $this->load->model('catalog/product');
        $this->load->model('catalog/category');
        $this->load->model('catalog/manufacturer');
        $this->load->model('design/seo_url');
        
        $output = '<?xml version="1.0" encoding="UTF-8"?>';
        $output .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        
        $base_url = $this->config->get('config_url');
        $language = $this->config->get('config_language');
        
        // Homepage
        $output .= '<url>';
        $output .= '<loc>' . rtrim($base_url, '/') . '/</loc>';
        $output .= '<changefreq>daily</changefreq>';
        $output .= '<priority>1.0</priority>';
        $output .= '<lastmod>' . date('Y-m-d') . '</lastmod>';
        $output .= '</url>';
        
        // Categories
        $categories = $this->model_catalog_category->getCategories(0);
        foreach ($categories as $category) {
            $category_url = $this->url->link('product/category', 'language=' . $language . '&path=' . $category['category_id'], true);
            $output .= '<url>';
            $output .= '<loc>' . htmlspecialchars($category_url, ENT_XML1, 'UTF-8') . '</loc>';
            $output .= '<changefreq>weekly</changefreq>';
            $output .= '<priority>0.8</priority>';
            $output .= '<lastmod>' . date('Y-m-d', strtotime($category['date_modified'] ?? 'now')) . '</lastmod>';
            $output .= '</url>';
            
            // Subcategories
            $subcategories = $this->model_catalog_category->getCategories($category['category_id']);
            foreach ($subcategories as $subcategory) {
                $subcategory_url = $this->url->link('product/category', 'language=' . $language . '&path=' . $category['category_id'] . '_' . $subcategory['category_id'], true);
                $output .= '<url>';
                $output .= '<loc>' . htmlspecialchars($subcategory_url, ENT_XML1, 'UTF-8') . '</loc>';
                $output .= '<changefreq>weekly</changefreq>';
                $output .= '<priority>0.7</priority>';
                $output .= '<lastmod>' . date('Y-m-d', strtotime($subcategory['date_modified'] ?? 'now')) . '</lastmod>';
                $output .= '</url>';
            }
        }
        
        // Products - get all active products
        $filter_data = [
            'start' => 0,
            'limit' => 10000 // Get all products
        ];
        
        $products = $this->model_catalog_product->getProducts($filter_data);
        foreach ($products as $product) {
            $product_url = $this->url->link('product/product', 'language=' . $language . '&product_id=' . $product['product_id'], true);
            
            // Use date_modified if available, otherwise date_added, otherwise current date
            $lastmod_date = 'now';
            if (!empty($product['date_modified']) && $product['date_modified'] != '0000-00-00 00:00:00') {
                $lastmod_date = $product['date_modified'];
            } elseif (!empty($product['date_added']) && $product['date_added'] != '0000-00-00 00:00:00') {
                $lastmod_date = $product['date_added'];
            }
            
            $output .= '<url>';
            $output .= '<loc>' . htmlspecialchars($product_url, ENT_XML1, 'UTF-8') . '</loc>';
            $output .= '<changefreq>weekly</changefreq>';
            $output .= '<priority>0.6</priority>';
            $output .= '<lastmod>' . date('Y-m-d', strtotime($lastmod_date)) . '</lastmod>';
            $output .= '</url>';
        }
        
        // Information pages
        $this->load->model('catalog/information');
        $informations = $this->model_catalog_information->getInformations();
        foreach ($informations as $information) {
            $info_url = $this->url->link('information/information', 'language=' . $language . '&information_id=' . $information['information_id'], true);
            $output .= '<url>';
            $output .= '<loc>' . htmlspecialchars($info_url, ENT_XML1, 'UTF-8') . '</loc>';
            $output .= '<changefreq>monthly</changefreq>';
            $output .= '<priority>0.5</priority>';
            $output .= '<lastmod>' . date('Y-m-d', strtotime($information['date_modified'] ?? 'now')) . '</lastmod>';
            $output .= '</url>';
        }
        
        $output .= '</urlset>';
        
        $this->response->addHeader('Content-Type: application/xml; charset=utf-8');
        $this->response->setOutput($output);
    }
}
