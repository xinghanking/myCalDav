CREATE TABLE IF NOT EXISTS `user`(
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `username` varchar(32) NOT NULL DEFAULT '',
    `password` varchar(32) NOT NULL DEFAULT '',
    `email` varchar(255) NOT NULL DEFAULT '',
    `home_uri` varchar(1024) NOT NULL DEFAULT '',
    `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `user` (`username`),
    UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin COMMENT='用户表';
CREATE TABLE IF NOT EXISTS  `prop_ns` (
    `id` tinyint unsigned NOT NULL AUTO_INCREMENT,
    `prefix` varchar(10) NOT NULL DEFAULT 'D',
    `uri` varchar(255) NOT NULL DEFAULT 'DAV:',
    `user_agent` varbinary(1024) NOT NULL DEFAULT '',
    `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `prefix`(`prefix`),
    UNIQUE KEY `uri` (`uri`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin COMMENT='命名空间表';
INSERT IGNORE INTO `prop_ns` (`id`, `prefix`, `uri`) VALUES (1, 'd', 'DAV:'),(2, 'c', 'urn:ietf:params:xml:ns:caldav'),(3, 'cs', 'http://calendarserver.org/ns/'),(4, 'ics', 'http://icalendar.org/ns/'),(5, 'card', 'urn:ietf:params:xml:ns:carddav'),(6, 'vc', 'urn:ietf:params:xml:ns:vcard'),(7, 'ical', 'http://apple.com/ns/ical/'),(8, 'dp', 'DAV:Push');
CREATE TABLE IF NOT EXISTS `calendar` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `uri` varchar(255) NOT NULL DEFAULT '',
    `owner_id` bigint unsigned NOT NULL DEFAULT '0',
    `tzid` varchar(20) NOT NULL DEFAULT 'Asia/Shanghai',
    `component_set` varchar(255) NOT NULL DEFAULT 'vevent,vtodo' COMMENT '支持的组件',
    `prop` json NOT NULL,
    `calscale` varchar(64) NOT NULL DEFAULT 'GREGORIAN',
    `comp_prop` varchar(512) NOT NULL DEFAULT '',
    `ics_data` mediumblob NOT NULL,
    `last_modified` char(40) GENERATED ALWAYS AS (json_unquote(json_extract(`prop`,_utf8mb4'$."d:getlastmodified"'))) STORED,
    `etag` varchar(255) GENERATED ALWAYS AS (json_unquote(json_extract(`prop`,_utf8mb4'$."d:getetag"'))) STORED COMMENT '修改标识',
    `sync_token` varchar(255) GENERATED ALWAYS AS (json_unquote(json_extract(`prop`,_utf8mb4'$."d:sync-token"'))) STORED,
    `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uri` (`uri`),
    KEY `owner_id` (`owner_id`),
    CONSTRAINT `calendar_ibfk_1` FOREIGN KEY (`owner_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin COMMENT='日历表';
CREATE TABLE IF NOT EXISTS `comp` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `calendar_id` bigint unsigned NOT NULL DEFAULT '0',
    `type` tinyint unsigned NOT NULL DEFAULT '0',
    `uri` varchar(255) NOT NULL DEFAULT '',
    `uid` varchar(255) NOT NULL DEFAULT '',
    `recurrence_id` varchar(255) NOT NULL DEFAULT '',
    `prop` json NOT NULL,
    `dtstart` int unsigned NOT NULL DEFAULT '0',
    `dtend` int unsigned NOT NULL DEFAULT '0',
    `comp_prop` json NOT NULL,
    `ics_data` text COLLATE utf8mb4_bin NOT NULL,
    `sequence` int unsigned NOT NULL DEFAULT '0',
    `last_modified` char(40) GENERATED ALWAYS AS (json_unquote(json_extract(`prop`,_utf8mb4'$."d:getlastmodified"'))) STORED,
    `etag` varchar(255) GENERATED ALWAYS AS (json_unquote(json_extract(`prop`,_utf8mb4'$."d:getetag"'))) STORED,
    `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `comp_ibfk_1` FOREIGN KEY (`calendar_id`) REFERENCES `calendar` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin COMMENT='日历组件表';
CREATE TABLE IF NOT EXISTS `timezone` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '唯一标识符',
    `calendar_id` bigint unsigned NOT NULL DEFAULT 0 COMMENT '日历id',
    `tzid` varchar(50) COLLATE utf8mb4_bin NOT NULL DEFAULT '',
    `standard` text COLLATE utf8mb4_bin,
    `daylight` text COLLATE utf8mb4_bin,
    `last_modified` varchar(16) NOT NULL DEFAULT '',
    `sequence` int unsigned NOT NULL DEFAULT '0',
    `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `calendar_id` (`calendar_id`,`tzid`),
    CONSTRAINT `timezone_ibfk_1` FOREIGN KEY (`calendar_id`) REFERENCES `calendar` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin COMMENT='时区表';
CREATE TABLE IF NOT EXISTS `sync_collect` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `owner_id` bigint unsigned NOT NULL DEFAULT '0',
    `calendar_id` bigint unsigned NOT NULL DEFAULT '0',
    `collect` json NOT NULL DEFAULT '',
    `sync_token` varchar(255) NOT NULL DEFAULT '',
    `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `sync_collect_ibfk_1` FOREIGN KEY (`owner_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `sync_collect_ibfk_2` FOREIGN KEY (`calendar_id`) REFERENCES `calendar` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin COMMENT='同步记录表';