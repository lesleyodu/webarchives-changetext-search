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

<form method="GET" action="addition_results.php">
<select name="searchtype" style="width:150px;">
  <option value="addsearch">added term/phrase</option>
</select>
<input type="text" name="aterm" value="<?php echo isset($_GET['aterm']) ? str_replace('"', '&quot;',$_GET['aterm']) : '' ?>"/>
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

$added_term = $_GET['aterm'];
//winter mushroom days cake gift gloomy mix
$query_show_text = 'added_term:'.$added_term;

//To Do: sanitize bc special characters throw exceptions, if selecting 'domain'
$is_added_phrase = FALSE;
if (str_contains($added_term, ' ')) {
    $is_added_phrase = TRUE;
    if ($added_term[0] == '"' && $added_term[strlen($added_term)-1] == '"') {
        $added_term = substr($added_term, 1, strlen($added_term)-2);
    }
    $aterms = str_replace(' ', ' OR ', $added_term);
    $query_show_text = 'added_term:('.$aterms.') text:"'.$added_term.'"';
}

$query_show_type = ($is_added_phrase) ? 'phrase' : 'term';
echo '<p><b>Search results for added '.$query_show_type.': '.$added_term.'</b></p>';

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

//To do: pagination
//$query->setStart(2)->setRows(20);

//query properties
$query->setFields(array('wayback_date','id','title', 'url', 'url_norm', 'score', 'content', 'validity_range'));
$query->addSort('score', $query::SORT_DESC);
$query->setDocumentClass('TemporalDoc');

//Make the query
$resultset = $client->select($query);
echo 'NumFound: '.$resultset->getNumFound();

//Iterate over results
$fields_to_show = array('title', 'url');
foreach ($resultset as $document) {

    echo '<hr/><table>';
    foreach ($document as $field => $value) {
        if (is_array($value)) {
            $value = implode(', ', $value);
        }
        if (in_array($field, $fields_to_show)) {
            echo '<tr><th>' . $field . '</th><td>' . $value . '</td></tr>';
        }
    }
    echo '<tr><th>addition</th><td><a href="' . $document->get_wayback_uri() . '">'.$document->format_wayback_date().'</a></td></tr>';


    echo '</table>';
}

}//end GET

htmlFooter();
