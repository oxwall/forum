<?php

/**
 * Copyright (c) 2009, Skalfa LLC
 * All rights reserved.

 * ATTENTION: This commercial software is intended for use with Oxwall Free Community Software http://www.oxwall.org/
 * and is licensed under Oxwall Store Commercial License.
 * Full text of this license can be found at http://www.oxwall.org/store/oscl
 */

$sql = "ALTER TABLE `".OW_DB_PREFIX."forum_post` ADD INDEX (`createStamp`);";

try
{
    Updater::getDbo()->query($sql);
}
catch ( Exception $e )
{
    Updater::getLogger()->addEntry(json_encode($e));
}