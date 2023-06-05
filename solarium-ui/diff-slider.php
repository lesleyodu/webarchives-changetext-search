<?php

//Solarium initialization
require_once(__DIR__.'/init.php');
require_once(__DIR__.'/temporal_document.php');

//Solarium header
htmlHeader(true);

function format_wayback_date_utc($wayback_date, $seconds = 0) {
    $date = date_create_from_format('YmdHis', $wayback_date);
    if ($seconds > 0) {
        $date->add(new DateInterval("PT".$seconds."S"));
    }
    if ($seconds < 0) {
        $date->sub(new DateInterval("PT".abs($seconds)."S"));
    }
    $datestr = date_format($date, 'Y-m-d H:i:s');
    return str_replace(' ', 'T', $datestr).'Z';
}

//TO DO: sanitize
if(ISSET($_GET['page']) && ISSET($_GET['wbdate1']) && ISSET($_GET['wbdate2'])){

//$url_norm = 'http://thebeet.com/category/recipes';
$url_norm = urldecode($_GET['page']);
//echo urlencode($url_norm);
//$wbdate1 = '20220304010520';
$wbdate1 = $_GET['wbdate1'];
$wbdate1formatted = format_wayback_date_utc($wbdate1, 1);
//$wbdate2 = '20220313005819';
$wbdate2 = $_GET['wbdate2'];
$wbdate2formatted = format_wayback_date_utc($wbdate2);
$deleted_term = '';
if(ISSET($_GET['dterm'])) {
    $deleted_term = $_GET['dterm'];
}

//Instantiate Solarium Client
$client = new Solarium\Client($adapter, $eventDispatcher, $config);
$query = $client->createSelect();

//set up query for diffs
$query->setQuery('url_norm:'.$url_norm);
$query->createFilterQuery('delrange')->setQuery('validity_range:['.$wbdate1formatted.' TO '.$wbdate2formatted.']');

//query properties
$query->setFields(array('wayback_date','id','title', 'url', 'url_norm', 'score', 'content', 'validity_range'));
$query->addSort('id', $query::SORT_ASC);
$query->setDocumentClass('TemporalDoc');

//Make the query
$resultset = $client->select($query);
$numfound = $resultset->getNumFound();
$numver = $numfound - 1;

$slider_html = <<<SLIDER
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
<!--<div id="box">Box</div>-->
<!-- https://stackoverflow.com/questions/22885702/html-for-the-pause-symbol-in-audio-and-video-control -->
<form oninput="amount.value=rangeInput.value">
  <button type="button" name="navbegin" onclick="navigateDiff(NAV_BEGIN)">&#x23EE;</button>
  <button type="button" name="navcoalrev" onclick="navigateDiff(NAV_COAL_REV)">&#x23EA;</button>
  <input type="range" id="rangeInput" name="rangeInput" min="1" max="$numver" value="1" oninput="updateValue()">
  <!--<div align="center" style="font-size:25px;">
    <output name="amount" for="rangeInput">1</output>
  </div>-->
  <button type="button" name="navcoalforw" onclick="navigateDiff(NAV_COAL_FORW)">&#x23E9;</button>
  <button type="button" name="navend" onclick="navigateDiff(NAV_END)">&#x23ED;</button>
</form>
SLIDER;
echo $slider_html;

//echo 'NumFound: '.$numfound;

$wb_dates = array();
$diff_out_arr = array();
//Iterate over results
$outerloop = 0;
foreach ($resultset as $document) {

    $wbdoclink = '<a href="' . $document->get_wayback_uri() . '">'.$document->format_wayback_date().'</a>';
    //array_push($wb_dates, $document->format_wayback_date());
    array_push($wb_dates, $wbdoclink);
    if ($outerloop == 0) {
        echo '<hr/><table>';
        echo '<tr><th>wb_date1</th><td id="wb_date1">'.$wbdoclink.'</td></tr>';
    }


    //Get the version of the page with the deletion, get the diff
    $query2 = $client->createSelect();
    $query2->setQuery('url_norm:'.$document->url_norm);
    $query2->createFilterQuery('crawltime')->setQuery('validity_range:['.$document->get_next_wayback_date().' TO '.$document->get_next_wayback_date().']');
    $query2->setDocumentClass('TemporalDoc');
    $query2->addSort('id', $query::SORT_DESC);
    $resultset2 = $client->select($query2);

    //Iterate over the 2nd query results

    $wbdoclink2 = '';

    $innerloop = 0;
    foreach ($resultset2 as $document2) {
       $wbdoclink2 = '<a href="' . $document2->get_wayback_uri() . '">'.$document2->format_wayback_date().'</a>';
       if ($outerloop == 0 && $innerloop == 0) {
         echo '<tr><th>wb_date2</th><td id="wb_date2">'.$wbdoclink2.'</td></tr>';
       }

       //TRUE overrides $deleted_term
       $diff_out = $document->diff($document2, $deleted_term, TRUE);
       if(ISSET($_GET['dterm'])) {
           $diff_out = preg_replace('/\b('.$deleted_term.')\b/i','<span style="background-color: #FFFF00">$1</span>' , $diff_out);
       }
       if (trim($diff_out) == '') {
           $diff_out = 'Page versions are identical';
       }
       array_push($diff_out_arr, array('diff' => $diff_out));
       if ($outerloop == 0 && $innerloop == 0) {
           echo '<tr><th>diff</th><td id="diff_td"><pre>'.$diff_out.'</pre></td></tr>';
       }
       $innerloop++;
       if ($innerloop > 0) {
           break;
       }
    }
    echo '</table>';

    $outerloop++;
    if ($outerloop == $numfound - 1) {
        array_push($wb_dates, $wbdoclink2);
        break;
    }

}

$slider_wb_js = "<script type=\"text/javascript\">\n";
foreach($wb_dates as $key => $value){ 
    $slider_wb_js .= "wb_push('$value');\n";
}
foreach($diff_out_arr as $key => $value){ 
    //$slider_wb_js .= "diff_push(".str_replace(array('\u00a0', '\u00c2'), "", json_encode($value)).");\n";
    $slider_wb_js .= "diff_push(".json_encode($value).");\n";
}
$slider_wb_js .= 'updateValue();</script>';
echo $slider_wb_js;

}//end GET

else {
    echo 'PLACEHOLDER PLEASE SET ALL VARIABLES';
}

htmlFooter();

?>