<?php

$sql = array(
    'ALTER  TABLE `' . OW_DB_PREFIX . 'forum_topic` CHANGE `status` `status` ENUM("approval","approved") NOT NULL DEFAULT "approved"',
    'ALTER  TABLE `' . OW_DB_PREFIX . 'forum_topic` DROP INDEX `topic_title`',
    'ALTER  TABLE `' . OW_DB_PREFIX . 'forum_post`  DROP INDEX `post_text`',
    'CREATE TABLE `' . OW_DB_PREFIX . 'forum_update_search_index` (
        `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
        `type` enum("move_topic") NOT NULL DEFAULT "move_topic",
        `entityId` int(10) unsigned NOT NULL,
        `updateStatus` enum("not_started","in_process") NOT NULL,
        PRIMARY KEY (`id`),
        KEY `updateStatus` (`updateStatus`)
    ) ENGINE=MyIsam DEFAULT CHARSET=utf8'
);

foreach ( $sql as $query )
{
    try
    {
        Updater::getDbo()->query($query);
    }
    catch ( Exception $e )
    {
        Updater::getLogger()->addEntry(json_encode($e));
    }
}