<?php

use Solarium\Core\Client\Adapter\Curl;
use Symfony\Component\EventDispatcher\EventDispatcher;

error_reporting(E_ALL);
ini_set('display_errors', true);

if (file_exists('config.php')) {
    require('config.php');
}

require $config['autoload'] ?? __DIR__.'\\..\\..\\..\\..\\vendor\\autoload.php';

$adapter = new Curl();
$eventDispatcher = new EventDispatcher();

function htmlHeader($slider = false)
{
    echo '<html><head>';
    echo '<title>Solarium examples</title>'."\n";
    echo '<link rel="stylesheet" href="diff-table.css">'."\n";
    echo '<link rel="stylesheet" href="serp.css">'."\n";
    if ($slider) {
       echo '<link rel="stylesheet" type="text/css" href="slider.css">'."\n";
       echo '<script type="text/javascript" src="slider.js">'."\n";
    }
    echo '</head><body>'."\n";
    //echo '<nav><a href="index.html">Back to Overview</a></nav><br>';
    echo '<article>';
}

function htmlFooter()
{
    echo '</article><br>';
    //echo '<nav><a href="index.html">Back to Overview</a></nav>';
    echo '</body></html>';
}