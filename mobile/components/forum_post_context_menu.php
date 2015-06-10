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
 * Forum post context menu class.
 *
 * @author Alex Ermashev <alexermashev@gmail.com>
 * @package ow.ow_plugins.forum.mobile.components
 * @since 1.0
 */
class FORUM_MCMP_ForumPostContextMenu extends OW_MobileComponent
{
    /**
     * Post id
     * @var integer
     */
    protected $postId;

    /**
     * Class constructor
     * 
     * @param array $params
     *      integer topicId
     *      integer postId
     */
    public function __construct( array $params = array() )
    {
        parent::__construct();

        $this->topicId = !empty($params['topicId']) ? $params['topicId'] : -1;
        $this->postId = !empty($params['postId']) ? $params['postId'] : -1;
    }

    /**
     * Render component
     * 
     * @return type
     */
    public function render()
    {
        $items[] = array(
            'group' => 'forum',
            'label' => OW::getLanguage()->text('forum', 'edit'),
            'order' => 1,
            'class' => null,
            'href' => null,
            'id' => null,
            'attributes' => array(
                'class' => 'forum_edit_post',
                'data-id' => $this->postId,
            )
        );

        $items[] = array(
            'group' => 'forum',
            'label' => OW::getLanguage()->text('forum', 'delete'),
            'order' => 1,
            'class' => null,
            'href' => OW::getRouter()->urlForRoute('delete-post', array('topicId' => $this->topicId, 'postId' => $this->postId)),
            'id' => null,
            'attributes' => array(
                'class' => 'forum_delete_post',
            )
        );

        $menu = new BASE_MCMP_ContextAction($items);
        return $menu->render();
    }
}