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
 * @author Egor Bulgakov <egor.bulgakov@gmail.com>
 * @package ow_plugins.forum.classes
 * @since 1.6.0
 */
class FORUM_CLASS_EventHandler
{
    /**
     * @var FORUM_CLASS_EventHandler
     */
    private static $classInstance;

    /**
     * @return FORUM_CLASS_EventHandler
     */
    public static function getInstance()
    {
        if ( self::$classInstance === null )
        {
            self::$classInstance = new self();
        }

        return self::$classInstance;
    }

    public function __construct() { }

    public function addNewContentItem( BASE_CLASS_EventCollector $event )
    {
        $resultArray = array(
            BASE_CMP_AddNewContent::DATA_KEY_ICON_CLASS => 'ow_ic_files',
            BASE_CMP_AddNewContent::DATA_KEY_URL => OW::getRouter()->urlForRoute('add-topic-default'),
            BASE_CMP_AddNewContent::DATA_KEY_LABEL => OW::getLanguage()->text('forum', 'discussion')
        );

        if ( !OW::getUser()->isAuthorized('forum', 'edit') )
        {
            $status = BOL_AuthorizationService::getInstance()->getActionStatus('forum', 'edit');

            if ( $status['status'] != BOL_AuthorizationService::STATUS_PROMOTED )
            {
                return;
            }

            $id = uniqid('add-new-forum-');
            $resultArray[BASE_CMP_AddNewContent::DATA_KEY_ID] = $id;

            $script = '$("#'.$id.'").click(function(){
                OW.authorizationLimitedFloatbox('.json_encode($status['msg']).');
                return false;
            });';
            OW::getDocument()->addOnloadScript($script);
        }

        $event->add($resultArray);
    }

    public function deleteUserContent( OW_Event $event )
    {
        $params = $event->getParams();

        if ( !isset($params['deleteContent']) || !(bool) $params['deleteContent'] )
        {
            return;
        }

        $userId = (int) $params['userId'];

        if ( $userId > 0 )
        {
            $forumService = FORUM_BOL_ForumService::getInstance();

            $forumService->deleteUserTopics($userId);
            $forumService->deleteUserPosts($userId);
        }
    }

    public function createSection( OW_Event $event )
    {
        $params = $event->getParams();

        if ( !isset($params['name']) || !isset($params['entity']) || !isset($params['isHidden']) )
        {
            return;
        }

        $forum_service = FORUM_BOL_ForumService::getInstance();

        $sectionDto = $forum_service->findSectionByEntity($params['entity']);

        if ( !isset($sectionDto) )
        {
            $sectionDto = new FORUM_BOL_Section();
            $sectionDto->name = $params['name'];
            $sectionDto->entity = $params['entity'];
            $sectionDto->isHidden = $params['isHidden'];
            $sectionDto->order = $forum_service->getNewSectionOrder();

            $forum_service->saveOrUpdateSection($sectionDto);
        }

    }

    public function deleteSection( OW_Event $event )
    {
        $params = $event->getParams();

        if ( !isset($params['name']) && !isset($params['entity']) )
        {
            return;
        }

        $forum_service = FORUM_BOL_ForumService::getInstance();

        if ( isset($params['name']) )
        {
            $section = $forum_service->getSection($params['name']);
        }

        if ( isset($params['entity']) )
        {
            $section = $forum_service->findSectionByEntity($params['entity']);
        }

        if ( !empty($section) )
        {
            $forum_service->deleteSection($section->getId());
        }
    }

    public function addWidget( OW_Event $event )
    {
        $params = $event->getParams();

        if ( !isset($params['place']) || !isset($params['section']) )
        {
            return;
        }

        try
        {
            $widgetService = BOL_ComponentAdminService::getInstance();
            $widget = $widgetService->addWidget('FORUM_CMP_LatestTopicsWidget', false);
            $placeWidget = $widgetService->addWidgetToPlace($widget, $params['place']);
            $widgetService->addWidgetToPosition($placeWidget, $params['section'], 0);
        }
        catch ( Exception $e ) { }
    }

    public function installWidget( OW_Event $e )
    {
        $params = $e->getParams();

        $widgetService = BOL_ComponentAdminService::getInstance();

        try
        {
            $widget = $widgetService->addWidget('FORUM_CMP_LatestTopicsWidget', false);
            $widgetPlace = $widgetService->addWidgetToPlace($widget, $params['place']);
            $widgetService->addWidgetToPosition($widgetPlace, $params['section'], $params['order']);
            $e->setData($widgetPlace->uniqName);
        }
        catch ( Exception $exception )
        {
            $e->setData(false);
        }
    }

    public function deleteWidget( OW_Event $event )
    {
        BOL_ComponentAdminService::getInstance()->deleteWidget('FORUM_CMP_LatestTopicsWidget');
    }

    public function createGroup( OW_Event $event )
    {
        $params = $event->getParams();

        if ( !$params['entity'] || !isset($params['name']) || !isset($params['description']) || !isset($params['entityId']) )
        {
            return;
        }

        $forumService = FORUM_BOL_ForumService::getInstance();

        $forumGroup = $forumService->findGroupByEntityId($params['entity'], $params['entityId']);

        if ( !isset($forumGroup) )
        {
            $section = $forumService->findSectionByEntity($params['entity']);

            $forumGroup = new FORUM_BOL_Group();
            $forumGroup->sectionId = $section->getId();
            $forumGroup->order = $forumService->getNewGroupOrder($section->getId());

            $forumGroup->name = $params['name'];
            $forumGroup->description = $params['description'];
            $forumGroup->entityId = $params['entityId'];

            $forumService->saveOrUpdateGroup($forumGroup);
        }
    }

    public function deleteGroup( OW_Event $event )
    {
        $params = $event->getParams();

        if ( !isset($params['entityId']) || !isset($params['entity']) )
        {
            return;
        }

        $forumService = FORUM_BOL_ForumService::getInstance();
        $group = $forumService->findGroupByEntityId($params['entity'], $params['entityId']);

        if ( !empty($group) )
        {
            $forumService->deleteGroup($group->getId());
        }
    }

    public function editGroup( OW_Event $event )
    {
        $params = $event->getParams();

        if ( !isset($params['entityId']) || !isset($params['entity']) )
        {
            return;
        }

        $forumService = FORUM_BOL_ForumService::getInstance();
        $group = $forumService->findGroupByEntityId($params['entity'], $params['entityId']);

        if ( !empty($group) )
        {
            if (!empty($params['name']))
            {
                $group->name = $params['name'];
            }

            if (!empty($params['description']))
            {
                $group->description = $params['description'];
            }

            $forumService->saveOrUpdateGroup($group);
        }
    }

    public function onNotifyActions( BASE_CLASS_EventCollector $e )
    {
        $e->add(array(
            'section' => 'forum',
            'action' => 'forum-add_post',
            'sectionIcon' => 'ow_ic_forum',
            'sectionLabel' => OW::getLanguage()->text('forum', 'email_notifications_section_label'),
            'description' => OW::getLanguage()->text('forum', 'email_notifications_setting_post'),
            'selected' => true
        ));
    }

    public function addPost( OW_Event $e )
    {
        $params = $e->getParams();

        $postId = $params['postId'];

        $forumService = FORUM_BOL_ForumService::getInstance();
        $post = $forumService->findPostById($postId);

        if ( !$post )
        {
            return;
        }

        $userIdList = FORUM_BOL_SubscriptionService::getInstance()->findTopicSubscribers($post->topicId);
        if ( empty($userIdList) )
        {
            return;
        }

        $params = array(
            'pluginKey' => 'forum',
            'entityType' => 'forum_topic_reply',
            'entityId' => $postId,
            'action' => 'forum-add_post',
            'time' => time()
        );

        $authorId = $post->userId;
        $avatar = BOL_AvatarService::getInstance()->getDataForUserAvatars(array($authorId));
        $postUrl = $forumService->getPostUrl($post->topicId, $postId);
        $topicUrl = OW::getRouter()->urlForRoute('topic-default', array('topicId' => $post->topicId));
        $topic = $forumService->findTopicById($post->topicId);

        $data = array(
            'avatar' => $avatar[$authorId],
            'string' => array(
                'key' => 'forum+email_notification_post',
                'vars' => array(
                    'userName' => $avatar[$authorId]['title'],
                    'userUrl' => $avatar[$authorId]['url'],
                    'postUrl' => $postUrl,
                    'topicUrl' => $topicUrl,
                    'title' => strip_tags($topic->title)
                )
            ),
            'content' => strip_tags($post->text),
            'url' => $postUrl
        );

        foreach ( $userIdList as $userId )
        {
            if ( $userId == $post->userId )
            {
                continue;
            }

            $params['userId'] = $userId;

            $event = new OW_Event('notifications.add', $params, $data);
            OW::getEventManager()->trigger($event);
        }
    }

    public function adsEnabled( BASE_CLASS_EventCollector $event )
    {
        $event->add('forum');
    }

    public function addAuthLabels( BASE_CLASS_EventCollector $event )
    {
        $language = OW::getLanguage();
        $event->add(
            array(
                'forum' => array(
                    'label' => $language->text('forum', 'auth_group_label'),
                    'actions' => array(
                        'edit' => $language->text('forum', 'auth_action_label_edit'),
                        'view' => $language->text('forum', 'auth_action_label_view'),
                        'subscribe' => $language->text('forum', 'auth_action_label_subscribe'),
                        'move_topic_to_hidden' => $language->text('forum', 'auth_action_label_move_topic_to_hidden')
                    )
                )
            )
        );
    }

    public function feedOnEntityAdd( OW_Event $e )
    {
        $params = $e->getParams();
        $data = $e->getData();

        if ( $params['entityType'] != 'forum-topic' )
        {
            return;
        }

        $topicId = (int) $params['entityId'];
        $service = FORUM_BOL_ForumService::getInstance();
        $topicDto = $service->findTopicById($topicId);
        $postDto = $service->findTopicFirstPost($topicId);
        $groupDto = $service->findGroupById($topicDto->groupId);
        $sectionDto = $service->findSectionById($groupDto->sectionId);
        $isHidden = (bool) $sectionDto->isHidden;

        if ( $postDto === null )
        {
            return;
        }

        if ( $groupDto->isPrivate )
        {
            return;
        }

        $topicUrl = OW::getRouter()->urlForRoute('topic-default', array('topicId' => $topicDto->id));
        $content = UTIL_String::truncate(strip_tags($postDto->text), 150, '...');
        $title = UTIL_String::truncate(strip_tags($topicDto->title), 100, '...');

        $data = array(
            'features' => array('likes'),
            'ownerId' => $topicDto->userId,
            'time' => (int) $postDto->createStamp,
            'content' => array(
                'format' => 'content',
                'vars' => array(
                    'title' => $title,
                    'description' => $content,
                    'url' => array(
                        "routeName" => 'topic-default',
                        "vars" => array('topicId' => $topicDto->id)
                    ),
                    'iconClass' => 'ow_ic_forum'
                )
            ),
            'string' => array('key' => 'forum+feed_activity_topic_string'),
            'toolbar' => array(array(
                'href' => $topicUrl,
                'label' => OW::getLanguage()->text('forum', 'feed_toolbar_discuss')
            ))
        );

        if ( $isHidden )
        {
            $data['params']['feedType'] = $sectionDto->entity;
            $data['params']['feedId'] = $groupDto->entityId;
            $data['params']['visibility'] = 2 + 4 + 8; // Visible for follows(2), autor (4) and current feed (8)
            $data['params']['postOnUserFeed'] = false;
            $data['contextFeedType'] = $data['params']['feedType'];
            $data['contextFeedId'] = $data['params']['feedId'];
        }

        $e->setData($data);
    }

    public function feedOnPostAdd( OW_Event $e )
    {
        $params = $e->getParams();

        $event = new OW_Event('feed.activity', array(
            'pluginKey' => 'forum',
            'entityType' => 'forum-topic',
            'entityId' => $params['topicId'],
            'userId' => $params['userId'],
            'activityType' => 'forum-post',
            'activityId' => $params['postId'],
            'subscribe' => true
        ), array(
            'postId' => $params['postId'],
            'string' => array('key' => 'forum+feed_activity_topic_reply_string')
        ));
        OW::getEventManager()->trigger($event);
    }

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

        if ( is_array($data['toolbar']) )
        {
            $data['toolbar'][] = array(
                'label' => $language->text('forum', 'feed_toolbar_replies', array('postCount' => $postCount))
            );
        }

        $event->setData($data);

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
        $postUrl = $service->getPostUrl($postDto->topicId, $postDto->id);

        if ( is_array($data['content']) && !empty($data['content']['vars']) )
        {
            $data['content']['vars']['activity'] = array(
                'url' => $postUrl,
                'title' => $language->text('forum', 'last_reply'),
                'avatarData' => $avatarData,
                'description' => $content
            );
        }

        $event->setData($data);
    }

    public function feedCollectConfigurableActivity( BASE_CLASS_EventCollector $event )
    {
        $language = OW::getLanguage();
        $event->add(array(
            'label' => $language->text('forum', 'feed_content_label'),
            'activity' => 'create:forum-topic'
        ));

        $event->add(array(
            'label' => $language->text('forum', 'feed_content_replies_label'),
            'activity' => 'forum-post:forum-topic'
        ));
    }

    public function subscribeUser( OW_Event $e )
    {
        $params = $e->getParams();
        $userId = (int) $params['userId'];
        $topicId = (int) $params['topicId'];

        if ( !$userId || ! $topicId )
        {
            return false;
        }

        $service = FORUM_BOL_SubscriptionService::getInstance();

        if ( $service->isUserSubscribed($userId, $topicId) )
        {
            return true;
        }

        $subs = new FORUM_BOL_Subscription();
        $subs->userId = $userId;
        $subs->topicId = $topicId;

        $service->addSubscription($subs);

        return true;
    }

    public function feedTopicLike( OW_Event $event )
    {
        $params = $event->getParams();

        if ( $params['entityType'] != 'forum-topic' )
        {
            return;
        }

        $service = FORUM_BOL_ForumService::getInstance();
        $topic = $service->findTopicById($params['entityId']);
        $userId = $topic->userId;

        $userName = BOL_UserService::getInstance()->getDisplayName($userId);
        $userUrl = BOL_UserService::getInstance()->getUserUrl($userId);
        $userEmbed = '<a href="' . $userUrl . '">' . $userName . '</a>';

        if ( $params['userId'] == $userId )
        {
            $string = array('key' => 'forum+feed_activity_owner_topic_like');
        }
        else
        {
            $string = array('key' => 'forum+feed_activity_topic_string_like', 'vars' => array('user' => $userEmbed));
        }

        OW::getEventManager()->trigger(new OW_Event('feed.activity', array(
            'activityType' => 'like',
            'activityId' => $params['userId'],
            'entityId' => $params['entityId'],
            'entityType' => $params['entityType'],
            'userId' => $params['userId'],
            'pluginKey' => 'photo'
        ), array(
            'string' => $string
        )));
    }

    public function init()
    {
        $this->genericInit();
        $em = OW::getEventManager();

        $em->bind(BASE_CMP_AddNewContent::EVENT_NAME, array($this, 'addNewContentItem'));
        $em->bind('forum.add_widget', array($this, 'addWidget'));
        $em->bind('forum.install_widget', array($this, 'installWidget'));
        $em->bind('forum.delete_widget', array($this, 'deleteWidget'));
        $em->bind('feed.on_item_render', array($this, 'feedOnItemRender'));
        $em->bind("base.collect_seo_meta_data", array($this, 'onCollectMetaData'));
    }

    public function sosialSharingGetForumInfo( OW_Event $event )
    {
        $params = $event->getParams();
        $data = $event->getData();
        $data['display'] = false;

        if ( empty($params['entityId']) )
        {
            return;
        }

        if ( $params['entityType'] == 'forum_topic' )
        {
            $topicDto = FORUM_BOL_ForumService::getInstance()->findTopicById($params['entityId']);

            $forumGroup = FORUM_BOL_ForumService::getInstance()->findGroupById($topicDto->groupId);
            $forumSection = FORUM_BOL_ForumService::getInstance()->findSectionById($forumGroup->sectionId);

            if ( !empty($topicDto) )
            {
                $data['display'] = !$forumSection->isHidden && !$forumGroup->isPrivate && BOL_AuthorizationService::getInstance()->isActionAuthorizedForGuest('forum', 'view');
            }
            
            $event->setData($data);
        }
    }

    public function afterContentApprove( OW_Event $event )
    {
        $params = $event->getParams();

        if ( $params["entityType"] != FORUM_BOL_ForumService::FEED_ENTITY_TYPE )
        {
            return;
        }

        if ( !$params["isNew"] )
        {
            return;
        }

        $forumService = FORUM_BOL_ForumService::getInstance();
        $topic = $forumService->findTopicById($params["entityId"]);

        if ( $topic === null )
        {
            return;
        }

        BOL_AuthorizationService::getInstance()->trackActionForUser($topic->userId, 'forum', 'edit');
    }

    /**
     * Get sitemap urls
     *
     * @param OW_Event $event
     * @return void
     */
    public function onSitemapGetUrls( OW_Event $event )
    {
        $params = $event->getParams();

        if ( BOL_AuthorizationService::getInstance()->isActionAuthorizedForGuest('forum', 'view') )
        {
            $offset = (int) $params['offset'];
            $limit  = (int) $params['limit'];
            $urls   = array();

            switch ( $params['entity'] )
            {
                case 'forum_topic' :
                    $topics = FORUM_BOL_ForumService::getInstance()->findLatestPublicTopicsIds($offset, $limit);

                    foreach ( $topics as $topicId )
                    {
                        $urls[] = OW::getRouter()->urlForRoute('topic-default', array(
                            'topicId' => $topicId
                        ));
                    }
                    break;

                case 'forum_group' :
                    $groups = FORUM_BOL_ForumService::getInstance()->findLatestPublicGroupsIds($offset, $limit);

                    foreach ( $groups as $groupId )
                    {
                        $urls[] = OW::getRouter()->urlForRoute('group-default', array(
                            'groupId' => $groupId
                        ));
                    }
                    break;

                case 'forum_section' :
                    $sections = FORUM_BOL_ForumService::getInstance()->findLatestPublicSectionsIds($offset, $limit);

                    foreach ( $sections as $sectionId )
                    {
                        $urls[] = OW::getRouter()->urlForRoute('section-default', array(
                            'sectionId' => $sectionId
                        ));
                    }
                    break;

                case 'forum_list' :
                    $urls[] = OW::getRouter()->urlForRoute('forum-default');
                    $urls[] = OW::getRouter()->urlForRoute('forum_advanced_search');
                    break;
            }

            if ( $urls )
            {
                $event->setData($urls);
            }
        }
    }

    public function onCollectMetaData( BASE_CLASS_EventCollector $e )
    {
        $language = OW::getLanguage();

        $items = array(
            array(
                "entityKey" => "home",
                "entityLabel" => $language->text("forum", "seo_meta_home_label"),
                "iconClass" => "ow_ic_house",
                "langs" => array(
                    "title" => "forum+meta_title_home",
                    "description" => "forum+meta_desc_home",
                    "keywords" => "forum+meta_keywords_home"
                ),
                "vars" => array("site_name")
            ),
            array(
                "entityKey" => "advSearch",
                "entityLabel" => $language->text("forum", "seo_meta_adv_search_label"),
                "iconClass" => "ow_ic_lens",
                "langs" => array(
                    "title" => "forum+meta_title_adv_search",
                    "description" => "forum+meta_desc_adv_search",
                    "keywords" => "forum+meta_keywords_adv_searche"
                ),
                "vars" => array("site_name")
            ),
            array(
                "entityKey" => "advSearchResult",
                "entityLabel" => $language->text("forum", "seo_meta_adv_search_result_label"),
                "iconClass" => "ow_ic_newsfeed",
                "langs" => array(
                    "title" => "forum+meta_title_adv_search_result",
                    "description" => "forum+meta_desc_adv_search_result",
                    "keywords" => "forum+meta_keywords_adv_searche_result"
                ),
                "vars" => array("site_name")
            ),
            array(
                "entityKey" => "section",
                "entityLabel" => $language->text("forum", "seo_meta_section_label"),
                "iconClass" => "ow_ic_forum",
                "langs" => array(
                    "title" => "forum+meta_title_section",
                    "description" => "forum+meta_desc_section",
                    "keywords" => "forum+meta_keywords_section"
                ),
                "vars" => array("site_name", "section_name")
            ),
            array(
                "entityKey" => "group",
                "entityLabel" => $language->text("forum", "seo_meta_group_label"),
                "iconClass" => "ow_ic_forum",
                "langs" => array(
                    "title" => "forum+meta_title_group",
                    "description" => "forum+meta_desc_group",
                    "keywords" => "forum+meta_keywords_group"
                ),
                "vars" => array("site_name", "group_name", "group_description")
            ),
            array(
                "entityKey" => "topic",
                "entityLabel" => $language->text("forum", "seo_meta_topic_label"),
                "iconClass" => "ow_ic_forum",
                "langs" => array(
                    "title" => "forum+meta_title_topic",
                    "description" => "forum+meta_desc_topic",
                    "keywords" => "forum+meta_keywords_topic"
                ),
                "vars" => array("site_name", "topic_name", "topic_description")
            ),
            array(
                "entityKey" => "sectionSearch",
                "entityLabel" => $language->text("forum", "seo_meta_section_search_label"),
                "iconClass" => "ow_ic_lens",
                "langs" => array(
                    "title" => "forum+meta_title_section_search",
                    "description" => "forum+meta_desc_section_search",
                    "keywords" => "forum+meta_keywords_section_search"
                ),
                "vars" => array("site_name", "section_name")
            ),
            array(
                "entityKey" => "groupSearch",
                "entityLabel" => $language->text("forum", "seo_meta_group_search_label"),
                "iconClass" => "ow_ic_lens",
                "langs" => array(
                    "title" => "forum+meta_title_group_search",
                    "description" => "forum+meta_desc_group_search",
                    "keywords" => "forum+meta_keywords_group_search"
                ),
                "vars" => array("site_name", "group_name", "group_description")
            ),
            array(
                "entityKey" => "topicSearch",
                "entityLabel" => $language->text("forum", "seo_meta_topic_search_label"),
                "iconClass" => "ow_ic_lens",
                "langs" => array(
                    "title" => "forum+meta_title_topic_search",
                    "description" => "forum+meta_desc_topic_search",
                    "keywords" => "forum+meta_keywords_topic_search"
                ),
                "vars" => array("site_name", "topic_name", "topic_description")
            ),
        );

        foreach ($items as &$item)
        {
            $item["sectionLabel"] = $language->text("forum", "seo_meta_section");
            $item["sectionKey"] = "forum";
            $e->add($item);
        }
    }

    public function genericInit()
    {
        $em = OW::getEventManager();

        $em->bind(OW_EventManager::ON_USER_UNREGISTER, array($this, 'deleteUserContent'));
        $em->bind('forum.create_section', array($this, 'createSection'));
        $em->bind('forum.delete_section', array($this, 'deleteSection'));
        $em->bind('forum.create_group', array($this, 'createGroup'));
        $em->bind('forum.delete_group', array($this, 'deleteGroup'));
        $em->bind('forum.edit_group', array($this, 'editGroup'));
        $em->bind('notifications.collect_actions', array($this, 'onNotifyActions'));
        $em->bind('forum.add_post', array($this, 'addPost'));
        $em->bind('ads.enabled_plugins', array($this, 'adsEnabled'));
        $em->bind('admin.add_auth_labels', array($this, 'addAuthLabels'));
        $em->bind('feed.on_entity_add', array($this, 'feedOnEntityAdd'));
        $em->bind('feed.on_entity_update', array($this, 'feedOnEntityAdd'));
        $em->bind('forum.add_post', array($this, 'feedOnPostAdd'));
        $em->bind('feed.collect_configurable_activity', array($this, 'feedCollectConfigurableActivity'));
        $em->bind('forum.subscribe_user', array($this, 'subscribeUser'));
        $em->bind('feed.after_like_added', array($this, 'feedTopicLike'));
        $em->bind('moderation.after_content_approve', array($this, 'afterContentApprove'));

        $credits = new FORUM_CLASS_Credits();
        $em->bind('usercredits.on_action_collect', array($credits, 'bindCreditActionsCollect'));
        $em->bind('usercredits.get_action_key', array($credits, 'getActionKey'));

        $em->bind('socialsharing.get_entity_info', array($this, 'sosialSharingGetForumInfo'));
        $em->bind("base.sitemap.get_urls", array($this, 'onSitemapGetUrls'));
    }
}