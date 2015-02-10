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
 * Forum customize action controller
 *
 * @author Egor Bulgakov <egor.bulgakov@gmail.com>
 * @package ow.ow_plugins.forum.controllers
 * @since 1.0
 */
class FORUM_CTRL_Customize extends OW_ActionController
{

    /**
     * Controller's default action
     */
    public function index()
    {
        $forumService = FORUM_BOL_ForumService::getInstance();

        $sectionGroupList = $forumService->getCustomSectionGroupList();
        $isModerator = OW::getUser()->isAuthorized('forum');

        if ( !$isModerator )
        {
            throw new Redirect404Exception();
        }

        $this->assign('sectionGroupList', $sectionGroupList);
        
        $plugin = OW::getPluginManager()->getPlugin('forum');

        //add langs for javascript
        OW::getLanguage()->addKeyForJs('forum', 'delete_section_confirm');
        OW::getLanguage()->addKeyForJs('forum', 'delete_group_confirm');
        OW::getLanguage()->addKeyForJs('forum', 'add_new_forum_title');
        OW::getLanguage()->addKeyForJs('forum', 'edit_section_title');
        OW::getLanguage()->addKeyForJs('forum', 'edit_group_title');
        
        OW::getDocument()->addScript(OW::getPluginManager()->getPlugin('base')->getStaticJsUrl() . 'jquery-ui.min.js');
        OW::getDocument()->addScript($plugin->getStaticJsUrl() . 'forum.js');

        $sortSectionOrderUrl = OW::getRouter()->urlFor('FORUM_CTRL_Customize', 'sortSectionOrder');
        $sortGroupOrderUrl = OW::getRouter()->urlFor('FORUM_CTRL_Customize', 'sortGroupOrder');
        $deleteSectionUrl = OW::getRouter()->urlFor('FORUM_CTRL_Customize', 'deleteSection');
        $deleteGroupUrl = OW::getRouter()->urlFor('FORUM_CTRL_Customize', 'deleteGroup');
        $getSectionUrl = OW::getRouter()->urlFor('FORUM_CTRL_Customize', 'getSection');
        $getGroupUrl = OW::getRouter()->urlFor('FORUM_CTRL_Customize', 'getGroup');
        $editSectionUrl = OW::getRouter()->urlFor('FORUM_CTRL_Customize', 'editSection');
        $editGroupUrl = OW::getRouter()->urlFor('FORUM_CTRL_Customize', 'editGroup');
        $addForumUrl = OW::getRouter()->urlFor('FORUM_CTRL_Customize', 'addForum');
        $suggestSectionUrl = OW::getRouter()->urlFor('FORUM_CTRL_Customize', 'suggestSection');
        $forumIndexUrl = OW::getRouter()->urlForRoute('forum-default');

        $onloadJs = "
			ForumCustomize.sortSectionOrderUrl = '$sortSectionOrderUrl';
			ForumCustomize.sortGroupOrderUrl = '$sortGroupOrderUrl';
			ForumCustomize.deleteSectionUrl = '$deleteSectionUrl';
			ForumCustomize.deleteGroupUrl = '$deleteGroupUrl';
			ForumCustomize.suggestSectionUrl = '$suggestSectionUrl';
			ForumCustomize.getSectionUrl = '$getSectionUrl';
			ForumCustomize.getGroupUrl = '$getGroupUrl';
			ForumCustomize.forumIndexUrl = '$forumIndexUrl';
			
        	ForumCustomize.construct();
        	";
        OW::getDocument()->addOnloadScript($onloadJs);

        $addForumForm = $this->generateAddForumForm($addForumUrl);
        $this->addForm($addForumForm);

        $editSectionForm = $this->generateEditSectionForm($editSectionUrl);
        $this->addForm($editSectionForm);

        $editGroupForm = $this->generateEditGroupForm($editGroupUrl);
        $this->addForm($editGroupForm);

        OW::getDocument()->setHeading(OW::getLanguage()->text('forum', 'forum'));
        OW::getDocument()->setHeadingIconClass('ow_ic_forum');
        
        OW::getNavigation()->activateMenuItem(OW_Navigation::MAIN, 'forum', 'forum');
    }

    /**
     * This action updates section orders called by ajax request
     */
    public function sortSectionOrder()
    {
        $isModerator = OW::getUser()->isAuthorized('forum');

        if ( OW::getRequest()->isAjax() && $_POST['data'] && $isModerator )
        {
            $forumService = FORUM_BOL_ForumService::getInstance();

            $postData = array();
            parse_str($_POST['data'], $postData);

            if ( !$postData['section'] )
            {
                return false;
            }

            $sectionOrderList = array_flip($postData['section']);

            $sectionDtoList = $forumService->findSectionList();

            foreach ( $sectionDtoList as $sectionDto )
            {
                $sectionDto->order = $sectionOrderList[$sectionDto->id] + 1;
                $forumService->saveOrUpdateSection($sectionDto);
            }
        }

        exit();
    }

    /**
     * This action updates group orders called by ajax request
     */
    public function sortGroupOrder()
    {
        $isModerator = OW::getUser()->isAuthorized('forum');

        if ( OW::getRequest()->isAjax() && $_POST['data'] && $isModerator )
        {
            $forumService = FORUM_BOL_ForumService::getInstance();

            $sectionGroupList = json_decode($_POST['data'], true);

            foreach ( $sectionGroupList as $sectionGroup )
            {
                if ( !$sectionGroup['order'] )
                {
                    continue;
                }

                $sectionId = (int) $sectionGroup['sectionId'];

                $postData = array();
                parse_str($sectionGroup['order'], $postData);

                $groupOrderList = array_flip($postData['group']);

                $groupDtoList = $forumService->findGroupByIdList($postData['group']);
                $groupDto = new FORUM_BOL_Group();
                foreach ( $groupDtoList as $groupDto )
                {
                    $groupDto->sectionId = $sectionId;
                    $groupDto->order = $groupOrderList[$groupDto->id] + 1;

                    $forumService->saveOrUpdateGroup($groupDto);
                }
            }
        }

        exit();
    }

    /**
     * This action deletes section called by ajax request
     */
    public function deleteSection()
    {
        $isModerator = OW::getUser()->isAuthorized('forum');

        $sectionId = (int) $_POST['sectionId'];

        if ( OW::getRequest()->isAjax() && $sectionId && $isModerator )
        {
            $forumService = FORUM_BOL_ForumService::getInstance();

            $forumService->deleteSection($sectionId);

            echo 1;
        }

        exit();
    }

    /**
     * This action deletes group called by ajax request
     */
    public function deleteGroup()
    {
        $isModerator = OW::getUser()->isAuthorized('forum');

        $groupId = (int) $_POST['groupId'];

        if ( OW::getRequest()->isAjax() && $groupId && $isModerator )
        {
            $forumService = FORUM_BOL_ForumService::getInstance();

            $forumService->deleteGroup($groupId);

            echo 1;
        }

        exit();
    }

    /**
     * This action adds section and group called by ajax request
     */
    public function addForum()
    {
        $isModerator = OW::getUser()->isAuthorized('forum');

        if ( OW::getRequest()->isAjax() && $_POST && $isModerator )
        {
            $groupName = trim($_POST['group-name']);
            $sectionName = trim($_POST['section']);
            $groupDescription = trim($_POST['description']);
            $isPrivate = $_POST['is-private'] == 'on';
            $roles = !empty($_POST['roles']) ? $_POST['roles'] : null;

            if ( !$groupName || !$sectionName || !$groupDescription )
            {
                exit();
            }

            $forumService = FORUM_BOL_ForumService::getInstance();

            $sectionDto = $forumService->getPublicSection($sectionName, 0);

            //create forum section
            if ( $sectionDto === null )
            {
                $sectionDto = new FORUM_BOL_Section();
                $sectionDto->name = $sectionName;
                $sectionDto->order = $forumService->getNewSectionOrder();

                $forumService->saveOrUpdateSection($sectionDto);
            }

            //create forum group
            $groupDto = new FORUM_BOL_Group();

            $groupDto->sectionId = $sectionDto->id;
            $groupDto->name = $groupName;
            $groupDto->description = $groupDescription;
            $groupDto->order = $forumService->getNewGroupOrder($sectionDto->id);
            $groupDto->isPrivate = (bool) $isPrivate;
            $groupDto->roles = count($roles) ? json_encode($roles) : null;

            $forumService->saveOrUpdateGroup($groupDto);

            echo true;
            exit();
        }
    }

    /**
     * This action suggests sections called by ajax request
     */
    public function suggestSection()
    {
        if ( OW::getRequest()->isAjax() && isset($_GET['q']) )
        {
            $sectionName = trim($_GET['q']);

            $forumService = FORUM_BOL_ForumService::getInstance();
            $sectionDtoList = $forumService->suggestSection($sectionName);

            if ( $sectionDtoList )
            {
                foreach ( $sectionDtoList as $sectionDto )
                {
                    echo "$sectionDto->name\t$sectionDto->id\n";
                }
            }
        }

        exit();
    }

    /**
     * This action edits section called by ajax request
     */
    public function editSection()
    {
        $isModerator = OW::getUser()->isAuthorized('forum');

        if ( OW::getRequest()->isAjax() && $_POST && $isModerator )
        {
            $sectionName = trim($_POST['section-name']);
            $sectionId = (int) $_POST['section-id'];

            if ( !$sectionName || !$sectionId )
            {
                exit();
            }

            $forumService = FORUM_BOL_ForumService::getInstance();
            $sectionDto = $forumService->findSectionById($sectionId);

            if ( $sectionDto === null )
            {
                exit();
            }

            $sectionDto->name = $sectionName;

            $forumService->saveOrUpdateSection($sectionDto);

            echo true;
            exit();
        }
    }

    /**
     * This action edits group called by ajax request
     */
    public function editGroup()
    {
        $isModerator = OW::getUser()->isAuthorized('forum');

        if ( OW::getRequest()->isAjax() && $_POST && $isModerator )
        {
            $groupId = (int) $_POST['group-id'];
            $groupName = trim($_POST['group-name']);
            $groupDescription = trim($_POST['description']);
            $isPrivate = $_POST['is-private'] == 'on';
            $roles = !empty($_POST['roles']) ? $_POST['roles'] : null;

            if ( !$groupId || !$groupName || !$groupDescription )
            {
                exit();
            }

            $forumService = FORUM_BOL_ForumService::getInstance();
            $groupDto = $forumService->findGroupById($groupId);

            if ( $groupDto === null )
            {
                exit();
            }

            $groupDto->name = $groupName;
            $groupDto->description = $groupDescription;
            $groupDto->isPrivate = (bool) $isPrivate;
            $groupDto->roles = count($roles) ? json_encode($roles) : null;

            $forumService->saveOrUpdateGroup($groupDto);

            echo true;
            exit();
        }
    }

    /**
     * This action returns section called by ajax request
     */
    public function getSection()
    {
        if ( OW::getRequest()->isAjax() && $_POST['sectionId'] )
        {
            $sectionId = (int) $_POST['sectionId'];

            $forumService = FORUM_BOL_ForumService::getInstance();
            $sectionDto = $forumService->findSectionById($sectionId);

            echo json_encode($sectionDto);
        }

        exit();
    }

    /**
     * This action returns group called by ajax request
     */
    public function getGroup()
    {
        if ( OW::getRequest()->isAjax() && $_POST['groupId'] )
        {
            $groupId = (int) $_POST['groupId'];

            $forumService = FORUM_BOL_ForumService::getInstance();
            $groupDto = $forumService->findGroupById($groupId);

            $groupDto->roles = json_decode($groupDto->roles, true);
            echo json_encode($groupDto);
        }

        exit();
    }

    /**
     * Generates add forum form
     * 
     * @param string $action
     * @return Form
     */
    private function generateAddForumForm( $action )
    {
        $language = OW::getLanguage();
        $form = new Form('add-forum-form');

        $form->setAction($action);

        $groupName = new TextField('group-name');
        $groupName->setRequired(true);
        $sValidator = new StringValidator(1, 255);
        $sValidator->setErrorMessage($language->text('forum', 'chars_limit_exceeded', array('limit' => 255)));
        $groupName->addValidator($sValidator);
        $form->addElement($groupName);

        $sectionField = new SuggestField('section');
        $sectionField->setRequired(true);
        $sectionField->setMinChars(1);
        $responderUrl = OW::getRouter()->urlFor('FORUM_CTRL_Customize', 'suggestSection');
        $sectionField->setResponderUrl($responderUrl);

        $firstSection = FORUM_BOL_ForumService::getInstance()->getFirstSection();
        if ( $firstSection )
        {
            $sectionField->setValue($firstSection->name);
        }

        $form->addElement($sectionField->setLabel($language->text('forum', 'section')));

        $description = new Textarea('description');
        $description->setRequired(true);
        $sValidator = new StringValidator(1, 50000);
        $sValidator->setErrorMessage($language->text('forum', 'chars_limit_exceeded', array('limit' => 50000)));
        $description->addValidator($sValidator);
        $form->addElement($description);

        $isPrivate = new CheckboxField('is-private');
        $form->addElement($isPrivate);
        
        $roles = new CheckboxGroup('roles');
        $authService = BOL_AuthorizationService::getInstance();
        $roleList = $authService->getRoleList();
        $options = array();
        foreach ( $roleList as $role )
        {
            $options[$role->id] = $authService->getRoleLabel($role->name);
        }
        $roles->addOptions($options);
        $roles->setColumnCount(2);
        $form->addElement($roles);
        
        $submit = new Submit('add');
        $submit->setValue($language->text('forum', 'add_new_forum_submit'));
        $form->addElement($submit);

        $form->setAjax(true);

        return $form;
    }

    /**
     * Generates edit section form
     * 
     * @param string $action
     * @return Form
     */
    private function generateEditSectionForm( $action )
    {
        $form = new Form('edit-section-form');
        $form->setAction($action);
        
        $lang = OW::getLanguage();

        $sectionName = new TextField('section-name');
        $sectionName->setRequired(true);
        $sValidator = new StringValidator(1, 255);
        $sValidator->setErrorMessage($lang->text('forum', 'chars_limit_exceeded', array('limit' => 255)));
        $sectionName->addValidator($sValidator);
        $form->addElement($sectionName);

        $sectionId = new HiddenField('section-id');
        $sectionId->setRequired(true);
        $form->addElement($sectionId);

        $submit = new Submit('save');
        $submit->setValue($lang->text('forum', 'edit_section_btn'));
        $form->addElement($submit);

        $form->setAjax(true);

        return $form;
    }

    /**
     * Generates edit group form
     * 
     * @param string $action
     * @return Form
     */
    private function generateEditGroupForm( $action )
    {
        $form = new Form('edit-group-form');
        $form->setAction($action);
        
        $lang = OW::getLanguage();

        $groupName = new TextField('group-name');
        $groupName->setRequired(true);
        $sValidator = new StringValidator(1, 255);
        $sValidator->setErrorMessage($lang->text('forum', 'chars_limit_exceeded', array('limit' => 255)));
        $groupName->addValidator($sValidator);
        $form->addElement($groupName);

        $description = new Textarea('description');
        $description->setRequired(true);
        $sValidator = new StringValidator(1, 50000);
        $sValidator->setErrorMessage($lang->text('forum', 'chars_limit_exceeded', array('limit' => 50000)));
        $description->addValidator($sValidator);
        $form->addElement($description);

        $groupId = new HiddenField('group-id');
        $groupId->setRequired(true);
        $form->addElement($groupId);
        
        $isPrivate = new CheckboxField('is-private');
        $form->addElement($isPrivate);
        
        $roles = new CheckboxGroup('roles');
        $authService = BOL_AuthorizationService::getInstance();
        $roleList = $authService->getRoleList();
        $options = array();
        foreach ( $roleList as $role )
        {
            $options[$role->id] = $authService->getRoleLabel($role->name);
        }
        $roles->addOptions($options);
        $roles->setColumnCount(2);
        $form->addElement($roles);

        $submit = new Submit('save');
        $submit->setValue($lang->text('forum', 'edit_group_btn'));
        $form->addElement($submit);

        $form->setAjax(true);

        return $form;
    }
}