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
 * @author Alex Ermashev <alexermashev@gmail.com>
 * @package ow.plugin.forum.mobile.controllers
 * @since 1.6.0
 */
class FORUM_MCTRL_Topic extends FORUM_MCTRL_AbstractForum
{
    /**
     * Topic index
     * 
     * @param array $params
     */
    public function index( array $params )
    {
        // get topic info
        if ( !isset($params['topicId']) 
                || ($topicDto = $this->forumService->findTopicById($params['topicId'])) === null )
        {
            throw new Redirect404Exception();
        }

        $forumGroup = $this->forumService->findGroupById($topicDto->groupId);
        $forumSection = $this->forumService->findSectionById($forumGroup->sectionId);

        // users cannot see topics in hidden sections
        if ( !$forumSection || $forumSection->isHidden )
        {
            throw new Redirect404Exception();
        }

        $userId = OW::getUser()->getId();
        $isModerator = OW::getUser()->isAuthorized('forum');

        // check the permission for private topic
        if ( $forumGroup->isPrivate )
        {
            if ( !$userId )
            {
                throw new AuthorizationException();
            }
            else if ( !$isModerator )
            {
                if ( !$this->forumService->isPrivateGroupAvailable($userId, json_decode($forumGroup->roles)) )
                {
                    throw new AuthorizationException();
                }
            }
        }

        //update topic's view count
        $topicDto->viewCount += 1;
        $this->forumService->saveOrUpdateTopic($topicDto);

        //update user read info
        $this->forumService->setTopicRead($topicDto->id, $userId);

        $page = !empty($_GET['page']) && (int) $_GET['page'] ? abs((int) $_GET['page']) : 1;

        $topicInfo = $this->forumService->getTopicInfo($topicDto->id);
        $postCount = $this->forumService->findTopicPostCount($topicDto->id);
        $postList  = $postCount 
            ? $this->forumService->getTopicPostList($topicDto->id, $page)
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
            $post['permalink'] = $this->forumService->getPostUrl($post['topicId'], $post['id'], true, $page);
            $post['number'] = ($page - 1) * $this->forumService->getPostPerPageConfig() + $iteration + 1;

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

        $canEdit = OW::getUser()->isAuthorized('forum', 'edit') || $isModerator ? true : false;
        $enableAttachments = OW::getConfig()->getValue('forum', 'enable_attachments');

        // paginate
        $perPage = $this->forumService->getPostPerPageConfig();
        $pageCount = ($postCount) ? ceil($postCount / $perPage) : 1;
        $paging = new BASE_CMP_PagingMobile($page, $pageCount, $perPage);

        //printVar($avatars);exit;
        //printVar($postList);exit;
        //printVar($this->forumService->findTopicFirstPost($topicDto->id));
        //printVar($topicInfo);

        // assign view variables
        $this->assign('topicInfo', $topicInfo);
        $this->assign('postList', $postList);
        $this->assign('onlineUsers', BOL_UserService::getInstance()->findOnlineStatusForUserList($userIds));
        $this->assign('avatars', BOL_AvatarService::getInstance()->getDataForUserAvatars($userIds));
        $this->assign('enableAttachments', $enableAttachments);        
        $this->assign('paging', $paging->render());
        $this->assign('firstTopic', $this->forumService->findTopicFirstPost($topicDto->id));
        $this->assign('canEdit', $canEdit);
        $this->assign('canLock', $isModerator);
        $this->assign('canSticky', $isModerator);
        $this->assign('canSubscribe', OW::getUser()->isAuthorized('forum', 'subscribe'));
        $this->assign('isSubscribed', $userId && FORUM_BOL_SubscriptionService::getInstance()->isUserSubscribed($userId, $topicDto->id));

//$this->assign('canPost', OW::getUser()->isAuthorized('forum', 'edit'));
        //$this->assign('isModerator', $isModerator);
        

        if ( $enableAttachments )
        {
            $this->assign('attachments', 
                    FORUM_BOL_PostAttachmentService::getInstance()->findAttachmentsByPostIdList($postIds));
        }

        OW::getDocument()->setDescription(OW::getLanguage()->text('forum', 'meta_description_forums'));
        OW::getDocument()->setHeading(OW::getLanguage()->text('forum', 'forum_topic'));
        OW::getDocument()->setTitle(OW::getLanguage()->text('forum', 'forum_topic'));
    }
}

        
        /*
                $isOwner = ( $topicDto->userId == $userId ) ? true : false;
        $canEdit = $isOwner || $isModerator;
        $canPost = OW::getUser()->isAuthorized('forum', 'edit');
        $canMoveToHidden = BOL_AuthorizationService::getInstance()->isActionAuthorized('forum', 'move_topic_to_hidden') && $isModerator;
        $canLock = $canSticky = $isModerator;
*/