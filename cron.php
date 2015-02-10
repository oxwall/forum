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

/**
 * Forum cron job.
 *
 * @author Egor Bulgakov <egor.bulgakov@gmail.com>
 * @package ow.ow_plugins.forum
 * @since 1.0
 */
class FORUM_Cron extends OW_Cron
{
    const TOPICS_DELETE_LIMIT = 5;
    
    const MEDIA_DELETE_LIMIT = 10;
    
    public function __construct()
    {
        parent::__construct();

        $this->addJob('topicsDeleteProcess', 1);
        
        $this->addJob('tempTopicsDeleteProcess', 60);
    }

    public function run()
    {
        
    }
    
    public function tempTopicsDeleteProcess()
    {
        $forumService = FORUM_BOL_ForumService::getInstance();
        
        $tmpTopics = $forumService->findTemporaryTopics(self::TOPICS_DELETE_LIMIT);
        
        if ( !$tmpTopics )
        {
            return;
        }
        
        foreach ( $tmpTopics as $topic )
        {
            $forumService->deleteTopic($topic['id']);
        }
    }

    public function topicsDeleteProcess()
    {
        $config = OW::getConfig();
        
        // check if uninstall is in progress
        if ( !$config->getValue('forum', 'uninstall_inprogress') )
        {
            return;
        }
        
        // check if cron queue is not busy
        if ( $config->getValue('forum', 'uninstall_cron_busy') )
        {
            return;
        }
        
        $config->saveConfig('forum', 'uninstall_cron_busy', 1);
        
        $forumService = FORUM_BOL_ForumService::getInstance();
        $forumService->deleteTopics(self::TOPICS_DELETE_LIMIT);
        
        $mediaPanelService = BOL_MediaPanelService::getInstance();
        $mediaPanelService->deleteImages('forum', self::MEDIA_DELETE_LIMIT);
        
        $config->saveConfig('forum', 'uninstall_cron_busy', 0);
        
        if ( (int) $forumService->countAllTopics() + (int) $mediaPanelService->countGalleryImages('forum') == 0 )
        {
            $config->saveConfig('forum', 'uninstall_inprogress', 0);
            BOL_PluginService::getInstance()->uninstall('forum');

            FORUM_BOL_ForumService::getInstance()->setMaintenanceMode(false);
        }
    }
}