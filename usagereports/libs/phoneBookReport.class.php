<?php
/*
  +----------------------------------------------------------------------+
  | Copyright (c) 2011 Jumping Bean                                      |
  +----------------------------------------------------------------------+
  | Unit 3 Appian Place, 373 Kent Avenue, Ferndale, South Africa         |
  | Tel:011 781 8014                                                     |
  | http://www.jumpingbean.co.za        http://www.ip-pbx.co.za          |
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

class phoneBookReport extends CostTrackerDatabase {

    private $total=0;
    private $startDate;
    private $endDate;
    private $src;
    private $accountcode;

    function __construct(){
       if (!$this->getCostTrackerConnection()) die ("Could not create connection to costtrackerdb");
       if (isset($_SESSION["params"])){
           $params=$_SESSION["params"];
           $this->startDate = $params['date_start'];
           $this->endDate = $params['date_end'];
           $this->accountcode = $params['accountcode'];
           $this->src =$params['src'];
           $this->total=$params['total'];
       }
    }

    function __destruct(){
        $this->conn->disconnect();
        $params = array (
           'date_start'=>$this->startDate,
           'date_end'=>$this->endDate,
           'accountcode'=>$this->accountcode,
           'src'=>$this->src,
           'total'=>$this->total);
        $_SESSION["params"]=$params;
     }

   function getTotal($filterParams){
       $excludeKnownNumbers=$filterParams["excludeknownnumbers"];
       $summarise=$filterParams["summarise"];

       if (empty($filterParams["extension"])){
           $src = '%%';
       }  else {
           $src = "SIP/$filterParams[extension]";
       }

       if ($this->checkCriteriaMatch($filterParams,$src)) return $this->total;

       if ($summarise=="on"){
           $sql="Select sum(num) as num from (Select 1 as num from report ";
           $sql.=" left join phonebook on number like dst where src like '$src'";
           $sql.=" and accountcode like '%$filterParams[accountcode]%' ";
           $sql.="and calldate between '$filterParams[date_start]' and '$filterParams[date_end]' ";
           if ($excludeKnownNumbers=="on") $sql.=" and name is null ";
           $sql.=" group by accountcode,username,if(isNull(name),'Unknown','Known')) as g ";
           $result = $this->conn->getFirstRowQuery($sql,true);
           $this->total=$result["num"];
       } else{
           $sql="Select sum(num) as num from (Select 1 as num from report ";
           $sql.=" left join phonebook on number like dst where src like '$src'";
           $sql.=" and accountcode like '%$filterParams[accountcode]%' ";
           $sql.=" and calldate between '$filterParams[date_start]' and '$filterParams[date_end]' ";
           if ($excludeKnownNumbers=="on") $sql.=" and name is null ";
           $sql.=" group by src,dst,accountcode,username) as g ";
           $result = $this->conn->getFirstRowQuery($sql,true);
           $this->total=$result["num"];
       }
       return $this->total;
   }

   private function checkCriteriaMatch($filterParams,$src){

       if ($this->startDate == $filterParams['date_start'] &&
           $this->endDate == $filterParams['date_end'] &&
           $this->accountcode == $filterParams['accountcode'] &&
           $this->src ==$src) {
                return true;
           } else {
               $this->startDate = $filterParams['date_start'];
               $this->endDate = $filterParams['date_end'];
               $this->accountcode = $filterParams['accountcode'];
               $this->src =$src;
               return false;
           }
   }

   function populateReportTable(){

           //get the raw data from cdr
           $sql="truncate table report; ";
           $this->conn->genQuery($sql);

//           $sql="Create table report(id integer PRIMARY KEY AUTO_INCREMENT, dst varchar(80),duration integer,total integer,accountcode varchar(20),src varchar(80),calldate datetime,username varchar(80),cost float(10,2));";
//           $this->conn->genQuery($sql);

           $sql="Select amount from rate where pattern like 'default'";
           $result = $this->conn->getFirstRowQuery($sql,true);
           $defaultRate=$result["amount"];

           $sql = "Insert into report (dst,duration,total,accountcode,src,calldate,cost) ";
           $sql .= "SELECT dst,billsec as duration,1 as total, accountcode,left(channel,7) as src,calldate,billsec*$defaultRate/60 FROM asteriskcdrdb.cdr  ";
           $sql.=" where disposition like 'ANSWERED' and length(dst)>3";
           $this->conn->genQuery($sql);

           //Lookup user names! Could maybe do in 1 query later.
           //pins need to be unique across pinsets :(
           $sql = "Update report r inner join  userpin u on u.pin= r.accountcode inner join ctuser  ctu on ctu.id=u.user_id ";
           $sql.= " set r.username=ctu.username where unix_timestamp(calldate)>=u.startDate and (u.endDate is null or u.endDate<=unix_timestamp(callDate));";
           $this->conn->genQuery($sql);

           //Set Costs
           $sql = "Update report r inner join  rate  ";
           $sql.= " set r.cost=r.duration*rate.amount/60 where match(r.dst) against (rate.pattern);";
           $this->conn->genQuery($sql);
   }

   function getUsernameForPin($pin,$date){
       $sql="Select username,startDate,endDate from ctuser inner join userpin on user_id = ctuser.id  where pin='$pin' and ";
       $sql.=" from_unixtime(startDate)<='$date' and (from_unixtime(endDate)>='$date' or endDate is null) ";
       $results = $this->conn->getFirstRowQuery($sql,true);
       return $results["username"];
   }

   function getReportData($filterParams,$limit,$offset){
       $excludeKnownNumbers=$filterParams["excludeknownnumbers"];
       $summarise=$filterParams["summarise"];

       if (empty($filterParams["extension"])){
           $src = '%%';
       }  else {
           $src = "SIP/$filterParams[extension]";
       }


       if($summarise=="on"){
           //Aggregate and return
           $sql="Select sum(duration) as duration,sum(total) as total,accountcode,username,if(isNull(name),'Unknown','Known') as name,sum(cost) as cost from report ";
           $sql.=" left join phonebook on number like dst where src like '$src' and calldate between '$filterParams[date_start]' and '$filterParams[date_end]' ";
           $sql.=" and accountcode like '%$filterParams[accountcode]%' ";
           if ($excludeKnownNumbers=="on") $sql.=" and name is null ";
           $sql.=" group by accountcode,username, if(isNull(name),'Unknown','Known') order by username,accountcode,if(isNull(name),'Unknown','Known'),accountcode, sum(total) desc,sum(duration) desc ";
           $sql.=" limit $limit offset $offset";
           $array = $this->conn->fetchTable($sql,true);
      }else{
           //Aggregate and return
           $sql="Select dst,sum(duration) as duration,sum(total) as total,accountcode,src,username,if(isNull(name),'Unknown',name)  as name ,sum(cost) as cost from report ";
           $sql.=" left join phonebook on number like dst where src like '$src' and calldate between '$filterParams[date_start]' and '$filterParams[date_end]'";
           $sql.=" and accountcode like '%$filterParams[accountcode]%' ";
           if ($excludeKnownNumbers=="on") $sql.=" and name is null ";
           $sql.=" group by src,dst,accountcode,username order by sum(total) desc,sum(duration) desc ";
           $sql.=" limit $limit offset $offset";
           $array = $this->conn->fetchTable($sql,true);
      }
      return $array;
   }

}
?>