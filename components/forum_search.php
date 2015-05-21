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
 * @author Egor Bulgakov <egor.bulgakov@gmail.com>
 * @package ow.ow_plugins.forum.components
 * @since 1.0
 */
class FORUM_CMP_ForumSearch extends OW_Component
{
    private $scope;
    
    public function __construct( array $params )
    {
        parent::__construct();
        
        $this->scope = $params['scope'];
        
        $value = isset($params['token']) ? trim(htmlspecialchars($params['token'])) : null;
        $userValue = isset($params['userToken']) ? trim(htmlspecialchars($params['userToken'])) : null;
        $invitation = $this->getInvitationLabel();
        
        $inputParams = array(
            'type' => 'text',
            'class' => !mb_strlen($value) ? 'invitation' : '',
            'value' => mb_strlen($value) ? $value : $invitation,
            'id' => UTIL_HtmlTag::generateAutoId('input')
        );
        $this->assign('input', UTIL_HtmlTag::generateTag('input', $inputParams));
        
        $userInputParams = array(
            'type' => 'text',
            'value' => $userValue,
            'id' => $inputParams['id'] . '_user'
        );
        $this->assign('userInput', UTIL_HtmlTag::generateTag('input', $userInputParams));

        $this->addComponent('filterContext', $this->getFilterContextAction());
        $this->assign('themeUrl', OW::getThemeManager()->getCurrentTheme()->getStaticImagesUrl());
        $this->assign('userToken', $userValue);
        
        switch ( $this->scope )
        {
            case 'topic':
                $location = json_encode(OW::getRouter()->urlForRoute('forum_search_topic', array('topicId' => $params['topicId'])));
                break;
                
            case 'group':
                $location = json_encode(OW::getRouter()->urlForRoute('forum_search_group', array('groupId' => $params['groupId'])));
                break;

            case 'section':
                $location = json_encode(OW::getRouter()->urlForRoute('forum_search_section', array('sectionId' => $params['sectionId'])));
                break;

            default:
                $location = json_encode(OW::getRouter()->urlForRoute('forum_search'));
                break;
        }
        
        $userInvitation = OW::getLanguage()->text('forum', 'enter_username');

        $script =
        'var invitation = '.json_encode($invitation).';
        var input = '.json_encode($inputParams['id']).';
        var userInvitation = '.json_encode($userInvitation).';
        var userInput = '.json_encode($userInputParams['id']).';

        $("#" + userInput).focus(function() {
            if ( $(this).val() == userInvitation ) {
                $(this).removeClass("invitation").val("");
            }
        });
        $("#" + userInput).blur(function() {
            if ( $(this).val() == "" ) {
                $(this).addClass("invitation").val(userInvitation);
            }
        });
        ';
        
        if ( !mb_strlen($value) )
        {
            $script .=
            '$("#" + input).focus(function() {
                if ( $(this).val() == invitation ) {
                    $(this).removeClass("invitation").val("");
                }
            });
            $("#" + input).blur(function() {
                if ( $(this).val() == "" ) {
                    $(this).addClass("invitation").val(invitation);
                }
            });
            ';
        }

        $script .= 
        'var $form = $("form#forum_search");
        $(".ow_miniic_delete", $form).click(function() {
            $(".forum_search_tag_input", $form).css({visibility : "hidden", height: "0px", padding: "0px"});
            $(".add_filter", $form).show();
            $("#forum_search_cont").removeClass("forum_search_inputs");
            $("#" + userInput).val("").removeClass("invitation");
        });

        $("#btn-filter-by-user").click(function() {
            $(".forum_search_tag_input", $form).css({visibility : "visible", height: "auto", padding: "4px"});
            $("#" + userInput).val(userInvitation).addClass("invitation");
            $(".add_filter", $form).hide();
            $("#forum_search_cont").addClass("forum_search_inputs");
        });

        $("#'.$inputParams['id'].', #'.$userInputParams['id'].'").keydown(function(e){
            if (e.keyCode == 13) {
                $(this).parents("form").submit();
                return false;
            }
        });
            
        $form.submit(function() {
            var value = $("#" + input).val();
            var userValue = $("#" + userInput).val();

            if ( value == invitation && !userValue.length || userValue == userInvitation && !value.length ) {
                return false;
            }

            if ( value == invitation ) {
                value = ""; $("#" + input).val(value);
            }

            if ( userValue == userInvitation ) {
                userValue = ""; $("#" + userInput).val(userValue);
            }

            var search = encodeURIComponent(value);
            userSearch = encodeURIComponent(userValue);
            document.location.href = '.$location.' + "?"
                + (search.length ? "&q=" + search : "")
                + (userSearch.length ? "&u=" + userSearch : "");

            return false;
        });
        ';

        OW::getDocument()->addOnloadScript($script);
    }
    
    private function getFilterContextAction( )
    {
        $context = new BASE_CMP_ContextAction();

        $action = new BASE_ContextAction();
        $action->setKey('forum-search');

        $context->addAction($action);

        $action = new BASE_ContextAction();
        $action->setKey('filter_by_user');
        $action->setParentKey('forum-search');
        $action->setLabel(OW::getLanguage()->text('forum', 'forms_search_username_field_label'));
        $action->setId('btn-filter-by-user');

        $context->addAction($action);

        $action = new BASE_ContextAction();
        $action->setKey('advanced_search');
        $action->setParentKey('forum-search');
        $action->setLabel(OW::getLanguage()->text('forum', 'advanced_search'));
        $action->setUrl(OW::getRouter()->urlForRoute('forum_advanced_search'));

        $context->addAction($action);

        return $context;
    }
    
    private function getInvitationLabel()
    {
        return OW::getLanguage()->text('forum', 'search_invitation_' . $this->scope);
    }
}