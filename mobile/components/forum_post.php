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
 * Forum post class.
 *
 * @author Alex Ermashev <alexermashev@gmail.com>
 * @package ow.ow_plugins.forum.mobile.components
 * @since 1.0
 */
class FORUM_MCMP_ForumPost extends OW_MobileComponent
{
    /**
     * Class constructor
     * 
     * @param array $params
     *      integer page
     *      array topicInfo
     */
    public function __construct(array $params = array())
    {
        parent::__construct();

        $forumService   = FORUM_BOL_ForumService::getInstance();
        $page           = !empty($params['page']) ? $params['page'] : 1;
        $topicInfo      = !empty($params['topicInfo']) ? $params['topicInfo'] : array();

        $canEdit = !empty($params['canEdit'])     
            ? (bool) $params['canEdit'] 
            : false;

        $canPost = !empty($params['canPost'])     
            ? (bool) $params['canPost'] 
            : false;

        $postCount = $forumService->findTopicPostCount($topicInfo['id']);
        $postList  = $postCount 
            ? $forumService->getTopicPostList($topicInfo['id'], $page)
            : array();

        OW::getEventManager()->trigger(new OW_Event('forum.topic_post_list', array('list' => $postList)));

        if ( !$postList )
        {
            throw new Redirect404Exception();
        }

        // process list of posts
        $iteration = 0;
        $userIds = array();
        $postIds = array();

        foreach ( $postList as &$post)
        {
            $post['text'] = UTIL_HtmlTag::autoLink($post['text']);
            $post['permalink'] = $forumService->getPostUrl($post['topicId'], $post['id'], true, $page);
            $post['number'] = ($page - 1) * $forumService->getPostPerPageConfig() + $iteration + 1;

            // get list of users
            if ( !in_array($post['userId'], $userIds) )
            {
                $userIds[$post['userId']] = $post['userId'];
            }

            if ( count($post['edited']) && !in_array($post['edited']['userId'], $userIds) )
            {
                $userIds[$post['edited']['userId']] = $post['edited']['userId'];
            }

            $iteration++;
            array_push($postIds, $post['id']);
        }

        $enableAttachments = OW::getConfig()->getValue('forum', 'enable_attachments');

        // paginate
        $perPage = $forumService->getPostPerPageConfig();
        $pageCount = ($postCount) ? ceil($postCount / $perPage) : 1;
        $paging = new BASE_CMP_PagingMobile($page, $pageCount, $perPage);
        
        // assign view variables
        $this->assign('topicInfo', $topicInfo);
        $this->assign('postList', $postList);
        $this->assign('onlineUsers', BOL_UserService::getInstance()->findOnlineStatusForUserList($userIds));
        $this->assign('avatars', BOL_AvatarService::getInstance()->getDataForUserAvatars($userIds));
        $this->assign('enableAttachments', $enableAttachments);        
        $this->assign('paging', $paging->render());
        $this->assign('firstTopic', $forumService->findTopicFirstPost($topicInfo['id']));
        $this->assign('canEdit', $canEdit);
        $this->assign('canPost', $canPost);
        $this->assign('postEnityType', FORUM_CLASS_ContentProvider::POST_ENTITY_TYPE);
        $this->assign('topicEnityType', FORUM_CLASS_ContentProvider::ENTITY_TYPE);
        $this->assign('postsCount', $forumService->findPostCountListByUserIds($userIds));

        if ( $enableAttachments )
        {
            $this->assign('attachments', 
                    FORUM_BOL_PostAttachmentService::getInstance()->findAttachmentsByPostIdList($postIds));
        }
    }
}