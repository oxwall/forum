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
 * Forum edit topic class.
 *
 * @author Alex Ermashev <alexermashev@gmail.com>
 * @package ow.ow_plugins.forum.mobile.components
 * @since 1.0
 */
class FORUM_MCMP_ForumEditTopic extends OW_MobileComponent
{
    /**
     * Class constructor
     * 
     * @param array $params
     *      integer topicId
     * @throws Redirect404Exception
     */
    public function __construct(array $params = array())
    {
        parent::__construct();

        $forumService = FORUM_BOL_ForumService::getInstance();
        $topicId = !empty($params['topicId']) ? $params['topicId'] : -1;

        $topicDto = $forumService->findTopicById($topicId);

        if ( !$topicDto )
        {
            throw new Redirect404Exception();
        }

        $forumGroup = $forumService->getGroupInfo($topicDto->groupId);
        $forumSection = $forumService->findSectionById($forumGroup->sectionId);
        $isHidden = $forumSection->isHidden;
        $userId = OW::getUser()->getId();

        // check access permissions
        if ( $isHidden )
        {
            throw new Redirect404Exception();
        }

        $isModerator = OW::getUser()->isAuthorized('forum');
        $canEdit = OW::getUser()->isAuthorized('forum', 'edit') && $userId == $topicDto->userId;

        if ( !$canEdit && !$isModerator )
        {
            throw new AuthorizationException();
        }

        // first topic's post
        $postDto = $forumService->findTopicFirstPost($topicId);
        $attachmentUid = uniqid();

        // get a form instance
        $form = new FORUM_CLASS_TopicEditForm(
            'topic_edit_form', 
            $attachmentUid,
            $topicDto,
            $postDto,
            true
        );

        $form->setAction(OW::getRouter()->urlForRoute('edit-topic', array(
            'id' => $topicDto->id
        )));

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
        $this->assign('topicId', $topicId);

        // include js files
        OW::getDocument()->addScript(OW::
                getPluginManager()->getPlugin('forum')->getStaticJsUrl() . 'mobile_attachment.js');
    }
}