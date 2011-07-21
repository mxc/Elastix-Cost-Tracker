<?php
/*
  +----------------------------------------------------------------------+
  | Copyright (c) 2011 Jumping Bean                                      |
  +----------------------------------------------------------------------+
  | Unit 3 Appian Place, 373 Kent Avenue, Ferndale, South Africa         |
  | Tel:011 781 8014                                                     |
  | http://www.jumpingbean.co.za       http://www.ip-pbx.co.za           |
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


require_once("/var/www/html/libs/paloSantoConfig.class.php");
require_once("/var/www/html/libs/paloSantoDB.class.php");

class CostTrackerDatabase {

    protected $conn;

    protected function getCostTrackerConnection(){

        $config = new paloConfig("/etc", "amportal.conf", "=", "[[:space:]]*=[[:space:]]*");
        $arrAMP = $config->leer_configuracion(false);

        $connStr = $arrAMP['AMPDBENGINE']['valor']."://".
        $arrAMP['AMPDBUSER']['valor']. ":".
        $arrAMP['AMPDBPASS']['valor']. "@".
        $arrAMP['AMPDBHOST']['valor']."/costtracker";

        $this->conn = new paloDB($connStr);
        if ($this->conn->connStatus)return false;
        else return true;
   }
}
?>
