<?php

/**
 * A class for holding basic calendar information
 * @package awl
 */
class CalendarInfo {
  public $url;
  public $displayname;
  public $getctag;

  function __construct( $url, $displayname = null, $getctag = null ) {
    $this->url = $url;
    $this->displayname = $displayname;
    $this->getctag = $getctag;
  }

  function __toString() {
    return( '(URL: '.$this->url.'   Ctag: '.$this->getctag.'   Displayname: '.$this->displayname .')'. "\n" );
  }
}

if(!defined("_FSOCK_TIMEOUT")){
  define("_FSOCK_TIMEOUT", 10);
}
