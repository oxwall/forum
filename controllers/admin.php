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
 * Forum admin action controller
 *
 * @author Egor Bulgakov <egor.bulgakov@gmail.com>
 * @package ow.ow_plugins.forum.controllers
 * @since 1.0
 */
class FORUM_CTRL_Admin extends ADMIN_CTRL_Abstract
{
    /**
     * Default action
     */
	public function index()
	{
        $language = OW::getLanguage();
        
        $configs =  OW::getConfig()->getValues('forum');
        
        $configSaveForm = new ConfigSaveForm();
        $this->addForm($configSaveForm);
            
        if ( OW::getRequest()->isPost() && $configSaveForm->isValid($_POST) )
        {
            $res = $configSaveForm->process();
            OW::getFeedback()->info($language->text('forum', 'settings_updated'));
            $this->redirect();
        }
        
	    if ( !OW::getRequest()->isAjax() )
        {
            $this->setPageHeading(OW::getLanguage()->text('forum', 'admin_config'));
            $this->setPageHeadingIconClass('ow_ic_forum');
        }
        
        $configSaveForm->getElement('enableAttachments')->setValue($configs['enable_attachments']);
	}
	
	/**
	 * Plugin uninstall action
	 */
    public function uninstall()
    {
        if ( isset($_POST['action']) && $_POST['action'] == 'delete_content' )
        {
            OW::getConfig()->saveConfig('forum', 'uninstall_inprogress', 1);
            
            FORUM_BOL_ForumService::getInstance()->setMaintenanceMode(true);

            $event = new OW_Event('forum.uninstall_plugin');
            OW::getEventManager()->trigger($event);

            OW::getFeedback()->info(OW::getLanguage()->text('forum', 'plugin_set_for_uninstall'));
            $this->redirect();
        }
                      
        $this->setPageHeading(OW::getLanguage()->text('forum', 'page_title_uninstall'));
        $this->setPageHeadingIconClass('ow_ic_delete');
        
        $this->assign('inprogress', (bool) OW::getConfig()->getValue('forum', 'uninstall_inprogress'));
        
        $js = new UTIL_JsGenerator();
        $js->jQueryEvent('#btn-delete-content', 'click', 'if ( !confirm("'.OW::getLanguage()->text('forum', 'confirm_delete_forum').'") ) return false;');
        
        OW::getDocument()->addOnloadScript($js);
    }
}


/**
 * Save Configurations form class
 */
class ConfigSaveForm extends Form
{
    /**
     * Class constructor
     *
     */
    public function __construct()
    {
        parent::__construct('configSaveForm');

        $language = OW::getLanguage();
        
        $attachEnableField = new CheckboxField('enableAttachments');
        $this->addElement($attachEnableField);
                
        // submit
        $submit = new Submit('save');
        $submit->setValue($language->text('base', 'edit_button'));
        $this->addElement($submit);
    }
    
    /**
     * Updates forum plugin configuration
     *
     * @return boolean
     */
    public function process( )
    {
        $values = $this->getValues();

        $config = OW::getConfig();

        $config->saveConfig('forum', 'enable_attachments', $values['enableAttachments']);
        
        return array('result' => true);
    }
}