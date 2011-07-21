<?php
/*
  +----------------------------------------------------------------------+
  | Copyright (c) 2011 Jumping Bean                                      |
  +----------------------------------------------------------------------+
  | Unit 3 Appian Place, 373 Kent Avenue, Ferndale, South Africa         |
  | Tel:011 781 8014                                                     |
  | http://www.jumpingbean.co.za    http://www.ip-pbx.co.za                                      |
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

//include_once ("modules/phonebookmaintenance/libs/entry.class.php");

require_once("/var/www/html/modules/phonebook/libs/costTrackerDatabase.class.php");

class PhoneBook extends CostTrackerDatabase {


    function __construct(){
           $this->getCostTrackerConnection();
    }

    function __destruct(){
        $this->conn->disconnect();
    }

    function getPhoneList(){
        $sql = "Select id,name,number from phonebook";
        return $this->conn->fetchTable($sql,true);
    }

    function saveEntry($entry){
        //strip out white spaces
        $entry->number = preg_replace("/\s*/","",$entry->number);
        if (empty($entry->id)){
            return $this->addEntry($entry);
        }else{
           return $this->updateEntry($entry);
        }
    }

    function getEntry($id){
        $sql = "Select id,name,number from phonebook where id = $id";
        $result = $this->conn->getFirstRowQuery($sql,true);
        $entry = new Entry($result["name"],$result["number"],$result["id"]);
        return $entry;
    }

    private function addEntry($entry){
        //check to see if entry exits
        $sql = "Select name,number from phonebook where name like '$entry->name' or number like '$entry->number'";
        $result = $this->conn->getFirstRowQuery($sql,true);
        if (!empty($result)) return false;
        $sql="Insert into phonebook (name,number) values ('$entry->name','$entry->number')";
        $this->conn->genQuery($sql);
        return true;
    }

    function deleteEntry($id){
        $sql = "Delete from phonebook where id = $id";
        $this->conn->genQuery($sql);
    }

    private function updateEntry($entry){
        $sql = "Update phonebook set number = '$entry->number' , name = '$entry->name' where id = $entry->id";
        return $this->conn->genQuery($sql);
    }



   function getFilteredPhoneList($searchText){
        $sql = "Select id,name,number from phonebook where name like '%$searchText%' or number like '%$searchText%'";
        return $this->conn->fetchTable($sql,true);
   }

   function getEntryFromNumber($number){
        $sql = "Select id,name,number from phonebook where number like '$number'";
        $result = $this->conn->getFirstRowQuery($sql,true);
        if (empty($result)) $entry = new Entry("Unknown","Unknown","-1");
        else $entry = new Entry($result["name"],$result["number"],$result["id"]);
        return $entry;
   }

}
?>
