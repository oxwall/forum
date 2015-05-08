<?php

$sql = array(
    'ALTER  TABLE `' . OW_DB_PREFIX . 'forum_topic` CHANGE `status` `status` ENUM("approval","approved") NOT NULL DEFAULT "approved"',
    'ALTER  TABLE `' . OW_DB_PREFIX . 'forum_topic` DROP INDEX `topic_title`',
    'ALTER  TABLE `' . OW_DB_PREFIX . 'forum_post`  DROP INDEX `post_text`',
    'CREATE TABLE `' . OW_DB_PREFIX . 'forum_update_search_index` (
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

// add a new config
$config = Updater::getConfigService();
if ( !$config->configExists('forum', 'update_search_index_cron_busy') )
{
    $config->addConfig('forum', 'update_search_index_cron_busy', 0, 'Update search index queue is busy');
}

// put all forum groups into the search index
$query = 'SELECT `id` FROM `' . OW_DB_PREFIX . 'forum_group`';
$forumGroups = Updater::getDbo()->queryForList($query);

foreach ($forumGroups as $forumGroup)
{
    Updater::getDbo()->query('INSERT INTO `' . OW_DB_PREFIX . 'forum_update_search_index` SET `entityId` = ?, `type` = ?', array(
        $forumGroup['id'],
        'update_group'
    ));
}

Updater::getLanguageService()->importPrefixFromZip(dirname(__FILE__) . DS . 'langs.zip', 'forum');