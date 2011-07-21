<?php
/*
  +----------------------------------------------------------------------+
  | Copyright (c) 2011 Jumping Bean                                      |
  +----------------------------------------------------------------------+
  | Unit 3 Appian Place, 373 Kent Avenue, Ferndale, South Africa         |
  | Tel:011 781 8014                                                     |
  | http://www.jumpingbean.co.za                                         |
  +----------------------------------------------------------------------+
  | The contents of this file are subject to the General Public License  |
  | (GPL) Version 2 (the "License"); you may not use this file except in |
  | compliance with the License. You may obtain a copy of the License at |
  | http://www.opensource.org/licenses/gpl-license.php                   |
  |                                                                      |
  | Software distributed under the License is distributed on an "AS IS"  |
  | basis, WITHOUT WARRANTY OF ANY KIND, either express or implied. See  |
  | the License for the specific language governing rights and           |
  | limitations under the License.                                       |
  +----------------------------------------------------------------------+
 */

class RateEntry {
    public $pattern;
    public $rate;
    public $id;

    function __constructDefault(){
        //default no arg constructor
    }

    function __construct($pattern,$rate,$id=null){
          $this->pattern = $pattern;
          $this->rate = $rate;
          $this->id = $id;
    }

}
?>
