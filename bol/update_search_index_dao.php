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
 * Data Access Object for `forum_update_search_index` table
 *
 * @author Alex Ermashev <alexermashev@gmail.com>
 * @package ow.ow_plugins.forum.bol
 * @since 1.0
 */
class FORUM_BOL_UpdateSearchIndexDao extends OW_BaseDao
{
    /**
     * High priority
     */
    const HIGH_PRIORITY = 1;

    /**
     * Normal priority
     */
    const NORMAL_PRIORITY = 0;

    /**
     * Delete topic
     */
    const DELETE_TOPIC = 'delete_topic';

    /**
     * Update topic
     */
    const UPDATE_TOPIC = 'update_topic';

    /**
     * Update topic posts
     */
    const UPDATE_TOPIC_POSTS = 'update_topic_posts';

    /**
     * Delete group
     */
    const DELETE_GROUP = 'delete_group';

    /**
     * Update group
     */
    const UPDATE_GROUP = 'update_group';

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
     * @var FORUM_BOL_UpdateSearchIndexDao
     */
    private static $classInstance;

    /**
     * Returns class instance
     *
     * @return FORUM_BOL_UpdateSearchIndexDao
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
        return 'FORUM_BOL_UpdateSearchIndex';
    }

    /**
     * @see OW_BaseDao::getTableName()
     *
     */
    public function getTableName()
    {
        return OW_DB_PREFIX . 'forum_update_search_index';
    }

    /**
     * Find first queue
     * 
     * @return FORUM_BOL_UpdateSearchIndex
     */
    public function findFirstQueue()
    {
        $example = new OW_Example();
        $example->setOrder('`priority` DESC, `id` ASC');

        return $this->findObjectByExample($example);
    }

    /**
     * Add a queue
     * 
     * @param integer $entityId
     * @param string $type
     * @param integer $priority
     * @return void
     */
    public function addQueue($entityId, $type, $priority = self::NORMAL_PRIORITY)
    {
        $updateSearchIndexDto = new FORUM_BOL_UpdateSearchIndex();
        $updateSearchIndexDto->entityId = $entityId;
        $updateSearchIndexDto->type     = $type;
        $updateSearchIndexDto->priority = $priority;

        $this->save($updateSearchIndexDto);
    }
}