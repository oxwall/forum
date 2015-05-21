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
class FORUM_MCTRL_Search extends FORUM_MCTRL_AbstractForum
{
    /**
     * Find posts into topic
     * 
     * @param array $params
     * @return void
     */
    public function inTopic( array $params = null )
    {
        $topicId = (int)$params['topicId'];
        $userId = OW::getUser()->getId();

        $topic = $this->forumService->findTopicById($topicId);
        $forumGroup = $this->forumService->findGroupById($topic->groupId);
        $forumSection = $this->forumService->findSectionById($forumGroup->sectionId);

        if ( $forumSection->isHidden )
        {
            throw new Redirect404Exception();
        }
 
        $isModerator = OW::getUser()->isAuthorized('forum');

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

        $this->searchEntities($params, 'topic');
    }

    /**
     * Search topics into group
     * 
     * @param array $params
     */
    public function inGroup( array $params = array() )
    {
        $groupId = (int)$params['groupId'];
        $forumGroup = $this->forumService->findGroupById($groupId);
        $userId = OW::getUser()->getId();

        if ( $this->forumService->groupIsHidden($groupId) )
        {
            throw new Redirect404Exception();
        }

        $isModerator = OW::getUser()->isAuthorized('forum');

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

        $this->searchEntities($params, 'group');
    }

    /**
     * Search topics into section
     * 
     * @param array $params
     * @return void
     */
    public function inSection( array $params = null )
    {
        $this->searchEntities($params, 'section');
    }

    /**
     * Controller's default action
     * 
     * @param array $params
     */
    public function inForums( array $params = array() )
    {
        $this->searchEntities($params, 'global');
    }

    /**
     * Search entites
     * 
     * @param array $params
     * @param string $type
     * @return void
     */
    private function searchEntities(array $params, $type)
    {
        $plugin = OW::getPluginManager()->getPlugin('forum');
        $this->setTemplate($plugin->getMobileCtrlViewDir() . 'search_result.html');

        $token = !empty($_GET['q']) && is_string($_GET['q']) 
            ? urldecode(trim($_GET['q'])) 
            : null;

        $page = !empty($_GET['page']) && (int) $_GET['page'] ? abs((int) $_GET['page']) : 1;
        $iterationPerPage = $this->forumService->getTopicPerPageConfig();

        if ( !mb_strlen($token) )
        {
            OW::getFeedback()->info(OW::getLanguage()->text('forum', 'please_enter_keyword_or_user_name'));
            $this->redirect(OW::getRouter()->urlForRoute('forum-default'));
        }

        $authors = array();

        // make a search
        switch ( $type )
        {
            case 'topic' :
                $topicId = (int) $params['topicId'];
                $backUrl = OW::getRouter()->urlForRoute('topic-default', array(
                    'topicId' => $topicId
                ));

                $pageTitle = OW::getLanguage()->text('forum', 'search_invitation_topic');
                $total = $this->forumService->countPostsInTopic($token, $topicId);
                $topics = $total
                    ? $this->forumService->findPostsInTopic($token, $topicId, $page)
                    : array();
                break;

            case 'group' :
                $groupId = (int) $params['groupId'];
                $backUrl = OW::getRouter()->urlForRoute('group-default', array(
                    'groupId' => $groupId
                ));

                $pageTitle = OW::getLanguage()->text('forum', 'search_invitation_group');
                $total = $this->forumService->countTopicsInGroup($token, $groupId);
                $topics = $total
                    ? $this->forumService->findTopicsInGroup($token, $groupId, $page)
                    : array();
                break;

            case 'section' :
                $sectionId = (int) $params['sectionId'];
                $backUrl = OW::getRouter()->urlForRoute('section-default', array(
                    'sectionId' => $sectionId
                ));

                $pageTitle = OW::getLanguage()->text('forum', 'search_invitation_section');
                $total = $this->forumService->countTopicsInSection($token, $sectionId);
                $topics = $total
                    ? $this->forumService->findTopicsInSection($token, $sectionId, $page)
                    : array();
                break;

            case 'global' :
                $backUrl = OW::getRouter()->urlForRoute('forum-default');
                $pageTitle = OW::getLanguage()->text('forum', 'search_invitation_all_forum');
                $total = $this->forumService->countGlobalTopics($token);
                $topics = $total
                    ? $this->forumService->findGlobalTopics($token, $page)
                    : array();
                break;
        }

        // collect authors 
        foreach ( $topics as $topic )
        {
            if ( !empty($topic['posts']) )
            {
                foreach ( $topic['posts'] as $post )
                {
                    if ( !in_array($post['userId'], $authors) )
                    {
                        array_push($authors, $post['userId']);
                    }
                }
            }
        }
        
        // assign view variables
        $this->assign('backUrl', $backUrl);
        $this->assign('iteration',  ($page - 1) * $iterationPerPage + 1);
        $this->assign('topics', $topics);
        $this->assign('displayNames', BOL_UserService::getInstance()->getDisplayNamesForList($authors));
        $this->assign('avatars', BOL_AvatarService::getInstance()->getDataForUserAvatars($authors));
        $this->assign('onlineUsers', BOL_UserService::getInstance()->findOnlineStatusForUserList($authors));

        // paging
        $perPage = $this->forumService->getTopicPerPageConfig();
        $pages = (int) ceil($total / $perPage);
        $paging = new BASE_CMP_PagingMobile($page, $pages, $perPage);
        $this->assign('paging', $paging->render());

        // set current page settings
        OW::getDocument()->setDescription(OW::getLanguage()->text('forum', 'meta_description_forums'));
        OW::getDocument()->setHeading($pageTitle);
        OW::getDocument()->setTitle($pageTitle);
    }
}