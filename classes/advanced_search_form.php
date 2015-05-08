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
class FORUM_CLASS_AdvancedSearchForm extends Form
{
    /**
     * Class constructor
     * 
     * @param string $name
     * @param array $sections
     */
    public function __construct( $name, array $sections = array() ) 
    {
        parent::__construct($name);

        $this->setMethod(self::METHOD_GET);
        $this->setAction(OW::getRouter()->urlForRoute('forum_advanced_search_result'));

        // keyword
        $keywordField = new TextField('keyword');
        $keywordField->setHasInvitation(true);
        $keywordField->setInvitation(OW::getLanguage()->text('forum', 'forms_search_keyword_field_invitation'));
        $this->addElement($keywordField);

        // username
        $userNameField = new TextField('username');
        $userNameField->setLabel(OW::getLanguage()->text('forum', 'forms_search_username_field_label'));
        $userNameField->setHasInvitation(true);
        $userNameField->setInvitation(OW::getLanguage()->text('forum', 'forms_search_username_field_invitation'));
        $this->addElement($userNameField);

        // parts
        $partsField = new SelectBox('parts[]');
        $partsField->setLabel(OW::getLanguage()->text('forum', 'forms_search_parts_field_label'));
        $partsField->addAttribute('multiple',  'multiple');
        $partsField->addAttribute('class',  'ow_multiselect');
        $partsField->setHasInvitation(false);
        $partsValues = array(
            '' => OW::getLanguage()->text('forum', 'forms_search_parts_field_value_all_forums')
        );

        // process parts values
        foreach( $sections as $section )
        {
            $partsValues['section_' . $section['sectionId']] = $section['sectionName'];

            if ( !empty($section['groups']) )
            {
                foreach( $section['groups'] as $group )
                {
                    $partsValues['group_' . $group['id']] = '&nbsp | --' . $group['name'];
                }
            }
        }

        $partsField->setOptions($partsValues);
        $partsField->setValue('');
        $this->addElement($partsField);

        // search in
        $searchInField = new RadioField('search_in');
        $searchInField->setLabel(OW::getLanguage()->text('forum', 'forms_search_search_in_field_label'));
        $searchInField->addOptions(array(
           'message' => OW::getLanguage()->text('forum', 'forms_search_search_in_field_value_message'),
           'title' => OW::getLanguage()->text('forum', 'forms_search_search_in_field_value_title'),
        ));
        $searchInField->setValue('message');
        $this->addElement($searchInField);

        // period
        $periodField = new SelectBox('period');
        $periodField->setLabel(OW::getLanguage()->text('forum', 'forms_search_period_field_label'));
        $periodField->addOptions(array(
           'today' => OW::getLanguage()->text('forum', 'forms_search_search_period_value_today'),
           'last_week' => OW::getLanguage()->text('forum', 'forms_search_search_period_value_last_week'),
           'last_month' => OW::getLanguage()->text('forum', 'forms_search_search_period_value_last_month'),
           'last_two_months' => OW::getLanguage()->text('forum', 'forms_search_search_period_value_last_two_months'),
           'last_three_months' => OW::getLanguage()->text('forum', 'forms_search_search_period_value_last_three_months'),
           'last_six_months' => OW::getLanguage()->text('forum', 'forms_search_search_period_value_last_six_months'),
           'last_year' => OW::getLanguage()->text('forum', 'forms_search_search_period_value_last_year')
        ));
        $this->addElement($periodField);

        // sort
        $sortField = new SelectBox('sort');
        $sortField->setLabel(OW::getLanguage()->text('forum', 'forms_search_sort_field_label'));
        $sortField->addOptions(array(
           'date' => OW::getLanguage()->text('forum', 'forms_search_sort_value_date'),
           'relevance' => OW::getLanguage()->text('forum', 'forms_search_sort_value_relevance'),
        ));
        $sortField->setValue('date');
        $this->addElement($sortField);

        // sort direction
        $sortDirectionField = new RadioField('sort_direction');
        $sortDirectionField->setLabel(OW::getLanguage()->text('forum', 'forms_search_sort_direction_field_label'));
        $sortDirectionField->addOptions(array(
           'increase' => OW::getLanguage()->text('forum', 'forms_search_sort_direction_field_value_increase'),
           'decrease' => OW::getLanguage()->text('forum', 'forms_search_sort_direction_field_value_decrease'),
        ));
        $sortDirectionField->setValue('decrease');
        $this->addElement($sortDirectionField);

        // submit
        $submit = new Submit('submit');
        $submit->setLabel(OW::getLanguage()->text('forum', 'forms_search_submit_field_label'));
        $this->addElement($submit);
    }
}