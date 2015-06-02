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
 * Forum search form class.
 *
 * @author Alex Ermashev <alexermashev@gmail.com>
 * @package ow.ow_plugins.forum.mobile.components
 * @since 1.0
 */
class FORUM_MCMP_ForumSearch extends OW_MobileComponent
{
    private $scope;
    private $id;

    public function __construct( array $params )
    {
        parent::__construct();

        $this->scope = !empty($params['scope']) ? $params['scope'] : 'all_forum';
        $this->id = !empty($params['id']) ? $params['id'] : null;

        switch ( $this->scope )
        {
            case 'topic':
                $location = OW::getRouter()->
                        urlForRoute('forum_search_topic', array('topicId' => $this->id));
                break;

            case 'group':
                $location = OW::getRouter()->
                        urlForRoute('forum_search_group', array('groupId' => $this->id));
                break;

            case 'section':
                $location = OW::getRouter()->
                        urlForRoute('forum_search_section', array('sectionId' => $this->id));
                break;

            default:
                $location = OW::getRouter()->urlForRoute('forum_search');
                break;
        }

        $invitation = OW::getLanguage()->text('forum', 'search_invitation_' . $this->scope);

        // add form       
        $this->addForm(new FORUM_MCLASS_SearchForm("search_form", $invitation, $location));

        // assign view variables
        $this->assign('invitation', $invitation);
        $this->assign('location', $location);
    }
}