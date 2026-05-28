/*M!999999\- enable the sandbox mode */
-- MariaDB dump 10.19-12.1.2-MariaDB, for Linux (x86_64)
--
-- Host: localhost    Database: gmcore_core
-- ------------------------------------------------------
-- Server version	10.11.14-MariaDB-0ubuntu0.24.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;

--
-- Temporary table structure for view `active_bans`
--

DROP TABLE IF EXISTS `active_bans`;
/*!50001 DROP VIEW IF EXISTS `active_bans`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `active_bans` AS SELECT
 1 AS `id`,
  1 AS `steamid`,
  1 AS `name`,
  1 AS `reason`,
  1 AS `banned_on`,
  1 AS `unban_time`,
  1 AS `banned_by`,
  1 AS `banned_by_steamid`,
  1 AS `server`,
  1 AS `ban_status`,
  1 AS `priority` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `bans`
--

DROP TABLE IF EXISTS `bans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `steamid` varchar(32) NOT NULL,
  `name` varchar(100) NOT NULL,
  `reason` text DEFAULT NULL,
  `banned_on` int(11) NOT NULL,
  `unban_time` int(11) NOT NULL DEFAULT 0,
  `banned_by` varchar(100) DEFAULT NULL,
  `banned_by_steamid` varchar(32) DEFAULT NULL,
  `server` varchar(100) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `void` varchar(10) DEFAULT NULL,
  `void_user` varchar(100) DEFAULT NULL,
  `void_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `unbanned_at` timestamp NULL DEFAULT NULL,
  `active_ban_key` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_active_ban` (`active_ban_key`),
  KEY `idx_steamid` (`steamid`),
  KEY `idx_server` (`server`),
  KEY `idx_status_void` (`status`,`void`),
  KEY `idx_unban_time` (`unban_time`),
  KEY `idx_banned_on` (`banned_on`)
) ENGINE=InnoDB AUTO_INCREMENT=2320 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `chat`
--

DROP TABLE IF EXISTS `chat`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `server` varchar(255) DEFAULT 'Unknown',
  `type` int(2) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `steamid` varchar(50) DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  `text` text DEFAULT NULL,
  `time` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=438157 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `discord_link`
--

DROP TABLE IF EXISTS `discord_link`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `discord_link` (
  `link_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `forum_id` int(10) unsigned NOT NULL,
  `discord_id` varchar(20) NOT NULL,
  `unlinked_by_forum_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `unlinked_at` timestamp NULL DEFAULT NULL,
  `active_forum_id` int(10) unsigned GENERATED ALWAYS AS (case when `unlinked_at` is null then `forum_id` else NULL end) VIRTUAL,
  `active_discord_id` varchar(20) GENERATED ALWAYS AS (case when `unlinked_at` is null then `discord_id` else NULL end) VIRTUAL,
  PRIMARY KEY (`link_id`),
  UNIQUE KEY `uq_active_forum_id` (`active_forum_id`),
  UNIQUE KEY `uq_active_discord_id` (`active_discord_id`)
) ENGINE=InnoDB AUTO_INCREMENT=117 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `discord_role_forum_group`
--

DROP TABLE IF EXISTS `discord_role_forum_group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `discord_role_forum_group` (
  `mapping_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `discord_role_id` varchar(20) NOT NULL,
  `forum_group_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `created_by_forum_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`mapping_id`),
  UNIQUE KEY `uq_role_group` (`discord_role_id`,`forum_group_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lottery_entries`
--

DROP TABLE IF EXISTS `lottery_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lottery_entries` (
  `steamid` varchar(20) NOT NULL,
  `player_name` varchar(64) NOT NULL,
  `tickets` int(11) NOT NULL,
  PRIMARY KEY (`steamid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lottery_history`
--

DROP TABLE IF EXISTS `lottery_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lottery_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `steamid` varchar(20) NOT NULL,
  `player_name` varchar(64) NOT NULL,
  `tickets` int(11) NOT NULL,
  `jackpot_won` int(11) NOT NULL,
  `is_claimed` tinyint(3) DEFAULT 0,
  `date_won` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=446 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `onlinelog`
--

DROP TABLE IF EXISTS `onlinelog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `onlinelog` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `steamid` varchar(30) DEFAULT NULL,
  `server` varchar(30) DEFAULT NULL,
  `ip` varchar(30) DEFAULT NULL,
  `token` varchar(30) DEFAULT NULL,
  `fsowner` varchar(30) DEFAULT NULL,
  `clientsid` varchar(30) DEFAULT NULL,
  `connect` int(11) DEFAULT NULL,
  `disconnect` int(11) DEFAULT NULL,
  `playtime` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sid connect` (`steamid`,`connect`,`server`) USING BTREE,
  KEY `Index 3` (`steamid`,`connect`,`disconnect`),
  KEY `idx_onlinelog_steamid` (`steamid`),
  KEY `idx_onlinelog_playtime_steamid` (`playtime`,`steamid`)
) ENGINE=InnoDB AUTO_INCREMENT=54284 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `player_data`
--

DROP TABLE IF EXISTS `player_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `player_data` (
  `steamid64` varchar(21) NOT NULL DEFAULT '',
  `in_game_name` varchar(32) DEFAULT '',
  `previous_names` mediumtext DEFAULT NULL,
  `last_seen` bigint(20) DEFAULT 0,
  `ip` varchar(16) DEFAULT '',
  `points` bigint(20) DEFAULT 0,
  `pointshop_data` longtext DEFAULT NULL CHECK (json_valid(`pointshop_data`)),
  `pdata` longtext DEFAULT '[]',
  `ip_history` longtext DEFAULT NULL CHECK (json_valid(`ip_history`)),
  PRIMARY KEY (`steamid64`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `player_dbdtime`
--

DROP TABLE IF EXISTS `player_dbdtime`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `player_dbdtime` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `steamid64` varchar(50) NOT NULL,
  `play_table` mediumtext NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=34261 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `player_notes`
--

DROP TABLE IF EXISTS `player_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `player_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `note` longtext NOT NULL,
  `sidfor` varchar(20) NOT NULL,
  `by` varchar(75) NOT NULL,
  `added` int(11) NOT NULL,
  `visibility` varchar(45) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1942 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `players_stats`
--

DROP TABLE IF EXISTS `players_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `players_stats` (
  `serverid` int(11) NOT NULL,
  `steamid64` varchar(21) NOT NULL,
  `date` datetime NOT NULL DEFAULT current_timestamp(),
  `minutes` int(11) NOT NULL DEFAULT 0,
  `kills` int(11) NOT NULL DEFAULT 0,
  `traitor_round_top_kills` int(11) NOT NULL DEFAULT 0,
  `traitor_kills_traitor` int(11) NOT NULL DEFAULT 0,
  `traitor_kills_innocent` int(11) NOT NULL DEFAULT 0,
  `traitor_kills_detective` int(11) NOT NULL DEFAULT 0,
  `innocent_kills_traitor` int(11) NOT NULL DEFAULT 0,
  `innocent_kills_innocent` int(11) NOT NULL DEFAULT 0,
  `innocent_kills_detective` int(11) NOT NULL DEFAULT 0,
  `detective_kills_traitor` int(11) NOT NULL DEFAULT 0,
  `detective_kills_innocent` int(11) NOT NULL DEFAULT 0,
  `detective_kills_detective` int(11) NOT NULL DEFAULT 0,
  `deaths` int(11) NOT NULL DEFAULT 0,
  `traitor_deaths` int(11) NOT NULL DEFAULT 0,
  `innocent_deaths` int(11) NOT NULL DEFAULT 0,
  `detective_deaths` int(11) NOT NULL DEFAULT 0,
  `suicides` int(11) NOT NULL DEFAULT 0,
  `headshots` int(11) NOT NULL DEFAULT 0,
  `killed_traitors_with_dna` int(11) NOT NULL DEFAULT 0,
  `traitor_rounds` int(11) NOT NULL DEFAULT 0,
  `traitor_wins` int(11) NOT NULL DEFAULT 0,
  `traitor_losses` int(11) NOT NULL DEFAULT 0,
  `traitor_rounds_survived` int(11) NOT NULL DEFAULT 0,
  `innocent_rounds` int(11) NOT NULL DEFAULT 0,
  `innocent_wins` int(11) NOT NULL DEFAULT 0,
  `innocent_losses` int(11) NOT NULL DEFAULT 0,
  `innocent_rounds_survived` int(11) NOT NULL DEFAULT 0,
  `detective_rounds` int(11) NOT NULL DEFAULT 0,
  `detective_wins` int(11) NOT NULL DEFAULT 0,
  `detective_losses` int(11) NOT NULL DEFAULT 0,
  `detective_rounds_survived` int(11) NOT NULL DEFAULT 0,
  `specdm_kills` int(11) NOT NULL DEFAULT 0,
  `specdm_deaths` int(11) NOT NULL DEFAULT 0,
  `prop_kills` int(11) NOT NULL DEFAULT 0,
  `force_kills` int(11) NOT NULL DEFAULT 0,
  `traitor_contribution` int(11) NOT NULL DEFAULT 0,
  `innocent_contribution` int(11) NOT NULL DEFAULT 0,
  `highest_kills_on_traitors` int(11) NOT NULL DEFAULT 0,
  `highest_kills_on_innos` int(11) NOT NULL DEFAULT 0,
  `created_on` datetime NOT NULL DEFAULT current_timestamp(),
  `last_seen_on` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_on` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`serverid`,`steamid64`),
  KEY `serverid` (`serverid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `punishments`
--

DROP TABLE IF EXISTS `punishments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `punishments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `steamid` varchar(21) NOT NULL,
  `server` varchar(100) NOT NULL,
  `punishment` varchar(25) NOT NULL,
  `added` int(11) NOT NULL,
  `admin` varchar(50) NOT NULL,
  `reason` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=21629 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `servers`
--

DROP TABLE IF EXISTS `servers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `servers` (
  `id` int(11) NOT NULL,
  `hostName` varchar(75) DEFAULT NULL,
  `niceName` char(20) DEFAULT NULL,
  `serverAddr` varchar(40) NOT NULL,
  `currentMap` varchar(100) NOT NULL,
  `playerCount` int(11) NOT NULL,
  `maxCount` int(11) NOT NULL,
  `staffCount` int(11) NOT NULL,
  `total_reports` int(11) NOT NULL,
  `active_reports` int(11) NOT NULL,
  `playersJSON` mediumtext NOT NULL,
  `lastUpdate` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping routines for database 'gmcore_core'
--
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'IGNORE_SPACE,STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `CleanupExpiredBans` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE  PROCEDURE `CleanupExpiredBans`()
BEGIN
    UPDATE bans
    SET status = 0
    WHERE unban_time > 0
      AND unban_time < UNIX_TIMESTAMP()
      AND status = 1;

    SELECT ROW_COUNT() as cleaned_bans;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'IGNORE_SPACE,STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `CleanupExpiredBansForServer` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE  PROCEDURE `CleanupExpiredBansForServer`(
    IN p_server VARCHAR(64)
)
BEGIN
    DECLARE cleaned_count INT DEFAULT 0;

    UPDATE bans
    SET status = 0
    WHERE server = p_server
      AND unban_time > 0
      AND unban_time < UNIX_TIMESTAMP()
      AND status = 1;

    SET cleaned_count = ROW_COUNT();

    SELECT
        cleaned_count as cleaned_bans,
        p_server as server_name,
        (SELECT COUNT(*) FROM bans WHERE server = p_server AND status = 1 AND void = 'N') as active_bans_remaining;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'IGNORE_SPACE,STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `GetPlayerBan` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE  PROCEDURE `GetPlayerBan`(
	IN p_steamid VARCHAR(32),
	IN p_server VARCHAR(64))
BEGIN
    -- First, clean up expired bans for this specific player
    -- This is more efficient than cleaning all expired bans
    UPDATE bans
    SET status = 0, unbanned_at = CURRENT_TIMESTAMP()
    WHERE steamid COLLATE utf8mb4_unicode_ci = p_steamid COLLATE utf8mb4_unicode_ci
      AND unban_time > 0
      AND unban_time < UNIX_TIMESTAMP()
      AND status = 1;

    -- Now get the active ban for the player
    SELECT
        id, server, reason, unban_time, banned_by, banned_on,
        CASE
            WHEN server COLLATE utf8mb4_unicode_ci = 'Global' COLLATE utf8mb4_unicode_ci THEN 1
            ELSE 2
        END as priority,
        CASE
            WHEN unban_time = 0 THEN 'Permanent'
            WHEN unban_time > UNIX_TIMESTAMP() THEN 'Active'
            ELSE 'Expired'
        END as ban_status
    FROM bans
    WHERE steamid COLLATE utf8mb4_unicode_ci = p_steamid COLLATE utf8mb4_unicode_ci
      AND (server COLLATE utf8mb4_unicode_ci = 'Global' COLLATE utf8mb4_unicode_ci
           OR server COLLATE utf8mb4_unicode_ci = p_server COLLATE utf8mb4_unicode_ci)
      AND (unban_time > UNIX_TIMESTAMP() OR unban_time = 0)
      AND void COLLATE utf8mb4_unicode_ci = 'N' COLLATE utf8mb4_unicode_ci
      AND status = 1
    ORDER BY priority ASC, banned_on DESC
    LIMIT 1;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Final view structure for view `active_bans`
--

/*!50001 DROP VIEW IF EXISTS `active_bans`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013  SQL SECURITY INVOKER */
/*!50001 VIEW `active_bans` AS select `gmcore_core`.`bans`.`id` AS `id`,`gmcore_core`.`bans`.`steamid` AS `steamid`,`gmcore_core`.`bans`.`name` AS `name`,`gmcore_core`.`bans`.`reason` AS `reason`,`gmcore_core`.`bans`.`banned_on` AS `banned_on`,`gmcore_core`.`bans`.`unban_time` AS `unban_time`,`gmcore_core`.`bans`.`banned_by` AS `banned_by`,`gmcore_core`.`bans`.`banned_by_steamid` AS `banned_by_steamid`,`gmcore_core`.`bans`.`server` AS `server`,case when `gmcore_core`.`bans`.`unban_time` = 0 then 'Permanent' when `gmcore_core`.`bans`.`unban_time` > unix_timestamp() then 'Active' else 'Expired' end AS `ban_status`,case when `gmcore_core`.`bans`.`server` collate utf8mb4_unicode_ci = 'Global' collate utf8mb4_unicode_ci then 1 else 2 end AS `priority` from `bans` where `gmcore_core`.`bans`.`status` = 1 */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

-- Dump completed on 2026-01-24 14:28:04
