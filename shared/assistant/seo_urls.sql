-- Pretty URL for the full-page garden assistant (OpenCart 4 `seo_url` table).
-- Replace `oc_` with your DB_PREFIX, and set `store_id` / `language_id` to match your shop.
-- Keyword must be unique per store; pick another slug if `garden-assistant` is taken.
--
-- Query string matched by rewrite: route=assistant/page
-- (see catalog URL: index.php?route=assistant/page&language=uk-ua)

INSERT INTO `oc_seo_url` (`store_id`, `language_id`, `key`, `value`, `keyword`, `sort_order`) VALUES
(0, 1, 'route', 'assistant/page', 'garden-assistant', 0);

-- Repeat for other storefront languages, e.g. Ukrainian:
-- INSERT INTO `oc_seo_url` (`store_id`, `language_id`, `key`, `value`, `keyword`, `sort_order`) VALUES
-- (0, 2, 'route', 'assistant/page', 'pomichnyk-sadu', 0);
