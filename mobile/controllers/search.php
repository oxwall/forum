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
     * Advanced search result
     */
    public function advancedResult()
    {        
        // get search params
        $keyword = !empty($_GET['keyword']) && is_string($_GET['keyword']) 
            ? urldecode(trim($_GET['keyword'])) 
            : null;

        $userName = !empty($_GET['username']) && is_string($_GET['username']) 
            ? urldecode(trim($_GET['username'])) 
            : null;

        $parts = !empty($_GET['parts']) && is_array($_GET['parts']) 
            ? $_GET['parts'] 
            : null;

        $searchIn = !empty($_GET['search_in']) && is_string($_GET['search_in']) 
            ? urldecode(trim($_GET['search_in'])) 
            : null;

        $period = !empty($_GET['period']) && is_string($_GET['period']) 
            ? urldecode(trim($_GET['period'])) 
            : null;

        $sort = !empty($_GET['sort']) && is_string($_GET['sort']) 
            ? urldecode(trim($_GET['sort'])) 
            : null;

        $sortDirection = !empty($_GET['sort_direction']) && is_string($_GET['sort_direction']) 
            ? urldecode(trim($_GET['sort_direction'])) 
            : null;

        $page = !empty($_GET['page']) && (int) $_GET['page'] ? abs((int) $_GET['page']) : 1;

        if ( !mb_strlen($keyword) && !mb_strlen($userName) )
        {
            OW::getFeedback()->error(OW::getLanguage()->text('forum', 'please_enter_keyword_or_user_name'));
            $this->redirect(OW::getRouter()->urlForRoute('forum_advanced_search'));
        }

        $userId = null;

        // filter by user id
        if ( $userName )
        {
            $userId = -1;
            $questionName = OW::getConfig()->getValue('base', 'display_name_question');
            $userInfo = BOL_UserService::getInstance()->
                    findUserIdListByQuestionValues(array($questionName => $userName), 0, 1);

            if ( $userInfo )
            {
                $userId = array_shift($userInfo);
            }
        }

        // make a search
        $searchInPosts = $searchIn == 'message' ? true : false;

        // search by keyword
        if ( $keyword ) {
            $topics = $this->forumService->advancedFindEntities($keyword, 
                    $page, $userId, $parts, $period, $sort, $sortDirection, $searchInPosts);
        }
        else {
            // search by a user
            $topics = $this->forumService->
                    advancedFindEntitiesByUser($userId, $page, $parts, $period, $sort, $sortDirection, $searchInPosts);
        }

        // collect authors 
        $authors = array();
        foreach ( $topics as $topic )
        {
            if ( !in_array($topic['userId'], $authors) )
            {
                array_push($authors, $topic['userId']);
            }

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

            if ( !empty($topic['lastPost']) ) 
            {
                if ( !in_array($topic['lastPost']['userId'], $authors) )
                {
                    array_push($authors, $topic['lastPost']['userId']);
                }
            }
        }

        $iterationPerPage = $searchIn == 'title'
            ? $this->forumService->getTopicPerPageConfig()
            : $this->forumService->getPostPerPageConfig();

        $location  = OW::getRouter()->urlForRoute('forum_advanced_search_result');
        $location .= '?' . http_build_query(array(
            'keyword' => $keyword,
            'username' => $userName,
            'parts' => $parts,
            'search_in' => $searchIn,
            'period' => $period,
            'sort' => $sort,
            'sort_direction' => $sortDirection
        ));

        // assign view variables
        $this->assign('topics', $topics);
        $this->assign('avatars', BOL_AvatarService::getInstance()->getDataForUserAvatars($authors));
        $this->assign('backUrl', OW::getRouter()->urlForRoute('forum_advanced_search'));

        $closeUrl = OW::getSession()->get('last_forum_page');
        $this->assign('closeUrl', ($closeUrl ? $closeUrl : OW::getRouter()->urlForRoute('forum-default')));
        $this->assign('displayNames', BOL_UserService::getInstance()->getDisplayNamesForList($authors));
        $this->assign('authorsUrls', BOL_UserService::getInstance()->getUserUrlsForList($authors));
        $this->assign('iteration',  ($page - 1) * $iterationPerPage + 1);
        $this->assign('onlineUsers', BOL_UserService::getInstance()->findOnlineStatusForUserList($authors));
        $this->assign('postsCount', $this->forumService->findPostCountListByUserIds($authors));
        $this->assign('drawTopicWrapper', true);
        $this->assign('location', $location);
        
        // set page title
        $pageTitle = OW::getLanguage()->text('forum', 'search_advanced_heading');
        $plugin = OW::getPluginManager()->getPlugin('forum');

        // paginate
        if ( OW::getRequest()->isAjax() )
        {
            $searchIn != 'title'
                ? $this->setTemplate($plugin->getMobileCtrlViewDir() . 'search_result_ajax.html')
                : $this->setTemplate($plugin->getMobileCtrlViewDir() . 'search_result_topic_ajax.html');

            die( $this->render() );
        }

        // include js files
        OW::getDocument()->addScript(OW::
                getPluginManager()->getPlugin('forum')->getStaticJsUrl() . 'mobile_pagination.js');

        $searchIn != 'title'
            ? $this->setTemplate($plugin->getMobileCtrlViewDir() . 'search_result.html')
            : $this->setTemplate($plugin->getMobileCtrlViewDir() . 'search_result_topic.html');

//        OW::getDocument()->setDescription(OW::getLanguage()->text('forum', 'meta_description_forums'));
        OW::getDocument()->setHeading($pageTitle);
//        OW::getDocument()->setTitle($pageTitle);

        $params = array(
            "sectionKey" => "forum",
            "entityKey" => "advSearchResult",
            "title" => "forum+meta_title_adv_search_result",
            "description" => "forum+meta_desc_adv_search_result",
            "keywords" => "forum+meta_keywords_adv_searche_result"
        );

        OW::getEventManager()->trigger(new OW_Event("base.provide_page_meta_info", $params));
    }

    /**
     * Advanced search
     */
    public function advanced()
    {
        // get all sections and forums
        $sections = $this->forumService->getCustomSectionGroupList();

        // add form
        $this->addForm(new FORUM_CLASS_AdvancedSearchForm('search_form', $sections));

        // set page title
        $pageTitle = OW::getLanguage()->text('forum', 'search_advanced_heading');

        // assign view variables
        $closeUrl = OW::getSession()->get('last_forum_page');
        $this->assign('closeUrl', ($closeUrl ? $closeUrl : OW::getRouter()->urlForRoute('forum-default')));
        
//        OW::getDocument()->setDescription(OW::getLanguage()->text('forum', 'meta_description_forums'));
        OW::getDocument()->setHeading($pageTitle);
//        OW::getDocument()->setTitle($pageTitle);

        $params = array(
            "sectionKey" => "forum",
            "entityKey" => "advSearch",
            "title" => "forum+meta_title_adv_search",
            "description" => "forum+meta_desc_adv_search",
            "keywords" => "forum+meta_keywords_adv_searche"
        );

        OW::getEventManager()->trigger(new OW_Event("base.provide_page_meta_info", $params));
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
        $drawTopicWrapper = true;

        // make a search
        switch ( $type )
        {
            case 'topic' :
                $iterationPerPage = $this->forumService->getPostPerPageConfig();
                $topicId = (int) $params['topicId'];

                $location = OW::getRouter()->
                        urlForRoute('forum_search_topic', array('topicId' => $topicId));

                $backUrl = OW::getRouter()->urlForRoute('topic-default', array(
                    'topicId' => $topicId
                ));

                $pageTitle = OW::getLanguage()->text('forum', 'search_invitation_topic');
                $topics = $this->forumService->findPostsInTopic($token, $topicId, $page);
                $drawTopicWrapper = OW::getRequest()->isAjax() ? false : true;
                break;

            case 'group' :
                $groupId = (int) $params['groupId'];
                $location = OW::getRouter()->
                        urlForRoute('forum_search_group', array('groupId' => $groupId));

                $backUrl = OW::getRouter()->urlForRoute('group-default', array(
                    'groupId' => $groupId
                ));

                $pageTitle = OW::getLanguage()->text('forum', 'search_invitation_group');
                $topics = $this->forumService->findTopicsInGroup($token, $groupId, $page);
                break;

            case 'section' :
                $sectionId = (int) $params['sectionId'];
                $location = OW::getRouter()->
                        urlForRoute('forum_search_section', array('sectionId' => $sectionId));

                $backUrl = OW::getRouter()->urlForRoute('section-default', array(
                    'sectionId' => $sectionId
                ));

                $pageTitle = OW::getLanguage()->text('forum', 'search_invitation_section');
                $topics = $this->forumService->findTopicsInSection($token, $sectionId, $page);
                break;

            case 'global' :
                $location = OW::getRouter()->urlForRoute('forum_search');
                $backUrl = OW::getRouter()->urlForRoute('forum-default');
                $pageTitle = OW::getLanguage()->text('forum', 'search_invitation_all_forum');
                $topics = $this->forumService->findGlobalTopics($token, $page);
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

        $plugin = OW::getPluginManager()->getPlugin('forum');

        // assign view variables
        $this->assign('drawTopicWrapper', $drawTopicWrapper);
        $this->assign('location', $location . '?q=' . urlencode($token));
        $this->assign('backUrl', $backUrl);
        $this->assign('iteration',  ($page - 1) * $iterationPerPage + 1);
        $this->assign('topics', $topics);
        $this->assign('displayNames', BOL_UserService::getInstance()->getDisplayNamesForList($authors));
        $this->assign('avatars', BOL_AvatarService::getInstance()->getDataForUserAvatars($authors));
        $this->assign('onlineUsers', BOL_UserService::getInstance()->findOnlineStatusForUserList($authors));
        $this->assign('postsCount', $this->forumService->findPostCountListByUserIds($authors));

        // paginate
        if ( OW::getRequest()->isAjax() )
        {
            $this->setTemplate($plugin->getMobileCtrlViewDir() . 'search_result_ajax.html');
            die( $this->render() );
        }

        $this->setTemplate($plugin->getMobileCtrlViewDir() . 'search_result.html');
        
        // include js files
        OW::getDocument()->addScript(OW::
                getPluginManager()->getPlugin('forum')->getStaticJsUrl() . 'mobile_pagination.js');

        // set current page settings
        OW::getDocument()->setDescription(OW::getLanguage()->text('forum', 'meta_description_forums'));
        OW::getDocument()->setHeading($pageTitle);
        OW::getDocument()->setTitle($pageTitle);
    }
}