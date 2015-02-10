<?php

$sql = "ALTER TABLE `".OW_DB_PREFIX."forum_group` ADD `isPrivate` TINYINT( 1 ) NULL DEFAULT '0';";

try {
    Updater::getDbo()->query($sql);
}
catch ( Exception $e ){ $exArr[] = $e; }


$sql = "ALTER TABLE `".OW_DB_PREFIX."forum_group` ADD `roles` TEXT NULL DEFAULT NULL;";

try {
    Updater::getDbo()->query($sql);
}
catch ( Exception $e ){ $exArr[] = $e; }


Updater::getLanguageService()->importPrefixFromZip(dirname(__FILE__) . DS . 'langs.zip', 'forum');