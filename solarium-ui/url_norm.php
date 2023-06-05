<?php

//https://www.php.net/manual/en/function.parse-url.php
//https://github.com/ukwa/webarchive-discovery/blob/0ef8d4cb4940d5377cc247a4ba0837873fb85c23/warc-indexer/src/main/java/uk/bl/wa/util/Normalisation.java#L136
function unparse_url($parsed_url) {
  $scheme   = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : 'http://';
  $scheme   = (strtolower($scheme) == 'https://') ? 'http://' : $scheme;
  $host     = isset($parsed_url['host']) ? $parsed_url['host'] : '';
  $port     = '';//isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
  $user     = '';//isset($parsed_url['user']) ? $parsed_url['user'] : '';
  $pass     = '';//isset($parsed_url['pass']) ? ':' . $parsed_url['pass']  : '';
  $pass     = '';//($user || $pass) ? "$pass@" : '';
  $path     = isset($parsed_url['path']) ? $parsed_url['path'] : '';
  $query    = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
  $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
  $url = strtolower("$scheme$user$pass$host$port$path$query$fragment");
  $url = preg_replace("~([a-z]+://)(?:www[0-9]*|ww2|ww)[.](.+)~", "$1$2", $url);
  $url = rtrim($url, '/');
  if (preg_match("~https?://[^/]+$~", $url)) {
    $url .= '/';
  }
  return $url;
}

function url_norm($url) {
  return unparse_url(parse_url($url));
}

?>