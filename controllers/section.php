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
 * Forum section action controller
 *
 * @author Egor Bulgakov <egor.bulgakov@gmail.com>
 * @package ow.ow_plugins.forum.controllers
 * @since 1.0
 */
class FORUM_CTRL_Section extends OW_ActionController
{
    /**
     * @var FORUM_BOL_ForumService
     */
    private $forumService;

    /**
     * Class constructor
     */
    public function __construct()
    {
        parent::__construct();

        $this->forumService = FORUM_BOL_ForumService::getInstance();

        if ( !OW::getRequest()->isAjax() )
        {
            OW::getNavigation()->activateMenuItem(OW_Navigation::MAIN, 'forum', 'forum');
        }
    }

    /**
     * Controller's default action
     *
     * @param array $params
     * @throws Redirect404Exception
     */
    public function index( array $params )
    {
        if ( !isset($params['sectionId']) || !($sectionId = (int) $params['sectionId']) )
        {
            throw new Redirect404Exception();
        }
        
        $forumSection = $this->forumService->findSectionById($sectionId);
        if ( !$forumSection || $forumSection->isHidden )
        {
            throw new Redirect404Exception();
        }

        $userId = OW::getUser()->getId();

        $bcItems = array(
            array(
                'href' => OW::getRouter()->urlForRoute('forum-default'),
                'label' => OW::getLanguage()->text('forum', 'forum_index')
            ),
            array(
                'label' => $forumSection->name
            )
        );

        $breadCrumbCmp = new BASE_CMP_Breadcrumb($bcItems);
        $this->addComponent('breadcrumb', $breadCrumbCmp);

        $sectionGroupList = $this->forumService->getSectionGroupList($userId, $sectionId);

        $authors = $this->forumService->getSectionGroupAuthorList($sectionGroupList);
        $this->assign('sectionGroupList', $sectionGroupList);

        $userNames = BOL_UserService::getInstance()->getUserNamesForList($authors);
        $this->assign('userNames', $userNames);

        $displayNames = BOL_UserService::getInstance()->getDisplayNamesForList($authors);
        $this->assign('displayNames', $displayNames);

        $this->addComponent('search', new FORUM_CMP_ForumSearch(array('scope' => 'section', 'sectionId' => $sectionId)));

        // remember the last forum page
        OW::getSession()->set('last_forum_page', OW_URL_HOME . OW::getRequest()->getRequestUri());

        OW::getDocument()->setHeading(OW::getLanguage()->text('forum', 'forum'));
        OW::getDocument()->setHeadingIconClass('ow_ic_forum');

        $params = array(
            "sectionKey" => "forum",
            "entityKey" => "section",
            "title" => "forum+meta_title_section",
            "description" => "forum+meta_desc_section",
            "keywords" => "forum+meta_keywords_section",
            "vars" => array( "section_name" => $forumSection->name )
        );

        OW::getEventManager()->trigger(new OW_Event("base.provide_page_meta_info", $params));
    }
}
