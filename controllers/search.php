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
class FORUM_CTRL_Search extends OW_ActionController
{
    private $forumService;
    
    public function __construct()
    {
        parent::__construct();
        
        $this->forumService = FORUM_BOL_ForumService::getInstance();
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
     * Search topics into group
     * 
     * @param array $params
     */
    public function inGroup( array $params = array() )
    {
        $groupId = (int)$params['groupId'];
        $forumGroup = $this->forumService->findGroupById($groupId);
        $userId = OW::getUser()->getId();

        $isHidden = $this->forumService->groupIsHidden($groupId);

        if ( $isHidden )
        {
            $forumSection = $this->forumService->findSectionById($forumGroup->sectionId);
            $isModerator = OW::getUser()->isAuthorized($forumSection->entity);

            $event = new OW_Event('forum.find_forum_caption', array('entity' => $forumSection->entity, 'entityId' => $forumGroup->entityId));
            OW::getEventManager()->trigger($event);

            $eventData = $event->getData();
            $componentForumCaption = $eventData['component'];

            $this->addComponent('componentForumCaption', $componentForumCaption);
        }
        else
        {
            $isModerator = OW::getUser()->isAuthorized('forum');
        }

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
     * Search entites
     * 
     * @param array $params
     * @param string $type
     * @return void
     */
    private function searchEntities(array $params, $type)
    {
        $plugin = OW::getPluginManager()->getPlugin('forum');
        $this->setTemplate($plugin->getCtrlViewDir() . 'search_result.html');
        $lang = OW::getLanguage();

        $token = !empty($_GET['q']) && is_string($_GET['q']) 
            ? urldecode(htmlspecialchars(trim($_GET['q']))) 
            : null;

        $userToken = !empty($_GET['u']) && is_string($_GET['u']) 
            ? urldecode(htmlspecialchars(trim($_GET['u']))) 
            : null;

        $sortBy = !empty($_GET['sort']) ? $_GET['sort'] : null; 
        $page = !empty($_GET['page']) && (int) $_GET['page'] ? abs((int) $_GET['page']) : 1;

        if ( !mb_strlen($token) )
        {
            $this->redirect(OW::getRouter()->urlForRoute('forum-default'));
        }

        $tokenQuery = '&q=' . $token;
        $userTokenQuery = $userToken ? '&u=' . $userToken : null;

        $userInfo = $userToken
            ? BOL_UserService::getInstance()->findByUsername($userToken)
            : null;

        // filter by user id
        $userId = $userToken
            ? ($userInfo ? $userInfo->id : -1)
            : null;

        $authors = array();

        // make a search
        switch ( $type )
        {
            case 'topic' :
                $topicId = (int)$params['topicId'];
                $sortUrl = OW::getRouter()->
                        urlForRoute('forum_search_topic', array('topicId' => $topicId)) . '?' . $tokenQuery . $userTokenQuery;

                $total = $this->forumService->countFindPostsInTopic($token, $topicId, $userId);
                $topics = $total
                    ? $this->forumService->findPostsInTopic($token, $topicId, $page, $sortBy, $userId)
                    : array();

                $this->addComponent('search', new FORUM_CMP_ForumSearch(
                    array('scope' => 'topic', 'token' => $token, 'userToken' => $userToken, 'topicId' => $topicId))
                );
                break;

            case 'group' :
                $groupId = (int)$params['groupId'];
                $sortUrl = OW::getRouter()->
                        urlForRoute('forum_search_group', array('groupId' => $groupId)) . '?' . $tokenQuery . $userTokenQuery;

                $total = $this->forumService->countFindTopicsInGroup($token, $groupId, $userId);
                $topics = $total
                    ? $this->forumService->findTopicsInGroup($token, $groupId, $page, $sortBy, $userId)
                    : array();

                $this->addComponent('search', new FORUM_CMP_ForumSearch(
                    array('scope' => 'group', 'token' => $token, 'userToken' => $userToken, 'groupId' => $groupId))
                );
                break;

            case 'section' :
                $sectionId = (int) $params['sectionId'];
                $sortUrl = OW::getRouter()->
                        urlForRoute('forum_search_section', array('sectionId' => $sectionId)) . '?' . $tokenQuery . $userTokenQuery;

                $total = $this->forumService->countFindTopicsInSection($token, $sectionId, $userId);
                $topics = $total
                    ? $this->forumService->findTopicsInSection($token, $sectionId, $page, $sortBy, $userId)
                    : array();

                $this->addComponent('search', new FORUM_CMP_ForumSearch(
                    array('scope' => 'section', 'sectionId' => $sectionId, 'token' => $token, 'userToken' => $userToken))
                );
                break;

            default :
            case 'global' :
                $sortUrl = OW::getRouter()->urlForRoute('forum_search') . '?' . $tokenQuery . $userTokenQuery;
                $total = $this->forumService->countFindGlobalTopics($token, $userId);
                $topics = $total
                    ? $this->forumService->findGlobalTopics($token, $page, $sortBy, $userId)
                    : array();

                $this->addComponent('search', new FORUM_CMP_ForumSearch(
                    array('scope' => 'all_forum', 'token' => $token, 'userToken' => $userToken))
                );
                break;
        }

        // collect authors 
        foreach ( $topics as $topic )
        {
            foreach ( $topic['posts'] as $post )
            {
                if ( !in_array($post['userId'], $authors) )
                {
                    array_push($authors, $post['userId']);
                }
            }
        }

        $this->assign('topics', $topics);
        $this->assign('token', $token);
        $this->assign('userToken', $userToken);
        $this->assign('avatars', BOL_AvatarService::getInstance()->getDataForUserAvatars($authors));

        // paging
        $perPage = $this->forumService->getTopicPerPageConfig();
        $pages = (int) ceil($total / $perPage);
        $paging = new BASE_CMP_Paging($page, $pages, $perPage);
        $this->assign('paging', $paging->render());

        // sort control
        $sortCtrl = new BASE_CMP_SortControl();
        $sortCtrl->addItem('date', $lang->text('forum', 'sort_by_date'), $sortUrl.'&sort=date', !$sortBy || $sortBy == 'date');
        $sortCtrl->addItem('relevance', $lang->text('forum', 'sort_by_relevance'), $sortUrl.'&sort=rel', $sortBy == 'rel');
        $this->addComponent('sort', $sortCtrl);

        $this->assign('avatars', BOL_AvatarService::getInstance()->getDataForUserAvatars($authors));

        OW::getDocument()->setHeading($lang->text('forum', 'search_page_heading'));
        OW::getDocument()->setHeadingIconClass('ow_ic_forum');
        OW::getNavigation()->activateMenuItem(OW_Navigation::MAIN, 'forum', 'forum');
    }

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

        if ( $forumSection && $forumSection->isHidden )
        {
            $event = new OW_Event('forum.find_forum_caption', array('entity' => $forumSection->entity, 'entityId' => $forumGroup->entityId));
            OW::getEventManager()->trigger($event);

            $eventData = $event->getData();
            $componentForumCaption = $eventData['component'];

            $this->addComponent('componentForumCaption', $componentForumCaption);

            $isModerator = OW::getUser()->isAuthorized($forumSection->entity);
        }
        else 
        {
            $isModerator = OW::getUser()->isAuthorized('forum');
        }

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
}
