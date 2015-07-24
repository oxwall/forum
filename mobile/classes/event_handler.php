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
 * Mobile forum event handler
 *
 * @author Egor Bulgakov <egor.bulgakov@gmail.com>
 * @package ow.plugin.forum.mobile.classes
 * @since 1.6.0
 */
class FORUM_MCLASS_EventHandler
{
    /**
     * @var FORUM_MCLASS_EventHandler
     */
    private static $classInstance;

    /**
     * @return FORUM_MCLASS_EventHandler
     */
    public static function getInstance()
    {
        if ( self::$classInstance === null )
        {
            self::$classInstance = new self();
        }

        return self::$classInstance;
    }

    private function __construct() { }

    public function feedOnItemRender( OW_Event $event )
    {
        $params = $event->getParams();
        $data = $event->getData();
        $language = OW::getLanguage();

        if ( $params['action']['entityType'] != 'forum-topic' )
        {
            return;
        }

        $service = FORUM_BOL_ForumService::getInstance();
        $postCount = $service->findTopicPostCount($params['action']['entityId']) - 1;

        if ( !$postCount )
        {
            return;
        }

        $postIds = array();
        foreach ( $params['activity'] as $activity )
        {
            if ( $activity['activityType'] == 'forum-post' )
            {
                $postIds[] = $activity['data']['postId'];
            }
        }

        if ( empty($postIds) )
        {
            return;
        }

        $postDto = null;
        foreach ( $postIds as $pid )
        {
            $postDto = $service->findPostById($pid);
            if ( $postDto !== null )
            {
                break;
            }
        }

        if ( $postDto === null )
        {
            return;
        }

        $postUrlEmbed = '...';
        $content = UTIL_String::truncate(strip_tags(str_replace("&nbsp;", "", $postDto->text)), 100, $postUrlEmbed);
        $usersData = BOL_AvatarService::getInstance()->getDataForUserAvatars(array($postDto->userId), true, true, true, false);

        $avatarData = $usersData[$postDto->userId];
        //$postUrl = $service->getPostUrl($postDto->topicId, $postDto->id);

        if ( is_array($data['content']) && !empty($data['content']['vars']) )
        {
            $data['content']['vars']['activity'] = array(
                'title' => $language->text('forum', 'latest_reply_from', array('url' => $avatarData['url'], 'user' => $avatarData['title'])),
                'avatarData' => $avatarData,
                'description' => $content
            );
        }

        $event->setData($data);
    }

    public function onMobileTopMenuAddLink( BASE_CLASS_EventCollector $event )
    {
        if ( !OW::getUser()->isAuthenticated() )
        {
            $backUri =  OW::getRouter()->uriForRoute('forum-default') . '?add_topic=1';
            $url = OW::getRequest()->
                    buildUrlQueryString(OW::getRouter()->urlForRoute('static_sign_in'), array('back-uri' => $backUri));

            $event->add(array(
                'prefix' => 'forum',
                'key' => 'forum_mobile',
                'url' => $url
            ));
        }
        else
        {
            $event->add(array(
                'prefix' => 'forum',
                'key' => 'forum_mobile',
                'url' => OW::getRequest()->buildUrlQueryString(OW::getRouter()->urlForRoute('forum-default'), array(
                    'add_topic' => 1
                ))
            ));
        }
    }

    public function onNotificationRender( OW_Event $e )
    {
        $params = $e->getParams();

        if ( $params['pluginKey'] == 'forum'
            && in_array($params['entityType'], array('forum_topic_reply')))
        {
            $data = $params['data'];
            $e->setData($data);
        }
    }

    public function init()
    {
        FORUM_CLASS_EventHandler::getInstance()->genericInit();

        $em = OW::getEventManager();

        $em->bind('feed.on_item_render', array($this, 'feedOnItemRender'));
        $em->bind('base.mobile_top_menu_add_options', array($this, 'onMobileTopMenuAddLink'));
        $em->bind('mobile.notifications.on_item_render', array($this, 'onNotificationRender'));
    }
}