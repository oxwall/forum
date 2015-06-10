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
 * Forum edit post class.
 *
 * @author Alex Ermashev <alexermashev@gmail.com>
 * @package ow.ow_plugins.forum.mobile.components
 * @since 1.0
 */
class FORUM_MCMP_ForumEditPost extends OW_MobileComponent
{
    /**
     * Class constructor
     * 
     * @param array $params
     *      integer topicId
     *      integer postId
     */
    public function __construct(array $params = array())
    {
        parent::__construct();
        $forumService = FORUM_BOL_ForumService::getInstance();

        $topicId = !empty($params['topicId']) ? $params['topicId'] : -1;
        $postId = !empty($params['postId']) ? $params['postId'] : -1;

        $topicDto = $forumService->findTopicById($topicId);
        $postDto = $forumService->findPostById($postId);
        
        if ( !$topicDto || !$postDto || $topicDto->id != $postDto->topicId)
        {
            throw new Redirect404Exception();
        }

        $forumGroup = $forumService->getGroupInfo($topicDto->groupId);
        $forumSection = $forumService->findSectionById($forumGroup->sectionId);
        $isHidden = $forumSection->isHidden;

        // check access permissions
        if ( $isHidden )
        {
            throw new Redirect404Exception();
        }

        $isModerator = OW::getUser()->isAuthorized('forum');
        $userId = OW::getUser()->getId();
        $firstPostDto = $forumService->findTopicFirstPost($topicId);

        if ( !$isModerator && ($userId != 
                $postDto->userId || $topicDto->locked) && $postDto->id != $firstPostDto->id )
        {
            throw new Redirect404Exception();
        }

        $attachmentUid = uniqid();

        // get a form instance
        $form = new FORUM_CLASS_PostForm(
            'post_form', 
            $attachmentUid, 
            $topicId, 
            true
        );

        $form->setAction(OW::getRouter()->urlForRoute('edit-post', array(
            'id' => $postId
        )));

        $form->getElement('text')->setValue($postDto->text);
        $this->addForm($form);

        // attachments
        $enableAttachments = OW::getConfig()->getValue('forum', 'enable_attachments');

        if ( $enableAttachments )
        {
            $attachmentService = FORUM_BOL_PostAttachmentService::getInstance();
            $attachmentList = $attachmentService->findAttachmentsByPostIdList(array($postDto->id));
            $attachments = array();

            // process attachments
            if ( $attachmentList ) 
            {
                $attachmentList = array_shift($attachmentList);

                $index = 0;
                foreach($attachmentList as $attachment)
                {
                    $attachments[$index] = array(
                        'id' => $index, 
                        'name' => $attachment['fileName'], 
                        'size' => $attachment['fileSize'],
                        'dbId' => $attachment['id']
                    );

                    $index++;
                }

                $attachments = json_encode($attachments);
            }

            $this->assign('attachments', $attachments); 
            $attachmentCmp = new BASE_CLASS_FileAttachment('forum', $attachmentUid);
            $this->addComponent('attachmentsCmp', $attachmentCmp);
        }

        // assign view variables
        $this->assign('enableAttachments', $enableAttachments);
        $this->assign('attachmentUid', $attachmentUid);

        // include js files
        OW::getDocument()->addScript(OW::
                getPluginManager()->getPlugin('forum')->getStaticJsUrl() . 'mobile_attachment.js');
    }
}