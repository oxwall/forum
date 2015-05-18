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
 * Forum topic context menu class.
 *
 * @author Alex Ermashev <alexermashev@gmail.com>
 * @package ow.ow_plugins.forum.mobile.components
 * @since 1.0
 */
class FORUM_MCMP_ForumTopicContextMenu extends OW_MobileComponent
{
    /**
     * Topic info
     * @var array
     */
    protected $topicInfo;

    /**
     * Can edit
     * @var boolean
     */
    protected $canEdit;

    /**
     * Can lock
     * @var boolean
     */
    protected $canLock;

    /**
     * Can sticky
     * @var boolean
     */
    protected $canSticky;

    /**
     * Can subscribe
     * @var boolean
     */
    protected $canSubscribe;

    /**
     * Is subscribed
     * @var boolean
     */
    protected $isSubscribed;

    /**
     * Is owner
     * @var boolean
     */
    protected $isOwner;

    /**
     * Is moderator
     * @var boolean
     */
    protected $isModerator;

    /**
     * Class constructor
     */
    public function __construct( array $params = array() )
    {
        parent::__construct();

        $this->topicInfo = !empty($params['topicInfo']) 
            ? $params['topicInfo'] 
            : array();

        $this->canEdit = !empty($params['canEdit'])     
            ? (bool) $params['canEdit'] 
            : false;

        $this->canLock = !empty($params['canLock']) 
            ? (bool) $params['canLock'] 
            : false;

        $this->canSticky = !empty($params['canSticky']) 
            ? (bool) $params['canSticky'] 
            : false;

        $this->canSubscribe = !empty($params['canSubscribe']) 
            ? (bool) $params['canSubscribe'] 
            : false;

        $this->isSubscribed = !empty($params['isSubscribed']) 
            ? (bool) $params['isSubscribed'] 
            : false;

        $this->isOwner = !empty($params['isOwner']) 
            ? (bool) $params['isOwner'] 
            : false;

        $this->isModerator = !empty($params['isModerator']) 
            ? (bool) $params['isModerator'] 
            : false;
    }

    /**
     * Get multi actions label
     * 
     * @param array $actions
     *      string id
     *      string label
     *      boolean hidden
     * @return string
     */
    protected function getMultiActionsLabel(array $actions)
    {
        $actionLabel = null;

        foreach($actions as $action)
        {
            $hidden = $action['hidden']
                ? 'style="display:none" ' 
                : null;

            $actionLabel .= '<span ' . $hidden . 'id="' . $action['id'] . '">'   . $action['label']. '</span>';
        }

        return $actionLabel;
    }

    /**
     * Render component
     * 
     * @return type
     */
    public function render()
    {
        $items = array();

        if ( $this->canEdit )
        {
            // edit action
            $items[] = array(
                "group" => 'forum',
                'label' => OW::getLanguage()->text('forum', 'new_topic_btn'),
                'order' => 1,
                'class' => null,
                'url' => null,
                'id' => 'forum_new_topic',
                'attributes' => array()
            );
        }

        if ( $this->canLock )
        {
            // lock/unlock action
            $items[] = array(
                "group" => 'forum',
                'label' => !empty($this->topicInfo['locked'])
                    ? OW::getLanguage()->text('forum', 'unlock_topic')
                    : OW::getLanguage()->text('forum', 'lock_topic'),
                'order' => 2,
                'class' => null,
                'url' => null,
                'id' => !empty($this->topicInfo['locked']) 
                    ? 'unlock_topic' 
                    : 'lock_topic'
            );
        }

        if ( $this->canSticky )
        {
            $label = $this->getMultiActionsLabel(array(
                array(
                    'id' => 'sticky_topic',
                    'label' => OW::getLanguage()->text('forum', 'sticky_topic'),
                    'hidden' => !empty($this->topicInfo['sticky'])
                ),
                array(
                    'id' => 'unsticky_topic',
                    'label' => OW::getLanguage()->text('forum', 'unsticky_topic'),
                    'hidden' => empty($this->topicInfo['sticky'])
                )
            ));

            // sticky/unsticky action
            $items[] = array(
                "group" => 'forum',
                'label' => $label,
                'order' => 3,
                'class' => null,
                'url' => null,
                'id' => null
            );
        }

        if ( $this->canSubscribe )
        {
            $label = $this->getMultiActionsLabel(array(
                array(
                    'id' => 'subscribe_topic',
                    'label' => OW::getLanguage()->text('forum', 'subscribe'),
                    'hidden' => $this->isSubscribed
                ),
                array(
                    'id' => 'unsubscribe_topic',
                    'label' => OW::getLanguage()->text('forum', 'unsubscribe'),
                    'hidden' => !$this->isSubscribed
                )
            ));

            // subscribe/unsubscribe
            $items[] = array(
                "group" => 'forum',
                'label' => $label,
                'order' => 4,
                'class' => null,
                'url' => null,
                'id' => null
            );
        }

        if ( $this->canEdit && ($this->isOwner || $this->isModerator) )
        {
            // edit action
            $items[] = array(
                "group" => 'forum',
                'label' => OW::getLanguage()->text('forum', 'edit'),
                'order' => 5,
                'class' => null,
                'url' => null,
                'id' => 'forum_edit_topic',
                'attributes' => array()
            );

            // delete action
            $items[] = array(
                "group" => 'forum',
                'label' => OW::getLanguage()->text('forum', 'delete'),
                'order' => 6,
                'class' => null,
                'url' => null,
                'id' => 'forum_delete_topic',
                'attributes' => array()
            );
        }

        $menu = new BASE_MCMP_ContextAction($items);
        return $menu->render();
    }
}