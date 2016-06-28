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
class FORUM_MCTRL_Section extends FORUM_MCTRL_AbstractForum
{
    /**
     * Section index
     * 
     * @param array $params
     */
    public function index( array $params )
    {
        if ( !isset($params['sectionId']) || !($sectionId = (int) $params['sectionId']) )
        {
            throw new Redirect404Exception();
        }

        // get the section info
        $forumSection = $this->forumService->findSectionById($sectionId);
        if ( !$forumSection || $forumSection->isHidden )
        {
            throw new Redirect404Exception();
        }

        $isModerator = OW::getUser()->isAuthorized('forum');
        $canEdit = OW::getUser()->isAuthorized('forum', 'edit') || $isModerator ? true : false;

        // include js translations
        OW::getLanguage()->addKeyForJs('forum', 'post_attachment');
        OW::getLanguage()->addKeyForJs('forum', 'attached_files');
        OW::getLanguage()->addKeyForJs('forum', 'confirm_delete_all_attachments');

        // assign view variables
        $this->assign('section', $forumSection);
        $this->assign('canEdit', $canEdit);
        $this->assign('promotion', BOL_AuthorizationService::getInstance()->getActionStatus('forum', 'edit'));

        // remember the last forum page
        OW::getSession()->set('last_forum_page', OW_URL_HOME . OW::getRequest()->getRequestUri());

//        OW::getDocument()->setDescription(OW::getLanguage()->text('forum', 'meta_description_forums'));
        OW::getDocument()->setHeading(OW::getLanguage()->text('forum', 'forum_section'));
//        OW::getDocument()->setTitle(OW::getLanguage()->text('forum', 'forum_section'));

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