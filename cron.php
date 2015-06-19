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

    const UPDATE_SEARCH_INDEX_LIFE_TIME = 3600;

    const UPDATE_SEARCH_INDEX_MAX_TIME = 10;

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
     * Get update search index dao
     * 
     * @return FORUM_BOL_UpdateSearchIndexDao
     */
    private function getUpdateSearchIndexDao()
    {
        return FORUM_BOL_UpdateSearchIndexDao::getInstance();
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

        // stop cron task execution
        if ( time() <  $cronBusyValue)
        {
            return;
        }

        // in process
        $config->saveConfig('forum', 'update_search_index_cron_busy', time() + self::UPDATE_SEARCH_INDEX_LIFE_TIME);
        $maxExecutionTime = time() + self::UPDATE_SEARCH_INDEX_MAX_TIME;

        while ( time() <  $maxExecutionTime )
        {
            // do we have any queue
            if ( null == ($firstQueue = $this->getUpdateSearchIndexDao()->findFirstQueue()) )
            {
                break;
            }

            // process queue
            switch ($firstQueue->type)
            {
                 // delete topic
                 case FORUM_BOL_UpdateSearchIndexDao::DELETE_TOPIC :
                     $result = $this->deleteTopicFromSearchIndex($firstQueue->entityId);
                     break;

                 // update topic
                 case FORUM_BOL_UpdateSearchIndexDao::UPDATE_TOPIC :
                     $result = $this->updateTopicInSearchIndex($firstQueue->entityId);
                     break;

                 // update topic posts
                 case FORUM_BOL_UpdateSearchIndexDao::UPDATE_TOPIC_POSTS :
                     $result = $this->
                         updateTopicPostsInSearchIndex($firstQueue->entityId, $firstQueue, $maxExecutionTime);
                     break;

                 // delete group
                 case FORUM_BOL_UpdateSearchIndexDao::DELETE_GROUP :
                     $result = $this->deleteGroupFromSearchIndex($firstQueue->entityId);
                     break;

                 // update group
                 case FORUM_BOL_UpdateSearchIndexDao::UPDATE_GROUP :
                     $result = $this->
                         updateGroupInSearchIndex($firstQueue->entityId, $firstQueue, $maxExecutionTime);
                     break;

                 default :
                     $result = true;
             }

             if ( !$result )
             {
                 break;
             }

            // task successfully completed 
            $this->getUpdateSearchIndexDao()->delete($firstQueue);
        }

        // check the plugin state       
        $pluginForum = BOL_PluginDao::getInstance()->findPluginByKey('forum');

        if ( !$pluginForum || !$pluginForum->isActive )
        {
            $this->getTextSearchService()->deactivateEntities();
        }

        // finished
        $config->saveConfig('forum', 'update_search_index_cron_busy', 0);
    }

    /**
     * Update group
     * 
     * @param integer $groupId
     * @param FORUM_BOL_UpdateSearchIndex $firstQueue
     * @param integer $maxExecutionTime
     * @return boolean
     */
    private function updateGroupInSearchIndex( $groupId, FORUM_BOL_UpdateSearchIndex $firstQueue, $maxExecutionTime )
    {
        $forumService = FORUM_BOL_ForumService::getInstance();

        // get the group info
        $group = $forumService->findGroupById($groupId);

        if ( $group )
        {
            $topicPage = 1;
            $lastEntityId = $firstQueue->lastEntityId; 

            // get group's topics 
            while ( time() <  $maxExecutionTime )
            {
                if ( null == ($topics = $forumService->
                        getSimpleGroupTopicList($group->id, $topicPage, $lastEntityId)) )
                {
                    return true;
                }

                // add topics into the group
                foreach ($topics as $topic)
                {
                    $this->getTextSearchService()->addTopic($topic);

                    // remember the last post id
                    $firstQueue->lastEntityId = $topic->id;
                    $this->getUpdateSearchIndexDao()->save($firstQueue);

                    // update topic's posts
                    FORUM_BOL_UpdateSearchIndexDao::getInstance()->addQueue($topic->id, 
                        FORUM_BOL_UpdateSearchIndexDao::UPDATE_TOPIC_POSTS, FORUM_BOL_UpdateSearchIndexDao::HIGH_PRIORITY);
                }

                $topicPage++;
            }

            return false;
        }

        return true;
    }

    /**
     * Delete group from the search index
     * 
     * @param integer $groupId
     * @return boolean
     */
    private function deleteGroupFromSearchIndex( $groupId )
    {
        $this->getTextSearchService()->deleteGroup($groupId);

        FORUM_BOL_UpdateSearchIndexDao::getInstance()->addQueue($groupId, 
                FORUM_BOL_UpdateSearchIndexDao::UPDATE_GROUP, FORUM_BOL_UpdateSearchIndexDao::HIGH_PRIORITY);

        return true;
    }

    /**
     * Delete topic from the search index
     * 
     * @param integer $topicId
     * @return boolean
     */
    private function deleteTopicFromSearchIndex( $topicId )
    {
        $this->getTextSearchService()->deleteTopic($topicId);

        FORUM_BOL_UpdateSearchIndexDao::getInstance()->addQueue($topicId, 
                FORUM_BOL_UpdateSearchIndexDao::UPDATE_TOPIC, FORUM_BOL_UpdateSearchIndexDao::HIGH_PRIORITY);

        return true;
    }

    /**
     * Update topic
     * 
     * @param integer $topicId
     * @return boolean
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

            FORUM_BOL_UpdateSearchIndexDao::getInstance()->addQueue($topicId, 
                    FORUM_BOL_UpdateSearchIndexDao::UPDATE_TOPIC_POSTS, FORUM_BOL_UpdateSearchIndexDao::HIGH_PRIORITY);
        }

        return true;
    }

    /**
     * Update topic posts
     * 
     * @param integer $topicId
     * @param FORUM_BOL_UpdateSearchIndex $firstQueue
     * @param integer $maxExecutionTime
     * @return boolean
     */
    private function updateTopicPostsInSearchIndex( $topicId, FORUM_BOL_UpdateSearchIndex $firstQueue, $maxExecutionTime )
    {
        $forumService = FORUM_BOL_ForumService::getInstance();

        // get the topic info
        $topic = $forumService->findTopicById($topicId);

        if ( $topic )
        {
            $postPage = 1;
            $lastEntityId = $firstQueue->lastEntityId;

            // get topic's post list
            while ( time() <  $maxExecutionTime )
            {
                if ( null == ($posts = $forumService->
                        getSimpleTopicPostList($topic->id, $postPage, $lastEntityId)) )
                {
                    return true;
                }

                // add posts into the topic
                foreach ($posts as $post)
                {
                    $this->getTextSearchService()->saveOrUpdatePost($post, true);

                    // remember the last post id
                    $firstQueue->lastEntityId = $post->id;
                    $this->getUpdateSearchIndexDao()->save($firstQueue);
                }

                $postPage++;
            }

            return false;
        }

        return true;
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
 
        if ( !$config->getValue('forum', 'delete_search_index_cron') )
        {
            $this->getTextSearchService()->deleteAllEntities();
            $config->saveConfig('forum', 'delete_search_index_cron', 1);
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