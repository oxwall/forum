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
 * Latest for Forum Group Topics Widget
 *
 * @author Zarif Safiullin <zaph.saph@gmail.com>
 * @package ow_plugins.forum.components
 * @since 1.0
 */
class FORUM_CMP_LatestTopicsWidget extends BASE_CLASS_Widget
{
    private $entity;
    private $entityId;

    /**
     * @param BASE_CLASS_WidgetParameter $paramObj
     * @return FORUM_CMP_LatestTopicsWidget
     */
    public function __construct( BASE_CLASS_WidgetParameter $paramObj )
    {
        parent::__construct();

        $confTopicCount = (int) $paramObj->customParamList['topicCount'];

        $this->entityId = (int) $paramObj->additionalParamList['entityId'];
        $this->entity = $paramObj->additionalParamList['entity'];

        $forumService = FORUM_BOL_ForumService::getInstance();
        $forumGroup = $forumService->findGroupByEntityId($this->entity, $this->entityId);
        if ( empty($forumGroup) )
        {
            $this->setVisible(false);
            return;
        }

        $topicList = $forumService->getGroupTopicList($forumGroup->getId(), 1, $confTopicCount);
        // get usernames list
        $userIds = array();
        $topicIds = array();

        foreach ( $topicList as $topic )
        {
            array_push($topicIds, $topic['id']);

            if ( isset($topic['lastPost']) && !in_array($topic['lastPost']['userId'], $userIds) )
            {
                array_push($userIds, $topic['lastPost']['userId']);
            }
        }

        $addTopicUrl = OW::getRouter()->urlForRoute('add-topic', array('groupId' => $forumGroup->getId()));
        $this->assign('addTopicUrl', $addTopicUrl);

        $params = array('entity' => $this->entity, 'entityId' => $this->entityId, 'action' => 'add_topic');
        $event = new OW_Event('forum.check_permissions', $params);
        OW::getEventManager()->trigger($event);

        $canAdd = $event->getData();

        $this->assign('canAdd', $canAdd);

        $attachments = FORUM_BOL_PostAttachmentService::getInstance()->getAttachmentsCountByTopicIdList($topicIds);
        $this->assign('attachments', $attachments);

        $usernames = BOL_UserService::getInstance()->getUserNamesForList($userIds);
        $this->assign('usernames', $usernames);

        $displayNames = BOL_UserService::getInstance()->getDisplayNamesForList($userIds);
        $this->assign('displayNames', $displayNames);

        $this->assign('topicList', $topicList);

        if ( $canAdd )
        {
            $this->setSettingValue(
                self::SETTING_TOOLBAR,
                array(
                    array(
                        'label' => OW::getLanguage()->text('forum', 'add_new'),
                        'href' => OW::getRouter()->urlForRoute('add-topic', array('groupId' => $forumGroup->getId()))
                    ),
                    array(
                        'label' => OW::getLanguage()->text('base', 'view_all'),
                        'href' => OW::getRouter()->urlForRoute('group-default', array('groupId' => $forumGroup->getId()))
                    )
                )
            );
        }
        else
        {
            $this->setSettingValue(
                self::SETTING_TOOLBAR,
                array(
                    array(
                        'label' => OW::getLanguage()->text('base', 'view_all'),
                        'href' => OW::getRouter()->urlForRoute('group-default', array('groupId' => $forumGroup->getId()))
                    )
                )
            );
        }
    }

    public static function getStandardSettingValueList()
    {
        return array(
            self::SETTING_SHOW_TITLE => true,
            self::SETTING_TITLE => OW::getLanguage()->text('forum', 'widget_latest_topics_label'),
            self::SETTING_WRAP_IN_BOX => false,
            self::SETTING_ICON => self::ICON_FILES
        );
    }

    public static function getSettingList()
    {
        $settingList = array();

        $settingList['topicCount'] = array(
            'presentation' => self::PRESENTATION_NUMBER,
            'label' => OW::getLanguage()->text('forum', 'cmp_widget_forum_topics_count'),
            'value' => 3
        );

        return $settingList;
    }

    public static function getAccess()
    {
        return self::ACCESS_ALL;
    }
}