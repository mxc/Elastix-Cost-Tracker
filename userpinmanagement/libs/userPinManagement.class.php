<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of userPinManagement
 *
 * @author mark
 */

include_once "libs/paloSantoConfig.class.php";
include_once "libs/paloSantoACL.class.php";
require_once("/var/www/html/modules/phonebook/libs/costTrackerDatabase.class.php");

class userPinManagement extends CostTrackerDatabase {
    private $aclConn;
    private $amportalConn;
    private $acl; //paloACL object

    function __construct(){
           if (!$this->getCostTrackerConnection()) die ("Could not create phonebook database connection!");
           if (!$this->getAmportalConnection()) die ("Could not create amportal database connection!");
           if (!$this->getACLConnection()) die ("Could not create acl database connection!");
           //get an paloACL object
           global $arrConf;
           $url = $arrConf['database_path'].'/acl.db';
           $this->acl= new paloACL($url);

    }

    function __destruct(){
        $this->conn->disconnect();
        $this->amportalConn->disconnect();
        $this->aclConn->disconnect();
    }

    //Sync Elastix users with cost tracker user db.
    //Elastix users are removed on deletion and need to
    //keep history
    function syncUsersFromACL(){
        $existingUsers = $this->getUsers();
        //$sql = "select id,username from acl_user";
        $aclUsers = array();
        $results = $this->acl->getUsers();
        foreach($results as $acluser){
            $aclUsers[$acluser[0]]=array("username" => $acluser[1],"lname" => $acluser[2]);
        }
        //create new users that are in the acl database
        $newUsers = array_diff(array_keys($aclUsers),array_keys($existingUsers));
        foreach ($newUsers as $newUser){
            $sql = "Insert into ctuser (acluser_id,username,lname,foundDate,active) values('$newUser','".$aclUsers["$newUser"]['username']."','".$aclUsers["$newUser"]['lname']."',".time().",1)";
            $this->conn->genQuery($sql);
        }
        //update users that have been delted from acl database
        $deletedUsers = array_diff(array_keys($existingUsers),array_keys($aclUsers));
        foreach($deletedUsers as $deleted){
            $sql = "Update ctuser set active=0, lostDate =".time()." where acluser_id = $deleted";
            $this->conn->genQuery($sql);
        }
    }


    function getUsers($all=false){
        //get users
        if(!$all) $sql="Select id,acluser_id,username,lname from ctuser where active=1";
        else $sql="Select id,acluser_id,username,lname from ctuser";
        $results = $this->conn->fetchTable($sql,true);
        $users = array();
        if (empty($results)) return $users;
        foreach($results as $user){
            $users[$user["acluser_id"]]["username"] = $user["username"];
            $users[$user["acluser_id"]]["id"] = $user["id"];
            $users[$user["acluser_id"]]["lname"] = $user["lname"];
            //$users[$user["acluser_id"]]["username"] = $user["username"];
        }
        return $users;

    }

    
    /*
     * This function updates FreePBX for changes to pinsets
     * User will have to go to freepbx to apply changes to Asterisk
     */
    function syncPinSetsToFreePBX(){
       $sql="Select freepbxpinset_id, pin from userpin inner join pinset on pinset_id = pinset.id where userpin.active=1 and pinset.active=1";
       $results = $this->conn->fetchTable($sql,true);
       foreach($results as $data){
           if (empty($freepbxPinsets[$data["freepbxpinset_id"]])){
               $freepbxPinsets[$data["freepbxpinset_id"]]= array();
           }
           array_push($freepbxPinsets[$data["freepbxpinset_id"]],$data["pin"]);
       }
       $flag=false;
       foreach(array_keys($freepbxPinsets) as $key){
           $passwords = $freepbxPinsets["$key"];
           $freepbxPins = $this->getFreePBXPins($key);
           $diff =array_diff($passwords, $freepbxPins);
           $diff2=array_diff($freepbxPins,$passwords);
           //check both ways for differences
           if (!empty($diff) || !empty($diff2)){
            $pins = implode("\n",$passwords);
            $sql ="Update pinsets set passwords ='$pins' where pinsets_id=$key";
            $this->amportalConn->genQuery($sql);
            $flag=true;
           }
       }
       //let freepbx know it needs to update
       if ($flag){
           $sql="Update admin set value='true' where variable like 'need_reload'";
           $this->amportalConn->genQuery($sql);
       }
    }

     private function getFreePBXPins($freePBXPinset_id){
        $sql = "select pinsets_id,passwords,description from pinsets where pinsets_id=$freePBXPinset_id";
        $result = $this->amportalConn->getFirstRowQuery($sql,true);
        $pins=explode("\n",$result["passwords"]);
        return $pins;
     }


    //Sync the pinset database in elastix with that in
    //Freepbx. Elastix needs to keep pinset history for reports
    //Freepbx only keep current config
    function syncPinSetsFromFreePBX(){
        $existingPinSets = $this->getElastixPinSets(); //keyed on freepbxpinset_id
        $sql = "select pinsets_id,passwords,description from pinsets";
        $results = $this->amportalConn->fetchTable($sql,true);
        $freepbxPinsets = array();
        foreach($results as $data){
            $freepxPinsets=$data["pinsets_id"];
            //add pinset to elastix if it doesn't exists
            if (!array_key_exists($data["pinsets_id"],$existingPinSets)){
                $sql="insert into pinset (freepbxpinset_id,foundDate,active,description) values ($data[pinsets_id],".time().",1,'$data[description]')";
                $this->conn->genQuery($sql);
            }
            //update the userpins tables!
            $passwordArray = explode("\n",$data["passwords"]);
            $userPins = $this->getUserPins($data["pinsets_id"]);
            $unkownuser_id = $this->getUnkownUserId();
            foreach($passwordArray as $password){
                    //insert into userpins and assign to unkown user .
                    if (!array_key_exists($password, $userPins)){
                        $pinset_id = $this->getElastixPinsetIdForFreePBXPinsetId($data["pinsets_id"]);
                        $sql = "Insert into userpin(pinset_id,user_id,pin,active,startDate)values($pinset_id,$unkownuser_id,$password,1,".time().")";
                        $this->conn->genQuery($sql);
                    }
            }
            //update pins that no longer exist!
            $deletedPins = array_diff(array_keys($userPins),$passwordArray);
            foreach($deletedPins as $deletedPin){
                $this->updateUserPinInactive($data["pinsets_id"],$deletedPin);
            }
        }
        //update deleted pinsets!
        $deletedPinsets = array_diff($existingPinSets,$freepbxPinsets);
        foreach($deletedPinsets as $deletedPinset){
            $this->updatePinsetsInactive($deletedPinset);
        }
    }

    function getUnkownUserId(){
       $sql = "Select id from ctuser where username like 'Unknown'";
       $result = $this->conn->getFirstRowQuery($sql,true);
       return $result["id"];
    }

    private function updatePinsetsInactive($pinset_id){
        $sql = "Update pinsets set active=0, lostDate=".time()." where freepbxpinset_id=$pinset_id";
        $this->conn->genQuery($sql);
        //also deactive any user pins in the defunct pinset.
        $elastixId =   $this->getElastixPinsetIdForFreePBXPinsetId($pinset_id);
        $sql="Update userpin set active=0,endDate=".time()." where pinset_id=$elastixId";
        $this->conn->genQuery($sql);
    }


    private function updateUserPinInactive($freepbxpinset_id,$pin){
        $id = $this->getElastixPinsetIdForFreePBXPinsetId($freepbxpinset_id);
        $sql = "Update userpin set active=0,endDate=".time()." where pin =$pin and pinset_id=$id";
        $this->conn->genQuery($sql);
    }

    private function getElastixPinsetIdForFreePBXPinsetId($freepbxpinset_id){
        $sql = "Select id from pinset where freepbxpinset_id = $freepbxpinset_id";
        $result = $this->conn->getFirstRowQuery($sql,true);
        if (empty($result)) {
            //should log something here!
            return -1;
        }else return $result["id"];
    }


    private function getElastixPinSets(){
            $sql="select freepbxpinset_id,foundDate,lostDate,description from pinset";
            $results = $this->conn->fetchTable($sql,true);
            $pinsetArray = array();
            if (empty($results)) return $pinsetArray;
            foreach($results as $pinset){
                $pinsetArray[$pinset["freepbxpinset_id"]]["foundDate"] =$pinset["foundDate"];
                $pinsetArray[$pinset["freepbxpinset_id"]]["lostDate"] =$pinset["lostDate"];
            }
            return $pinsetArray;
    }

   function getUserPins($pinset_id){
        //get active user pinset maps
        $sql="Select pinset_id,user_id,pin,active,startDate,endDate from userpin where pinset_id=$pinset_id and active=1";
        $results = $this->conn->fetchTable($sql,true);
        $userpins = array();
        if(empty($results)) return $userpins;
        foreach($results as $userpin){
            $userpins[$userpin["pin"]]["startDate"] = $userpin["startDate"];
        }
        return $userpins;
    }

    function getActivePinSets(){
        $sql = "select id,freepbxpinset_id,description from pinset where active=1";
        $results = $this->conn->fetchTable($sql,true);
        $array = array();
        $array["%"]="All"; //add widlcard search
        if (empty($results)) return $array;
        foreach($results as $data){
            $array[$data["id"]] = $data["description"];
        }
        return $array;
    }

    function addUserPin($pinset_id,$user,$pin,$startDate=null,$endDate=null){
        //check if we getting natural keys or surrogate keys
        //if natural convert to surrogate keys
        if (!is_numeric($pinset_id)) $pinset_id = $this->getPinsetIdFromName($pinset_id);
        if (!is_numeric($user)) $user = $this->getUserIdFromUserName ($user);

        if (empty($startDate) && empty($endDate)){
            $sql="Insert into userpin (pinset_id,user_id,pin,active,startDate) values($pinset_id,$user,$pin,1,".time().");";
            $results = $this->conn->genQuery($sql);
        } else{
            $startDate =strtotime($startDate);
            $endDate=strtotime($endDate);
            $sql="Insert into userpin (pinset_id,user_id,pin,active,startDate,endDate) values($pinset_id,$user,$pin,0,$startDate,$endDate);";
            $results = $this->conn->genQuery($sql);
        }
    }

    function updateUserPin($pinset,$user,$pin,$userpin_id){
        //we get the pinset name and not id so do a lookup
        $pinset_id = $this->getPinsetIdFromName($pinset);
        $sql="update userpin set user_id= $user where pinset_id=$pinset_id and pin=$pin;";
        $results = $this->conn->genQuery($sql);
    }

    function deactiveateUserPin($userpin_id){
        //$pinset_id = $this->getPinsetIdFromName($pinset);
        //$user_id = $this->getUserIdFromUserName($user);
        $sql="update userpin set active=0, endDate=".time()." where id=$userpin_id and active=1;";
        $results = $this->conn->genQuery($sql);
    }

    private function getPinsetIdFromName($pinset){
        $sql = "Select id from pinset where description = '$pinset'";
        $result = $this->conn->getFirstRowQuery($sql,true);
        if (empty($result)) {
            //should log something here!
            return -1;
        }else return $result["id"];
    }

    private function getUserIdFromUserName($username){
        $sql = "Select id from ctuser where username = '$username'";
        $result = $this->conn->getFirstRowQuery($sql,true);
        if (empty($result)) {
            //should log something here!
            return -1;
        }else return $result["id"];
    }

    function getPinsetUserData($filterParams){
//        //Get all users
//        $users = $this->getUsers(true);
//
        //get users with pins
        $sql = "select userpin.id as id, user_id,username,lname,pin,description as lpinset, userpin.active,startDate,endDate from userpin inner join pinset on pinset.id = pinset_id";
        $sql.=" inner join ctuser on user_id = ctuser.id";
        $sql1="";
        $sql2="";
        if (!empty($filterParams["pinset"])&& $filterParams["pinset"]!="%"){
            if (!is_numeric($filterParams["pinset"])) $filterParams["pinset"] =$this->getPinsetIdFromName($filterParams["pinset"]) ;
            $sql1=" pinset_id = $filterParams[pinset]";
        }
        if (!empty($filterParams["username"])) {
                $username = $filterParams["username"];
                $sql2= "(username like '%$username%' or  lname like '%$username%')";
        }
        if (!empty($sql1) && !empty($sql2)){
            $sql.=" where".$sql1." and ".$sql2;
        }else if (!empty($sql1) || !empty($sql2)){
            $sql.=" where ".$sql1.$sql2;
        }
        if($filterParams["activeOnly"]!="off") $sql.=" and userpin.active=1"; //active pinsets/user combos only
        
        $userpins = $this->conn->fetchTable($sql,true);
        
        $array = array();
        foreach($userpins as $userpin){
            $array[] = array(
                  "username" => $userpin["username"],
                  "lname" => $userpin["lname"],
                  "pin"  => $userpin["pin"],
                  "userid" =>$userpin["user_id"],
                  "pinset" => $userpin["lpinset"],
                  "active"=>$userpin["active"],
                  "startDate"=>$userpin["startDate"],
                  "endDate"=>$userpin["endDate"],
                  "userpin_id"=>$userpin["id"]);
        }
        return $array;
    }


    function getUserPin($userpin_id){
        $sql = "select pinset_id,freepbxpinset_id,description as lpinset,username,lname,pin from userpin";
        $sql.=" inner join pinset on pinset_id = pinset.id";
        $sql.=" inner join ctuser on user_id = ctuser.id where userpin.id=$userpin_id";
        $results = $this->conn->getFirstRowQuery($sql,true);
        return $results;
    }

    function getAvailableUsersForPinset($pinset_id){
        $sql = "select username,lname,id from ctuser";
        $sql.=" where id not in (Select user_id from userpin where pinset_id=$pinset_id and active=1 order by username)";
        $results = $this->conn->fetchTable($sql,true);
        $users = array();
        if (empty($results)) return $users;
        foreach ($results as $user){
            $users[$user["id"]]=$user["username"];
        }
        return $users;
    }

    function getNewPassword(){
        do{
            $pin = rand(1000,9999);
            //$sql = "select id from userpin where pinset_id=$pinset_id and pin=$pin";
            //make sure pin is unique across pinsets. Only easy way to match cdr accountcodes.
            //to the correct user when duplicates can occurr across pinsets. cdr record only
            //keeps pin code not the pinset to which it belongs making lookups difficult
            //for reporting
            $sql = "select id from userpin where pin=$pin";
            $results = $this->conn->getFirstRowQuery($sql,true);
        } while (!empty($results));
        return $pin;
    }

    //could probably simplify this.
    function validatePin($params){
        $user_id = $params["username1"];
        $pin = $params["pin1"];
        $startDate=$params["startDate"];
        $endDate=$params["endDate"];
        $pinset_id = $params["pinset1"];

        if (!is_numeric($user_id)) $user_id = $this->getUserIdFromUserName ($user_id);
        if (!is_numeric($pinset_id)) $pinset_id = $this->getPinsetIdFromName ($pinset_id);
        $startDate= strtotime($startDate.' 00:00:00');
        $endDate= strtotime($endDate.' 23:59:59');

        //check if pin is unique
        $sql="Select pin from userpin p inner join ctuser on ctuser.id=p.user_id";
        $sql.=" where  pin=$pin and pinset_id=$pinset_id and startDate>='$startDate' and (endDate<='$endDate' or endDate is null);";
        $result=$this->conn->getFirstRowQuery($sql);
        if (!empty($result)) return false;


        //check we don't have a setting for this user
        $sql="Select username from userpin p inner join ctuser on ctuser.id=p.user_id";
        $sql.=" where  '$startDate' between p.startDate and p.endDate and endDate is not null and user_id=$user_id";
        $result=$this->conn->getFirstRowQuery($sql);
        if (!empty($result)) return false;

        $sql="Select username from userpin p inner join ctuser on ctuser.id=p.user_id";
        $sql.=" where  '$startDate' >= p.startDate and p.endDate is null and user_id=$user_id";
        $result=$this->conn->getFirstRowQuery($sql);
        if (!empty($result)) return false;

        //
        $sql="Select username from userpin p inner join ctuser on ctuser.id=p.user_id";
        $sql.=" where  '$endDate' between p.startDate and p.endDate and endDate is not null and user_id=$user_id";
        $result=$this->conn->getFirstRowQuery($sql);
        if (!empty($result)) return false;

        return true;
    }

   private function getACLConnection(){
       global $arrConf;
       $aclDSN =$arrConf['database_path'].'/acl.db';
       $this->aclConn= new paloDB($aclDSN);
       if ($this->aclConn->connStatus)return false;
       else return true;
   }

   private function getAmportalConnection(){
        $dsn = $this->getFreePBXDSN();
        $this->amportalConn = new paloDB($dsn);
        if ($this->amportalConn->connStatus) {
            return false;
        } else {
            return true;
        }
    }

    private function getFreePBXDSN(){
        //Get the amportal configuration file to create dsn for freepbx database.
        $pConfig     = new paloConfig("/etc", "amportal.conf", "=", "[[:space:]]*=[[:space:]]*");
        $arrAMP      = $pConfig->leer_configuracion(false);

        $dsnAsterisk = $arrAMP['AMPDBENGINE']['valor']."://".
                       $arrAMP['AMPDBUSER']['valor']. ":".
                       $arrAMP['AMPDBPASS']['valor']. "@".
                       $arrAMP['AMPDBHOST']['valor']."/asterisk";
        return $dsnAsterisk;
    }

}
?>
