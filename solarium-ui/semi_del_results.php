<?php

/* ********************************************
 * Search over semi-deletions - result page
 * Dependencies: 
 *   1) php-diff: https://github.com/jfcherng/php-diff
 *   2) intl package (may need to uncomment in php.ini)
 * ********************************************
 */

//Solarium initialization
require_once(__DIR__.'/init.php');
require_once(__DIR__.'/temporal_document.php');

//Solarium header
htmlHeader();

//Search form below
?>

<form method="GET" action="semi_del_results.php">
<select name="searchtype">
  <option value="delsearch">deleted term/phrase</option>
</select>
<input type="text" name="dterm" value="<?php echo isset($_GET['dterm']) ? str_replace('"', '&quot;',$_GET['dterm']) : '' ?>"/>
<button name="search">Search</button>
</form>
<br />

<?php

//if the form has been submitted, show the results:
if(ISSET($_GET['search'])){

//Determine if it's a term or phrase

$deleted_term = $_GET['dterm'];
//creamy pot vegan tuesday financial
//"one pot"
$query_show_text = 'semi_del_term:'.$deleted_term;

$is_deleted_phrase = FALSE;
if (str_contains($deleted_term, ' ')) {
    $is_deleted_phrase = TRUE;
    if ($deleted_term[0] == '"' && $deleted_term[strlen($deleted_term)-1] == '"') {
        $deleted_term = substr($deleted_term, 1, strlen($deleted_term)-2);
    }
    $dterms = str_replace(' ', ' OR ', $deleted_term);
    $query_show_text = 'semi_del_term:('.$dterms.') text:"'.$deleted_term.'"';
}

$query_show_type = ($is_deleted_phrase) ? 'phrase' : 'term';
echo '<p><b>Search results for deleted '.$query_show_type.': '.$deleted_term.'</b></p>';

//Instantiate Solarium Client
$client = new Solarium\Client($adapter, $eventDispatcher, $config);
$query = $client->createSelect();

//set up query for deletions
$query->setQuery($query_show_text);

//query properties
$query->setFields(array('wayback_date','id','title', 'url', 'url_norm', 'score', 'content', 'validity_range'));
$query->addSort('score', $query::SORT_DESC);
$query->setDocumentClass('TemporalDoc');
$query->setStart(0)->setRows(20); //to do: paginate

//Make the query
$resultset = $client->select($query);
$ceilwarn = ($is_deleted_phrase) ? ' (ceiling)' : '';
echo 'NumFound'.$ceilwarn.': '.$resultset->getNumFound();

//Iterate over results
$fields_to_show = array('title', 'url');
foreach ($resultset as $document) {

    
    //echo '<hr>candidate: '.$document->url.'<hr>';
    

    $ifmatchout = "";

    $ifmatchout .= '<hr/><table>';
    foreach ($document as $field => $value) {
        if (is_array($value)) {
            $value = implode(', ', $value);
        }
        if (in_array($field, $fields_to_show)) {
            $ifmatchout .= '<tr><th>' . $field . '</th><td>' . $value . '</td></tr>';
        }
    }
    $ifmatchout .= '<tr><th>pre-deletion</th><td><a href="' . $document->get_wayback_uri() . '">'.$document->format_wayback_date().'</a></td></tr>';

    //Get the version of the page with the deletion, get the diff
    $query2 = $client->createSelect();
    $query2->setQuery('url_norm:'.$document->url_norm);
    $query2->createFilterQuery('crawltime')->setQuery('validity_range:['.$document->get_next_wayback_date().' TO '.$document->get_next_wayback_date().']');
    $query2->setDocumentClass('TemporalDoc');
    $query2->addSort('id', $query::SORT_DESC);
    $resultset2 = $client->select($query2);

    //Iterate over the 2nd query results
    $innerloop = 0;
    foreach ($resultset2 as $document2) {

      if ($innerloop == 0) {
        //echo $document->compare_phrase_freq($document2, $deleted_term);
      }

     if (!$is_deleted_phrase ||
        ($is_deleted_phrase && $document->compare_phrase_freq($document2, $deleted_term) > 0)) {
       echo $ifmatchout;
       echo '<tr><th>post-deletion</th><td><a href="' . $document2->get_wayback_uri() . '">'.$document2->format_wayback_date().'</a></td></tr>';
       $diff_out = $document->diff($document2, $deleted_term);
       $diff_out2 = preg_replace('/\b('.$deleted_term.')\b/i','<span style="background-color: #FFFF00">$1</span>' , $diff_out);
       if ($diff_out == $diff_out2 && $is_deleted_phrase) {
           $deleted_expl = explode(' ', $deleted_term);
           foreach($deleted_expl as $de_value) {
               $diff_out = preg_replace('/\b('.$de_value.')\b/i','<span style="background-color: #FFFF00">$1</span>' , $diff_out);
           }
       }
       else {
           $diff_out = $diff_out2;
       }
       echo '<tr><th>diff</th><td><pre>'.json_decode(str_replace(array('\u00e2', '\u20ac', '\u2122'), array('', '', '\''), json_encode($diff_out))).'</pre></td></tr>';
       echo '<tr><th>diff over time</th><td><a href="diff-slider.php?page='.urlencode($document->url_norm).'&wbdate1='.$document->wayback_date.'&wbdate2='.$document2->wayback_date.'&dterm='.$deleted_term.'">View diff over time</a></td></tr>';
       echo '<tr><th>deletion animation</th><td><a href="http://localhost:8050/cgi-bin/web_diff.py?page='.urlencode($document->url_norm).'&wbdate1='.$document->wayback_date.'&wbdate2='.$document2->wayback_date.'&dterm='.$deleted_term.'" target="_blank">View animated deletion</a></td></tr>';

       $innerloop++;
       if ($innerloop > 0) {
           break;
       }
      }
    }

//TO DO
//semi additions
//also consider full deletions searching with OR like in this code

/*
    //Get the version of the page with the addition, calculate lifespan
    $query3 = $client->createSelect();
    $query3->setQuery('url_norm:'.$document->url_norm);
    $query3_query_str = $deleted_term;
    if ($is_deleted_phrase) {
        $query3_query_str = '('.str_replace(' ', ' OR ', $query3_query_str).')';
    }
    $query3->createFilterQuery('termadd')->setQuery('added_term:'.$query3_query_str);
    $query3->setDocumentClass('TemporalDoc');
    $query3->addSort('id', $query::SORT_DESC);//if added more than once
    $resultset3 = $client->select($query3);
    $date_del = date_create($document2->format_wayback_date());

    //Iterate over the 3rd query results
    $innerloop = 0;
    foreach ($resultset3 as $document3) {

        $date_add = date_create($document3->format_wayback_date());
        if ($date_add >= $date_del) {
            continue;//if added more than once
        }

        if ($is_deleted_phrase) {

            //To calculate the content lifespan of a deleted phrase,
            //Take the later of the added terms (doc3)
            //Search for the phrase in the text field with dates >= doc3
            //Sort smallest to largest, the smallest is the addition

            //Get the version of the page with the phrase addition, calculate lifespan
            $query4 = $client->createSelect();
            $query4->setQuery('url_norm:'.$document->url_norm);
            $query4->createFilterQuery('delphr')->setQuery('text:"'.$deleted_term.'"');
            $query4->createFilterQuery('delrange')->setQuery('validity_range:['.$document3->format_wayback_date().' TO *]');
            $query4->setDocumentClass('TemporalDoc');
            $query4->addSort('id', $query::SORT_ASC);//if added more than once
            $resultset4 = $client->select($query3);

            //Iterate over the 4th query results
            $innerloop4 = 0;
            foreach ($resultset4 as $document4) {
                $date_add = date_create($document4->format_wayback_date());
                echo '<tr><th>addition</th><td><a href="' . $document4->get_wayback_uri() . '">'.$document4->format_wayback_date().'</a></td></tr>';
                echo '<tr><th>content lifespan</th><td>'.date_diff($date_add, $date_del)->format('%a days').'</td></tr>';

                $innerloop4++;
                if ($innerloop4 > 0) {
                    break;
                }
            }

            $innerloop++;
        }
        else {
            echo '<tr><th>addition</th><td><a href="' . $document3->get_wayback_uri() . '">'.$document3->format_wayback_date().'</a></td></tr>';
            echo '<tr><th>content lifespan</th><td>'.date_diff($date_add, $date_del)->format('%a days').'</td></tr>';
            $innerloop++;
        }

        if ($innerloop > 0) {
            break;
        }
    }
*/

    echo '</table>';
}

}//end GET

htmlFooter();
