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
 * Forum section class.
 *
 * @author Alex Ermashev <alexermashev@gmail.com>
 * @package ow.ow_plugins.forum.mobile.components
 * @since 1.0
 */
class FORUM_MCMP_ForumSection extends OW_MobileComponent
{
    /**
     * Class constructor
     * 
     * @param array $params
     */
    public function __construct(array $params = array())
    {
        parent::__construct();

        $sectionId = !empty($params['sectionId']) 
            ? (int) $params['sectionId'] 
            : null;

        $forumService = FORUM_BOL_ForumService::getInstance();
        $userId = OW::getUser()->getId();

        $sectionGroupList = $forumService->getSectionGroupList($userId, $sectionId);
        $authors = $forumService->getSectionGroupAuthorList($sectionGroupList);

        // assign view variables
        $this->assign('singleMode', null != $sectionId);
        $this->assign('sectionGroupList', $sectionGroupList);
        $this->assign('displayNames', BOL_UserService::getInstance()->getDisplayNamesForList($authors));
    }
}