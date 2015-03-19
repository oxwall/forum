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
 * Forum add topic controller
 *
 * @author Egor Bulgakov <egor.bulgakov@gmail.com>
 * @package ow.ow_plugins.forum.controllers
 * @since 1.0
 */
class FORUM_CTRL_AddTopic extends OW_ActionController
{

    /**
     * Controller's default action
     *
     * @param array $params
     * @throws AuthorizationException
     * @throws AuthenticateException
     */
    public function index( array $params = null )
    {
        $groupId = isset($params['groupId']) && (int) $params['groupId'] ? (int) $params['groupId'] : 0;

        $forumService = FORUM_BOL_ForumService::getInstance();

        $forumGroup = $forumService->getGroupInfo($groupId);
        if ( $forumGroup )
        {
            $forumSection = $forumService->findSectionById($forumGroup->sectionId);
            $isHidden = $forumSection->isHidden;
        }
        else
        {
            $isHidden = false;
        }

        if ( !OW::getUser()->isAuthenticated() )
        {
            throw new AuthenticateException();
        }

        $userId = OW::getUser()->getId();

        $this->assign('authMsg', null);

        if ( $isHidden && isset($forumSection) )
        {
            $eventParams = array('entity' => $forumSection->entity, 'entityId' => $forumGroup->entityId, 'action' => 'add_topic');
            $event = new OW_Event('forum.check_permissions', $eventParams);
            OW::getEventManager()->trigger($event);

            if ( !$event->getData() )
            {
                throw new AuthorizationException();
            }

            if ( !OW::getUser()->isAuthorized($forumSection->entity, 'add_topic') )
            {
                $status = BOL_AuthorizationService::getInstance()->getActionStatus($forumSection->entity, 'add_topic');
                throw new AuthorizationException($status['msg']);
            }

            $event = new OW_Event('forum.find_forum_caption', array('entity' => $forumSection->entity, 'entityId' => $forumGroup->entityId));
            OW::getEventManager()->trigger($event);

            $eventData = $event->getData();

            /** @var OW_Component $componentForumCaption */
            $componentForumCaption = $eventData['component'];

            if ( !empty($componentForumCaption) )
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
                    'href' => OW::getRouter()->urlForRoute('group-default', array('groupId' => $forumGroup->getId())),
                    'label' => OW::getLanguage()->text($forumSection->entity, 'view_all_topics')
                )
            );

            $breadCrumbCmp = new BASE_CMP_Breadcrumb($bcItems);
            $this->addComponent('breadcrumb', $breadCrumbCmp);

            OW::getNavigation()->deactivateMenuItems(OW_Navigation::MAIN);
            OW::getNavigation()->activateMenuItem(OW_Navigation::MAIN, $forumSection->entity, $eventData['key']);

            $groupSelect = array(array('label' => $forumGroup->name, 'value' => $forumGroup->getId(), 'disabled' => false));

            OW::getDocument()->setHeading(OW::getLanguage()->text($forumSection->entity, 'create_new_topic', array('group' => $forumGroup->name)));
        }
        else
        {
            $canEdit = OW::getUser()->isAuthorized('forum', 'edit');

            if ( !$userId )
            {
                throw new AuthorizationException();
            }
            else if ( !$canEdit )
            {
                $status = BOL_AuthorizationService::getInstance()->getActionStatus('forum', 'edit');
                throw new AuthorizationException($status['msg']);
            }

            if ( !OW::getRequest()->isAjax() )
            {
                OW::getNavigation()->activateMenuItem(OW_Navigation::MAIN, 'forum', 'forum');
            }

            $groupSelect = $forumService->getGroupSelectList(0, false, $userId);

            OW::getDocument()->setHeading(OW::getLanguage()->text('forum', 'create_new_topic'));
        }

        OW::getDocument()->setDescription(OW::getLanguage()->text('forum', 'meta_description_add_topic'));
        OW::getDocument()->setTitle(OW::getLanguage()->text('forum', 'meta_title_add_topic'));
        OW::getDocument()->setHeadingIconClass('ow_ic_write');

        $this->assign('isHidden', $isHidden);

        $uid = uniqid();
        $form = $this->generateForm($groupSelect, $groupId, $isHidden, $uid);

        OW::getDocument()->addStyleDeclaration('
			.disabled_option {
				color: #9F9F9F;
    		}
		');

        $enableAttachments = OW::getConfig()->getValue('forum', 'enable_attachments');
        if ( $enableAttachments )
        {
            $attachmentCmp = new BASE_CLASS_FileAttachment('forum', $uid);
            $this->addComponent('attachments', $attachmentCmp);
        }

        $this->assign('enableAttachments', $enableAttachments);

        if ( OW::getRequest()->isPost() && $form->isValid($_POST) )
        {
            $data = $form->getValues();

            if ( $data['group'] )
            {
                $topicDto = new FORUM_BOL_Topic();

                $topicDto->userId = $userId;
                $topicDto->groupId = $data['group'];
                $topicDto->title = strip_tags($data['title']);

                $forumService->addTopic($topicDto);

                $postDto = new FORUM_BOL_Post();

                $postDto->topicId = $topicDto->id;
                $postDto->userId = $userId;

                $postDto->text = UTIL_HtmlTag::stripJs(UTIL_HtmlTag::stripTags($data['text'], array('form', 'input', 'button'), null, true));
                $postDto->createStamp = time();

                $forumService->saveOrUpdatePost($postDto);
                $topicDto->lastPostId = $postDto->getId();

                $forumService->saveOrUpdateTopic($topicDto);

                // subscribe author to new posts
                if ( $data['subscribe'] )
                {
                    $subService = FORUM_BOL_SubscriptionService::getInstance();

                    $subs = new FORUM_BOL_Subscription();
                    $subs->userId = $userId;
                    $subs->topicId = $topicDto->id;

                    $subService->addSubscription($subs);
                }

                if ( $enableAttachments )
                {
                    $filesArray = BOL_AttachmentService::getInstance()->getFilesByBundleName('forum', $data['attachmentUid']);

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
                        
                        BOL_AttachmentService::getInstance()->deleteAttachmentByBundle('forum', $data['attachmentUid']);

                        if ( $skipped )
                        {
                            OW::getFeedback()->warning(OW::getLanguage()->text('forum', 'not_all_attachments_added'));
                        }
                    }
                }

                $topicUrl = OW::getRouter()->urlForRoute('topic-default', array('topicId' => $topicDto->id));

                //Newsfeed
                $params = array(
                    'pluginKey' => 'forum',
                    'entityType' => 'forum-topic',
                    'entityId' => $topicDto->id,
                    'userId' => $topicDto->userId
                );

                $event = new OW_Event('feed.action', $params);
                OW::getEventManager()->trigger($event);

                if ( $isHidden && isset($forumSection) )
                {
                    BOL_AuthorizationService::getInstance()->trackAction($forumSection->entity, 'add_topic');
                }
                else
                {
                    BOL_AuthorizationService::getInstance()->trackAction('forum', 'edit');
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

                OW::getEventManager()->trigger(new OW_Event(FORUM_BOL_ForumService::EVENT_AFTER_TOPIC_ADD, array(
                    'topicId' => $topicDto->id
                )));

                $this->redirect($topicUrl);
            }
            else
            {
                $form->getElement('group')->addError(OW::getLanguage()->text('forum', 'select_group_error'));
            }
        }
    }

    /**
     * Generates Add Topic Form.
     *
     * @param array $groupSelect
     * @param int $groupId
     * @param $isHidden
     * @param $uid
     * @return Form
     */
    private function generateForm( $groupSelect, $groupId, $isHidden, $uid )
    {
        $form = new Form('add-topic-form');
        $form->setEnctype("multipart/form-data");

        $lang = OW::getLanguage();

        $attachmentUid = new HiddenField('attachmentUid');
        $attachmentUid->setValue($uid);
        $attachmentUid->setRequired(true);
        $form->addElement($attachmentUid);

        $title = new TextField('title');
        $title->setRequired(true);
        $sValidator = new StringValidator(1, 255);
        $sValidator->setErrorMessage($lang->text('forum', 'chars_limit_exceeded', array('limit' => 255)));
        $title->addValidator($sValidator);
        $form->addElement($title);

        if ( $isHidden )
        {
            $group = new HiddenField('group');
            $group->setValue($groupId);
        }
        else
        {
            $group = new ForumSelectBox('group');
            $group->setOptions($groupSelect);
            if ( $groupId )
            {
                $group->setValue($groupId);
            }
            $group->setRequired(true);
            $group->addValidator(new IntValidator());
        }

        $form->addElement($group);

        $btnSet = array(BOL_TextFormatService::WS_BTN_IMAGE, BOL_TextFormatService::WS_BTN_VIDEO, BOL_TextFormatService::WS_BTN_HTML);
        $text = new WysiwygTextarea('text', $btnSet);
        $text->setRequired(true);
        $sValidator = new StringValidator(1, 50000);
        $sValidator->setErrorMessage($lang->text('forum', 'chars_limit_exceeded', array('limit' => 50000)));
        $text->addValidator($sValidator);
        $form->addElement($text);

        $subscribe = new CheckboxField('subscribe');
        $subscribe->setLabel($lang->text('forum', 'subscribe'));
        $subscribe->setValue(true);
        $form->addElement($subscribe);

        $post = new Submit('post');
        $post->setValue($lang->text('forum', 'add_post_btn'));
        $form->addElement($post);

        $this->addForm($form);

        return $form;
    }
}
