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
class FORUM_MCTRL_Group extends FORUM_MCTRL_AbstractForum
{
    /**
     * Section index
     * 
     * @param array $params
     */
    public function index( array $params )
    {
        if ( !isset($params['groupId']) || !($groupId = (int) $params['groupId']) )
        {
            throw new Redirect404Exception();
        }

        // get group info
        $groupInfo = $this->forumService->getGroupInfo($groupId);
        if ( !$groupInfo )
        {
            throw new Redirect404Exception();
        }

        $forumSection = $groupInfo 
            ? $this->forumService->findSectionById($groupInfo->sectionId)
            : null;

        if ( $forumSection->isHidden )
        {
            throw new Redirect404Exception();
        }
    
        $userId = OW::getUser()->getId();
        $isModerator = OW::getUser()->isAuthorized('forum');
        $canEdit = OW::getUser()->isAuthorized('forum', 'edit') || $isModerator ? true : false;

        // check permissions
        if ( $groupInfo->isPrivate )
        {
            if ( !$userId && !$isModerator )
            {
                if ( !$this->forumService->isPrivateGroupAvailable($userId, json_decode($groupInfo->roles)) )
                {
                    $status = BOL_AuthorizationService::getInstance()->getActionStatus('forum', 'view');
                    throw new AuthorizationException($status['msg']);
                }
            }
        }

        // get topics
        $page = !empty($_REQUEST['page']) && (int) $_REQUEST['page'] ? abs((int) $_REQUEST['page']) : 1;
        $topicList = $this->forumService->getGroupTopicList($groupId, $page, null);
        $topicIds = array();
        $authors = $this->forumService->getGroupTopicAuthorList($topicList, $topicIds);

        $stickyTopics = array();
        $regularTopics = array();

        // process topics
        foreach ($topicList as $topic)
        {
            // collect topics authors
            if ( !in_array($topic['userId'], $authors) )
            {
                array_push($authors, $topic['userId']);
            }

            $topic['sticky'] 
                ? $stickyTopics[] = $topic : $regularTopics[] = $topic;
        }

        // assign view variables
        $this->assign('canEdit', $canEdit);
        $this->assign('promotion', BOL_AuthorizationService::getInstance()->getActionStatus('forum', 'edit'));
        $this->assign('stickyTopics', $stickyTopics);
        $this->assign('regularTopics', $regularTopics);
        $this->assign('displayNames', BOL_UserService::getInstance()->getDisplayNamesForList($authors));
        $this->assign('authorsUrls', BOL_UserService::getInstance()->getUserUrlsForList($authors));

        $this->assign('group',   $groupInfo);
        $this->assign('attachments', FORUM_BOL_PostAttachmentService::getInstance()->
                getAttachmentsCountByTopicIdList($topicIds));

        // paginate
        if ( OW::getRequest()->isAjax() )
        {
            $plugin = OW::getPluginManager()->getPlugin('forum');
            $this->setTemplate($plugin->getMobileCtrlViewDir() . 'group_index_ajax.html');
            die( $this->render() );
        }

        // include js files
        OW::getDocument()->addScript(OW::
                getPluginManager()->getPlugin('forum')->getStaticJsUrl() . 'mobile_pagination.js');

        // include js translations
        OW::getLanguage()->addKeyForJs('forum', 'post_attachment');
        OW::getLanguage()->addKeyForJs('forum', 'attached_files');
        OW::getLanguage()->addKeyForJs('forum', 'confirm_delete_all_attachments');

        // remember the last forum page
        OW::getSession()->set('last_forum_page', OW_URL_HOME . OW::getRequest()->getRequestUri());

//        OW::getDocument()->setDescription(OW::getLanguage()->text('forum', 'meta_description_forums'));
        OW::getDocument()->setHeading(OW::getLanguage()->text('forum', 'forum_group'));
//        OW::getDocument()->setTitle(OW::getLanguage()->text('forum', 'forum_group'));

        $params = array(
            "sectionKey" => "forum",
            "entityKey" => "group",
            "title" => "forum+meta_title_group",
            "description" => "forum+meta_desc_group",
            "keywords" => "forum+meta_keywords_group",
            "vars" => array( "group_name" => $groupInfo->name, "group_description" => $groupInfo->description )
        );

        OW::getEventManager()->trigger(new OW_Event("base.provide_page_meta_info", $params));
    }
}