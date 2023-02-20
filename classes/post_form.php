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
class FORUM_CLASS_PostForm extends Form
{
    const PLUGIN_KEY = FORUM_BOL_ForumService::PLUGIN_KEY;

    /**
     * Min text length
     */
    const MIN_TEXT_LENGTH = 1;

    /**
     * Max text length
     */
    const MAX_TEXT_LENGTH = 65535;

    /**
     * Text invitation
     * @var string
     */
    protected $textInvitation;

    /**
     * Class constructor
     * 
     * @param string $name
     * @param string $attachmentUid
     * @param integer $topicId
     * @param boolean $mobileWysiwyg
     */
    public function __construct( $name, $attachmentUid, $topicId, $mobileWysiwyg = false ) 
    {

        parent::__construct($name, self::PLUGIN_KEY);
        $lang = OW::getLanguage();

        $topicIdField = new HiddenField('topic', self::PLUGIN_KEY);
        $topicIdField->setValue($topicId);
        $this->addElement($topicIdField);

        // attachments
        $attachmentUidField = new HiddenField('attachmentUid', self::PLUGIN_KEY);
        $attachmentUidField->setValue($attachmentUid);
        $this->addElement($attachmentUidField);

        // text
        if ( $mobileWysiwyg )
        {
            $textField = new MobileWysiwygTextarea('text', self::PLUGIN_KEY);
        }
        else {
            $textField = new WysiwygTextarea('text', self::PLUGIN_KEY, array(
                BOL_TextFormatService::WS_BTN_IMAGE, 
                BOL_TextFormatService::WS_BTN_VIDEO, 
                BOL_TextFormatService::WS_BTN_HTML
            ));
        }

        $textField->setRequired(true);
        $sValidator = new StringValidator(self::MIN_TEXT_LENGTH, self::MAX_TEXT_LENGTH);
        $sValidator->setErrorMessage($lang->text('forum', 'chars_limit_exceeded', array('limit' => self::MAX_TEXT_LENGTH)));
        $textField->addValidator($sValidator);
        $this->addElement($textField);

        // submit
        $submit = new Submit('submit', self::PLUGIN_KEY);
        $submit->setValue($lang->text('forum', 'add_post_btn'));
        $this->addElement($submit);
    }
}
