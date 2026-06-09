-- V2__pointshop_items_table.sql
-- Description: Create pointshop_items table and migrate existing JSON item data
-- Author: My Dime Is Up
-- Date: 2/7/2026

-- ============================================================================
-- MIGRATION START
-- ============================================================================

CREATE TABLE IF NOT EXISTS `pointshop_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `item_uuid` char(36) NOT NULL,
  `steamid64` varchar(20) NOT NULL,
  `item_id` varchar(128) NOT NULL,
  `equipped` tinyint(1) NOT NULL DEFAULT 0,
  `modifiers` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pointshop_items_steamid_item` (`steamid64`, `item_id`),
  KEY `idx_pointshop_items_steamid64` (`steamid64`),
  KEY `idx_pointshop_items_uuid` (`item_uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrate existing pointshop_data JSON into pointshop_items
INSERT INTO `pointshop_items` (`item_uuid`, `steamid64`, `item_id`, `equipped`, `modifiers`)
SELECT
  UUID(),
  pd.`steamid64`,
  jt.`item_id`,
  CASE
    WHEN JSON_UNQUOTE(JSON_EXTRACT(pd.`pointshop_data`, CONCAT('$.', jt.`item_id`, '.Equipped'))) IN ('true', '1') THEN 1
    ELSE 0
  END AS `equipped`,
  COALESCE(JSON_UNQUOTE(JSON_EXTRACT(pd.`pointshop_data`, CONCAT('$.', jt.`item_id`, '.Modifiers'))), '[]') AS `modifiers`
FROM `player_data` pd
JOIN JSON_TABLE(JSON_KEYS(pd.`pointshop_data`), '$[*]' COLUMNS (
  `item_id` varchar(128) PATH '$'
)) jt
WHERE pd.`pointshop_data` IS NOT NULL
  AND pd.`pointshop_data` <> ''
  AND pd.`pointshop_data` <> '{}'
ON DUPLICATE KEY UPDATE
  `equipped` = VALUES(`equipped`),
  `modifiers` = VALUES(`modifiers`);
