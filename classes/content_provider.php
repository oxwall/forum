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
 * @author Zarif Safiullin <zaph.work@gmail.com>
 * @package ow.ow_plugins.forum
 * @since 1.7.2
 */
class FORUM_CLASS_ContentProvider
{
    const ENTITY_TYPE = FORUM_BOL_ForumService::FEED_ENTITY_TYPE;
    const POST_ENTITY_TYPE = FORUM_BOL_ForumService::FEED_POST_ENTITY_TYPE;

    /**
     * Singleton instance.
     *
     * @var FORUM_CLASS_ContentProvider
     */
    private static $classInstance;

    /**
     * Returns an instance of class (singleton pattern implementation).
     *
     * @return FORUM_CLASS_ContentProvider
     */
    public static function getInstance()
    {
        if ( self::$classInstance === null )
        {
            self::$classInstance = new self();
        }

        return self::$classInstance;
    }

    /**
     *
     * @var FORUM_BOL_ForumService
     */
    private $service;

    private function __construct()
    {
        $this->service = FORUM_BOL_ForumService::getInstance();
    }

    public function onCollectTypes( BASE_CLASS_EventCollector $event )
    {
        $event->add(array(
            "pluginKey" => "forum",
            "group" => "forum",
            "groupLabel" => OW::getLanguage()->text("forum", "content_forums_label"),
            "entityType" => self::ENTITY_TYPE,
            "entityLabel" => OW::getLanguage()->text("forum", "content_forum_label"),
            "displayFormat" => "content"
        ));

        $event->add(array(
            "pluginKey" => "forum",
            "group" => "forum",
            "groupLabel" => OW::getLanguage()->text("forum", "content_forums_label"),
            "entityType" => self::POST_ENTITY_TYPE,
            "entityLabel" => OW::getLanguage()->text("forum", "content_post_label"),
            "displayFormat" => "content",
            "moderation" => array(BOL_ContentService::MODERATION_TOOL_FLAG)
        ));
    }

    public function onGetInfo( OW_Event $event )
    {
        $params = $event->getParams();

        if ( $params["entityType"] != self::ENTITY_TYPE && $params["entityType"] != self::POST_ENTITY_TYPE )
        {
            return;
        }

        if ($params["entityType"] == self::ENTITY_TYPE)
        {
            $topics = $this->service->findTopicListByIds($params["entityIds"]);
            $out = array();
            /**
             * @var FORUM_BOL_Post $post
             */
            foreach ( $topics as $topic )
            {
                $info = array();

                $topicPost = $this->service->findTopicFirstPost($topic->id);

                $info["id"] = $topic->id;
                $info["userId"] = $topic->userId;
                $info["url"] = OW::getRouter()->urlForRoute('topic-default', array('topicId' => $topic->id));
                $info["label"] = OW::getLanguage()->text('forum', 'content_forum_topic', array('topicUrl'=>$info["url"], 'title'=>$topic->title));
                $info["title"] = $topic->title;
                $info["description"] = $topicPost->text;

                $info["timeStamp"] = $topicPost->createStamp;

                $out[$topic->id] = $info;
            }
        }
        elseif ( $params["entityType"] == self::POST_ENTITY_TYPE )
        {
            $posts = $this->service->findPostListByIds($params["entityIds"]);
            $out = array();
            /**
             * @var FORUM_BOL_Post $post
             */
            foreach ( $posts as $post )
            {
                $info = array();
                $topicInfo = $this->service->getTopicInfo($post->topicId);

                $info["id"] = $post->id;
                $info["userId"] = $post->userId;
                $info["url"] = $this->service->getPostUrl($post->topicId, $post->id);
                $info["label"] = OW::getLanguage()->text('forum', 'content_forum_post_in_topic', array('topicUrl'=>$info["url"], 'title'=>UTIL_String::truncate($topicInfo['title'], 20)));
                $info["text"] = $post->text;

                $info["timeStamp"] = $post->createStamp;

                $out[$post->id] = $info;
            }
        }

        $event->setData($out);

        return $out;
    }

    public function onUpdateInfo( OW_Event $event )
    {
        $params = $event->getParams();
        $data = $event->getData();

        if ( $params["entityType"] != self::ENTITY_TYPE )
        {
            return;
        }

        foreach ( $data as $topicId => $info )
        {
            $status = $info['status'] == BOL_ContentService::STATUS_APPROVAL ? FORUM_BOL_ForumService::STATUS_APPROVAL : FORUM_BOL_ForumService::STATUS_APPROVED;

            $topicDto = $this->service->findTopicById($topicId);
            $topicDto->status = $status;

            $this->service->saveOrUpdateTopic($topicDto);
        }
    }

    public function onDelete( OW_Event $event )
    {
        $params = $event->getParams();

        if ( $params["entityType"] != self::ENTITY_TYPE && $params["entityType"] != self::POST_ENTITY_TYPE )
        {
            return;
        }

        if ( $params["entityType"] == self::ENTITY_TYPE )
        {
            foreach ( $params["entityIds"] as $topicId )
            {
                $this->service->deleteTopic($topicId);
            }
        }
        elseif ( $params["entityType"] == self::POST_ENTITY_TYPE )
        {
            foreach ( $params["entityIds"] as $postId )
            {
                $this->service->deletePost($postId);
            }
        }
    }

    // Forum events

    public function onAfterTopicAdd( OW_Event $event )
    {
        $params = $event->getParams();

        OW::getEventManager()->trigger(new OW_Event(BOL_ContentService::EVENT_AFTER_ADD, array(
            'entityType' => self::ENTITY_TYPE,
            'entityId' => $params['topicId']
        ), array(
            'string' => array('key' => 'forum+content_add_string')
        )));
    }

    public function onAfterTopicEdit( OW_Event $event )
    {
        $params = $event->getParams();

        OW::getEventManager()->trigger(new OW_Event(BOL_ContentService::EVENT_AFTER_CHANGE, array(
            'entityType' => self::ENTITY_TYPE,
            'entityId' => $params['topicId']
        ), array(
            'string' => array('key' => 'forum+content_edited_string')
        )));
    }

    public function onBeforeTopicDelete( OW_Event $event )
    {
        $params = $event->getParams();

        OW::getEventManager()->trigger(new OW_Event(BOL_ContentService::EVENT_BEFORE_DELETE, array(
            'entityType' => self::ENTITY_TYPE,
            'entityId' => $params['topicId']
        )));
    }

    public function init()
    {
        OW::getEventManager()->bind(FORUM_BOL_ForumService::EVENT_AFTER_TOPIC_ADD, array($this, 'onAfterTopicAdd'));
        OW::getEventManager()->bind(FORUM_BOL_ForumService::EVENT_AFTER_TOPIC_EDIT, array($this, 'onAfterTopicEdit'));
        OW::getEventManager()->bind(FORUM_BOL_ForumService::EVENT_BEFORE_TOPIC_DELETE, array($this, 'onBeforeTopicDelete'));

        OW::getEventManager()->bind(BOL_ContentService::EVENT_COLLECT_TYPES, array($this, "onCollectTypes"));
        OW::getEventManager()->bind(BOL_ContentService::EVENT_GET_INFO, array($this, "onGetInfo"));
        OW::getEventManager()->bind(BOL_ContentService::EVENT_UPDATE_INFO, array($this, "onUpdateInfo"));
        OW::getEventManager()->bind(BOL_ContentService::EVENT_DELETE, array($this, "onDelete"));
    }
}