<?php

// add a new config
$config = Updater::getConfigService();
if ( !$config->configExists('forum', 'delete_search_index_cron') )
{
    $config->addConfig('forum', 'delete_search_index_cron', 0, 'Delete search index');
}
