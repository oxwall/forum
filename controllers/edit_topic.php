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
 * Forum edit topic action controller
 *
 * @author Egor Bulgakov <egor.bulgakov@gmail.com>
 * @package ow.ow_plugins.forum.controllers
 * @since 1.0
 */
class FORUM_CTRL_EditTopic extends OW_ActionController
{

    /**
     * Controller's default action
     *
     * @param array $params
     * @throws AuthorizationException
     * @throws Redirect404Exception
     */
    public function index( array $params = null )
    {
        $forumService = FORUM_BOL_ForumService::getInstance();

        if ( !isset($params['id']) || !($topicId = (int) $params['id']) )
        {
            throw new Redirect404Exception();
        }

        $topicDto = $forumService->findTopicById($topicId);

        if ( !$topicDto )
        {
            throw new Redirect404Exception();
        }

        $forumGroup = $forumService->getGroupInfo($topicDto->groupId);
        $forumSection = $forumService->findSectionById($forumGroup->sectionId);

        $isHidden = $forumSection->isHidden;

        $userId = OW::getUser()->getId();

        if ( $isHidden )
        {
            $isModerator = OW::getUser()->isAuthorized($forumSection->entity);

            $eventParams = array('entity' => $forumSection->entity, 'entityId' => $forumGroup->entityId, 'action' => 'add_topic');
            $event = new OW_Event('forum.check_permissions', $eventParams);
            OW::getEventManager()->trigger($event);
            $canPost = $event->getData();

            //check permissions
            $canEdit = OW::getUser()->isAuthorized($forumSection->entity, 'add_topic') && $userId == $topicDto->userId;

            if ( !$isModerator )
            {
                if ( !$canPost )
                {
                    throw new AuthorizationException();
                }
                else if ( !$canEdit )
                {
                    $status = BOL_AuthorizationService::getInstance()->getActionStatus(
                        $forumSection->entity, 'add_topic'
                    );
                    throw new AuthorizationException($status['msg']);
                }
            }
        }
        else
        {
            $isModerator = OW::getUser()->isAuthorized('forum');
            $canEdit = OW::getUser()->isAuthorized('forum', 'edit') && $userId == $topicDto->userId;

            if ( !$canEdit && !$isModerator )
            {
                throw new AuthorizationException();
            }
        }

        // first topic's post
        $postDto = $forumService->findTopicFirstPost($topicId);
        $this->assign('post', $postDto);

        $uid = uniqid();
        $editTopicForm = $this->generateEditTopicForm($topicDto, $postDto, $uid);
        $this->addForm($editTopicForm);
        $lang = OW::getLanguage();
        $router = OW::getRouter();

        $topicInfo = $forumService->getTopicInfo($topicId);
        $groupUrl = $router->urlForRoute('group-default', array('groupId' => $topicDto->groupId));
        $topicUrl = $router->urlForRoute('topic-default', array('topicId' => $topicDto->id));
        
        $lang->addKeyForJs('forum', 'confirm_delete_attachment');

        $attachmentService = FORUM_BOL_PostAttachmentService::getInstance();

        $enableAttachments = OW::getConfig()->getValue('forum', 'enable_attachments');
        $this->assign('enableAttachments', $enableAttachments);

        if ( $enableAttachments )
        {
            $attachments = $attachmentService->findAttachmentsByPostIdList(array($postDto->id));
            $this->assign('attachments', $attachments);

            $attachmentCmp = new BASE_CLASS_FileAttachment('forum', $uid);
            $this->addComponent('attachmentsCmp', $attachmentCmp);
        }
        
        if ( OW::getRequest()->isPost() && $editTopicForm->isValid($_POST) )
        {
            $values = $editTopicForm->getValues();
            
            $topicId = (int) $values['topic-id'];
            $postId = (int) $values['post-id'];
            $title = trim($values['title']);
            $text = trim($values['text']);

            $topicDto = $forumService->findTopicById($topicId);
            $postDto = $forumService->findPostById($postId);

            if ( $topicDto === null || $postDto === null || ($topicDto->userId != $userId && !$isModerator) )
            {
                exit();
            }

            //save topic
            $topicDto->title = strip_tags($title);
            $forumService->saveOrUpdateTopic($topicDto);

            //save post
            $postDto->text = UTIL_HtmlTag::stripJs(UTIL_HtmlTag::stripTags($text, array('form', 'input', 'button'), null, true));
            $forumService->saveOrUpdatePost($postDto);

            //save post edit info
            $editPostDto = $forumService->findEditPost($postId);

            if ( $editPostDto === null )
            {
                $editPostDto = new FORUM_BOL_EditPost();
            }

            $editPostDto->postId = $postId;
            $editPostDto->userId = $userId;
            $editPostDto->editStamp = time();
            $forumService->saveOrUpdateEditPost($editPostDto);

            if ( $enableAttachments )
            {
                $filesArray = BOL_AttachmentService::getInstance()->getFilesByBundleName('forum', $values['attachmentUid']);

                if ( $filesArray )
                {
                    $attachmentService = FORUM_BOL_PostAttachmentService::getInstance();
                    $skipped = 0;

                    foreach ( $filesArray as $file )
                    {
                        $attachmentDto = new FORUM_BOL_PostAttachment();
                        $attachmentDto->postId = $postDto->id;
                        $attachmentDto->fileName = $file['dto']->origFileName;
                        $attachmentDto->fileNameClean = $file['dto']->fileName;
                        $attachmentDto->fileSize = $file['dto']->size * 1024;
                        $attachmentDto->hash = uniqid();

                        $added = $attachmentService->addAttachment($attachmentDto, $file['path']);

                        if ( !$added )
                        {
                            $skipped++;
                        }
                    }

                    BOL_AttachmentService::getInstance()->deleteAttachmentByBundle('forum', $values['attachmentUid']);
                    
                    if ( $skipped )
                    {
                        OW::getFeedback()->warning(OW::getLanguage()->text('forum', 'not_all_attachments_added'));
                    }
                }
            }
            
            $params = array(
                'topicId' => $topicDto->id,
                'entity' => $forumSection->entity ? $forumSection->entity : NULL,
                'entityId' => $forumGroup->entityId ? $forumGroup->entityId : NULL,
                'userId' => $topicDto->userId,
                'topicUrl' => $topicUrl,
                'topicTitle' => $topicDto->title,
                'postText' => $postDto->text
            );
            $event = new OW_Event('forum.topic_add', $params);
            OW::getEventManager()->trigger($event);

            OW::getEventManager()->trigger(new OW_Event('feed.action', array(
                'pluginKey' => 'forum',
                'entityType' => 'forum-topic',
                'entityId' => $topicDto->id,
                'userId' => $topicDto->userId,
                'time' => $postDto->createStamp
            )));

            OW::getEventManager()->trigger(new OW_Event(FORUM_BOL_ForumService::EVENT_AFTER_TOPIC_EDIT, array(
                'topicId' => $topicDto->id
            )));

            $this->redirect($topicUrl);
        }

        OW::getDocument()->setHeading(OW::getLanguage()->text('forum', 'edit_topic_title'));
        OW::getDocument()->setHeadingIconClass('ow_ic_edit');

        $this->assign('isHidden', $isHidden);

        if ( $isHidden )
        {
            $event = new OW_Event('forum.find_forum_caption', array('entity' => $forumSection->entity, 'entityId' => $forumGroup->entityId));
            OW::getEventManager()->trigger($event);

            $eventData = $event->getData();

            /** @var OW_Component $componentForumCaption */
            $componentForumCaption = $eventData['component'];

            if (!empty($componentForumCaption))
            {
                $this->assign('componentForumCaption', $componentForumCaption->render());
            }
            else
            {
                $componentForumCaption = false;
                $this->assign('componentForumCaption', $componentForumCaption);
            }

            $bcItems = array(
                array(
                    'href' => OW::getRouter()->urlForRoute('topic-default', array('topicId' => $topicId )),
                    'label' => OW::getLanguage()->text('forum', 'back_to_topic')
                )
            );

            $breadCrumbCmp = new BASE_CMP_Breadcrumb($bcItems);
            $this->addComponent('breadcrumb', $breadCrumbCmp);

            OW::getNavigation()->deactivateMenuItems(OW_Navigation::MAIN);
            OW::getNavigation()->activateMenuItem(OW_Navigation::MAIN, $forumSection->entity, $eventData['key']);
        }
        else
        {
            $bcItems = array(
                array(
                    'href' => $router->urlForRoute('forum-default'),
                    'label' => $lang->text('forum', 'forum_index')
                ),
                array(
                    'href' => $router->urlForRoute('forum-default') . '#section-' . $topicInfo['sectionId'],
                    'label' => $topicInfo['sectionName']
                ),
                array(
                    'href' => $groupUrl,
                    'label' => $topicInfo['groupName']
                ),
                array(
                    'href' => $topicUrl,
                    'label' => htmlspecialchars($topicDto->title)
                )
            );

            $breadCrumbCmp = new BASE_CMP_Breadcrumb($bcItems, $lang->text('forum', 'topic_location'));
            $this->addComponent('breadcrumb', $breadCrumbCmp);

            OW::getNavigation()->activateMenuItem(OW_Navigation::MAIN, 'forum', 'forum');
        }
    }

    /**
     * Generates edit topic form.
     *
     * @param $topicDto
     * @param $postDto
     * @param $uid
     * @return Form
     */
    private function generateEditTopicForm( $topicDto, $postDto, $uid )
    {
        $form = new Form('edit-topic-form');
        $form->setEnctype('multipart/form-data');
        
        $lang = OW::getLanguage();
        
        $topicIdField = new HiddenField('topic-id');
        $topicIdField->setValue($topicDto->id);
        $form->addElement($topicIdField);

        $postIdField = new HiddenField('post-id');
        $postIdField->setValue($postDto->id);
        $form->addElement($postIdField);

        $attachmentUid = new HiddenField('attachmentUid');
        $attachmentUid->setValue($uid);
        $attachmentUid->setRequired(true);
        $form->addElement($attachmentUid);

        $topicTitleField = new TextField('title');
        $topicTitleField->setValue($topicDto->title);
        $topicTitleField->setRequired(true);
        $sValidator = new StringValidator(1, 255);
        $sValidator->setErrorMessage($lang->text('forum', 'chars_limit_exceeded', array('limit' => 255)));
        $topicTitleField->addValidator($sValidator);
        $form->addElement($topicTitleField);

        $btnSet = array(BOL_TextFormatService::WS_BTN_IMAGE, BOL_TextFormatService::WS_BTN_VIDEO, BOL_TextFormatService::WS_BTN_HTML);
        $postText = new WysiwygTextarea('text', $btnSet);
        $postText->setValue($postDto->text);
        $postText->setRequired(true);
        $sValidator = new StringValidator(1, 50000);
        $sValidator->setErrorMessage($lang->text('forum', 'chars_limit_exceeded', array('limit' => 50000)));
        $postText->addValidator($sValidator);
        $form->addElement($postText);

        $submit = new Submit('save');
        $submit->setValue($lang->text('base', 'edit_button'));
        $form->addElement($submit);

        return $form;
    }
}
