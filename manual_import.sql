CREATE TABLE IF NOT EXISTS `achievements` (
  `ac_user` int(11) NOT NULL,
  `ac_id` varchar(255) DEFAULT NULL,
  `ac_count` int(11) DEFAULT 0,
  `ac_date` binary(14) DEFAULT NULL,
  UNIQUE KEY `ac_user` (`ac_user`,`ac_id`)
);