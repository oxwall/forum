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
 * Forum search result formatter class.
 *
 * @author Egor Bulgakov <egor.bulgakov@gmail.com>
 * @package ow.ow_plugins.forum.classes
 * @since 1.0
 */
class FORUM_CLASS_ForumSearchResultFormatter
{
    private $delimiter = " &hellip; ";
    
    private $maxlength = 300;
    
    private $highlightClass = "ow_highbox";
    
    private $text;
    
    private $words = array();
    
    private $wordsCount;
    
    
    public function __construct() { }
    
    public function setDelimiter( $delim )
    {
        $this->delimiter = $delim;
    }
    
    public function setHighlightClass( $class )
    {
        $this->highlightClass = $class;
    }
    
    public function setMaxlength( $length )
    {
        $this->maxlength = $length;
    }
    
    public function formatResult( $text, array $words, $highlight = true )
    {
        $this->text = $text;
        $this->words = $words;
        
        $excerption = $this->getExcerption($this->text, $this->words);
        
        if ( $highlight )
        {
            $excerption = $this->highlightText($excerption);
        }
        
        return $excerption;
    }
    
    private function getExcerption( $text, array $words )
    {
        if ( mb_strlen($text) < $this->maxlength * 1.15 ) // too short
        {
            return $text;
        } 
        else 
        {
            switch ( $this->wordsCount = $this->getWordsCount() ) 
            {
                case 0:
                    return $this->getStartExcerption();

                case 1:
                    return $this->getSingleWordExcerption();
                
                case 2:
                    return $this->getCoupleWordsExcerption();
                
                default:
                    return $this->getMultipleWordsExcerption();
            }
        }
    }
    
    private function getWordsCount()
    { 
        foreach ( $this->words as $id => $word ) 
        {
            if ( !preg_match("/(?<![\pL\pN_])".preg_quote($word, '/')."(?![\pL\pN_])/iu", $this->text) ) 
            {
                unset($this->words[$id]);
            }
        }

        return count($this->words);
    }
    
    private function getStartExcerption()
    {
        preg_match("/^(?:.{0,$this->maxlength}[\.;:,]|.{0,$this->maxlength}(?![\pL\pN_]))/smiu", $this->text, $matches);
        
        return $matches[0] . $this->delimiter;
    }
    
    private function getSingleWordExcerption()
    {
        $word = reset($this->words);
        $spacelength = round($this->maxlength / 2);

        preg_match("/(\W|^).{0,$spacelength}(?<![\pL\pN_])".preg_quote($word, '/')."(?![\pL\pN_]).{0,$spacelength}(\W|$)/smiu", $this->text, $matches);

        $noLeftDelim = preg_match('/(?:^|' . "\r|\n" . ')' . preg_quote($matches[0], '/') . '/u', $this->text) ? true : false;
        $noRightDelim = preg_match('/' . preg_quote($matches[2], '/') . '(?:$|' . "\r|\n" . ')/u', $this->text) ? true : false;
        
        return ($noLeftDelim ? "" : $this->delimiter) . $matches[0] . ($noRightDelim ? "" : $this->delimiter);
    }
    
    private function getCoupleWordsExcerption()
    {
        $spacelength = round($this->maxlength / 2.15);
        $word1 = preg_quote(reset($this->words), '/');
        $word2 = preg_quote(next($this->words), '/');

        if ( preg_match("/(\W|^)(?:\w+\W+){3,7}(?:$word1(?![\pL\pN_]).{0,$spacelength}(?<![\pL\pN_])$word2|$word2(?![\pL\pN_]).{0,$spacelength}(?<![\pL\pN_])$word1)(?![\pL\pN_]).{0,$spacelength}(\W|$)/smiu", $this->text, $matches) ) 
        {
            return ($matches[1] != "" ? $this->delimiter : "") . $matches[0] . ($matches[2] != "" ? $this->delimiter : "");
        }
        else 
        {
            $spacelength = round($spacelength / 2.5);
            preg_match("/(\W|^).{0,$spacelength}(?<![\pL\pN_])$word1(?![\pL\pN_]).{0,$spacelength}(?=\W)/smiu", $this->text, $matches);

            $matchedtext = ($matches[1] != "" ? $this->delimiter : "") . $matches[0] . $this->delimiter;
            preg_match("/(\W).{0,$spacelength}(?<![\pL\pN_])$word2(?![\pL\pN_]).{0,$spacelength}(\W|$)/smiu", $this->text, $matches);
            
            return $matchedtext . $matches[0] . ($matches[2] != "" ? $this->delimiter : "");
        }
    }
    
    private function getMultipleWordsExcerption()
    {
        $spacelength = round($this->maxlength / ($this->wordsCount + 0.15 - ($this->wordsCount * .15)));

        if ( preg_match_all("/(\s|^)(?:\w+\s+){3,7}(?:(?<=(?<![\pL\pN_]))(?:" . preg_quote(implode("|", $this->words), '/') . ")(?=(?![\pL\pN_])).{0,$spacelength}){{$this->wordsCount}}(\s|$)/smiu", $this->text, $matches) )
        {
            $maxwords = 0;
            foreach ( $matches[0] as $key => $match ) 
            {
                $foundwords = 0;
                preg_match_all("/(?:(?<![\pL\pN_]))(?:" . preg_quote(implode("|", $this->words), '/') . ")(?:(?![\pL\pN_]))/iu", mb_strtolower($match), $words);
                $wordcount = count(array_unique($words[0])) + count($words[0]) / ($this->wordsCount * 1.6);
                if ( $wordcount > $maxwords ) 
                {
                    $maxwords = $wordcount;
                    $maxkey = $key;
                }
                if ( $wordcount >= $this->wordsCount ) 
                {
                    return ($matches[1][$key] != "" ? $this->delimiter : "") . $match . ($matches[2][$key] != "" ? $this->delimiter : "");
                }
            }
            
            if ( $maxwords > 1 ) 
            {
                return ($matches[1][$maxkey] != "" ? $this->delimiter : "") . $matches[0][$maxkey] . ($matches[2][$maxkey] != "" ? $this->delimiter : "");
            }
        }
        
        return $this->getCoupleWordsExcerption();
    }
    
    private function highlightText( $text ) 
    {
        foreach ( $this->words as $id => $word ) 
        {
           $text = preg_replace('/(?<![\pL\pN_])([\w-]*' . preg_quote($word, '/') . '[\w-]*)(?![\pL\pN_])/iu', '<span class="'.$this->highlightClass.'">\\1</span>', $text);
        }
        
        return $text;
    }
}