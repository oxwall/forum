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
 * Forum Service Class
 *
 * @author Egor Bulgakov <egor.bulgakov@gmail.com>
 * @package ow.ow_plugins.forum.bol
 * @since 1.0
 */
final class FORUM_BOL_ForumService
{
    const EVENT_AFTER_POST_DELETE = 'forum.after_post_delete';
    const EVENT_AFTER_POST_EDIT = 'forum.after_post_edit';
    const EVENT_AFTER_TOPIC_DELETE = 'forum.after_topic_delete';
    const EVENT_AFTER_TOPIC_EDIT = 'forum.after_topic_edit';
    const EVENT_AFTER_TOPIC_ADD = 'forum.after_topic_add';
    const EVENT_BEFORE_TOPIC_DELETE = 'forum.before_topic_delete';
    const FEED_ENTITY_TYPE = 'forum-topic';
    const FEED_POST_ENTITY_TYPE = 'forum-post';

    const STATUS_APPROVAL = 'approval';
    const STATUS_APPROVED = 'approved';

    /**
     * @var FORUM_BOL_ForumService
     */
    private static $classInstance;
    /**
     * @var FORUM_BOL_GroupDao
     */
    private $groupDao;
    /**
     * @var FORUM_BOL_SectionDao
     */
    private $sectionDao;
    /**
     * @var FORUM_BOL_TopicDao
     */
    private $topicDao;
    /**
     * @var FORUM_BOL_PostDao
     */
    private $postDao;
    /**
     * @var BOL_UserDao
     */
    private $userDao;

    /**
     * Class constructor
     */
    private function __construct()
    {
        $this->sectionDao = FORUM_BOL_SectionDao::getInstance();
        $this->groupDao = FORUM_BOL_GroupDao::getInstance();
        $this->topicDao = FORUM_BOL_TopicDao::getInstance();
        $this->postDao = FORUM_BOL_PostDao::getInstance();
        $this->userDao = BOL_UserDao::getInstance();
    }

    /**
     * Get text search service
     * 
     * @return FORUM_BOL_TextSearchService
     */
    private function getTextSearchService()
    {
        return FORUM_BOL_TextSearchService::getInstance();
    }

    /**
     * Returns class instance
     *
     * @return FORUM_BOL_ForumService
     */
    public static function getInstance()
    {
        if ( !isset(self::$classInstance) )
            self::$classInstance = new self();

        return self::$classInstance;
    }

    /**
     * Returns Sections Group List
     *
     * @param int $forUserId
     * @param int $sectionId
     * @return array
     */
    public function getSectionGroupList( $forUserId, $sectionId = null )
    {
        $groupList = $this->sectionDao->getSectionGroupList(false, $sectionId);

        $sectionGroupList = array();
        $curSectionId = 0;
        
        $authService = BOL_AuthorizationService::getInstance();
        $userRoleIdList = array();
        if ( $forUserId )
        {
            $roleList = $authService->getRoleList();
            $roleListAssoc = array();
            foreach ( $roleList as $roleDto )
            {
                $roleListAssoc[$roleDto->id] = $roleDto;
            }
            
            $userRoles = $authService->findUserRoleList($forUserId);
            foreach ( $userRoles as $role )
            {
                $userRoleIdList[] = $role->id;
            }
        }

        foreach ( $groupList as $group )
        {
            if ( $group['isPrivate'] ) 
            {
                $allowedRoleIdList = json_decode($group['roles']);
                
                if ( !$forUserId )
                {
                    continue;
                }
                else if ( !OW::getUser()->isAuthorized('forum') )
                {
                    if ( !$this->isPrivateGroupAvailable($forUserId, $allowedRoleIdList, $userRoleIdList) )
                    {
                        continue;
                    }
                }
                if ( $allowedRoleIdList )
                {
                    $group['roles'] = array();
                    foreach ( $allowedRoleIdList as $id )
                    {
                        if ( !empty($roleListAssoc[$id]) )
                        {
                            $group['roles'][] = $authService->getRoleLabel($roleListAssoc[$id]->name);
                        }
                    }
                }
            }
            $sectionId = $group['sectionId'];

            if ( $curSectionId != $sectionId )
            {
                $section = array(
                    'sectionId' => $group['sectionId'],
                    'sectionName' => $group['sectionName'],
                    'sectionOrder' => $group['sectionOrder'],
                    'sectionUrl' => OW::getRouter()->urlForRoute('section-default', array('sectionId' => $sectionId)),
                    'groups' => array()
                );

                $sectionGroupList[$sectionId] = $section;
            }

            $group['topicCount'] = $this->getGroupTopicCount($group['id']);
            $group['replyCount'] = $this->getGroupPostCount($group['id']) - $group['topicCount'];
            $group['lastReply'] = $this->getGroupLastReply($group['id']);
            $group['groupUrl'] = OW::getRouter()->urlForRoute('group-default', array('groupId' => $group['id']));

            $sectionGroupList[$sectionId]['groups'][] = $group;

            $curSectionId = $sectionId;
        }

        return $sectionGroupList;
    }
    
    public function isSingleForumMode( $sectionGroupList )
    {
        if ( count($sectionGroupList) > 1 )
        {
            return false;
        }

        $firstSection = array_shift($sectionGroupList);

        if ( isset($firstSection['groups']) && count($firstSection['groups']) == 1 )
        {
            return true;
        }

        return false;
    }
    
    public function isPrivateGroupAvailable( $userId, $allowedRoleIdList, $userRoleIdList = null )
    {
        if ( !$allowedRoleIdList )
        {
            return false;
        }
        
        if ( !$userRoleIdList )
        {
            $authService = BOL_AuthorizationService::getInstance();
            $userRoles = $authService->findUserRoleList($userId);
            
            $userRoleIdList = array();
            foreach ( $userRoles as $role )
            {
                $userRoleIdList[] = $role->id;
            }
        }
        
        $match = array_intersect($userRoleIdList, $allowedRoleIdList);

        return (bool) $match;
    }

    /**
     * Returns Sections Group List
     *
     * @return array
     */
    public function getCustomSectionGroupList()
    {
        $groupList = $this->sectionDao->getCustomSectionGroupList();

        $sectionGroupList = array();
        $curSectionId = 0;
        
        $authService = BOL_AuthorizationService::getInstance();
        $roleList = $authService->getRoleList();
        $roleListAssoc = array();
        foreach ( $roleList as $roleDto )
        {
            $roleListAssoc[$roleDto->id] = $roleDto;
        }

        foreach ( $groupList as $group )
        {
            $sectionId = $group['sectionId'];

            if ( $curSectionId != $sectionId )
            {
                $section = array(
                    'sectionId' => $group['sectionId'],
                    'sectionName' => $group['sectionName'],
                    'sectionOrder' => $group['sectionOrder'],
                    'sectionUrl' => OW::getRouter()->urlForRoute('section-default', array('sectionId' => $sectionId)),
                    'groups' => array()
                );

                $sectionGroupList[$sectionId] = $section;
            }
            
            if ( $group['isPrivate'] && strlen($group['roles']) )
            {
                $roleIdList = json_decode($group['roles']);
                
                $group['roles'] = array();
                foreach ( $roleIdList as $id )
                {
                    if ( !empty($roleListAssoc[$id]) )
                    {
                        $group['roles'][] = $authService->getRoleLabel($roleListAssoc[$id]->name);
                    }
                }
            }

            if ( $group['id'] )
            {
                $group['topicCount'] = $this->getGroupTopicCount($group['id']);
                $group['replyCount'] = $this->getGroupPostCount($group['id']) - $group['topicCount'];
                $group['groupUrl'] = OW::getRouter()->urlForRoute('group-default', array('groupId' => $group['id']));

                $sectionGroupList[$sectionId]['groups'][] = $group;
            }

            $curSectionId = $sectionId;
        }

        return $sectionGroupList;
    }

    /**
     * Returns section list
     * 
     * @return array 
     */
    public function findSectionList()
    {
        return $this->sectionDao->findAll();
    }

    /**
     * Saves or updates section
     * 
     * @param FORUM_BOL_Section $sectionDto
     */
    public function saveOrUpdateSection( $sectionDto )
    {
        $this->sectionDao->save($sectionDto);
    }

    /**
     * Deletes section
     * 
     * @param int $sectionId
     */
    public function deleteSection( $sectionId )
    {
        $groupIdList = $this->groupDao->findIdListBySectionId($sectionId);
        $topicIdList = ( $groupIdList ) ? $this->topicDao->findIdListByGroupIds($groupIdList) : array();

        //delete section topics
        foreach ( $topicIdList as $topicId )
        {
            $this->deleteTopic($topicId);
        }

        //delete section groups
        $this->groupDao->deleteByIdList($groupIdList);

        //delete section
        $this->sectionDao->deleteById($sectionId);
    }

    /**
     * Returns section list
     * 
     * @param string $sectionName
     * @return array
     */
    public function suggestSection( $sectionName )
    {
        if ( strlen($sectionName) )
        {
            $sectionDtoList = $this->sectionDao->suggestSection($sectionName);
        }
        else
        {
            $sectionDtoList = $this->sectionDao->findGeneralSectionList();
        }

        return $sectionDtoList;
    }

    /**
     * Returns section
     *
     * @param string $sectionName
     * @param int $sectionId
     * @return FORUM_BOL_Section
     */
    public function getSection( $sectionName, $sectionId = 0 )
    {
        return $this->sectionDao->findSection($sectionName, $sectionId);
    }

    /**
     * Searches for a section, excluding hidden sections
     *
     * @param string $sectionName
     * @param int $sectionId
     * @return FORUM_BOL_Section
     */
    public function getPublicSection( $sectionName, $sectionId = 0 )
    {
        return $this->sectionDao->findPublicSection($sectionName, $sectionId);
    }

    /**
     * Returns first section
     *
     * @return FORUM_BOL_Section
     */
    public function getFirstSection()
    {
        return $this->sectionDao->findFirstSection();
    }

    /**
     * Returns section
     * 
     * @param int $sectionId
     * @return FORUM_BOL_Section
     */
    public function findSectionById( $sectionId )
    {
        return $this->sectionDao->findById($sectionId);
    }

    /**
     * Returns new section's order
     * 
     * @return int
     */
    public function getNewSectionOrder()
    {
        return $this->sectionDao->getNewSectionOrder();
    }

    /**
     * Returns new group's order
     * 
     * @param int $sectionId
     * @return int
     */
    public function getNewGroupOrder( $sectionId )
    {
        return $this->groupDao->getNewGroupOrder($sectionId);
    }

    /**
     * Deletes group
     * 
     * @param int $groupId
     */
    public function deleteGroup( $groupId )
    {
        $topicIdList = $this->topicDao->findIdListByGroupIds(array($groupId));

        //delete group topics
        foreach ( $topicIdList as $topicId )
        {
            $this->deleteTopic($topicId);
        }

        //delete group
        $this->groupDao->deleteById($groupId);
    }

    /**
     * Returns group select list
     *
     * @param int $excludeGroupId
     * @param boolean $includeHidden
     * @param int $forUserId
     * @return array
     */
    public function getGroupSelectList( $excludeGroupId = 0, $includeHidden = false, $forUserId = null )
    {
        $groupList = $this->sectionDao->getSectionGroupList( $includeHidden );

        $selectList = array();
        $curSectionId = 0;

        $userRoleIdList = array();
        if ( $forUserId )
        {
            $authService = BOL_AuthorizationService::getInstance();
            $roleList = $authService->getRoleList();
            $roleListAssoc = array();
            foreach ( $roleList as $roleDto )
            {
                $roleListAssoc[$roleDto->id] = $roleDto;
            }
            
            $userRoles = $authService->findUserRoleList($forUserId);
            foreach ( $userRoles as $role )
            {
                $userRoleIdList[] = $role->id;
            }
        }
        
        foreach ( $groupList as $group )
        {
            if ( $group['id'] == $excludeGroupId )
            {
                continue;
            }
            
            if ( $group['isPrivate'] ) 
            {
                $allowedRoleIdList = json_decode($group['roles']);
                
                if ( !$forUserId )
                {
                    continue;
                }
                else if ( !OW::getUser()->isAuthorized('forum') )
                {
                    if ( !$this->isPrivateGroupAvailable($forUserId, $allowedRoleIdList, $userRoleIdList) )
                    {
                        continue;
                    }
                }
            }

            $sectionId = $group['sectionId'];

            if ( $curSectionId != $sectionId )
            {
                $selectList[] = array(
                    'label' => '- ' . $group['sectionName'],
                    'value' => 0,
                    'disabled' => true
                );
            }

            $selectList[] = array(
                'label' => $group['name'],
                'value' => $group['id'],
                'disabled' => false
            );

            $curSectionId = $sectionId;
        }

        return $selectList;
    }
    
    public function getPrivateUnavailableGroupIdList( $forUserId )
    {       
        $groupList = $this->sectionDao->getSectionGroupList();

        $userRoleIdList = array();
        if ( $forUserId )
        {
            $authService = BOL_AuthorizationService::getInstance();
            $roleList = $authService->getRoleList();
            $roleListAssoc = array();
            foreach ( $roleList as $roleDto )
            {
                $roleListAssoc[$roleDto->id] = $roleDto;
            }
            
            $userRoles = $authService->findUserRoleList($forUserId);
            $userRoleIdList = array();
            foreach ( $userRoles as $role )
            {
                $userRoleIdList[] = $role->id;
            }
        }
        
        $idList = array();
        foreach ( $groupList as $group )
        {            
            if ( $group['isPrivate'] ) 
            {
                $allowedRoleIdList = json_decode($group['roles']);
                
                if ( !$forUserId || !$this->isPrivateGroupAvailable($forUserId, $allowedRoleIdList, $userRoleIdList) )
                {
                    $idList[] = $group['id'];
                }
            }
        }

        return $idList;
    }

    /**
     * Returns group info
     * 
     * @param int $groupId
     * @return FORUM_BOL_Group
     */
    public function getGroupInfo( $groupId )
    {
        $groupId = (int) $groupId;

        if ( !$groupId )
        {
            return false;
        }

        return $this->groupDao->findById($groupId);
    }

    /**
     * Returns group's post count
     * 
     * @param int $groupId
     * @return int
     */
    public function getGroupPostCount( $groupId )
    {
        return $this->topicDao->findGroupPostCount($groupId);
    }

    /**
     * Returns group's topic list
     * 
     * @param int $groupId
     * @param int $page
     * @param int $count
     * @return array
     */
    public function getGroupTopicList( $groupId, $page, $count = null )
    {
        if ( !isset($count) )
        {
            $count = $this->getTopicPerPageConfig();
        }
        $first = ($page - 1) * $count;

        $topicList = $this->topicDao->findGroupTopicList($groupId, $first, $count);

        $postIds = array();
        $topicIds = array();

        foreach ( $topicList as $topic )
        {
            $postIds[] = $topic['lastPostId'];
            $topicIds[] = $topic['id'];
        }

        $postList = $this->getTopicLastReplyList($postIds);
        $userId = OW::getUser()->getId();

        $readTopicIds = array();
        if ( $userId && $topicIds )
        {
            $readTopicDao = FORUM_BOL_ReadTopicDao::getInstance();
            $readTopicIds = $readTopicDao->findUserReadTopicIds($topicIds, $userId);
        }

        foreach ( $topicList as &$topic )
        {
            //prepare post Info
            $postInfo = isset($postList[$topic['id']]) ? $postList[$topic['id']] : null;
            if ( $postInfo )
            {
                $postInfo['postUrl'] = $this->getLastPostUrl($topic['id'], $topic['postCount'], $postInfo['postId']);
                $topic['lastPost'] = $postInfo;
            }
            
            //prepare topic info
            $topic['replyCount'] = $topic['postCount'] - 1;
            $topic['new'] = ($userId && !in_array($topic['id'], $readTopicIds));
            $topic['topicUrl'] = OW::getRouter()->urlForRoute('topic-default', array('topicId' => $topic['id']));
        }

        return $topicList;
    }

    /**
     * Returns last topics list
     * 
     * @param int $topicLimit
     * @param array $excludeGroupIdList
     * @return array
     */
    public function getLatestTopicList( $topicLimit, $excludeGroupIdList = null )
    {
        $topicList = $this->topicDao->findLastTopicList($topicLimit, $excludeGroupIdList);

        if ( !$topicList )
        {
            return array();
        }

        $postIds = array();
        foreach ( $topicList as $topic )
        {
            $postIds[] = $topic['lastPostId'];
            $topicIds[] = $topic['id'];
        }

        $postList = $this->getTopicLastReplyList($postIds);

        $topics = array();
        foreach ( $topicList as $topic )
        {
            if ( empty($postList[$topic['id']]) )
            {
                continue;
            }
            //prepare post Info
            $postInfo = $postList[$topic['id']];
            $postInfo['postUrl'] = $this->getLastPostUrl($topic['id'], $topic['postCount'], $postInfo['postId']);

            //prepare topic info
            $topic['lastPost'] = $postInfo;
            $topic['topicUrl'] = OW::getRouter()->urlForRoute('topic-default', array('topicId' => $topic['id']));
            $topics[] = $topic;
        }

        return $topics;
    }
    
    public function getUserTopicList( $userId )
    {
        if ( !$userId )
        {
            return false;
        }

        return $this->topicDao->findUserTopicList($userId);
    }

    /**
     * Deletes user posted forum topics
     * 
     * @param $userId
     * @return boolean
     */
    public function deleteUserTopics( $userId )
    {
        $topicList = $this->getUserTopicList($userId);

        if ( $topicList )
        {
            foreach ( $topicList as $topic )
            {
                $this->deleteTopic($topic['id']);
            }
        }

        return true;
    }

    public function deleteUserPosts( $userId )
    {
        $postList = $this->postDao->findUserPostList($userId);

        if ( $postList )
        {
            foreach ( $postList as $post )
            {
                $topic = $this->findTopicById($post->topicId); 
                
                if ( $topic && ($topic->lastPostId == $post->id) )
                {
                    $prev = $this->postDao->findPreviousPost($topic->id, $post->id);
                    if ( $prev )
                    {
                        $topic->lastPostId = $prev->id;
                        $this->topicDao->save($topic);
                    }
                }
                $this->deletePost($post->id);
            }
        }
        
            return true;
    }

    /**
     * Returns group's topic count
     * 
     * @param int $groupId
     * @return int
     */
    public function getGroupTopicCount( $groupId )
    {
        return $this->topicDao->findGroupTopicCount($groupId);
    }
    
    public function getGroupLastReply( $groupId )
    {
        $post = $this->postDao->findGroupLastPost($groupId);
        
        if ( $post )
        {
            $post['topicUrl'] = OW::getRouter()->urlForRoute('topic-default', array('topicId' => $post['topicId']));
            $post['postUrl'] = $this->getPostUrl($post['topicId'], $post['id']);
            $post['titleTruncated'] = mb_substr($post['title'], 0, 23) . (mb_strlen($post['title'])  > 23 ? '&hellip;' : '');
        }
        
        return $post;
    }

    /**
     * Returns group list
     * 
     * @param array $groupIds
     * @return array
     */
    public function findGroupByIdList( $groupIds )
    {
        return $this->groupDao->findByIdList($groupIds);
    }

    /**
     * Returns sections list
     * 
     * @param array $sectionIds
     * @return array
     */
    public function findSectionsByIdList( $sectionIds )
    {
        $sections = $this->sectionDao->findByIdList($sectionIds);

        if ( $sections )
        {
            $sectionList = array();

            foreach ( $sections as $section )
            {
                $sectionList[$section->id] = $section;
            }

            return $sectionList;
        }

        return false;
    }

    /**
     * Saves or updates group
     * 
     * @param FORUM_BOL_Group $groupDto
     */
    public function saveOrUpdateGroup( $groupDto )
    {
        $this->groupDao->save($groupDto);

        // update forum group into the search index
        $this->getTextSearchService()->saveOrUpdateGroup($groupDto);
    }

    /**
     * Returns group
     * 
     * @param int $groupId
     * @return FORUM_BOL_Group
     */
    public function findGroupById( $groupId )
    {
        return $this->groupDao->findById($groupId);
    }

    /**
     * Returns topics last reply info
     * 
     * @param array $postIds
     * @return array
     */
    public function getTopicLastReplyList( $postIds )
    {
        $postDtoList = $this->postDao->findByIdList($postIds);

        $postList = array();
        foreach ( $postDtoList as $postDto )
        {
            $postInfo = array(
                'postId' => $postDto->id,
                'topicId' => $postDto->topicId,
                'userId' => $postDto->userId,
                'text' => strip_tags($postDto->text),
                'createStamp' => UTIL_DateTime::formatDate($postDto->createStamp)
            );
            $postList[$postDto->topicId] = $postInfo;
        }

        return $postList;
    }

    /**
     * Returns topic info
     * 
     * @param int $topicId
     * @return array
     */
    public function getTopicInfo( $topicId )
    {
        $topicId = (int) $topicId;

        if ( !$topicId )
        {
            return false;
        }

        $topicInfo = $this->topicDao->findTopicInfo($topicId);

        return $topicInfo;
    }

    /**
     * Returns topic Dto
     * 
     * @param int $topicId
     * @return FORUM_BOL_Topic
     */
    public function findTopicById( $topicId )
    {
        return $this->topicDao->findById($topicId);
    }

    public function addTopic( $topicDto )
    {
        $this->topicDao->save($topicDto);

        // add a topic into the search index
        $this->getTextSearchService()->addTopic($topicDto);
    }

    /**
     * Saves or updates topic
     * 
     * @param FORUM_BOL_Topic $topicDto
     * @param boolean $rebuildTopic
     * @param boolean $refreshPosts
     */
    public function saveOrUpdateTopic( $topicDto, $refreshPosts = false )
    {
        $this->topicDao->save($topicDto);

        // add or edit a topic into the search index
        $this->getTextSearchService()->saveOrUpdateTopic($topicDto, $refreshPosts);
    }

    /**
     * Sets topic as read by user
     *
     * @param int $topicId
     * @param int $userId
     * @return bool
     */
    public function setTopicRead( $topicId, $userId )
    {
        if ( !$topicId || !$userId )
        {
            return false;
        }

        $readTopicDao = FORUM_BOL_ReadTopicDao::getInstance();

        $readTopicDto = $readTopicDao->findTopicRead($topicId, $userId);

        if ( $readTopicDto === null )
        {
            $readTopicDto = new FORUM_BOL_ReadTopic();

            $readTopicDto->topicId = $topicId;
            $readTopicDto->userId = $userId;

            $readTopicDao->save($readTopicDto);
        }

        return true;
    }

    /**
     * Deletes topic read info
     *
     * @param int $topicId
     * @return bool
     */
    public function deleteByTopicId( $topicId )
    {
        $readTopicDao = FORUM_BOL_ReadTopicDao::getInstance();

        $readTopicDao->deleteByTopicId($topicId);

        return true;
    }

    /**
     * Returns group's topic list
     * 
     * @param integer $groupId
     * @param integer $page
     * @param integer $lastTopicId
     * @return FORUM_BOL_Topic 
     */
    public function getSimpleGroupTopicList( $groupId, $page, $lastTopicId = null )
    {
        $count = $this->getPostPerPageConfig();
        $first = ($page - 1) * $count;

        return $this->topicDao->findSimpleGroupTopicList($groupId, $first, $count, $lastTopicId);
    }

    /**
     * Returns simple topic's post list
     * 
     * @param int $topicId
     * @param integer $page
     * @param integer $lastPostId
     * @return array of FORUM_BOL_Post
     */
    public function getSimpleTopicPostList( $topicId, $page, $lastPostId = null )
    {
        $count = $this->getPostPerPageConfig();
        $first = ($page - 1) * $count;

        return $this->postDao->findTopicPostList($topicId, $first, $count, $lastPostId);
    }

    /**
     * Returns topic's post list
     * 
     * @param int $topicId
     * @param integer $page
     * @return array
     */
    public function getTopicPostList( $topicId, $page )
    {
        $count = $this->getPostPerPageConfig();
        $first = ($page - 1) * $count;

        $postDtoList = $this->postDao->findTopicPostList($topicId, $first, $count);
        $postList = array();
        $postIds = array();

        //prepare topic posts
        foreach ( $postDtoList as $postDto )
        {
            $post = array(
                'id' => $postDto->id,
                'topicId' => $postDto->topicId,
                'userId' => $postDto->userId,
                'text' => $this->formatQuote($postDto->text),
                'createStamp' => UTIL_DateTime::formatDate($postDto->createStamp),
                'edited' => array()
            );

            $postList[$postDto->id] = $post;

            $postIds[] = $postDto->id;
        }

        $editedPostDtoList = ( $postIds ) ? FORUM_BOL_EditPostDao::getInstance()->findByPostIdList($postIds) : array();

        //get edited posts array
        foreach ( $editedPostDtoList as $editedPostDto )
        {
            $editedPost = array(
                'postId' => $editedPostDto->postId,
                'userId' => $editedPostDto->userId,
                'editStamp' => UTIL_DateTime::formatDate($editedPostDto->editStamp)
            );

            $postList[$editedPostDto->postId]['edited'] = $editedPost;
        }

        return $postList;
    }

    /**
     * Returns topic's post count
     * 
     * @param int $topicId
     * @return int
     */
    public function findTopicPostCount( $topicId )
    {
        return (int) $this->postDao->findTopicPostCount($topicId);
    }

    /**
     * Saves or updates post
     * 
     * @param FORUM_BOL_Post $postDto
     */
    public function saveOrUpdatePost( $postDto )
    {
        $this->postDao->save($postDto);

        // add or edit a post into the search index
        $this->getTextSearchService()->saveOrUpdatePost($postDto);
    }

    /**
     * Returns edit post
     * 
     * @param int $postId
     * @return FORUM_BOL_EditPost
     */
    public function findEditPost( $postId )
    {
        $editPostDao = FORUM_BOL_EditPostDao::getInstance();

        return $editPostDao->findByPostId($postId);
    }

    /**
     * Saves or updates edit post
     * 
     * @param FORUM_BOL_EditPost $editPostDto
     */
    public function saveOrUpdateEditPost( $editPostDto )
    {
        $editPostDao = FORUM_BOL_EditPostDao::getInstance();

        $editPostDao->save($editPostDto);
    }

    /**
     * Returns post url
     * 
     * @param int $topicId
     * @param int $postId
     * @param boolean $anchor
     * @param int $page
     * @return string
     */
    public function getPostUrl( $topicId, $postId, $anchor = true, $page = null )
    {
        if ( empty($page) || !$page )
        {
            $count = $this->getPostPerPageConfig();
            $postNumber = $this->postDao->findPostNumber($topicId, $postId);
            $page = ceil($postNumber / $count);
        }
        
        $topicUrl = OW::getRouter()->urlForRoute('topic-default', array('topicId' => $topicId));
        $anchor_str = ($anchor) ? "#post-$postId" : "";
        $postUrl = $topicUrl . "?page=$page" . $anchor_str;

        return $postUrl;
    }

    /**
     * Returns last post url
     * 
     * @param int $topicId
     * @param int $postCount
     * @param int $postId
     * @return string
     */
    public function getLastPostUrl( $topicId, $postCount, $postId )
    {
        $count = $this->getPostPerPageConfig();
        $page = ceil($postCount / $count);

        $topicUrl = OW::getRouter()->urlForRoute('topic-default', array('topicId' => $topicId));
        $postUrl = $topicUrl . "?page=$page#post-$postId";

        return $postUrl;
    }

    /**
     * Returns config value for the number of posts per page
     *
     * @return int
     */
    public function getPostPerPageConfig()
    {
        return 20; // TODO: get config
    }

    /**
     * Returns config value for the number of topics per page
     *
     * @return int
     */
    public function getTopicPerPageConfig()
    {
        return 20; // TODO: get config
    }

    /**
     * Returns post
     * 
     * @param int $postId
     * @return FORUM_BOL_Post
     */
    public function findPostById( $postId )
    {
        return $this->postDao->findById($postId);
    }

    public function findPostListByIds( $postIdList )
    {
        return $this->postDao->findByIdList( $postIdList );
    }

    /**
     * Returns previous post
     * 
     * @param int $topicId
     * @param int $postId
     * @return FORUM_BOL_Post
     */
    public function findPreviousPost( $topicId, $postId )
    {
        return $this->postDao->findPreviousPost($topicId, $postId);
    }

    /**
     * Returns topic's first post
     * 
     * @param int $topicId
     * @return FORUM_BOL_Post
     */
    public function findTopicFirstPost( $topicId )
    {
        return $this->postDao->findTopicFirstPost($topicId);
    }

    /**
     * Deletes post
     * 
     * @param int $postId
     */
    public function deletePost( $postId )
    {
        $editPostDao = FORUM_BOL_EditPostDao::getInstance();

        //delete post edit info
        $editPostDao->deleteByPostId($postId);

        //delete post
        $this->postDao->deleteById($postId);

        //delete attachments
        FORUM_BOL_PostAttachmentService::getInstance()->deletePostAttachments($postId);

        //delete flags
        BOL_FlagService::getInstance()->deleteByTypeAndEntityId(FORUM_CLASS_ContentProvider::POST_ENTITY_TYPE, $postId);

        $event = new OW_Event(self::EVENT_AFTER_POST_DELETE, array('postId' => $postId));
        OW::getEventManager()->trigger($event);

        // delete a post from the search index
        $this->getTextSearchService()->deletePost($postId);
    }

    /**
     * Deletes topic
     * 
     * @param int $topicId
     */
    public function deleteTopic( $topicId )
    {
        //delete flags
        BOL_FlagService::getInstance()->deleteByTypeAndEntityId(FORUM_CLASS_ContentProvider::ENTITY_TYPE, $topicId);
        
        $editPostDao = FORUM_BOL_EditPostDao::getInstance();
        $readTopicDao = FORUM_BOL_ReadTopicDao::getInstance();

        $postIds = $this->postDao->findTopicPostIdList($topicId);

        if ( $postIds )
        {
            //delete topic posts edit info
            $editPostDao->deleteByPostIdList($postIds);
            
            //delete topic posts
            foreach ( $postIds as $post )
            {
                $this->deletePost($post);
            }
        }

        //delete topic read info
        $readTopicDao->deleteByTopicId($topicId);

        OW::getEventManager()->trigger(new OW_Event(self::EVENT_BEFORE_TOPIC_DELETE, array(
            'topicId' => $topicId
        )));

        //delete topic
        $this->topicDao->deleteById($topicId);

        OW::getEventManager()->trigger(new OW_Event('feed.delete_item', array(
            'entityType' => 'forum-topic',
            'entityId' => $topicId
        )));

        $event = new OW_Event(self::EVENT_AFTER_TOPIC_DELETE, array('topicId' => $topicId));
        OW::getEventManager()->trigger($event);

        // delete a topic from the search index
        $this->getTextSearchService()->deleteTopic($topicId);
    }

    public function formatQuote( $text )
    {
        $quote_reg = "#\<blockquote\sfrom=.?([^\"]*).?\>#i";

        //replace quote tag
        if ( preg_match_all($quote_reg, $text, $text_arr) )
        {
            $key = 0;
            foreach ( $text_arr[0] as $key => $value )
            {
                $quote = '<blockquote class="ow_quote">' .
                    '<span class="ow_small ow_author">' . OW::getLanguage()->text('forum', 'forum_quote') . ' ' .
                    OW::getLanguage()->text('forum', 'forum_quote_from') . ' <b>' . $text_arr[1][$key] . '</b></span><br />';
                $text = str_replace($value, $quote, $text);
            }

            $is_closed = $key - substr_count($text, '</blockquote>') - 1;

            if ( $is_closed && $is_closed > 0 )
            {
                for ( $i = 0; $is_closed > $i; $i++ )
                    $text .= "</blockquote>";
            }

            $text = nl2br($text);
        }

        return $text;
    }

    public function deleteTopics( $limit )
    {
        $topics = $this->topicDao->getTopicIdListForDelete($limit);

        if ( $topics )
        {
            foreach ( $topics as $topicId )
            {
                $this->deleteTopic($topicId);
            }
        }
    }

    public function countAllTopics()
    {
        return $this->topicDao->countAll();
    }

    public function setMaintenanceMode( $mode = true )
    {
        $config = OW::getConfig();

        if ( $mode )
        {
            $state = (int) $config->getValue('base', 'maintenance');
            $config->saveConfig('forum', 'maintenance_mode_state', $state);
            OW::getApplication()->setMaintenanceMode($mode);
        }
        else
        {
            $state = (int) $config->getValue('forum', 'maintenance_mode_state');
            $config->saveConfig('base', 'maintenance', $state);
        }
    }

    /**
     * Returns section
     *
     * @param string $entity
     * @return FORUM_BOL_Section
     */
    public function findSectionByEntity( $entity )
    {
        if ( !$entity )
        {
            return null;
        }

        return $this->sectionDao->findByEntity($entity);
    }

    /**
     * Returns group by specified entity id
     *
     * @param string $entity
     * @param int $entityId
     * @return FORUM_BOL_Group
     */
    public function findGroupByEntityId( $entity, $entityId )
    {
        $entityId = (int) $entityId;

        if ( !$entityId || !isset($entity) )
        {
            return null;
        }

        $section = $this->sectionDao->findByEntity($entity);
        if (empty($section))
        {
            return null;
        }

        return $this->groupDao->findByEntityId($section->getId(), $entityId);
    }
    
    public function groupIsHidden( $groupId )
    {
        $groupInfo = $this->getGroupInfo($groupId);
        if ( $groupInfo )
        {
            $forumSection = $this->findSectionById($groupInfo->sectionId);
            
            if ( $forumSection )
            {
                return $forumSection->isHidden;
            }
        }

        return false;
    }

    /**
     * Process found topics
     * 
     * @param array $topics
     * @param string $token
     * @return array
     */
    protected function processFoundTopics( array $topics, $token = null )
    {
        $topicsIds = array();

        // collect list of topics id
        foreach($topics as $topic)
        {
            $topicsIds[] = $topic['entityId'];
        }

        $topics = $this->topicDao->findListByTopicIds($topicsIds);

        // process topics
        $formatter = new FORUM_CLASS_ForumSearchResultFormatter();

        foreach($topics as &$topic)
        {
            $topic['topicUrl'] = OW::getRouter()->urlForRoute('topic-default', array('topicId' => $topic['id']));
            if (null == ($postDto = $this->findTopicFirstPost($topic['id'])))
            {
                continue;
            }

            $text = strip_tags($postDto->text);

            $topic['posts'][] = array(
                'postId' => $postDto->id,
                'topicId' => $postDto->topicId,
                'userId' => $postDto->userId,
                'text' => $token ? $formatter->formatResult($text, array($token)) : $text,
                'createStamp' => UTIL_DateTime::formatDate($postDto->createStamp),
                'postUrl' => $this->getPostUrl($postDto->topicId, $postDto->id)
            );
        }

        return $topics; 
    }

    /**
     * Process found posts
     * 
     * @param array $posts
     * @param string $token
     * @return array
     */
    protected function processFoundPosts( $posts, $token = null  )
    {
        $postsIds = array();

        // collect list of posts id
        foreach($posts as $post)
        {
            $postsIds[] = $post['entityId'];
        }

        // get list posts by ids
        $posts = $this->postDao->findListByPostIds($postsIds);
        $postList = array();
        $formatter = new FORUM_CLASS_ForumSearchResultFormatter();

        // wrap posts as a part of topic
        foreach($posts as $post)
        {
            // get topic info
            if ( empty($postList[$post['topicId']]) )
            {
                 $postList[$post['topicId']] = $this->topicDao->findTopicInfo($post['topicId']);
                 $postList[$post['topicId']]['topicUrl'] = 
                        OW::getRouter()->urlForRoute('topic-default', array('topicId' => $post['topicId']));
            }

            $text = strip_tags($post['text']);

            $postList[$post['topicId']]['posts'][] = array(
                'postId' => $post['id'],
                'topicId' => $post['topicId'],
                'userId' => $post['userId'],
                'text' => $token ? $formatter->formatResult($text, array($token)) : $text,
                'createStamp' => UTIL_DateTime::formatDate($post['createStamp']),
                'postUrl' => $this->getPostUrl($post['topicId'], $post['id'])
            );
        }

        return $postList;
    }

    /**
     * Get count of topics in all sections
     * 
     * @param string $token
     * @param integer $userId
     * @return integer
     */
    public function countFindGlobalTopics( $token, $userId )
    {
        return $this->getTextSearchService()->countFindGlobalTopics($token, $userId);
    }

    /**
     * Find topics in all sections
     * 
     * @param string $token
     * @param integer $page
     * @param string $sortBy
     * @param integer $userId
     * @return array
     */
    public function findGlobalTopics( $token, $page, $sortBy = null, $userId = null )
    {
        $limit = $this->getTopicPerPageConfig();
        $first = ( $page - 1 ) * $limit;

        // make a search
        $topics = $this->getTextSearchService()->
                findGlobalTopics($token, $first, $limit, $sortBy, $userId);

        if ( $topics )
        {
            return $this->processFoundTopics($topics, $token);
        }

        return array();
    }

    /**
     * Get count of topics in all sections
     * 
     * @param integer $userId
     * @return integer
     */
    public function countFindGlobalTopicsByUser( $userId )
    {
        return $this->getTextSearchService()->countFindGlobalTopicsByUser($userId);
    }

    /**
     * Find topics in all sections
     * 
     * @param integer $userId
     * @param integer $page
     * @param string $sortBy
     * @return array
     */
    public function findGlobalTopicsByUser( $userId, $page, $sortBy = null )
    {
        $limit = $this->getTopicPerPageConfig();
        $first = ( $page - 1 ) * $limit;

        // make a search
        $topics = $this->getTextSearchService()->
                findGlobalTopicsByUser($userId, $first, $limit, $sortBy);

        if ( $topics )
        {
            return $this->processFoundTopics($topics);
        }

        return array();
    }

    /**
     * Get count of topics in section
     * 
     * @param string $token
     * @param integer $sectionId
     * @param integer $userId
     * @return integer
     */
    public function countFindTopicsInSection( $token, $sectionId, $userId )
    {
        return $this->getTextSearchService()->countFindTopicsInSection($token, $sectionId, $userId);
    }

    /**
     * Find topics in section
     * 
     * @param sting $token
     * @param integer $sectionId
     * @param integer $page
     * @param string $sortBy
     * @param integer $userId
     * @return array
     */
    public function findTopicsInSection( $token, $sectionId, $page, $sortBy = null, $userId = null )
    {
        $limit = $this->getTopicPerPageConfig();
        $first = ( $page - 1 ) * $limit;

        // make a search
        $topics = $this->getTextSearchService()->
                findTopicsInSection($token, $sectionId, $first, $limit, $sortBy, $userId);

        if ( $topics )
        {
            return $this->processFoundTopics($topics, $token);
        }

        return array();
    }

    /**
     * Get count of topics in section
     * 
     * @param integer $userId
     * @param integer $sectionId     
     * @return integer
     */
    public function countFindTopicsInSectionByUser( $userId, $sectionId )
    {
        return $this->getTextSearchService()->countFindTopicsInSectionByUser($userId, $sectionId);
    }

    /**
     * Find topics in section
     * 
     * @param integer $userId
     * @param integer $sectionId
     * @param integer $page
     * @param string $sortBy
     * @return array
     */
    public function findTopicsInSectionByUser( $userId, $sectionId, $page, $sortBy = null )
    {
        $limit = $this->getTopicPerPageConfig();
        $first = ( $page - 1 ) * $limit;

        // make a search
        $topics = $this->getTextSearchService()->
                findTopicsInSectionByUser($userId, $sectionId, $first, $limit, $sortBy);

        if ( $topics )
        {
            return $this->processFoundTopics($topics);
        }

        return array();
    }

    /**
     * Get count of topics in group
     * 
     * @param string $token
     * @param integer $groupId
     * @param integer $userId
     * @return integer
     */
    public function countFindTopicsInGroup( $token, $groupId, $userId )
    {
        return $this->getTextSearchService()->countFindTopicsInGroup($token, $groupId, $userId);
    }

    /**
     * Find topics in group
     * 
     * @param sting $token
     * @param integer $groupId
     * @param integer $page
     * @param string $sortBy
     * @param integer $userId
     * @return array
     */
    public function findTopicsInGroup( $token, $groupId, $page, $sortBy = null, $userId = null )
    {
        $limit = $this->getTopicPerPageConfig();
        $first = ( $page - 1 ) * $limit;

        // make a search
        $topics = $this->getTextSearchService()->
                findTopicsInGroup($token, $groupId, $first, $limit, $sortBy, $userId);

        if ( $topics )
        {
            return $this->processFoundTopics($topics, $token);
        }

        return array();
    }

    /**
     * Get count of topics in group
     *
     * @param integer $userId
     * @param integer $groupId
     * @return integer
     */
    public function countFindTopicsInGroupByUser( $userId, $groupId )
    {
        return $this->getTextSearchService()->countFindTopicsInGroupByUser($userId, $groupId);
    }

    /**
     * Find topics in group
     * 
     * @param integer $userId
     * @param integer $groupId
     * @param integer $page
     * @param string $sortBy
     * @return array
     */
    public function findTopicsInGroupByUser( $userId, $groupId, $page, $sortBy = null )
    {
        $limit = $this->getTopicPerPageConfig();
        $first = ( $page - 1 ) * $limit;

        // make a search
        $topics = $this->getTextSearchService()->
                findTopicsInGroupByUser($userId, $groupId, $first, $limit, $sortBy);

        if ( $topics )
        {
            return $this->processFoundTopics($topics);
        }

        return array();
    }

    /**
     * Get count of entities in advanced search
     * 
     * @param string $keyword
     * @param integer $userId
     * @param array $parts
     * @param string $period
     * @param boolean $searchPosts
     * @return integer
     */
    public function countAdvancedFindEntities( $keyword, $userId = null, $parts = array(), $period = null, $searchPosts = true )
    {
        return $this->getTextSearchService()->
                countAdvancedFindEntities($keyword, $userId, $parts, $period, $searchPosts);
    }

    /**
     * Advanced find entites
     * 
     * @param string $keyword
     * @param integer $page
     * @param integer $userId
     * @param array $parts
     * @param string $period
     * @param string $sort
     * @param string $sortDirection
     * @param boolean $searchPosts
     * @return array
     */
    public function advancedFindEntities( $keyword, $page, $userId = null, 
            $parts = array(), $period = null, $sort = null, $sortDirection = null, $searchPosts = true )
    {
        // per page
        $limit = $searchPosts 
            ? $this->getPostPerPageConfig()
            : $this->getTopicPerPageConfig();

        $first = ( $page - 1 ) * $limit;

        $entities = $this->getTextSearchService()->
                advancedFindEntities($keyword, $first, $limit, $userId, $parts, $period, $sort, $sortDirection, $searchPosts);

        // process entities
        if ( $entities )
        {
            return $searchPosts
                ? $this->processFoundPosts($entities, $keyword)
                : $this->processFoundTopics($entities, $keyword);
        }

        return array();
    }

    /**
     * Get count of entities in advanced search
     * 
     * @param integer $userId
     * @param array $parts
     * @param string $period
     * @param boolean $searchPosts
     * @return integer
     */
    public function countAdvancedFindEntitiesByUser( $userId, $parts = array(), $period = null, $searchPosts = true )
    {
        return $this->getTextSearchService()->
                countAdvancedFindEntitiesByUser($userId, $parts, $period, $searchPosts);
    }

    /**
     * Advanced find entites
     * 
     * @param integer $userId    
     * @param integer $page
     * @param array $parts
     * @param string $period
     * @param string $sort
     * @param string $sortDirection
     * @param boolean $searchPosts
     * @return array
     */
    public function advancedFindEntitiesByUser( $userId, $page, 
            $parts = array(), $period = null, $sort = null, $sortDirection = null, $searchPosts = true )
    {
        // per page
        $limit = $searchPosts 
            ? $this->getPostPerPageConfig()
            : $this->getTopicPerPageConfig();

        $first = ( $page - 1 ) * $limit;

        $entities = $this->getTextSearchService()->
                advancedFindEntitiesByUser($userId, $first, $limit, $parts, $period, $sort, $sortDirection, $searchPosts);

        
        // process entities
        if ( $entities )
        {
            return $searchPosts
                ? $this->processFoundPosts($entities)
                : $this->processFoundTopics($entities);
        }

        return array();
    }

    /**
     * Get count of posts in topic
     * 
     * @param string $token
     * @param integer $topicId
     * @param integer $userId
     * @return integer
     */
    public function countFindPostsInTopic( $token, $topicId, $userId )
    {
        return $this->getTextSearchService()->countFindPostsInTopic($token, $topicId, $userId);
    }

    /**
     * Find posts in topic
     * 
     * @param sting $token
     * @param integer $topicId
     * @param integer $page
     * @param string $sortBy
     * @param integer $userId
     * @return array
     */
    public function findPostsInTopic( $token, $topicId, $page, $sortBy = null, $userId = null )
    {
        $limit = $this->getTopicPerPageConfig();
        $first = ( $page - 1 ) * $limit;

        // make a search
        $posts = $this->getTextSearchService()->
                findPostsInTopic($token, $topicId, $first, $limit, $sortBy, $userId);

        if ( $posts )
        {
            return $this->processFoundPosts($posts, $token);
        }

        return array();
    }

    /**
     * Get count of posts in topic
     * 
     * @param integer $userId
     * @param integer $topicId
     * @return integer
     */
    public function countFindPostsInTopicByUser( $userId, $topicId )
    {
        return $this->getTextSearchService()->countFindPostsInTopicByUser($userId, $topicId);
    }

    /**
     * Find posts in topic
     * 
     * @param integer $userId
     * @param integer $topicId
     * @param integer $page
     * @param string $sortBy
     * @return array
     */
    public function findPostsInTopicByUser( $userId, $topicId, $page, $sortBy = null )
    {
        $limit = $this->getTopicPerPageConfig();
        $first = ( $page - 1 ) * $limit;

        // make a search
        $posts = $this->getTextSearchService()->
                findPostsInTopicByUser($userId, $topicId, $first, $limit, $sortBy);

        if ( $posts )
        {
            return $this->processFoundPosts($posts);
        }

        return array();
    }

    public function highlightSearchWords( $string, $token )
    {
        $token = preg_quote($token, "/");
        $string = preg_replace("/($token)/i", '<span class="ow_highbox">\1</span>', $string);

        return $string;
    }

    public function findTemporaryTopics( $limit )
    {
        return $this->topicDao->findTemporaryTopicList($limit);
    }

    public function findTopicListByIds( $topicIdList )
    {
        return $this->topicDao->findByIdList( $topicIdList );
    }
}