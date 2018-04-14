<?php
class Log {
  public $_file;
  public $_enabled = False;

  function __construct($file, $enable){
    $this->_file = $file;
    $this->_enabled = $enable;
  }

  function write($string, $prefix=False){
    if ($this->_enabled) {
      # open file and append $string
      $fp = fopen($this->_file, "a+");
      if ($prefix != False) {
        fwrite($fp, sprintf("%s: (%s) %s\n", date("Y-m-d H:i:s"), $prefix, $string));
      } else {
        fwrite($fp, sprintf("%s: %s\n", date("Y-m-d H:i:s"), $string));
      }
      fclose($fp);
    }
  }

}
