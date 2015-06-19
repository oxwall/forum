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
     * @param int $lastPostId
     * @return array of FORUM_BOL_Post
     */
    public function findTopicPostList( $topicId, $first, $count, $lastPostId = null )
    {
        $example = new OW_Example();
        $example->andFieldEqual('topicId', $topicId);

        if ( $lastPostId )
        {
            $example->andFieldGreaterThan('id', $lastPostId);
        }

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

    /**
     * Returns users post count list by ids
     * 
     * @param array $userIds
     * @return array 
     */
    public function findPostCountListByUserIds( array $userIds )
    {
        if ( !$userIds )
        {
            return array();
        }

        $userIds = $this->dbo->mergeInClause($userIds);

        $query = "
		SELECT `userId` , COUNT( * ) AS `postsCount`
		FROM `" . $this->getTableName() . "`
		WHERE `userId` IN (" . $userIds .") GROUP BY `userId`";

        $values =  $this->dbo->queryForList($query);
        $processedValues = array();

        foreach($values as $value) {
            $processedValues[$value['userId']] = $value['postsCount'];
        }

        return $processedValues;
    }

    /**
     * Returns post list by ids
     * 
     * @param array $postIds
     * @return array 
     */
    public function findListByPostIds( array $postIds )
    {
        if ( !$postIds )
        {
            return array();
        }

        $postsIn = $this->dbo->mergeInClause($postIds);

        $query = "
		SELECT  *
		FROM `" . $this->getTableName() . "`
		WHERE id IN (" . $postsIn .") ORDER BY FIELD(id, " . $postsIn . ")";

        return $this->dbo->queryForList($query);
    }

    private function countTokenWords( $token )
    {
        $str = preg_replace("/ +/", " ", $token);
        $array = explode(" ", $str);

        return count($array);
    }
}