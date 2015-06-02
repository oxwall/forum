<?php

Updater::getNavigationService()->addMenuItem(OW_Navigation::MOBILE_TOP, 'forum-default', 'forum', 'forum_mobile', OW_Navigation::VISIBLE_FOR_ALL);

Updater::getLanguageService()->importPrefixFromZip(dirname(__FILE__) . DS . 'langs.zip', 'forum');

