# DDLs

## DiscordLink

```sql
-- gmcore_core_sandbox.discord_link definition

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
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

## DiscordRoleForumGroup

```sql
-- gmcore_core_sandbox.discord_role_forum_group definition

CREATE TABLE `discord_role_forum_group` (
  `mapping_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `discord_role_id` varchar(20) NOT NULL,
  `forum_group_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `created_by_forum_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`mapping_id`),
  UNIQUE KEY `uq_role_group` (`discord_role_id`,`forum_group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```