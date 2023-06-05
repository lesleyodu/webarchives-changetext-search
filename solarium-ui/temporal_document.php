<?php

//Solarium initialization
require_once(__DIR__.'/init.php');

//Diff library initialization
include __DIR__ . '/../../../../vendor/autoload.php';
use Jfcherng\Diff\ {Differ, DiffHelper, Factory\RendererFactory, Renderer\RendererConstant};

// this is the custom result document class
class TemporalDoc extends Solarium\QueryType\Select\Result\Document
{
    public function get_wayback_uri()
    {
        return "http://localhost:8060/netarchivesuite/" . $this->wayback_date . '/' . $this->url;
    }
    public function diff($doc2, $deleted_term, $full_diff = FALSE) {
        // renderer class name:
        //     Text renderers: Context, JsonText, Unified
        //     HTML renderers: Combined, Inline, JsonHtml, SideBySide
        $rendererName = ($full_diff) ? 'SideBySide' : 'Inline';
        $differOptions = ['ignoreCase' => true];
        $rendererOptions = ['detailLevel' => 'word',
                            'lineNumbers' => false];
        $jsonResult = DiffHelper::calculate($this->guess_add_newlines() , $doc2->guess_add_newlines(), 'Json');
        //print_r($jsonResult);


        if ($full_diff) {
            $htmlRenderer = RendererFactory::make($rendererName, $rendererOptions);
            return $htmlRenderer->renderArray(json_decode($jsonResult, true));
        }

        //keep only lines with deleted term
        $jsonObj = json_decode($jsonResult, TRUE);
        //print_r($jsonObj);
        //echo count($jsonObj);
        $jsonRet = array();
        //https://stackoverflow.com/questions/45007705/how-to-merge-all-key-values-into-one-in-single-array-in-php
        $jsonObjMerge = array_merge(...$jsonObj);
        //print_r($jsonObjMerge);
        foreach($jsonObjMerge as $key => $value) {
            //print $key;
            if (str_contains(strtolower(strip_tags(join('', $value['old']['lines']))), strtolower($deleted_term))) {
              //echo 'here1';
              $oldval = strtolower(strip_tags(join('', $value['old']['lines'])));
              $newval = strtolower(strip_tags(join('', $value['new']['lines'])));
              if (substr_count($oldval,  strtolower($deleted_term)) != substr_count($newval,  strtolower($deleted_term))) {
               //echo 'here';
              //if (!str_contains(strtolower(strip_tags(join('', $value['new']['lines']))), strtolower($deleted_term))) {
                //if there are more than 5 lines, filter more
                $linect = count($value['old']['lines']) + count($value['new']['lines']);
                if ($linect > 5) {
                    $valueoldlines = array();
                    foreach ($value['old']['lines'] as $k) {
                        if (str_contains(strtolower(strip_tags($k)), strtolower($deleted_term))) {
                            array_push($valueoldlines, $k);
                        }
                    }
                    $value['old']['lines'] = $valueoldlines;
                    if (!str_contains(strtolower(strip_tags(join('', $value['new']['lines']))), strtolower($deleted_term))) {
              
                        $value['new']['lines'] = array();
                    }
                    else {
                      $valuenewlines = array();
                      foreach ($value['new']['lines'] as $k) {
                        if (str_contains(strtolower(strip_tags($k)), strtolower($deleted_term))) {
                            array_push($valuenewlines, $k);
                        }
                      }
                      $value['new']['lines'] = $valuenewlines;
                    }
                }

                //add the diff part to the results either way
                array_push($jsonRet, $value);
              }
            }
            else {
               //echo strtolower(strip_tags(join('', $value['old']['lines'])));
               //echo join('', $value['old']['lines']);
            }
        }

        if (count($jsonRet) == 0 && str_contains($deleted_term, ' ')) {
            $deleted_term_expl = explode(' ', $deleted_term);
            return $this->diff($doc2, $deleted_term_expl[0], $full_diff);
        }

        $k = 0;
        //$hidefront = '<span style="display: none;">';
        //$hideend = '</span>';
        for($j = 0; $j < count($jsonRet); $j++) {
            for ($i = 0; $i < count($jsonRet[$j]['old']['lines']); $i++) {
                 if (strlen($jsonRet[$j]['old']['lines'][$i]) > 400) {
                     $jsonRet[$j]['old']['lines'][$i] = $this->filter_phrase_10($jsonRet[$j]['old']['lines'][$i], $deleted_term);
                 }
                 $k++;
                 if ($k > 5) {
                     array_pop($jsonRet[$j]['old']['lines']);
                     $i--;
                 }
                 //if ($k > 5) $jsonRet[$j]['old']['lines'][$i] = '***'.$jsonRet[$j]['old']['lines'][$i];
                 //if ($k == 5) $jsonRet[$j]['old']['lines'][$i] = $jsonRet[$j]['old']['lines'][$i].$hidefront;
                 //if ($k >= 5 && $j == count($jsonRet) - 1 && $i == count($jsonRet[$j]['old']['lines']) - 1 && count($jsonRet[$j]['new']['lines']) == 0) $jsonRet[$j]['old']['lines'][$i] = $jsonRet[$j]['old']['lines'][$i].$hideend;

            }
            for ($i = 0; $i < count($jsonRet[$j]['new']['lines']); $i++) {
                 $k++;
                 if ($k > 5) {
                     array_pop($jsonRet[$j]['new']['lines']);
                     $i--;
                 }
                 //if ($k > 5) $jsonRet[$j]['new']['lines'][$i] = '***'.$jsonRet[$j]['new']['lines'][$i];
                 //if ($k == 5) $jsonRet[$j]['new']['lines'][$i] = $jsonRet[$j]['new']['lines'][$i].$hidefront;
                 //if ($k >= 5 && $j == count($jsonRet) - 1 && $i == count($jsonRet[$j]['new']['lines']) - 1) $jsonRet[$j]['old']['lines'][$i] = $jsonRet[$j]['old']['lines'][$i].$hideend;

                //consider shortening added lines >400 chars
            }
        }

        if ($k > 5) {
           $pushon = '('.($k-5).' more matching differences on this page)';
           if ($k - 5 == 1) $pushon = str_replace('differences', 'difference', $pushon);
           /*if (count($jsonRet[count($jsonRet)-1]['new']['lines']) > 0) {
               array_push($jsonRet[count($jsonRet)-1]['new']['lines'], $pushon);
           }
           else {
               array_push($jsonRet[count($jsonRet)-1]['old']['lines'], $pushon);
           }*/
           $pushonarr1 = array("offset" => 99, "lines" => array($pushon));
           $pushonarr = array("tag" => 1, "old" => $pushonarr1, "new" => $pushonarr1);
           array_push($jsonRet, $pushonarr);

        }

        echo "\n\n<!--";
        print_r($jsonRet);
        echo "-->\n\n";

        $jsonResult2 = json_encode($jsonRet);
        $htmlRenderer = RendererFactory::make($rendererName, $rendererOptions);
        return $htmlRenderer->renderArray(array(json_decode($jsonResult2, true)));
    }
    public function guess_add_newlines() {
        //newlines are removed when indexing, but help diff be more accurate
        $contentt = $this->content;
        $search_arr = array('. ', '! ', '? ');
        $replace_arr = array(".\n", "!\n", "?\n");
        return str_replace($search_arr, $replace_arr, $contentt) . "\n";
    }
    public function get_next_wayback_date() {
        //return preg_replace('/[^0-9]/', '', substr($this->validity_range, -21));

        //return the next wayback date with its formatting in tact
        return substr($this->validity_range, -21,20);
    }
    public function format_wayback_date() {
       $date = date_create_from_format('YmdHis', $this->wayback_date);
       return date_format($date, 'Y-m-d H:i:s');
    }

    public function content_joined_with_spaces() {
        return $this->text_joined_with_spaces($this->content);
    }

    public function text_joined_with_spaces($phrase) {

        $text_joined_with_spaces = "";

        //https://emptyheap2019.github.io/posts/parse-words-php/
        $it = IntlBreakIterator::createWordInstance("en_US");
        $it->setText($phrase);
        $parts = $it->getPartsIterator();

        $punctuation = [
        IntlChar::CHAR_CATEGORY_DASH_PUNCTUATION,
        IntlChar::CHAR_CATEGORY_START_PUNCTUATION,
        IntlChar::CHAR_CATEGORY_END_PUNCTUATION,
        IntlChar::CHAR_CATEGORY_CONNECTOR_PUNCTUATION,
        IntlChar::CHAR_CATEGORY_OTHER_PUNCTUATION,
        IntlChar::CHAR_CATEGORY_INITIAL_PUNCTUATION,
        IntlChar::CHAR_CATEGORY_FINAL_PUNCTUATION,
            ];
        foreach ($parts as $word) {
            if (mb_strlen($word) == 1 &&
                (in_array(IntlChar::charType($word), $punctuation) || IntlChar::isUWhiteSpace($word)))
            {
                continue;
            }
            $text_joined_with_spaces .= strtolower($word) . ' ';
        }
        return $text_joined_with_spaces;
    }

    public function count_phrase_freq($phrase) {

        $text_joined_with_spaces = $this->content_joined_with_spaces();
        return substr_count($text_joined_with_spaces, $phrase);
    }

    public function compare_phrase_freq($doc2, $phrase) {
        //echo $this->count_phrase_freq($phrase).' '.$doc2->count_phrase_freq($phrase);
        return $this->count_phrase_freq($phrase) - $doc2->count_phrase_freq($phrase);
    }

    public function filter_phrase_10($phrase, $deleted_term) {
        $text_joined_with_spaces = $this->text_joined_with_spaces($phrase);
        if(str_contains($deleted_term, ' ')) {
             //deal with this...

             $text_joined_with_spaces = strtolower($text_joined_with_spaces);
             $deleted_term = strtolower($deleted_term);
             $text_array1 = explode($deleted_term, $text_joined_with_spaces);
             $filter_phrase = "";
             for ($i = 1; $i < count($text_array1); $i++) {
                 $text_array2 = explode(' ', $text_array1[$i]);
                 $text_array2b = explode(' ', $text_array1[$i - 1]);
                 //keep first 10 and last 10 words;

                     $text_array3 = array_slice($text_array2b, max(0, count($text_array2b) - 11));
                     if (count($text_array2b) - 10 > 0) $filter_phrase .= '...';
                     $filter_phrase .= implode(' ', $text_array3);
                     $filter_phrase .= $deleted_term;

                     $text_array3 = array_slice($text_array2, 0, 11);
                     $filter_phrase .= implode(' ', $text_array3);
                     if (count($text_array2) > 10 ) $filter_phrase .= '...';
                 

             }
             return $filter_phrase;
        }
        else {
            $text_array = explode(' ', $text_joined_with_spaces);
            $filter_phrase = "";
            $last_index = 0;
            for ($i = 0; $i < count($text_array); $i++) {
                 if (strtolower($text_array[$i]) == strtolower($deleted_term)) {
                     if ($i - 10 > 0 && $last_index == 0) $filter_phrase .= '...';
                     for ($j = max(0, $i - 10); $j < min(count($text_array), $i + 10); $j++) {
                         if ($j < $last_index) continue;
                         $filter_phrase .= $text_array[$j];
                         if ($j != min(count($text_array), $i + 10) - 1) $filter_phrase .=  ' ';
                         //this will lose all punctuation...
                     }
                     if ($i + 10 < count($text_array)) $filter_phrase .= '...';
                     else $filter_phrase .= '.';
                     $last_index = min(count($text_array), $i + 10);
                 }
            }
            return $filter_phrase;
        }
    }

}

?>