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
class FORUM_CLASS_TopicAddForm extends Form
{
    /**
     * Min title length
     */
    const MIN_TITLE_LENGTH = 1;

    /**
     * Max title length
     */
    const MAX_TITLE_LENGTH = 255;

    /**
     * Min post length
     */
    const MIN_POST_LENGTH = 1;

    /**
     * Max post length
     */
    const MAX_POST_LENGTH = 65535;

    /**
     * Title invitation
     * @var string
     */
    protected $titleInvitation;

    /**
     * Class constructor
     * 
     * @param string $name
     * @param string $attachmentUid
     * @param array $groupSelect
     * @param integer $groupId
     * @param boolean $mobileWysiwyg
     * @param boolean $isSectionHidden
     */
    public function __construct($name, $attachmentUid, 
            array $groupSelect, $groupId = null, $mobileWysiwyg = false, $isSectionHidden  = false) 
    {

        parent::__construct($name);
        $lang = OW::getLanguage();

        // attachments
        $attachmentUidField = new HiddenField('attachmentUid');
        $attachmentUidField->setValue($attachmentUid);
        $this->addElement($attachmentUidField);

        // title
        $titleField = new TextField('title');
        $titleField->setRequired(true);
        $sValidator = new StringValidator(self::MIN_TITLE_LENGTH, self::MAX_TITLE_LENGTH);
        $sValidator->setErrorMessage($lang->
                text('forum', 'chars_limit_exceeded', array('limit' => self::MAX_TITLE_LENGTH)));

        $titleField->addValidator($sValidator);
        $this->addElement($titleField);

        // group
        if ( $isSectionHidden )
        {
            $groupField = new HiddenField('group');
            $groupField->setValue($groupId);
        }
        else
        {
            $groupField = new ForumSelectBox('group');
            $groupField->setOptions($groupSelect);

            if ( $groupId )
            {
                $groupField->setValue($groupId);
            }

            // process list of groups for the validator
            $groupIds = array();

            foreach($groupSelect as $group)
            {
                if ( !$group['value'] || $group['disabled'] )
                {
                    continue;
                }

                $groupIds[] = $group['value'];
            }

            $groupField->setRequired(true);
            $groupField->addValidator(new IntValidator());
            $groupField->addValidator(new InArrayValidator($groupIds));
        }

        $this->addElement($groupField);

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

        $textField->setRequired(true);
        $sValidator = new StringValidator(self::MIN_POST_LENGTH, self::MAX_POST_LENGTH);
        $sValidator->setErrorMessage($lang->text('forum', 'chars_limit_exceeded', array('limit' => self::MAX_POST_LENGTH)));
        $textField->addValidator($sValidator);
        $this->addElement($textField);

        // subscribe
        $subscribeField = new CheckboxField('subscribe');
        $subscribeField->setLabel($lang->text('forum', 'subscribe'));
        $subscribeField->setValue(true);
        $this->addElement($subscribeField);

        // submit
        $submit = new Submit('post');
        $submit->setValue($lang->text('forum', 'add_post_btn'));
        $this->addElement($submit);
    }

    /**
     * Set title invitation
     * 
     * @param string $invitation
     * @return void
     */
    public function setTitleInvitation($invitation)
    {
        $this->getElement('title')->setHasInvitation(true)->setInvitation($invitation);
    }
}