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

$config = OW::getConfig();

if ( !$config->configExists('forum', 'attachment_filesize') )
{
    $config->addConfig('forum', 'attachment_filesize', 32, 'Attachment maximum file size');
}


if ( !defined('OW_PLUGIN_XP') )
{

    $plugin = OW::getPluginManager()->getPlugin('forum');
    $staticJsDir = OW_DIR_STATIC_PLUGIN . $plugin->getModuleName() . DS  . 'js' . DS;
    @copy($plugin->getStaticJsDir() . 'forum.js', $staticJsDir . 'forum.js');

}


Updater::getLanguageService()->importPrefixFromZip(dirname(__FILE__) . DS . 'langs.zip', 'forum');

$exArr = array();

try
{
    Updater::getDbo()->update("ALTER TABLE `".OW_DB_PREFIX."forum_group` CHANGE `description` `description` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL");
}
catch ( Exception $e ){ $exArr[] = $e; }



$start = 0;
$count = 100;

while ( true )
{
    $postList = array();
    
    $sql = "SELECT * FROM `".OW_DB_PREFIX."forum_post` 
        ORDER BY `createStamp` ASC LIMIT :start, :count";
    
    try
    {
        $postList = Updater::getDbo()->queryForList($sql, array('start' => $start, 'count' => $count));
    }
    catch ( Exception $e ){ $exArr[] = $e; }

    if ( empty($postList) )
    {
        break;
    }
    
    foreach ( $postList as $post )
    {
        $sql = "UPDATE `".OW_DB_PREFIX."forum_post` 
            SET `text` = :text 
            WHERE `id` = :id";
        
        try
        {
            Updater::getDbo()->query($sql, array('text' => nl2br(fromBBtoHtml($post['text'])), 'id' => $post['id']));
        }
        catch ( Exception $e ){ $exArr[] = $e; }
    }
    
    $start += $count;
}


function fromBBtoHtml( $txt )
{
    $tagList = array(
        array(
            'tag' => 'a',
            'pair' => true,
            'attributes' => array('href')
        ),
        array(
            'tag' => 'img',
            'pair' => false,
            'attributes' => array('src', 'class', 'style')
        ),
        array(
            'tag' => 'strong',
            'pair' => true,
            'attributes' => array()
        ),
        array(
            'tag' => 'u',
            'pair' => true,
            'attributes' => array()
        ),
        array(
            'tag' => 'i',
            'pair' => true,
            'attributes' => array()
        )
    );    

    $result = $txt;

    foreach ( $tagList as $tag )
    {
        if ( empty($tag['tag']) || !isset($tag['pair']) )
        {
            continue;
        }

        $tagName = $tag['tag'];
        $pair = $tag['pair'];
        $attributes = (!empty($tag['attributes']) && is_array($tag['attributes']) ) ? $tag['attributes'] : array();

        $pairRegexp = $pair ? '(.*?)?\[[\s]*\/[\s]*' . $tagName . '\s*\]' : '';

        $regexp = '/\[\s*' . $tagName . '\s*.*?\]' . $pairRegexp . '/s';

        preg_match_all($regexp, $result, $matches);
        if ( preg_match_all($regexp, $result, $matches) )
        {
            foreach ( $matches[0] as $key => $match )
            {
                $attr = '';
                $tagString = $match;

                foreach ( $attributes as $attribute )
                {
                    if ( preg_match('/' . $attribute . '=\'.*?\'/', $tagString, $attrMatches) )
                    {
                        $attr .= $attrMatches[0] . ' ';
                    }
                    else if ( preg_match('/' . $attribute . '=".*?"/', $tagString, $attrMatches) )
                    {
                        $attr .= $attrMatches[0] . ' ';
                    }
                }

                $string = '<' . $tagName . ' ' . $attr;
                if ( $pair )
                {
                    $string .= '>';
                }
                else
                {
                    $string .= '/>';
                }

                if ( $pair && !empty($matches[1][$key]) )
                {
                    $innerHtml = $matches[1][$key];
                    $string .= $innerHtml . '</' . $tagName . '>';
                }

                $result = mbStrReplace($result, $tagString, $string);
            }
        }
    }

    return $result;
}

function mbStrReplace( $haystack, $search, $replace, $offset = 0, $encoding = 'auto' )
{
    $lenSch = mb_strlen($search, $encoding);
    $lenRep = mb_strlen($replace, $encoding);

    while ( ($offset = mb_strpos($haystack, $search, $offset, $encoding)) !== false )
    {
        $haystack = mb_substr($haystack, 0, $offset, $encoding) . $replace . mb_substr($haystack, $offset + $lenSch, mb_strlen($haystack), $encoding);
        $offset = $offset + $lenRep;

        if ( $offset > mb_strlen($haystack, $encoding) )
        {
            break;
        }
    }
    
    return $haystack;
}