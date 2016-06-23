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
OW::getNavigation()->addMenuItem(OW_Navigation::MAIN, 'forum-default', 'forum', 'forum', OW_Navigation::VISIBLE_FOR_ALL);

$widgetService = BOL_ComponentAdminService::getInstance();

$widget = $widgetService->addWidget('FORUM_CMP_ForumTopicsWidget', false);
$placeWidget = $widgetService->addWidgetToPlace($widget, BOL_ComponentAdminService::PLACE_INDEX);
$widgetService->addWidgetToPosition($placeWidget, BOL_ComponentService::SECTION_LEFT);

$event = new BASE_CLASS_EventCollector('forum.collect_widget_places');
OW::getEventManager()->trigger($event);

foreach( $event->getData() as $widgetInfo )
{
    try
    {
        $widget = $widgetService->addWidget('FORUM_CMP_LatestTopicsWidget', false);
        $widgetPlace = $widgetService->addWidgetToPlace($widget, $widgetInfo['place']);
        $widgetService->addWidgetToPosition($widgetPlace, $widgetInfo['section'], $widgetInfo['order']);
    }
    catch ( Exception $e )
    {

    }
}

require_once dirname(__FILE__) . DS .  'classes' . DS . 'credits.php';
$credits = new FORUM_CLASS_Credits();
$credits->triggerCreditActionsAdd();

// Mobile activation
OW::getNavigation()->addMenuItem(OW_Navigation::MOBILE_TOP, 'forum-default', 'forum', 'forum_mobile', OW_Navigation::VISIBLE_FOR_ALL);

require_once dirname(__FILE__) . DS .  'bol' . DS . 'text_search_service.php';
FORUM_BOL_TextSearchService::getInstance()->activateEntities();

// register sitemap entities
BOL_SeoService::getInstance()->addSitemapEntity('forum', 'forum_sitemap', 'forum', array(
    'forum_list',
    'forum_section',
    'forum_group',
    'forum_topic'
));
