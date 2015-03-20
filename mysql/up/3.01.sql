CREATE TABLE `gitrepo` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uniqid` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
  `repo` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
  `date` datetime DEFAULT NULL,
  `user_id` int(11) unsigned DEFAULT NULL,
  `published` tinyint(1) DEFAULT '0',
  `hits` int(11) unsigned DEFAULT '0',
  `subtitle` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `author` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `private` tinyint(1) unsigned DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci