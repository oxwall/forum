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
 * Data Access Object for `forum_post` table
 *
 * @author Egor Bulgakov <egor.bulgakov@gmail.com>
 * @package ow.ow_plugins.forum.bol
 * @since 1.0
 */
class FORUM_BOL_PostDao extends OW_BaseDao
{

    /**
     * Class constructor
     *
     */
    protected function __construct()
    {
        parent::__construct();
    }
    /**
     * Class instance
     *
     * @var FORUM_BOL_PostDao
     */
    private static $classInstance;

    /**
     * Returns class instance
     *
     * @return FORUM_BOL_PostDao
     */
    public static function getInstance()
    {
        if ( self::$classInstance === null )
        {
            self::$classInstance = new self();
        }

        return self::$classInstance;
    }

    /**
     * @see OW_BaseDao::getDtoClassName()
     *
     */
    public function getDtoClassName()
    {
        return 'FORUM_BOL_Post';
    }

    /**
     * @see OW_BaseDao::getTableName()
     *
     */
    public function getTableName()
    {
        return OW_DB_PREFIX . 'forum_post';
    }

    /**
     * Returns topic's post list
     *
     * @param int $topicId
     * @param int $first
     * @param int $count
     * @return array of FORUM_BOL_Post
     */
    public function findTopicPostList( $topicId, $first, $count )
    {
        $example = new OW_Example();

        $example->andFieldEqual('topicId', $topicId);
        $example->setOrder('`id`');
        $example->setLimitClause($first, $count);

        return $this->findListByExample($example);
    }

    /**
     * Returns topic's post count
     *
     * @param int $topicId
     * @return int
     */
    public function findTopicPostCount( $topicId )
    {
        $example = new OW_Example();

        $example->andFieldEqual('topicId', $topicId);

        return $this->countByExample($example);
    }

    /**
     * Returns post number in the topic
     *
     * @param int $topicId
     * @param int $postId
     * @return int
     */
    public function findPostNumber( $topicId, $postId )
    {
        $example = new OW_Example();

        $example->andFieldEqual('topicId', $topicId);
        $example->andFieldLessOrEqual('id', $postId);

        return $this->countByExample($example);
    }

    /**
     * Finds previous post in the topic
     *
     * @param int $topicId
     * @param int $postId
     * @return FORUM_BOL_Post
     */
    public function findPreviousPost( $topicId, $postId )
    {
        $example = new OW_Example();

        $example->andFieldEqual('topicId', $topicId);
        $example->andFieldLessThan('id', $postId);
        $example->setOrder('`id` DESC');
        $example->setLimitClause(0, 1);

        return $this->findObjectByExample($example);
    }

    /**
     * Finds topic post id list
     *
     * @param int $topicId
     * @return array
     */
    public function findTopicPostIdList( $topicId )
    {
        $example = new OW_Example();

        $example->andFieldEqual('topicId', $topicId);

        $query = "
		SELECT `id` FROM `" . $this->getTableName() . "`
		" . $example;

        return $this->dbo->queryForColumnList($query);
    }

    public function findUserPostIdList( $userId )
    {
        $example = new OW_Example();

        $example->andFieldEqual('userId', $userId);

        $query = "
            SELECT `id` FROM `" . $this->getTableName() . "` " . $example;

        return $this->dbo->queryForColumnList($query);
    }
    
    public function findUserPostList( $userId )
    {
        $example = new OW_Example();
        $example->andFieldEqual('userId', $userId);

        return $this->findListByExample($example);
    }

    public function findTopicFirstPost( $topicId )
    {
        $example = new OW_Example();

        $example->andFieldEqual('topicId', $topicId);
        $example->setOrder("`id`");
        $example->setLimitClause(0, 1);

        return $this->findObjectByExample($example);
    }
    
    public function findGroupLastPost( $groupId )
    {
        if ( empty($groupId) )
        {
            return null;
        }
        
        $sql = 'SELECT `p`.*, `t`.`title`
            FROM `' . $this->getTableName() . '` AS `p`
                INNER JOIN `' . FORUM_BOL_TopicDao::getInstance()->getTableName() . '` AS `t`
                    ON(`p`.`topicId`=`t`.`id`)
            WHERE `t`.`groupId` = :groupId AND `t`.`status` = :status
            ORDER BY `p`.`createStamp` DESC
            LIMIT 1';
        
        return $this->dbo->queryForRow($sql, array('groupId' => $groupId, 'status' => FORUM_BOL_ForumService::STATUS_APPROVED));
    }
    
    public function getUserTokenJoinString( $userToken )
    {
        $userTokenJoin = "";

        if ( mb_strlen($userToken) )
        {
            $question = OW::getConfig()->getValue('base', 'display_name_question');
            if ( $question == 'username' )
            {
                $userTokenJoin = " INNER JOIN `".BOL_UserDao::getInstance()->getTableName()."` AS `u`
                    ON (`u`.`id` = `p`.`userId` AND `u`.`username` LIKE '".$this->dbo->escapeString($userToken)."%') ";
            }
            else
            {
                $userTokenJoin = " INNER JOIN `".BOL_QuestionDataDao::getInstance()->getTableName()."` AS `qd`
                    ON (`qd`.`userId`=`p`.`userId` AND `qd`.`questionName`='realname'
                    AND `qd`.`textValue` LIKE '".$this->dbo->escapeString($userToken)."%') ";
            }
        }

        return $userTokenJoin;
    }
    
    public function searchInGroups( $token, $userToken, $page, $limit, $excludeGroupIdList = null, $sortBy = null )
    {
        $excludeCond = $excludeGroupIdList ? ' AND `g`.`id` NOT IN ('.implode(',', $excludeGroupIdList).') = 1' : '';
        $sortCond = $sortBy == 'date' ? ' `p`.`createStamp` DESC, ' : '';
        
        $limit = (int) $limit;
        $first = ( $page - 1 ) * $limit;
        
        if ( !mb_strlen($token) ) // search by name only
        {
            $query = "SELECT `t`.*, `g`.`sectionId`, `s`.`name` AS `sectionName`, `g`.`name` AS `groupName`
                FROM `".$this->getTableName()."` AS `p`
                INNER JOIN `".FORUM_BOL_TopicDao::getInstance()->getTableName()."` AS `t` ON (`t`.`id`=`p`.`topicId`)
                INNER JOIN `".FORUM_BOL_GroupDao::getInstance()->getTableName()."` AS `g` ON (`g`.`id`=`t`.`groupId`)
                INNER JOIN `".FORUM_BOL_SectionDao::getInstance()->getTableName()."` AS `s` ON (`s`.`id`=`g`.`sectionId`)
                " . $this->getUserTokenJoinString($userToken) . "
                WHERE 1 ".$excludeCond." AND `s`.`isHidden` = 0 AND `t`.`status` = :status
                GROUP BY `t`.`id`
                ORDER BY `p`.`createStamp` DESC
                LIMIT :first, :limit";

            $params = array('status' => FORUM_BOL_ForumService::STATUS_APPROVED, 'first' => (int)$first, 'limit' => (int)$limit);
        }
        else
        {
            $multiple = $this->countTokenWords($token) > 1;
            $booleanMode = $multiple ? ' IN BOOLEAN MODE' : '';
            $token = $booleanMode ? '"'.$token.'"' : $token;

        $query = "SELECT `t`.*, `g`.`sectionId`, `s`.`name` AS `sectionName`, `g`.`name` AS `groupName`, 
                MATCH (`t`.`title`) AGAINST(:token".$booleanMode."), MATCH (`p`.`text`) AGAINST(:token".$booleanMode.")
            FROM `".$this->getTableName()."` AS `p`
            INNER JOIN `".FORUM_BOL_TopicDao::getInstance()->getTableName()."` AS `t` ON (`t`.`id`=`p`.`topicId`)
            INNER JOIN `".FORUM_BOL_GroupDao::getInstance()->getTableName()."` AS `g` ON (`g`.`id`=`t`.`groupId`)
            INNER JOIN `".FORUM_BOL_SectionDao::getInstance()->getTableName()."` AS `s` ON (`s`.`id`=`g`.`sectionId`)
                " . $this->getUserTokenJoinString($userToken) . "
                WHERE (MATCH (`t`.`title`) AGAINST(:token".$booleanMode.") OR MATCH (`p`.`text`) AGAINST(:token".$booleanMode."))
            ".$excludeCond." AND `s`.`isHidden` = 0 AND `t`.`status` = :status
            GROUP BY `t`.`id`
                ORDER BY ".$sortCond." MATCH (`t`.`title`) AGAINST(:token".$booleanMode.") DESC, MATCH (`p`.`text`) AGAINST(:token".$booleanMode.") DESC
            LIMIT :first, :limit";
        
        $params = array('status' => FORUM_BOL_ForumService::STATUS_APPROVED, 'token' => $token, 'first' => (int)$first, 'limit' => (int)$limit);
        }
        
        return $this->dbo->queryForList($query, $params);
    }
    
    public function countFoundTopicsInGroups( $token, $userToken, $excludeGroupIdList = null )
    {
        $excludeCond = $excludeGroupIdList ? ' AND `g`.`id` NOT IN ('.implode(',', $excludeGroupIdList).') = 1' : '';

        if ( !mb_strlen($token) ) // search by name only
        {
            $query = "SELECT count(DISTINCT(`t`.`id`))
                FROM `".$this->getTableName()."` AS `p`
                INNER JOIN `".FORUM_BOL_TopicDao::getInstance()->getTableName()."` AS `t` ON (`t`.`id`=`p`.`topicId`)
                INNER JOIN `".FORUM_BOL_GroupDao::getInstance()->getTableName()."` AS `g` ON (`g`.`id`=`t`.`groupId`)
                INNER JOIN `".FORUM_BOL_SectionDao::getInstance()->getTableName()."` AS `s` ON (`s`.`id`=`g`.`sectionId`)
                " . $this->getUserTokenJoinString($userToken) . "
                WHERE 1 ".$excludeCond." AND `s`.`isHidden` = 0 AND `t`.`status` = :status";
            $params = array('status' => FORUM_BOL_ForumService::STATUS_APPROVED);
        }
        else
        {
            $multiple = $this->countTokenWords($token) > 1;
            $booleanMode = $multiple ? ' IN BOOLEAN MODE' : '';
            $token = $booleanMode ? '"'.$token.'"' : $token;

        $query = "SELECT count(DISTINCT(`t`.`id`)) 
            FROM `".$this->getTableName()."` AS `p`
            INNER JOIN `".FORUM_BOL_TopicDao::getInstance()->getTableName()."` AS `t` ON (`t`.`id`=`p`.`topicId`)
            INNER JOIN `".FORUM_BOL_GroupDao::getInstance()->getTableName()."` AS `g` ON (`g`.`id`=`t`.`groupId`)
            INNER JOIN `".FORUM_BOL_SectionDao::getInstance()->getTableName()."` AS `s` ON (`s`.`id`=`g`.`sectionId`)
                " . $this->getUserTokenJoinString($userToken) . "
                WHERE (MATCH (`t`.`title`) AGAINST(:token".$booleanMode.") OR MATCH (`p`.`text`) AGAINST(:token".$booleanMode."))
            ".$excludeCond." AND `s`.`isHidden` = 0 AND `t`.`status` = :status";
            $params = array('token' => $token, 'status' => FORUM_BOL_ForumService::STATUS_APPROVED);
        }

        return (int) $this->dbo->queryForColumn($query, $params);
    }

    public function searchInSection( $token, $userToken, $sectionId, $page, $limit, $excludeGroupIdList = null, $sortBy = null )
    {
        $excludeCond = $excludeGroupIdList ? ' AND `g`.`id` NOT IN ('.implode(',', $excludeGroupIdList).') = 1' : '';
        $sortCond = $sortBy == 'date' ? ' `p`.`createStamp` DESC, ' : '';

        $limit = (int) $limit;
        $first = ( $page - 1 ) * $limit;

        if ( !mb_strlen($token) ) // search by name only
        {
            $query = "SELECT `t`.*, `g`.`sectionId`, `s`.`name` AS `sectionName`, `g`.`name` AS `groupName`
                FROM `".$this->getTableName()."` AS `p`
                INNER JOIN `".FORUM_BOL_TopicDao::getInstance()->getTableName()."` AS `t` ON (`t`.`id`=`p`.`topicId`)
                INNER JOIN `".FORUM_BOL_GroupDao::getInstance()->getTableName()."` AS `g` ON (`g`.`id`=`t`.`groupId`)
                INNER JOIN `".FORUM_BOL_SectionDao::getInstance()->getTableName()."` AS `s` ON (`s`.`id`=`g`.`sectionId`)
                " . $this->getUserTokenJoinString($userToken) . "
                WHERE 1 ".$excludeCond." AND `s`.`id` = :sectionId
                GROUP BY `t`.`id`
                ORDER BY `p`.`createStamp` DESC
                LIMIT :first, :limit";

            $params = array('first' => $first, 'limit' => $limit, 'sectionId' => $sectionId);
        }
        else
        {
            $multiple = $this->countTokenWords($token) > 1;
            $booleanMode = $multiple ? ' IN BOOLEAN MODE' : '';
            $token = $booleanMode ? '"'.$token.'"' : $token;

            $query = "SELECT `t`.*, `g`.`sectionId`, `s`.`name` AS `sectionName`, `g`.`name` AS `groupName`,
                MATCH (`t`.`title`) AGAINST(:token".$booleanMode."), MATCH (`p`.`text`) AGAINST(:token".$booleanMode.")
                FROM `".$this->getTableName()."` AS `p`
                INNER JOIN `".FORUM_BOL_TopicDao::getInstance()->getTableName()."` AS `t` ON (`t`.`id`=`p`.`topicId`)
                INNER JOIN `".FORUM_BOL_GroupDao::getInstance()->getTableName()."` AS `g` ON (`g`.`id`=`t`.`groupId`)
                INNER JOIN `".FORUM_BOL_SectionDao::getInstance()->getTableName()."` AS `s` ON (`s`.`id`=`g`.`sectionId`)
                " . $this->getUserTokenJoinString($userToken) . "
                WHERE (MATCH (`t`.`title`) AGAINST(:token".$booleanMode.") OR MATCH (`p`.`text`) AGAINST(:token".$booleanMode."))
                ".$excludeCond." AND `s`.`id` = :sectionId
                GROUP BY `t`.`id`
                ORDER BY ".$sortCond." MATCH (`t`.`title`) AGAINST(:token".$booleanMode.") DESC, MATCH (`p`.`text`) AGAINST(:token".$booleanMode.") DESC
                LIMIT :first, :limit";

            $params = array('token' => $token, 'first' => $first, 'limit' => $limit, 'sectionId' => $sectionId);
        }

        return $this->dbo->queryForList($query, $params);
    }

    public function countFoundTopicsInSection( $token, $userToken, $sectionId, $excludeGroupIdList = null )
    {
        $excludeCond = $excludeGroupIdList ? ' AND `g`.`id` NOT IN ('.implode(',', $excludeGroupIdList).') = 1' : '';

        if ( !mb_strlen($token) ) // search by name only
        {
            $query = "SELECT count(DISTINCT(`t`.`id`))
                FROM `".$this->getTableName()."` AS `p`
                INNER JOIN `".FORUM_BOL_TopicDao::getInstance()->getTableName()."` AS `t` ON (`t`.`id`=`p`.`topicId`)
                INNER JOIN `".FORUM_BOL_GroupDao::getInstance()->getTableName()."` AS `g` ON (`g`.`id`=`t`.`groupId`)
                INNER JOIN `".FORUM_BOL_SectionDao::getInstance()->getTableName()."` AS `s` ON (`s`.`id`=`g`.`sectionId`)
                " . $this->getUserTokenJoinString($userToken) . "
                WHERE 1 ".$excludeCond." AND `s`.`id` = :sectionId";
        
            $params = array('sectionId' => $sectionId);
        }
        else
        {
            $multiple = $this->countTokenWords($token) > 1;
            $booleanMode = $multiple ? ' IN BOOLEAN MODE' : '';
            $token = $booleanMode ? '"'.$token.'"' : $token;

            $query = "SELECT count(DISTINCT(`t`.`id`))
                FROM `".$this->getTableName()."` AS `p`
                INNER JOIN `".FORUM_BOL_TopicDao::getInstance()->getTableName()."` AS `t` ON (`t`.`id`=`p`.`topicId`)
                INNER JOIN `".FORUM_BOL_GroupDao::getInstance()->getTableName()."` AS `g` ON (`g`.`id`=`t`.`groupId`)
                INNER JOIN `".FORUM_BOL_SectionDao::getInstance()->getTableName()."` AS `s` ON (`s`.`id`=`g`.`sectionId`)
                " . $this->getUserTokenJoinString($userToken) . "
                WHERE (MATCH (`t`.`title`) AGAINST(:token".$booleanMode.") OR MATCH (`p`.`text`) AGAINST(:token".$booleanMode."))
                ".$excludeCond." AND `s`.`id` = :sectionId";

            $params = array('token' => $token, 'sectionId' => $sectionId);
    }
    
        return (int)$this->dbo->queryForColumn($query, $params);
    }
    
    public function searchInGroup( $token, $userToken, $page, $limit, $groupId, $isHidden = 0, $sortBy = null )
    {
        $hiddenCond = $isHidden ? ' AND `s`.`isHidden` = 1' : ' AND `s`.`isHidden` = 0';
        $sortCond = $sortBy == 'date' ? ' `p`.`createStamp` DESC, ' : '';
        
        $limit = (int) $limit;
        $first = ( $page - 1 ) * $limit;
        
        if ( !mb_strlen($token) ) // search by name only
        {
            $query = "SELECT `t`.*, `g`.`sectionId`, `s`.`name` AS `sectionName`, `g`.`name` AS `groupName`
                FROM `".$this->getTableName()."` AS `p`
                INNER JOIN `".FORUM_BOL_TopicDao::getInstance()->getTableName()."` AS `t` ON (`t`.`id`=`p`.`topicId`)
                INNER JOIN `".FORUM_BOL_GroupDao::getInstance()->getTableName()."` AS `g` ON (`g`.`id`=`t`.`groupId`)
                INNER JOIN `".FORUM_BOL_SectionDao::getInstance()->getTableName()."` AS `s` ON (`s`.`id`=`g`.`sectionId`)
                " . $this->getUserTokenJoinString($userToken) . "
                WHERE `g`.`id` = :groupId AND `t`.`status` = :status " . $hiddenCond."
                GROUP BY `t`.`id`
                ORDER BY `p`.`createStamp` DESC
                LIMIT :first, :limit";

            $params = array('groupId' => $groupId, 'status' => FORUM_BOL_ForumService::STATUS_APPROVED, 'first' => (int)$first, 'limit' => (int)$limit);
        }
        else
        {
            $multiple = $this->countTokenWords($token) > 1;
            $booleanMode = $multiple ? ' IN BOOLEAN MODE' : '';
            $token = $booleanMode ? '"'.$token.'"' : $token;

        $query = "SELECT `t`.*, `g`.`sectionId`, `s`.`name` AS `sectionName`, `g`.`name` AS `groupName`, 
                MATCH (`t`.`title`) AGAINST(:token".$booleanMode."), MATCH (`p`.`text`) AGAINST(:token".$booleanMode.")
            FROM `".$this->getTableName()."` AS `p`
            INNER JOIN `".FORUM_BOL_TopicDao::getInstance()->getTableName()."` AS `t` ON (`t`.`id`=`p`.`topicId`)
            INNER JOIN `".FORUM_BOL_GroupDao::getInstance()->getTableName()."` AS `g` ON (`g`.`id`=`t`.`groupId`)
            INNER JOIN `".FORUM_BOL_SectionDao::getInstance()->getTableName()."` AS `s` ON (`s`.`id`=`g`.`sectionId`)
                " . $this->getUserTokenJoinString($userToken) . "
                WHERE (MATCH (`t`.`title`) AGAINST(:token".$booleanMode.") OR MATCH (`p`.`text`) AGAINST(:token".$booleanMode."))
            AND `g`.`id` = :groupId AND `t`.`status` = :status " . $hiddenCond."
            GROUP BY `t`.`id`
                ORDER BY ".$sortCond." MATCH (`t`.`title`) AGAINST(:token".$booleanMode.") DESC,
                MATCH (`p`.`text`) AGAINST(:token".$booleanMode.") DESC
            LIMIT :first, :limit";
        
        $params = array('token' => $token, 'groupId' => $groupId, 'status' => FORUM_BOL_ForumService::STATUS_APPROVED, 'first' => (int)$first, 'limit' => (int)$limit);
        }
        
        return $this->dbo->queryForList($query, $params);
    }
    
    public function countFoundTopicsInGroup( $token, $userToken, $groupId, $isHidden = 0 )
    {
        $hiddenCond = $isHidden ? ' AND `s`.`isHidden` = 1' : ' AND `s`.`isHidden` = 0';

        if ( !mb_strlen($token) ) // search by name only
        {
        $query = "SELECT count(DISTINCT(`t`.`id`)) 
            FROM `".$this->getTableName()."` AS `p`
            INNER JOIN `".FORUM_BOL_TopicDao::getInstance()->getTableName()."` AS `t` ON (`t`.`id`=`p`.`topicId`)
            INNER JOIN `".FORUM_BOL_GroupDao::getInstance()->getTableName()."` AS `g` ON (`g`.`id`=`t`.`groupId`)
            INNER JOIN `".FORUM_BOL_SectionDao::getInstance()->getTableName()."` AS `s` ON (`s`.`id`=`g`.`sectionId`)
                " . $this->getUserTokenJoinString($userToken) . "
                WHERE `g`.`id` = :groupId AND `t`.`status` = :status " . $hiddenCond;

            $params = array('groupId' => $groupId, 'status' => FORUM_BOL_ForumService::STATUS_APPROVED);
        }
        else
        {
            $multiple = $this->countTokenWords($token) > 1;
            $booleanMode = $multiple ? ' IN BOOLEAN MODE' : '';
            $token = $booleanMode ? '"'.$token.'"' : $token;

            $query = "SELECT count(DISTINCT(`t`.`id`))
                FROM `".$this->getTableName()."` AS `p`
                INNER JOIN `".FORUM_BOL_TopicDao::getInstance()->getTableName()."` AS `t` ON (`t`.`id`=`p`.`topicId`)
                INNER JOIN `".FORUM_BOL_GroupDao::getInstance()->getTableName()."` AS `g` ON (`g`.`id`=`t`.`groupId`)
                INNER JOIN `".FORUM_BOL_SectionDao::getInstance()->getTableName()."` AS `s` ON (`s`.`id`=`g`.`sectionId`)
                " . $this->getUserTokenJoinString($userToken) . "
                WHERE (MATCH (`t`.`title`) AGAINST(:token".$booleanMode.") OR MATCH (`p`.`text`) AGAINST(:token".$booleanMode."))
            AND `g`.`id` = :groupId AND `t`.`status` = :status " . $hiddenCond;
        
        $params = array('token' => $token, 'groupId' => $groupId, 'status' => FORUM_BOL_ForumService::STATUS_APPROVED);
        }
        
        return (int)$this->dbo->queryForColumn($query, $params);
    }
    
    public function searchInTopic( $token, $userToken, $topicId, $sortBy = null )
    {
        $sortCond = $sortBy == 'date' ? ' `createStamp` DESC, ' : '';
        
        if ( !mb_strlen($token) ) // search by name only
        {
            $query = "SELECT `p`.*
                FROM `".$this->getTableName()."` AS `p`
                INNER JOIN `".FORUM_BOL_TopicDao::getInstance()->getTableName()."` AS `t` ON(`p`.`topicId` = `t`.`id`)
                " . $this->getUserTokenJoinString($userToken) . "
                WHERE `p`.`topicId` = :topicId AND `t`.`status` = :status
                ORDER BY `createStamp` DESC";

            $params = array('topicId' => $topicId, 'status' => FORUM_BOL_ForumService::STATUS_APPROVED);
        }
        else
        {
            $multiple = $this->countTokenWords($token) > 1;
            $booleanMode = $multiple ? ' IN BOOLEAN MODE' : '';
            $token = $booleanMode ? '"'.$token.'"' : $token;

            $query = "SELECT `p`.*, MATCH (`p`.`text`) AGAINST(:token".$booleanMode.")
                FROM `".$this->getTableName()."` AS `p`
                INNER JOIN `".FORUM_BOL_TopicDao::getInstance()->getTableName()."` AS `t` ON(`p`.`topicId` = `t`.`id`)
                " . $this->getUserTokenJoinString($userToken) . "
                WHERE MATCH (`p`.`text`) AGAINST(:token".$booleanMode.") AND `p`.`topicId` = :topicId AND `t`.`status` = :status
                ORDER BY ".$sortCond." MATCH (`p`.`text`) AGAINST(:token".$booleanMode.") DESC";
        
        $params = array('token' => $token, 'topicId' => $topicId, 'status' => FORUM_BOL_ForumService::STATUS_APPROVED);
        }
        
        return $this->dbo->queryForObjectList($query, 'FORUM_BOL_Post', $params);
    }

    private function countTokenWords( $token )
    {
        $str = preg_replace("/ +/", " ", $token);
        $array = explode(" ", $str);

        return count($array);
    }
}