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
 * @package ow.ow_plugins.forum
 * @since 1.7.2
 */
class FORUM_CLASS_TopicEditForm extends Form
{
    /**
     * Class constructor
     * 
     * @param string $name
     * @param string $uid
     * @param FORUM_BOL_Topic $topicDto
     * @param FORUM_BOL_Post $postDto
     * @param boolean $mobileWysiwyg
     */
    public function __construct( $name, $uid, FORUM_BOL_Topic $topicDto, FORUM_BOL_Post $postDto, $mobileWysiwyg = false ) 
    {
        parent::__construct($name);

        $lang = OW::getLanguage();

        $topicIdField = new HiddenField('topic-id');
        $topicIdField->setValue($topicDto->id);
        $this->addElement($topicIdField);

        $postIdField = new HiddenField('post-id');
        $postIdField->setValue($postDto->id);
        $this->addElement($postIdField);

        $attachmentUid = new HiddenField('attachmentUid');
        $attachmentUid->setValue($uid);
        $this->addElement($attachmentUid);

        // title
        $titleField = new TextField('title');
        $titleField->setValue($topicDto->title);
        $titleField->setRequired(true);
        $sValidator = new StringValidator(FORUM_CLASS_TopicAddForm::MIN_TITLE_LENGTH, FORUM_CLASS_TopicAddForm::MAX_TITLE_LENGTH);
        $sValidator->setErrorMessage($lang->
                text('forum', 'chars_limit_exceeded', array('limit' => FORUM_CLASS_TopicAddForm::MAX_TITLE_LENGTH)));

        $titleField->addValidator($sValidator);
        $this->addElement($titleField);

        // post
        if ( $mobileWysiwyg )
        {
            $textField = new MobileWysiwygTextarea('text');
        }
        else {
            $textField = new WysiwygTextarea('text', array(
                BOL_TextFormatService::WS_BTN_IMAGE, 
                BOL_TextFormatService::WS_BTN_VIDEO, 
                BOL_TextFormatService::WS_BTN_HTML
            ));
        }

        $textField->setValue($postDto->text);
        $textField->setRequired(true);
        $sValidator = new StringValidator(FORUM_CLASS_TopicAddForm::MIN_POST_LENGTH, FORUM_CLASS_TopicAddForm::MAX_POST_LENGTH);
        $sValidator->setErrorMessage($lang->text('forum', 'chars_limit_exceeded', array('limit' => FORUM_CLASS_TopicAddForm::MAX_POST_LENGTH)));
        $textField->addValidator($sValidator);
        $this->addElement($textField);

        $submit = new Submit('save');
        $submit->setValue($lang->text('base', 'edit_button'));
        $this->addElement($submit);
    }
}