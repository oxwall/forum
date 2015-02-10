<?php

/**
 * This software is intended for use with Oxwall Free Community Software http://www.oxwall.org/ and is
 * licensed under The BSD license.

 * ---
 * Copyright (c) 2011, Oxwall Foundation
 * All rights reserved.

 * Redistribution and use in source and binary forms, with or without modification, are permitted provided that the
 * following conditions are met:
 *
 *  - Redistributions of source code must retain the above copyright notice, this list of conditions and
 *  the following disclaimer.
 *
 *  - Redistributions in binary form must reproduce the above copyright notice, this list of conditions and
 *  the following disclaimer in the documentation and/or other materials provided with the distribution.
 *
 *  - Neither the name of the Oxwall Foundation nor the names of its contributors may be used to endorse or promote products
 *  derived from this software without specific prior written permission.

 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 * PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO,
 * PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED
 * AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

$exArr = array();

try
{
    $sql = "CREATE TABLE IF NOT EXISTS `".OW_DB_PREFIX."forum_subscription` (
      `id` int(11) NOT NULL auto_increment,
      `userId` int(11) NOT NULL,
      `topicId` int(11) NOT NULL,
      PRIMARY KEY  (`id`),
      UNIQUE KEY `userId` (`userId`,`topicId`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
    
    Updater::getDbo()->query($sql);
}
catch ( Exception $e ){ $exArr[] = $e; }

try
{
    Updater::getDbo()->query("ALTER TABLE `" . OW_DB_PREFIX . "forum_post` ADD FULLTEXT `post_text` (`text`)");
}
catch ( Exception $e ){ $exArr[] = $e; }

try
{    
    Updater::getDbo()->query("ALTER TABLE `" . OW_DB_PREFIX . "forum_topic` ADD FULLTEXT `topic_title` (`title`)");
}
catch ( Exception $e ){ $exArr[] = $e; }

Updater::getLanguageService()->importPrefixFromZip(dirname(__FILE__) . DS . 'langs.zip', 'forum');

try 
{
    $groupId = Updater::getAuthorizationService()->findGroupIdByName('forum');
    
    if ( $groupId )
    {
        $action = new BOL_AuthorizationAction();
        $action->name = 'subscribe';
        $action->groupId = $groupId;
        $action->availableForGuest = false;
        
        Updater::getAuthorizationService()->addAction($action, array('en' => 'Subscribe to forum topics'));
    }
}
catch ( Exception $e ) { $exArr[] = $e; }

print_r($exArr);
