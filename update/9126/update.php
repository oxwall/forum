<?php

Updater::getLanguageService()->deleteLangKey('forum', 'please_enter_keyword_or_user_name');
Updater::getLanguageService()->importPrefixFromZip(dirname(__FILE__) . DS . 'langs.zip', 'forum');

