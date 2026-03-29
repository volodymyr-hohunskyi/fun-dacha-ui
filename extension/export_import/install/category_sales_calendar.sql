-- Category sales calendar (seasonal promo windows by category). Replace oc_ with your DB_PREFIX if needed.
CREATE TABLE IF NOT EXISTS `oc_category_sales_calendar` (
  `category_id` int(11) NOT NULL,
  `discount_percent` varchar(16) NOT NULL DEFAULT '10%',
  `period_start` varchar(10) NOT NULL DEFAULT '',
  `period_end` varchar(10) NOT NULL DEFAULT '',
  `note` text NOT NULL,
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
