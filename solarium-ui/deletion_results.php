<?php

/* ********************************************
 * Search over deletions - result page
 * Dependencies: 
 *   1) php-diff: https://github.com/jfcherng/php-diff
 * ********************************************
 */

//Solarium initialization
require_once(__DIR__.'/init.php');
require_once(__DIR__.'/temporal_document.php');
require_once(__DIR__.'/url_norm.php');

//Solarium header
htmlHeader();

//Search form below
$domsearchsel = '';
$urlsearchsel = '';
if(isset($_GET['pagename'])) {
    if ($_GET['pagesearchtype'] == 'domainsearch') {
        $domsearchsel = ' selected';
    }
    else if ($_GET['pagesearchtype'] == 'urlsearch') {
        $urlsearchsel = ' selected';
    }
}
?>

<form method="GET" action="deletion_results.php">
<select name="searchtype" style="width:150px;">
  <option value="delsearch">deleted term/phrase</option>
</select>
<input type="text" name="dterm" value="<?php echo isset($_GET['dterm']) ? str_replace('"', '&quot;',$_GET['dterm']) : '' ?>"/>
<br/>
<select name="pagesearchtype" style="width:150px;">
  <option value="domainsearch"<?php echo $domsearchsel ?>>domain</option>
  <option value="urlsearch"<?php echo $urlsearchsel ?>>url</option>
</select>
<input type="text" name="pagename" placeholder="any" value="<?php echo isset($_GET['pagename']) ? str_replace('"', '&quot;',$_GET['pagename']) : '' ?>"/>

<button name="search">Search</button>
</form>
<br />

<?php

//if the form has been submitted, show the results:
if(ISSET($_GET['search'])){

//Determine if it's a term or phrase

$deleted_term = $_GET['dterm'];
$query_show_text = 'deleted_term:'.$deleted_term;

//To Do: sanitize bc special characters throw exceptions, if selecting 'domain'
$is_deleted_phrase = FALSE;
if (str_contains($deleted_term, ' ')) {
    $is_deleted_phrase = TRUE;
    if ($deleted_term[0] == '"' && $deleted_term[strlen($deleted_term)-1] == '"') {
        $deleted_term = substr($deleted_term, 1, strlen($deleted_term)-2);
    }
    $dterms = str_replace(' ', ' deleted_term:', $deleted_term);
    $query_show_text = 'deleted_term:'.$dterms.' text:"'.$deleted_term.'"';
}

$query_show_type = ($is_deleted_phrase) ? 'phrase' : 'term';

//Instantiate Solarium Client
$client = new Solarium\Client($adapter, $eventDispatcher, $config);
$query = $client->createSelect();

//set up query for deletions
$query->setQuery($query_show_text);

if (trim($_GET['pagename']) != '') {
    if ($_GET['pagesearchtype'] == 'domainsearch') {
       $query->createFilterQuery('siterange')->setQuery('domain:'.$_GET['pagename']);
    }
    else if ($_GET['pagesearchtype'] == 'urlsearch') {
        $url_normed = url_norm($_GET['pagename']);
        $helper = $query->getHelper();
        $url_norm_esc = $helper->escapeTerm($url_normed);
        $query->createFilterQuery('siterange')->setQuery('url_norm:'.$url_norm_esc);
    }
}

$pgnumb = 1;
if(ISSET($_GET['pgnum'])){
    $pgnumb = $_GET['pgnum'];
}

//To do: pagination
$results_pp = 10;
$result_startnum = $results_pp * $pgnumb - 10;
$query->setStart($result_startnum)->setRows($results_pp);

//query properties
$query->setFields(array('wayback_date','id','title', 'url', 'url_norm', 'score', 'content', 'validity_range'));
$query->addSort('score', $query::SORT_DESC);
$query->setDocumentClass('TemporalDoc');

//Make the query
$resultset = $client->select($query);
//echo 'NumFound: '.$resultset->getNumFound();

$pagin_end = min($resultset->getNumFound(), $results_pp * $pgnumb);
echo 'Results '.($result_startnum + 1).' - '.$pagin_end. ' of '.$resultset->getNumFound() . ' for deleted '.$query_show_type. ': <b>' .$deleted_term.'</b>';

//Iterate over results
$fields_to_show = array('title', 'url');
foreach ($resultset as $document) {

    echo "\n";

    echo '<hr/><table>';
    foreach ($document as $field => $value) {
        if (is_array($value)) {
            $value = implode(', ', $value);
        }
        if (in_array($field, $fields_to_show)) {
            $titlecss = '';
            if ($field == 'title') {
                $titlecss = ' class="title"';
            }
            echo '<tr><th>&nbsp;</th><td'.$titlecss.'>' . $value . '</td></tr>';
        }
    }

    //Get the version of the page with the deletion, get the diff
    $query2 = $client->createSelect();
    $query2->setQuery('url_norm:'.$document->url_norm);
    $query2->createFilterQuery('crawltime')->setQuery('validity_range:['.$document->get_next_wayback_date().' TO '.$document->get_next_wayback_date().']');
    $query2->setDocumentClass('TemporalDoc');
    $query2->addSort('id', $query::SORT_DESC);
    $resultset2 = $client->select($query2);

    //Iterate over the 2nd query results
    $document2 = $resultset2->getIterator()->current();
    
    echo '<tr><th>&nbsp;</th><td><a href="' . $document->get_wayback_uri() . '">Pre-deletion memento</a> &middot; <a href="' . $document2->get_wayback_uri() . '">Post-deletion memento</a> &middot; <a href="http://localhost:8050/cgi-bin/web_diff.py?page='.urlencode($document->url_norm).'&wbdate1='.$document->wayback_date.'&wbdate2='.$document2->wayback_date.'&dterm='.$deleted_term.'" target="_blank">Animated deletion</a></td></tr>';

    $dateprepost = $document->format_wayback_date() .' to '. $document2->format_wayback_date();

    $diff_out = $document->diff($document2, $deleted_term);
    $diff_out = preg_replace('/\b('.$deleted_term.')\b/i','<span style="background-color: #FFFF00">$1</span>' , $diff_out);
    $diff_out = str_replace("Differences</th>", "Differences: $dateprepost</th>", $diff_out);
    echo '<tr><th>&nbsp;</th><td><pre>'.$diff_out.'</pre></td></tr>';

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

    $query3b = $client->createSelect();
    $query3b->setQuery('url_norm:'.$document->url_norm);
    $query3b->setDocumentClass('TemporalDoc');
    $query3b->addSort('id', $query::SORT_ASC);
    $resultset3b = $client->select($query3b);
    $date_firstver = date_create($resultset3b->getIterator()->current()->format_wayback_date());

    //Iterate over the 3rd query results
    //$innerloop = 0;
    foreach ($resultset3 as $document3) {

        $date_add = date_create($document3->format_wayback_date());
        if ($date_add >= $date_del) {
            continue;//if added more than once
        }

        $serp_add_text = 'Addition';
        if ($date_firstver == $date_add) {
            $serp_add_text = 'First version';
        }

        $slid_diff_page = $document3;

        if ($is_deleted_phrase) {

            //To calculate the content lifespan of a deleted phrase,
            //Take the later of the added terms (doc3)
            //Search for the phrase in the text field with dates >= doc3
            //Sort smallest to largest, the smallest is the addition

            //Get the version of the page with the phrase addition, calculate lifespan
            $query4 = $client->createSelect();
            $query4->setQuery('url_norm:'.$document->url_norm);
            $query4->createFilterQuery('delphr')->setQuery('text:"'.$deleted_term.'"');
            $wb4 = str_replace(' ', 'T', $document3->format_wayback_date()).'Z';
            $query4->createFilterQuery('delrange')->setQuery('validity_range:['.$wb4.' TO *]');
            $query4->setDocumentClass('TemporalDoc');
            $query4->addSort('id', $query::SORT_ASC);//if added more than once
            $resultset4 = $client->select($query4);

            //Iterate over the 4th query results
            $document4 = $resultset4->getIterator()->current();
            $slid_diff_page = $document4;
            $date_add = date_create($document4->format_wayback_date());
        }


        $ddif = date_diff($date_add, $date_del)->format('%y year and %a day');
        $ddifarr = explode(' and ', $ddif);
        $ddifpr = $ddifarr[0];
        if (str_starts_with($ddifpr, '0')) {
            $ddifpr = $ddifarr[1];
        }

         echo '<tr><th>&nbsp;</th><td><a href="' . $slid_diff_page-> get_wayback_uri() . '">'.$serp_add_text.' memento</a> ('.substr($slid_diff_page->format_wayback_date(), 0,10).', '.$ddifpr.' lifespan) &middot; <a href="diff-slider.php?page='.urlencode($document->url_norm).'&wbdate1='.$slid_diff_page->wayback_date.'&wbdate2='.$document2->wayback_date.'&dterm='.$deleted_term.'">Sliding diff</a></td></tr>';
         break;
    }

    echo '</table>';
}

$newget = $_GET;
$newget['pgnum'] = 1;
echo '<hr/><a href="deletion_results.php?'.http_build_query($newget).'">&laquo;</a> ';
//To do: When a lot of pages truncate how many are shown
for ($i = 1; $i <= ceil($resultset->getNumFound() / $results_pp); $i++) {

    $newget['pgnum'] = $i;
    $pagtext = $i;
    if ($pgnumb == $i) {
        $pagtext = "<b style=\"background-color: #DDDDCC;\">$pagtext</b>";
    }
    echo '<a href="deletion_results.php?'.http_build_query($newget)."\">$pagtext</a> ";
}
$newget['pgnum'] = ceil($resultset->getNumFound() / $results_pp);
echo '<a href="deletion_results.php?'.http_build_query($newget).'">&raquo;</a> ';

}//end GET

htmlFooter();
