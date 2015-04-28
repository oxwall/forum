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
$plugin = OW::getPluginManager()->getPlugin('forum');

OW::getAutoloader()->addClass('ForumSelectBox', $plugin->getRootDir() . 'classes' . DS . 'forum_select_box.php');
OW::getAutoloader()->addClass('ForumStringValidator', $plugin->getRootDir() . 'classes' . DS . 'forum_string_validator.php');

OW::getRouter()->addRoute(new OW_Route('forum-default', 'forum', 'FORUM_CTRL_Index', 'index'));
OW::getRouter()->addRoute(new OW_Route('customize-default', 'forum/customize', 'FORUM_CTRL_Customize', 'index'));
OW::getRouter()->addRoute(new OW_Route('section-default', 'forum/section/:sectionId', 'FORUM_CTRL_Section', 'index'));
OW::getRouter()->addRoute(new OW_Route('group-default', 'forum/:groupId', 'FORUM_CTRL_Group', 'index'));
OW::getRouter()->addRoute(new OW_Route('topic-default', 'forum/topic/:topicId', 'FORUM_CTRL_Topic', 'index'));

OW::getRouter()->addRoute(new OW_Route('add-topic-default', 'forum/addTopic', 'FORUM_CTRL_AddTopic', 'index'));
OW::getRouter()->addRoute(new OW_Route('add-topic', 'forum/addTopic/:groupId', 'FORUM_CTRL_AddTopic', 'index'));

OW::getRouter()->addRoute(new OW_Route('sticky-topic', 'forum/stickyTopic/:topicId/:page', 'FORUM_CTRL_Topic', 'stickyTopic'));
OW::getRouter()->addRoute(new OW_Route('lock-topic', 'forum/lockTopic/:topicId/:page', 'FORUM_CTRL_Topic', 'lockTopic'));
OW::getRouter()->addRoute(new OW_Route('delete-topic', 'forum/deleteTopic/:topicId', 'FORUM_CTRL_Topic', 'deleteTopic'));
OW::getRouter()->addRoute(new OW_Route('get-post', 'forum/getPost/:postId', 'FORUM_CTRL_Topic', 'getPost'));
OW::getRouter()->addRoute(new OW_Route('edit-post', 'forum/edit-post/:id', 'FORUM_CTRL_EditPost', 'index'));
OW::getRouter()->addRoute(new OW_Route('edit-topic', 'forum/edit-topic/:id', 'FORUM_CTRL_EditTopic', 'index'));
OW::getRouter()->addRoute(new OW_Route('move-topic', 'forum/moveTopic', 'FORUM_CTRL_Topic', 'moveTopic'));
OW::getRouter()->addRoute(new OW_Route('subscribe-topic', 'forum/subscribe-topic/:id', 'FORUM_CTRL_Topic', 'subscribeTopic'));
OW::getRouter()->addRoute(new OW_Route('unsubscribe-topic', 'forum/unsubscribe-topic/:id', 'FORUM_CTRL_Topic', 'unsubscribeTopic'));

OW::getRouter()->addRoute(new OW_Route('add-post', 'forum/addPost/:topicId/:uid', 'FORUM_CTRL_Topic', 'addPost'));
OW::getRouter()->addRoute(new OW_Route('delete-post', 'forum/deletePost/:topicId/:postId', 'FORUM_CTRL_Topic', 'deletePost'));
OW::getRouter()->addRoute(new OW_Route('forum_delete_attachment', 'forum/deleteAttachment/', 'FORUM_CTRL_Topic', 'ajaxDeleteAttachment'));
OW::getRouter()->addRoute(new OW_Route('forum_admin_config', 'admin/plugins/forum', 'FORUM_CTRL_Admin', 'index'));
OW::getRouter()->addRoute(new OW_Route('forum_uninstall', 'admin/forum/uninstall', 'FORUM_CTRL_Admin', 'uninstall'));
OW::getRouter()->addRoute(new OW_Route('forum_advanced_search', 'forum/advanced-search/', 'FORUM_CTRL_Search', 'advanced'));
OW::getRouter()->addRoute(new OW_Route('forum_advanced_search_result', 'forum/advanced-search/result', 'FORUM_CTRL_Search', 'advancedResult'));
OW::getRouter()->addRoute(new OW_Route('forum_search', 'forum/search/', 'FORUM_CTRL_Search', 'inForums'));
OW::getRouter()->addRoute(new OW_Route('forum_search_group', 'forum/:groupId/search/', 'FORUM_CTRL_Search', 'inGroup'));
OW::getRouter()->addRoute(new OW_Route('forum_search_section', 'forum/section/:sectionId/search/', 'FORUM_CTRL_Search', 'inSection'));
OW::getRouter()->addRoute(new OW_Route('forum_search_topic', 'forum/topic/:topicId/search/', 'FORUM_CTRL_Search', 'inTopic'));
OW::getRouter()->addRoute(new OW_Route('forum_approve_topic', 'forum/approve/:id', 'FORUM_CTRL_Topic', 'approve'));

FORUM_CLASS_EventHandler::getInstance()->init();
FORUM_CLASS_ContentProvider::getInstance()->init();