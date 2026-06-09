-- V3__player_data_in_game_name_length.sql
-- Description: Increase player_data.in_game_name length from 32 to 50
-- fixes error: [GMCore] [Player Controller] Error updating player data. Error: Data too long for column 'in_game_name' at row 1
-- Author: Pete Savarese
-- Date: 3/5/2026

-- ============================================================================
-- MIGRATION START
-- ============================================================================

ALTER TABLE `player_data`
  MODIFY COLUMN `in_game_name` varchar(50) DEFAULT '';
