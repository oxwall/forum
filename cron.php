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
    
    const SEARCH_QUEUE_ENTITIES_COUNT = 1;

    const UPDATE_SEARCH_INDEX_LIFE_TIME = 3600;

    public function __construct()
    {
        parent::__construct();

        $this->addJob('topicsDeleteProcess', 1);
        
        $this->addJob('tempTopicsDeleteProcess', 60);

        $this->addJob('updateSearchIndex', 1);
    }

    public function run()
    {
        
    }

    /**
     * Update search index
     * 
     * @throws Exception
     * @return void
     */
    public function updateSearchIndex()
    {
        $config = OW::getConfig();
        $cronBusyValue = (int) $config->getValue('forum', 'update_search_index_cron_busy');

        if ( time() <  $cronBusyValue)
        {
            return;
        }

        $config->saveConfig('forum', 'update_search_index_cron_busy', time() + self::UPDATE_SEARCH_INDEX_LIFE_TIME);
        $updateSearchIndexDao = FORUM_BOL_UpdateSearchIndexDao::getInstance();
        $queueEntities = $updateSearchIndexDao->findQueueEntities(self::SEARCH_QUEUE_ENTITIES_COUNT);

        if ( $queueEntities )
        {
            foreach ($queueEntities as $entity) 
            {   switch ($entity->type)
                {
                    // delete topic
                    case FORUM_BOL_UpdateSearchIndexDao::DELETE_TOPIC :
                        $this->deleteTopicFromSearchIndex($entity->entityId);
                        break;

                    // update topic
                    case FORUM_BOL_UpdateSearchIndexDao::UPDATE_TOPIC :
                        $this->updateTopicInSearchIndex($entity->entityId);
                        break;

                    // delete group
                    case FORUM_BOL_UpdateSearchIndexDao::DELETE_GROUP :
                        $this->deleteGroupFromSearchIndex($entity->entityId);
                        break;

                    // update group
                    case FORUM_BOL_UpdateSearchIndexDao::UPDATE_GROUP :
                        $this->updateGroupInSearchIndex($entity->entityId);
                        break;
                }

                $updateSearchIndexDao->delete($entity);
            }
        }

        $config->saveConfig('forum', 'update_search_index_cron_busy', 0);
    }

    /**
     * Update group
     * 
     * @param integer $groupId
     * @return void
     */
    private function updateGroupInSearchIndex( $groupId )
    {
        $forumService = FORUM_BOL_ForumService::getInstance();
        
        // get the group info
        $group = $forumService->findGroupById($groupId);

        if ( $group )
        {
            $topicPage = 1;

            // get group's topics 
            while ( true )
            {
                if ( null == ($topics = $forumService->getSimpleGroupTopicList($group->id, $topicPage)) )
                {
                    break;
                }

                // add topics into the group
                foreach ($topics as $topic)
                {
                    $this->getTextSearchService()->addTopic($topic);

                    // get topic's post list
                    $postPage = 1;
                    while ( true )
                    {
                        if ( null == ($posts = $forumService->getTopicPostList($topic->id, $postPage, false)) )
                        {
                            break;
                        }

                        // add posts into the topic
                        foreach ($posts as $post)
                        {
                            $this->getTextSearchService()->saveOrUpdatePost($post, true);   
                        }

                        $postPage++;
                    }
                }

                $topicPage++;
            }
        }            
    }

    /**
     * Delete group from the search index
     * 
     * @param integer $groupId
     * @return void
     */
    private function deleteGroupFromSearchIndex( $groupId )
    {
        $this->getTextSearchService()->deleteGroup($groupId);

        FORUM_BOL_UpdateSearchIndexDao::getInstance()->
                addQueue($groupId, FORUM_BOL_UpdateSearchIndexDao::UPDATE_GROUP);
    }

    /**
     * Update topic
     * 
     * @param integer $topicId
     * @return void
     */
    private function updateTopicInSearchIndex( $topicId )
    {
        $forumService = FORUM_BOL_ForumService::getInstance();

        // get the topic info
        $topic = $forumService->findTopicById($topicId);

        if ( $topic )
        {
            // add the topic
            $this->getTextSearchService()->addTopic($topic);

            $postPage = 1;

            // get topic's post list
            while ( true )
            {
                if ( null == ($posts = $forumService->getTopicPostList($topic->id, $postPage, false)) )
                {
                    break;
                }

                // add posts into the topic
                foreach ($posts as $post)
                {
                    $this->getTextSearchService()->saveOrUpdatePost($post, true);   
                }

                $postPage++;
            }
        }
    }

    /**
     * Delete topic from the search index
     * 
     * @param integer $topicId
     * @return void
     */
    private function deleteTopicFromSearchIndex( $topicId )
    {
        $this->getTextSearchService()->deleteTopic($topicId);

        FORUM_BOL_UpdateSearchIndexDao::getInstance()->
                addQueue($topicId, FORUM_BOL_UpdateSearchIndexDao::UPDATE_TOPIC);
    }

    /**
     * Get text search service
     * 
     * @return FORUM_BOL_TextSearchService
     */
    private function getTextSearchService()
    {
        return FORUM_BOL_TextSearchService::getInstance();
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