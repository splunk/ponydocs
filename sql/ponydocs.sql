CREATE TABLE `ponydocs_doclinks` (
	`from_link` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
	`to_link` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
	KEY `pdl_to_link` (`to_link`(50)),
	KEY `pdl_from_link` (`from_link`(50))
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE `ponydocs_cache` (
	`cachekey` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
	`expires` INT NOT NULL DEFAULT 0,
	`data` longtext COLLATE utf8_unicode_ci,
	PRIMARY KEY  (`cachekey`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
