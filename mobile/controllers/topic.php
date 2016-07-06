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
        $isOwner = ( $topicDto->userId == $userId ) ? true : false;

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

        $topicInfo = $this->forumService->getTopicInfo($topicDto->id);
        $page = !empty($_GET['page']) && (int) $_GET['page'] ? abs((int) $_GET['page']) : 1;
        $canEdit = OW::getUser()->isAuthorized('forum', 'edit') || $isModerator ? true : false;

        // include js translations
        OW::getLanguage()->addKeyForJs('forum', 'post_attachment');
        OW::getLanguage()->addKeyForJs('forum', 'attached_files');
        OW::getLanguage()->addKeyForJs('forum', 'confirm_delete_all_attachments');
        OW::getLanguage()->addKeyForJs('forum', 'confirm_delete_attachment');

        // assign view variables
        $firstPost = $this->forumService->findTopicFirstPost($topicDto->id);
        $this->assign('firstTopicPost', $firstPost);
        $this->assign('userId', $userId);
        $this->assign('topicInfo', $topicInfo);
        $this->assign('page', $page);
        $this->assign('isOwner', $isOwner);
        $this->assign('isModerator', $isModerator);
        $this->assign('canEdit', $canEdit);
        $this->assign('canPost', $canEdit);
        $this->assign('canLock', $isModerator);
        $this->assign('canSticky', $isModerator);
        $this->assign('canSubscribe', OW::getUser()->isAuthorized('forum', 'subscribe'));
        $this->assign('isSubscribed', $userId 
                && FORUM_BOL_SubscriptionService::getInstance()->isUserSubscribed($userId, $topicDto->id));
        
        // remember the last forum page
        OW::getSession()->set('last_forum_page', OW_URL_HOME . OW::getRequest()->getRequestUri());

        // set current page settings
//        OW::getDocument()->setDescription(OW::getLanguage()->text('forum', 'meta_description_forums'));
        OW::getDocument()->setHeading(OW::getLanguage()->text('forum', 'forum_topic'));
//        OW::getDocument()->setTitle(OW::getLanguage()->text('forum', 'forum_topic'));

        $params = array(
            "sectionKey" => "forum",
            "entityKey" => "topic",
            "title" => "forum+meta_title_topic",
            "description" => "forum+meta_desc_topic",
            "keywords" => "forum+meta_keywords_topic",
            "vars" => array( "topic_name" => $topicInfo['title'], "topic_description" => $firstPost->text )
        );

        OW::getEventManager()->trigger(new OW_Event("base.provide_page_meta_info", $params));
    }

    
    /**
     * Delete forum post
     */
    public function ajaxDeletePost( array $params )
    {
        $result  = false;
        $postUrl = null;

        $topicId = !empty($params['topicId']) ? (int) $params['topicId'] : null;
        $postId = !empty($params['postId']) ? (int) $params['postId'] : null;

        if ( OW::getRequest()->isPost() && $topicId && $postId ) 
        {
            $topicDto = $this->forumService->findTopicById($topicId);
            $postDto = $this->forumService->findPostById($postId);

            if ( $topicDto && $postDto )
            {
                $forumGroup = $this->forumService->findGroupById($topicDto->groupId);
                $forumSection = $this->forumService->findSectionById($forumGroup->sectionId);
                $userId = OW::getUser()->getId();
                $isModerator = OW::getUser()->isAuthorized('forum');

                if ( !$forumSection->isHidden && ($postDto->userId == $userId || $isModerator) )
                {
                    $prevPostDto = $this->forumService->findPreviousPost($topicId, $postId);

                    if ( $prevPostDto ) 
                    {
                        $topicDto->lastPostId = $prevPostDto->id;
                        $this->forumService->saveOrUpdateTopic($topicDto);

                        $this->forumService->deletePost($postId);
                        $postUrl = $this->forumService->getPostUrl($topicId, $prevPostDto->id, false);
                        $result = true;
                    }
                }
            }
        }

        die(json_encode(array(
            'result' => $result,
            'url' => $postUrl
        )));
    }

    /**
     * Delete attachment
     */
    public function ajaxDeleteAttachment()
    {
        $result  = false;
        $attachmentIds = !empty($_POST['id']) ? $_POST['id'] : null;

        if ( OW::getRequest()->isPost() && $attachmentIds ) 
        {
            if (!is_array($attachmentIds)) {
                $attachmentIds = array($attachmentIds);
            }

            $attachmentService = FORUM_BOL_PostAttachmentService::getInstance();
            $forumService = FORUM_BOL_ForumService::getInstance();
            $userId = OW::getUser()->getId();
            $isAuthorized = OW::getUser()->isAuthorized('forum');

            foreach ($attachmentIds as $attachmentId)
            {                
                $attachment = $attachmentService->findPostAttachmentById($attachmentId);

                if ( $attachment ) 
                {                    
                    $post = $forumService->findPostById($attachment->postId);

                    if ( $post )
                    {
                        // check the ownership
                        if ( $isAuthorized || $post->userId == $userId )
                        {
                            $attachmentService->deleteAttachment($attachment->id);
                            $result = true;
                            continue;
                        }
                    }
                }

                $result = false;
            }
        }

        die(json_encode(array(
            'result' => $result 
        )));
    }

    /**
     * This action deletes the topic
     *
     * @param array $params
     */
    public function ajaxDeleteTopic( array $params )
    {
        $result  = false;
        $topicId = !empty($params['topicId']) ? (int) $params['topicId'] : -1;
        $userId = OW::getUser()->getId();

        if ( OW::getRequest()->isPost() ) 
        {
            $topicDto = $this->forumService->findTopicById($topicId);

            if ( $topicDto )
            {
                $isModerator = OW::getUser()->isAuthorized('forum');
                $forumGroup = $this->forumService->findGroupById($topicDto->groupId);
                $forumSection = $this->forumService->findSectionById($forumGroup->sectionId);

                if ( !$forumSection->isHidden 
                        && ($isModerator || $userId == $topicDto->userId))
                {
                    $this->forumService->deleteTopic($topicId);
                    $result = true;
                }
            }
        }

        die(json_encode(array(
            'result' => $result 
        )));
    }

    /**
     * This action subscribe or unsubscribe the topic
     *
     * @param array $params
     */
    public function ajaxSubscribeTopic( array $params )
    {
        $result  = false;
        $topicId = !empty($params['topicId']) ? (int) $params['topicId'] : -1;
        $userId = OW::getUser()->getId();

        if ( OW::getRequest()->isPost() ) 
        {
            $subscribeService = FORUM_BOL_SubscriptionService::getInstance();
            $topicDto = $this->forumService->findTopicById($topicId);

            if ( $topicDto )
            {
                if ( OW::getUser()->isAuthorized('forum', 'subscribe') )
                {
                    if ( !$subscribeService->isUserSubscribed($userId, $topicId) )
                    {
                        $subscription = new FORUM_BOL_Subscription;
                        $subscription->userId = $userId;
                        $subscription->topicId = $topicId;

                        $subscribeService->addSubscription($subscription);
                    }
                    else
                    {
                        $subscribeService->deleteSubscription($userId, $topicId);
                    }

                    $result = true;
                }
            }
        }

        die(json_encode(array(
            'result' => $result 
        )));
    }

    /**
     * This action locks or unlocks the topic
     *
     * @param array $params
     */
    public function ajaxLockTopic( array $params )
    {
        $result  = false;
        $topicId = !empty($params['topicId']) ? (int) $params['topicId'] : -1;

        if ( OW::getRequest()->isPost() ) 
        {
            $isModerator = OW::getUser()->isAuthorized('forum');
            $topicDto = $this->forumService->findTopicById($topicId);

            if ( $topicDto )
            {
                if ( $isModerator )
                {
                    $topicDto->locked = ($topicDto->locked) ? 0 : 1;
                    $this->forumService->saveOrUpdateTopic($topicDto);
                    $result = true;
                }
            }
        }

        die(json_encode(array(
            'result' => $result 
        )));
    }

    /**
     * This action sticky or unsticky the topic
     *
     * @param array $params
     */
    public function ajaxStickyTopic( array $params )
    {
        $result  = false;
        $topicId = !empty($params['topicId']) ? (int) $params['topicId'] : -1;

        if ( OW::getRequest()->isPost() ) 
        {
            $isModerator = OW::getUser()->isAuthorized('forum');
            $topicDto = $this->forumService->findTopicById($topicId);

            if ( $topicDto )
            {
                if ( $isModerator )
                {
                    $topicDto->sticky = ($topicDto->sticky) ? 0 : 1;
                    $this->forumService->saveOrUpdateTopic($topicDto);
                    $result = true;
                }
            }
        }

        die(json_encode(array(
            'result' => $result 
        )));
    }
}