<?php
/**
 * @author Zarif Safiullin <zaph.work@gmail.com>
 * @package ow.ow_plugins.forum
 * @since 1.7.2
 */

$sql = array(
    'ALTER TABLE `' . OW_DB_PREFIX . 'forum_topic` ADD `status` ENUM("approval","approved","blocked") NOT NULL DEFAULT "approved" , ADD INDEX (`status`)'
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

Updater::getLanguageService()->importPrefixFromZip(dirname(__FILE__) . DS . 'langs.zip', 'forum');
