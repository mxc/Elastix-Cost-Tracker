<?php
/*
  +----------------------------------------------------------------------+
  | Copyright (c) 2011 Jumping Bean                                      |
  +----------------------------------------------------------------------+
  | Unit 3 Appian Place, 373 Kent Avenue, Ferndale, South Africa         |
  | Tel:011 781 8014                                                     |
  | http://www.jumpingbean.co.za    http://www.ip-pbx.co.za              |
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

require_once("/var/www/html/modules/phonebook/libs/costTrackerDatabase.class.php");
require_once("/var/www/html/libs/paloSantoDB.class.php");

class phoneBookRates extends CostTrackerDatabase {

    function __construct(){
           if (!$this->getCostTrackerConnection()) die ("Could not create costtracker database connection!");
    }

    function __destruct(){
        $this->conn->disconnect();
    }



   function getTotalRecords($filterParams){
       $array = array();
       $sql = "SELECT count(*) as total FROM rate where ";
       $sql .= "pattern like '%$filterParams[pattern]%'";
       $result  = $this->conn->getFirstRowQuery($sql,true);
       if (empty($result)){
           return 0;
       } else  {
           return $result["total"];
       }
   }

   function getReportData($filterParams,$limit,$offset){
       $sql = "SELECT id,pattern,amount FROM rate where pattern like '%$filterParams[pattern]%'";
       $sql .=" limit $limit offset $offset";
       $array = $this->conn->fetchTable($sql,true);
       return $array;
   }

   function saveEntry($entry){
        if (empty($entry->id)){
            return $this->addEntry($entry);
        }else{
           return $this->updateEntry($entry);
        }
    }

    function getEntry($id){
        $sql = "Select id,pattern,amount from rate where id = $id";
        $result = $this->conn->getFirstRowQuery($sql,true);
        $entry = new RateEntry($result["pattern"],$result["amount"],$result["id"]);
        return $entry;
    }

    private function addEntry($entry){
        //check to see if entry exits
        $sql = "Select pattern,amount from rate where patern like '$entry->pattern'";
        $result = $this->conn->getFirstRowQuery($sql,true);
        if (!empty($result)) return false;
        $sql = "Insert into rate (pattern,amount) values ('$entry->pattern',$entry->rate)";
        $this->conn->genQuery($sql);
        return true;
    }

    function deleteEntry($id){
        $sql = "Delete from rate where id = $id";
        $this->conn->genQuery($sql);
    }

    private function updateEntry($entry){
        $sql = "Update rate set pattern = '$entry->pattern' , amount = $entry->rate where id = $entry->id";
        return $this->conn->genQuery($sql);
    }

    function getRateForNumber($number){
        $sql = "Select pattern,amount from rate where substr('$number',0,length(pattern)) like pattern";
        $result = $this->conn->getFirstRowQuery($sql,true);
        if (empty($result)) {
            $sql = "Select pattern,amount from rate where pattern like 'default'";
            $result = $this->conn->getFirstRowQuery($sql,true);
        }
        return $result["amount"];
    }

}
?>