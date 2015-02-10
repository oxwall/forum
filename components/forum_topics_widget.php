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
 * Forum topics widget component
 *
 * @author Egor Bulgakov <egor.bulgakov@gmail.com>
 * @package ow.ow_plugins.forum.components
 * @since 1.0
 */
class FORUM_CMP_ForumTopicsWidget extends BASE_CLASS_Widget
{
    private $forumService;

    /**
     * Class constructor
     */
    public function __construct( BASE_CLASS_WidgetParameter $paramObj )
    {
        parent::__construct();

        $this->forumService = FORUM_BOL_ForumService::getInstance();


        $confTopicCount = (int) $paramObj->customParamList['topicCount'];

        $confPostLength = (int) $paramObj->customParamList['postLength'];

        if ( OW::getUser()->isAuthorized('forum') )
        {
            $excludeGroupIdList = array();
        }
        else
        {
            $excludeGroupIdList = $this->forumService->getPrivateUnavailableGroupIdList(OW::getUser()->getId());
        }
        $topics = $this->forumService->getLatestTopicList($confTopicCount, $excludeGroupIdList);

        if ( $topics )
        {
            $this->assign('topics', $topics);

            $userIds = array();
            $groupIds = array();
            $toolbars = array();

            foreach ( $topics as $topic )
            {
                if ( !in_array($topic['lastPost']['userId'], $userIds) )
                {
                    array_push($userIds, $topic['lastPost']['userId']);
                }

                if ( !in_array($topic['groupId'], $groupIds) )
                {
                    array_push($groupIds, $topic['groupId']);
                }
            }

            $avatars = BOL_AvatarService::getInstance()->getDataForUserAvatars($userIds, true, false);
            $this->assign('avatars', $avatars);

            $urls = BOL_UserService::getInstance()->getUserUrlsForList($userIds);

            // toolbars
            foreach ( $topics as $key => $topic )
            {
                $userId = $topic['lastPost']['userId'];
                $toolbars[$topic['lastPost']['postId']][] = array(
                    'class' => 'ow_icon_control ow_ic_user',
                    'href' => !empty($urls[$userId]) ? $urls[$userId] : '#',
                    'label' => !empty($avatars[$userId]['title']) ? $avatars[$userId]['title'] : ''
                );

                $toolbars[$topic['lastPost']['postId']][] = array(
                    'label' => $topic['lastPost']['createStamp'],
                    'class' => 'ow_ipc_date'
                );
            }
            $this->assign('toolbars', $toolbars);

            $this->assign('postLength', $confPostLength);

            $groups = $this->forumService->findGroupByIdList($groupIds);

            $groupList = array();

            $sectionIds = array();

            foreach ( $groups as $group )
            {
                $groupList[$group->id] = $group;

                if ( !in_array($group->sectionId, $sectionIds) )
                {
                    array_push($sectionIds, $group->sectionId);
                }
            }
            $this->assign('groups', $groupList);

            $sectionList = $this->forumService->findSectionsByIdList($sectionIds);
            $this->assign('sections', $sectionList);

            $tb = array();
            if ( OW::getUser()->isAuthorized('forum', 'edit') )
            {
                $tb[] = array(
                    'label' => OW::getLanguage()->text('forum', 'add_new'),
                    'href' => OW::getRouter()->urlForRoute('add-topic-default')
                );
            }
            $tb[] = array(
                'label' => OW::getLanguage()->text('forum', 'goto_forum'),
                'href' => OW::getRouter()->urlForRoute('forum-default')
            );

            $this->setSettingValue(self::SETTING_TOOLBAR, $tb);
        }
        else
        {
            if ( !OW::getUser()->isAuthorized('forum', 'edit') )
            {
                $this->setVisible(false);

                return;
            }

            $this->assign('topics', null);
        }
    }

    public static function getSettingList()
    {
        $settingList = array();

        $settingList['topicCount'] = array(
            'presentation' => self::PRESENTATION_NUMBER,
            'label' => OW::getLanguage()->text('forum', 'cmp_widget_forum_topics_count'),
            'value' => 5
        );

        $settingList['postLength'] = array(
            'presentation' => self::PRESENTATION_NUMBER,
            'label' => OW::getLanguage()->text('forum', 'cmp_widget_forum_topics_post_lenght'),
            'value' => 50
        );

        return $settingList;
    }

    public static function validateSettingList( $settingList )
    {
        parent::validateSettingList($settingList);

        $validationMessage = OW::getLanguage()->text('forum', 'cmp_widget_forum_topics_count_msg');

        if ( !preg_match('/^\d+$/', $settingList['topicCount']) )
        {
            throw new WidgetSettingValidateException($validationMessage, 'topicCount');
        }
        if ( $settingList['topicCount'] > 20 )
        {
            throw new WidgetSettingValidateException($validationMessage, 'topicCount');
        }

        $validationMessage = OW::getLanguage()->text('forum', 'cmp_widget_forum_topics_post_length_msg');

        if ( !preg_match('/^\d+$/', $settingList['postLength']) )
        {
            throw new WidgetSettingValidateException($validationMessage, 'postLength');
        }
        if ( $settingList['postLength'] > 1000 )
        {
            throw new WidgetSettingValidateException($validationMessage, 'postLength');
        }
    }

    public static function getAccess()
    {
        return self::ACCESS_ALL;
    }

    public static function getStandardSettingValueList()
    {
        return array(
            self::SETTING_TITLE => OW::getLanguage()->text('forum', 'forum_topics_widget'),
            self::SETTING_ICON => self::ICON_FILES,
            self::SETTING_SHOW_TITLE => true
        );
    }
}