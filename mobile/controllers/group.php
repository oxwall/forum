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

        $userId = OW::getUser()->getId();
        $isModerator = OW::getUser()->isAuthorized('forum');

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
        $page = !empty($_GET['page']) && (int) $_GET['page'] ? abs((int) $_GET['page']) : 1;
        $topicList = $this->forumService->getGroupTopicList($groupId, $page);
        $topicCount = $this->forumService->getGroupTopicCount($groupId);
        $topicIds = array();
        $authors = $this->forumService->getGroupTopicAuthorList($topicList, $topicIds);

        // process topics
        $stickyTopics = array();
        $regularTopics = array();

        foreach ($topicList as $topic)
        {
            $topic['sticky'] 
                ? $stickyTopics[] = $topic : $regularTopics[] = $topic;
        }

        // assign view variables
        $this->assign('stickyTopics', $stickyTopics);
        $this->assign('regularTopics', $regularTopics);
        $this->assign('displayNames', BOL_UserService::getInstance()->getDisplayNamesForList($authors));
        $this->assign('group',   $groupInfo);
        $this->assign('sectionUrl', OW::getRouter()->
                urlForRoute('section-default', array('sectionId' => $groupInfo->sectionId)));

        $this->assign('attachments', FORUM_BOL_PostAttachmentService::getInstance()->
                getAttachmentsCountByTopicIdList($topicIds));

        // paginate
        $perPage = $this->forumService->getTopicPerPageConfig();
        $pageCount = ($topicCount) ? ceil($topicCount / $perPage) : 1;
        $paging = new BASE_CMP_Paging($page, $pageCount, $perPage);
        $this->assign('paging', $paging->render());

        OW::getDocument()->setDescription(OW::getLanguage()->text('forum', 'meta_description_forums'));
        OW::getDocument()->setHeading(OW::getLanguage()->text('forum', 'forum_group'));
        OW::getDocument()->setTitle(OW::getLanguage()->text('forum', 'forum_group'));
    }
}