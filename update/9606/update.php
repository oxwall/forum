<?php

$sql = array(
    'CREATE TABLE IF NOT EXISTS `' . OW_DB_PREFIX . 'forum_update_search_index` (
        `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
        `type` varchar(50) NOT NULL,
        `entityId` int(10) unsigned NOT NULL,
        `lastEntityId` int(10) unsigned DEFAULT NULL,
        `priority` tinyint(1) unsigned NOT NULL DEFAULT "0",
        PRIMARY KEY (`id`)
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