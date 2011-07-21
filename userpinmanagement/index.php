<?php
/*
  +----------------------------------------------------------------------+
  | Copyright (c) 2011 Jumping Bean                                      |
  +----------------------------------------------------------------------+
  | Unit 3 Appian Place, 373 Kent Avenue, Ferndale, South Africa         |
  | Tel:011 781 8014                                                     |
  | http://www.jumpingbean.co.za     http://www.ip-pbx.co.za             |
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

function _moduleContent(&$smarty, $module_name){

    include_once "libs/paloSantoDB.class.php";
    include_once "libs/paloSantoConfig.class.php";
    include_once "modules/$module_name/configs/default.conf.php";
    include_once "libs/paloSantoGrid.class.php";
    include_once "libs/paloSantoForm.class.php";
    include_once "libs/misc.lib.php";
    include_once "modules/userpinmanagement/libs/userPinManagement.class.php";
    include_once "libs/paloSantoACL.class.php";


    //Get translations
    $lang=get_language();
    $base_dir=dirname($_SERVER['SCRIPT_FILENAME']);
    $lang_file="modules/$module_name/lang/$lang.lang";
    if (file_exists("$base_dir/$lang_file")) include_once "$lang_file";
    else include_once "modules/$module_name/lang/en.lang";

    //global variables
    global $arrConf;
    global $arrConfModule;
    global $arrLang;
    global $arrLangModule;

    //Create configuration array
    $arrConf = array_merge($arrConf,$arrConfModule);
    $arrConf["module_name"]= $module_name;
    $arrLang = array_merge($arrLang,$arrLangModule);

    //folder path for custom templates
    $base_dir=dirname($_SERVER['SCRIPT_FILENAME']);
    $templates_dir=(isset($arrConf['templates_dir']))?$arrConf['templates_dir']:'themes';
    $local_templates_dir="$base_dir/modules/$module_name/".$templates_dir.'/'.$arrConf['theme'];

    if (isset($_REQUEST["cancel"])){
        $content = getGridContent($smarty, $module_name, $arrLang, $local_templates_dir);
    } else if (isset($_REQUEST["add"])){
        $content = getAddUserpinDisplayForm($smarty,$local_templates_dir);
    }  else if (isset($_REQUEST["addHistorical"])){
        $content = getAddHistoricalUserpinDisplayForm($smarty,$local_templates_dir);
    } else if(isset($_REQUEST["save"])){
        $content = saveForm($smarty,$local_templates_dir,$module_name);
    } else if(isset($_REQUEST["Delete"])){
        $userPinManagement = new userPinManagement();
        foreach($_REQUEST["userpinId"] as $userpinId){
            $userPinManagement->deactiveateUserPin($userpinId);
        }
        $userPinManagement->syncPinSetsToFreePBX();
        $content = getGridContent($smarty, $module_name, $arrLang, $local_templates_dir);
    } else if (isset($_REQUEST["type"])){
        switch ($_REQUEST["type"]){
        case "reassign":
            $content = getReassignDisplayForm($smarty,$local_templates_dir);
            break;
        case "changepassword":
            $content=getChangePasswordDisplayForm($smarty,$local_templates_dir);
            break;
        case "userlookup":
            $content = getAvailableUsers();
            break;
        case "genpassword":
            $userPinManagement= new UserPinManagement();
            $content = $userPinManagement->getNewPassword();
            break;
        default:
            $content = getGridContent($smarty, $module_name, $arrLang, $local_templates_dir);
        }
    } else {
        $content = getGridContent($smarty, $module_name, $arrLang, $local_templates_dir);
    }
    return $content;
}


function getAddHistoricalUserpinDisplayForm(&$smarty,$local_templates_dir){
    global $arrLang;
    //Create Add Userpin form
    $smarty->assign("type","addhistorical");
    $userPinManagement = new userPinManagement();
    $paloForm = getAddHistoricalUserpinDisplayRawForm($smarty, $userPinManagement);
    $values = array("startDate"=>$_REQUEST["startDate"],"endDate"=>$_REQUEST["endDate"],"pinset1"=>$_REQUEST["pinset1"],"username1"=>$_REQUEST["username1"]);
    $form = $paloForm->fetchForm("$local_templates_dir/userpin.tpl", "Add New Pin",$values);
    return $form;
}

function getAddHistoricalUserpinDisplayRawForm($smarty,$userPinManagement){
    global $arrLang;
    $paloForm = getUserPinForm($smarty,$userPinManagement);
    //set fields readonly
    //$paloForm->arrFormElements["pinset"]["INPUT_EXTRA_PARAM"]=array("readonly"=>"true");
    $paloForm->arrFormElements["username1"]["LABEL"].="+";
    $paloForm->arrFormElements["pin1"]["LABEL"].="+";
    $paloForm->arrFormElements["pin1"]["INPUT_EXTRA_PARAM"]=array("");
    $paloForm->arrFormElements["pinset1"]["LABEL"].="+";
    $pinsets = $userPinManagement->getActivePinSets();
    unset($pinsets['%']); //remove the all option.
    $paloForm->arrFormElements["pinset1"]["INPUT_EXTRA_PARAM"]=$pinsets;
    $paloForm->arrFormElements["pinset1"]["INPUT_TYPE"]="SELECT";
    $paloForm->arrFormElements["pinset1"]["SIZE"]=1;
    $pinset_ids = array_keys($pinsets);
    //Get all users included deactivated ones.
    $avail_users = $userPinManagement->getUsers(true);
    $users;
    foreach($avail_users as $user){
        $users[$user["id"]]=$user["username"];
    }
    $paloForm->arrFormElements["username1"]["INPUT_EXTRA_PARAM"]=$users;
    $paloForm->arrFormElements["startDate"]["LABEL"]=$arrLang["Start Date"];
    $paloForm->arrFormElements["startDate"]["REQUIRED"]="yes";
    $paloForm->arrFormElements["startDate"]["INPUT_TYPE"]="DATE";
    $paloForm->arrFormElements["startDate"]["VALIDATION_TYPE"]="ereg";
    $paloForm->arrFormElements["startDate"]["VALIDATION_EXTRA_PARAM"]="^[[:digit:]]{1,2}[[:space:]]+[[:alnum:]]{3}[[:space:]]+[[:digit:]]{4}$";

    $paloForm->arrFormElements["endDate"]["LABEL"]=$arrLang["End Date"];
    $paloForm->arrFormElements["endDate"]["REQUIRED"]="yes";
    $paloForm->arrFormElements["endDate"]["INPUT_TYPE"]="DATE";
    $paloForm->arrFormElements["endDate"]["VALIDATION_TYPE"]="ereg";
    $paloForm->arrFormElements["endDate"]["VALIDATION_EXTRA_PARAM"]="^[[:digit:]]{1,2}[[:space:]]+[[:alnum:]]{3}[[:space:]]+[[:digit:]]{4}$";
    return $paloForm;
}



function getAddUserpinDisplayForm(&$smarty,$local_templates_dir){
    //Create Add Userpin form
    $smarty->assign("type","addnew");
    $userPinManagement = new userPinManagement();
    $paloForm = getAddUserpinDisplayRawForm($smarty, $userPinManagement);

    //Javascript function to change drop down box options when pinset is changed
    $js = ' $(document).ready(function() {$("#pinset1").change(';
    $js.='function() {';
    //$js.="$.get('index.php',{menu:'userpinmanagement',type:'userlookup',id:$('#pinset option:selected')},function(data){alert.show(data);}";
    $js.="var id= $(\"#pinset1 option:selected\").val();";
    $js.="$('#username1').load('index.php?menu=userpinmanagement&rawmode=yes&type=userlookup&id='+id); genPin();";
    $js.="";
    $js.='});';

    $js.='$("#autogen").click(function (){ ';
    $js.=' genPin();';
    $js.='});';
    $js.='});';

    $js.='function genPin() { var id= $("#pinset1 option:selected").val();';
    $js.='$.get("index.php?menu=userpinmanagement&rawmode=yes&type=genpassword&id="+id, function(data){ $("input[name=\'pin1\']").val(data); })}; ';

    $smarty->assign("script",$js);
    $autogen="<a href='#' id='autogen'>Generate Password</a>";
    $smarty->assign("autogen",$autogen);
    $values = array("pinset1"=>$_REQUEST["pinset1"],"username1"=>$_REQUEST["username1"]);
    $form = $paloForm->fetchForm("$local_templates_dir/userpin.tpl", "Add New Pin",$values);
    return $form;
}

function getAddUserpinDisplayRawForm($smarty,$userPinManagement){
    $paloForm = getUserPinForm($smarty,$userPinManagement);
    //set fields readonly
    //$paloForm->arrFormElements["pinset"]["INPUT_EXTRA_PARAM"]=array("readonly"=>"true");
    $paloForm->arrFormElements["username1"]["LABEL"].="+";
    $paloForm->arrFormElements["pin1"]["LABEL"].="+";
    $paloForm->arrFormElements["pin1"]["INPUT_EXTRA_PARAM"]=array("readonly"=>"true");
    $paloForm->arrFormElements["pinset1"]["LABEL"].="+";
    $pinsets = $userPinManagement->getActivePinSets();
    unset($pinsets['%']); //remove the all option.
    $paloForm->arrFormElements["pinset1"]["INPUT_EXTRA_PARAM"]=$pinsets;
    $paloForm->arrFormElements["pinset1"]["INPUT_TYPE"]="SELECT";
    $paloForm->arrFormElements["pinset1"]["SIZE"]=1;
    //since there is not userpinset id coming through we need to look up the default;
    //may not be empty if reposting form due to error.
    if (empty($paloForm->arrFormElements["username1"]["INPUT_EXTRA_PARAM"])){
        $pinset_ids = array_keys($pinsets);
        $default_avail_users = $userPinManagement->getAvailableUsersForPinset($pinset_ids[0]);
        $paloForm->arrFormElements["username1"]["INPUT_EXTRA_PARAM"]=$default_avail_users;
    }
    return $paloForm;

}

function getReassignDisplayForm(&$smarty,$local_templates_dir){
    //Create Re-assign form
    $smarty->assign("type","reassign");
    $userPinManagement = new userPinManagement();
    $userpin = $userPinManagement->getUserPin($_REQUEST["userpin_id"]);
    $paloForm = getReassignDisplayRawForm($smarty,$userPinManagement);
    //set default values
    $values = array("pinset1" =>$userpin["lpinset"], "pinset_id"=>$userpin["pinset_id"],"pin1"=>$userpin["pin"],"username1"=>$userpin["username"]);
    $form = $paloForm->fetchForm("$local_templates_dir/userpin.tpl", "Re-assign Pin",$values);
    return $form;
}

function getReassignDisplayRawForm($smarty,$userPinManagement){
    $paloForm = getUserPinForm($smarty,$userPinManagement);
    $paloForm->arrFormElements["pinset1"]["INPUT_EXTRA_PARAM"]=array("readonly"=>"true");
    $paloForm->arrFormElements["pin1"]["INPUT_EXTRA_PARAM"]=array("readonly"=>"true");
    $paloForm->arrFormElements["username1"]["LABEL"].="+";
    return $paloForm;
}

function getChangePasswordDisplayForm(&$smarty,$local_templates_dir){
     //Create Change Password form
    $smarty->assign("type","changepassword");
    $userPinManagement = new userPinManagement();
    $userpin = $userPinManagement->getUserPin($_REQUEST["userpin_id"]);

    //Javascript function to change drop down box options when pinset is changed
    $js = ' $(document).ready(function() {';
    $js.='$("#autogen").click(function (){ ';
    $js.=' genPin();';
    $js.='});';
    $js.='});';
    $js.='function genPin() { var id= $("#pinset1 option:selected").val();';
    $js.='$.get("index.php?menu=userpinmanagement&rawmode=yes&type=genpassword&id="+id, function(data){ $("input[name=\'pin1\']").val(data); })}; ';

    $smarty->assign("script",$js);
    $autogen="<a href='#' id='autogen'>Generate Password</a>";
    $smarty->assign("autogen",$autogen);

    $paloForm = getChangePasswordDisplayRawForm($smarty, $userPinManagement);
    //set default values
    $values = array("pinset1" =>$userpin["lpinset"], "pinset_id"=>$userpin["pinset_id"],"pin1"=>$userpin["pin"],"username1"=>$userpin["username"]);
    $form = $paloForm->fetchForm("$local_templates_dir/userpin.tpl", "Re-assign Pin",$values);
    return $form;
}

function getChangePasswordDisplayRawForm($smarty,$userPinManagement){
    $paloForm = getUserPinForm($smarty,$userPinManagement);
    //set fields readonlu
    $paloForm->arrFormElements["pinset1"]["INPUT_EXTRA_PARAM"]=array("readonly"=>"true");
    $paloForm->arrFormElements["username1"]["INPUT_EXTRA_PARAM"]=array("readonly"=>"true");
    $paloForm->arrFormElements["username1"]["INPUT_TYPE"]="TEXT";
    $paloForm->arrFormElements["pin1"]["LABEL"].="+";
    $paloForm->arrFormElements["pin1"]["INPUT_EXTRA_PARAM"]=array("readonly"=>"true");
    return $paloForm;
}

function saveForm(&$smarty,$local_templates_dir,$module_name){
    //populate params with values from submitted form
    //only one here but may add more later
    global $arrLang;

    $params = array(
        'pinset1' => '',
        'pin1'=>'',
        'username1'=>'',
        'userpin_id'=>'',
        'type'=>'',
        'startDate'=>'',
        'endDate'=>'',
        );

    foreach (array_keys($params) as $param) {
        if (!empty ($_REQUEST["$param"]))
            $params["$param"] = $_REQUEST["$param"];
    }

   $userPinManagement = new userPinManagement();
   $type = ($params['type']);
   switch ($type){
        case 'addhistorical':
            $form = getAddHistoricalUserpinDisplayRawForm($smarty,$userPinManagement);
            break;
        case 'addnew':
            $form = getAddUserpinDisplayRawForm($smarty,$userPinManagement);
            break;
        case 'reassign':
            $form =  getReassignDisplayRawForm($smarty,$userPinManagement);
            break;
        case 'changepassword':
            $form = getChangePasswordDisplayRawForm($smarty,$userPinManagement);
            break;
    }

    //validate that the update does not violate pinset constraints
    $valid = $userPinManagement->validatePin($params);
    $validDateRange=true;
    if (!empty($params["startDate"]) && !empty($params["endDate"])){
        $start = strtotime ($params["startDate"]." 00:00:00");
        $end = strtotime ($params["endDate"]." 23:59:59");
        print_r($start."--".$end);
        if ($start>$end) $validDateRange= false;
    }



    //validate form
    if ((isset($form) && !$form->validateForm($params)) || !$valid || !$validDateRange) {
       $errorFields = array_keys($form->arrErroresValidacion);
       if (!is_array($errorFields)) $errorFields=array();
        if (!$valid){
           array_push($errorFields,$arrLang["There was a violation for this combination of input."]);
       }
        if (!$validDateRange){
           array_push($errorFields,$arrLang["Start date must be before end date."]);
       }


       $smarty->assign(array(
            'mb_title' => _tr('Validation Error'),
            'mb_message' => '<b>' . _tr('The following fields contain errors') . ':</b><br/>' .
            implode(', ',$errorFields)));

       switch ($type){
            case 'addhistorical':
                $content = getAddHistoricalUserpinDisplayForm($smarty,$local_templates_dir);
                break;
            case 'addnew':
                $content = getAddUserpinDisplayForm($smarty,$local_templates_dir);
                break;
            case 'reassign':
                $content =  getReassignDisplayForm($smarty,$local_templates_dir);
                break;
            case 'changepassword':
                $content = getChangePasswordDisplayForm($smarty,$local_templates_dir);
                break;
            default:
                $content="error -$type";
       }
    } else {
        switch ($type){
            case 'addhistorical':
                $userPinManagement->addUserPin($params["pinset1"], $params["username1"], $params["pin1"],$params["startDate"],$params["endDate"]);
                break;
            case 'addnew':
                $userPinManagement->addUserPin($params["pinset1"], $params["username1"], $params["pin1"]);
                break;
            case 'reassign':
                $userPinManagement->updateUserPin($params["pinset1"], $params["username1"], $params["pin1"],$params["userpin_id"]);
                break;
            case 'changepassword':
                $userPinManagement->deactiveateUserPin($params["userpin_id"]);
                $userPinManagement->addUserPin($params["pinset1"], $params["username1"], $params["pin1"]);
                break;
            default:
                $content="error  -$type";
        }
        $userPinManagement->syncPinSetsToFreePBX();
        global $arrLang;
        $content = getGridContent($smarty, $module_name, $arrLang, $local_templates_dir);
       }
       return $content;
}


function getUserPinForm($smarty,$userPinManagement){
    global $arrLang;
    $smarty->assign("Cancel",$arrLang["Cancel"]);
    $smarty->assign("Submit",$arrLang["Submit"]);
    
    if (!empty($_REQUEST["userpin_id"])){
        $userpin = $userPinManagement->getUserPin($_REQUEST["userpin_id"]);
        $availableUser = $userPinManagement->getAvailableUsersForPinset($userpin["pinset_id"]);
        //can reassign to unkown user!
        if ($_REQUEST["type"]=="reassign"){
            $unknownuser_id = $userPinManagement->getUnkownUserId();
            if (!array_key_exists($unknownuser_id, $availableUser))
                $availableUser[$unknownuser_id] = "Unknown";
        }
    }
    else $availableUser ='';

    $formElements = array(
        "pinset1" =>  array("LABEL"                  => $arrLang["Pin Set"],
                            "REQUIRED"               => "yes",
                            "INPUT_TYPE"             => "TEXT",
                            "VALIDATION_TYPE"        => "TEXT",
                            "SIZE"                   => "25"),

        "pin1"       => array("LABEL"                 => $arrLang["Pin"],
                            "REQUIRED"               => "yes",
                            "INPUT_TYPE"             => "TEXT",
                            "VALIDATION_TYPE"        => "ereg",
                            "VALIDATION_EXTRA_PARAM" => "^[[:digit:]]{1,10}$",
                            "SIZE"                   => "25"),

        "username1" =>  array("LABEL"                  => $arrLang["User Name"],
                            "REQUIRED"               => "yes",
                            "INPUT_TYPE"             => "SELECT",
                            "INPUT_EXTRA_PARAM"      => $availableUser,
                            "VALIDATION_TYPE"        => "TEXT"),
        "userpin_id"=> array("LABEL"                 => $arrLang["User Pin Id"],
                            "INPUT_TYPE"             => "HIDDEN",
                            "SIZE"                   => "25"));

    $paloForm = new paloForm($smarty, $formElements);
    return $paloForm;
}

function getGridContent($smarty, $module_name, $arrLang, $local_templates_dir){

    $limit  =20;
    $grid  = new paloSantoGrid($smarty);
    $smarty->assign("Search",$arrLang["Search"]);
    $smarty->assign("Clear",$arrLang["Clear"]);
    $smarty->assign("Add",$arrLang["Add New"]);
    $smarty->assign("AddHistorical",$arrLang["Add Historical Pin"]);
    global $userPinManagement;

    $userPinManagement = new userPinManagement();
    //Sync the user and pinset tables
    $userPinManagement->syncPinSetsFromFreePBX();
    $userPinManagement->syncUsersFromACL();

    //Create filter form
    $formElements = array(
        "pinset" =>  array("LABEL"                  => $arrLang["Pin Set"],
                            "REQUIRED"               => "yes",
                            "INPUT_TYPE"             => "SELECT",
                            "INPUT_EXTRA_PARAM"      => $userPinManagement->getActivePinSets(),
                            "VALIDATION_TYPE"        => "TEXT",
                            "MULTIPLE"               => false,
                            "SIZE"                   => "1"),
        "username" =>  array("LABEL"                  => $arrLang["User Name"],
                            "REQUIRED"               => "no",
                            "INPUT_TYPE"             => "TEXT",
                            "INPUT_EXTRA_PARAM"      => "",
                            "VALIDATION_TYPE"        => "TEXT"),
        "activeOnly" =>  array("LABEL"                => $arrLang["Active Only"],
                            "REQUIRED"               => "no",
                            "INPUT_TYPE"             => "CHECKBOX",
                            "INPUT_EXTRA_PARAM"      => "",
                            "VALIDATION_TYPE"        => ""));
    //set param defaults
    $filterParams = array(
        'username' =>'',
        'pinset' =>'%',
        'activeOnly'=>'',
    );

    //populate params with values from submitted form
    foreach (array_keys($filterParams) as $param) {
        if (isset($_REQUEST["$param"]))
            $filterParams["$param"] = $_REQUEST["$param"];
    }
    //Only for first pass want active only on!
    if (empty($_REQUEST["pinset"])){
        $filterParams["activeOnly"]="on";
    }


    $filterForm = new paloForm($smarty, $formElements);
    $htmlFilter = $filterForm->fetchForm("$local_templates_dir/filter.tpl", "",$filterParams);

    if (!$filterForm->validateForm($filterParams)) {
        $smarty->assign(array(
            'mb_title' => _tr('Validation Error'),
            'mb_message' => '<b>' . _tr('The following fields contain errors') . ':</b><br/>' .
            implode(', ', array_keys($filterForm->arrErroresValidacion)),
        ));
    }

    //Get the data for grid!
    //build up url array for use in the navigation buttons for form resubmission
    $url = array("username"=>$filterParams['username'], "pinset"=>$filterParams['pinset'],"activeOnly"=>$filterParams['activeOnly']);
    $data= formatData($userPinManagement->getPinsetUserData($filterParams));
    $total  = count($data);
    $nav = isset($_GET['nav'])?$_GET['nav']:NULL;
    $start = isset($_GET['start'])?$_GET['start']:NULL;
    $offset = $grid->getOffSet($limit,$total,$nav,$start);
    $start=($total==0) ? 0 : $offset + 1;
    $end = ($offset + $limit) <= $total ? $offset + $limit : $total;

    //Set remaining grid configs
    $arrGrid = array("title"    => $arrLang["User Management"],
        "url"      => $url,
        "start" => $start,
        "total"    =>$total,
        "end"      =>$end,
        "icon"     => "images/endpoint.png",
        "width"    => "99%",
        "columns"  => array(
                            0 => array("name"      => $arrLang["User Name"],
                                       "property1" => ""),
                            1 => array("name"      => $arrLang["Pin Set"],
                                       "property1" => ""),
                            2 => array("name"      => $arrLang["Pin"],
                                       "property1" => ""),
                            3 => array("name"      => $arrLang["Active Only"],
                                       "property1" => ""),
                            4 => array("name"      => $arrLang["Start Date"],
                                       "property1" => ""),
                            5 => array("name"      => $arrLang["End Date"],
                                       "property1" => ""),
                            6 => array("name"      => "<input type='submit' name='Delete' value='{$arrLang['Delete']}' class='button' onclick=\" return confirmSubmit('{$arrLang["Are you sure you wish to delete these user pins?"]}');\" />",
                                       "property1" => ""),
                            7 => array("name"      => $arrLang["Change Password"],
                                        "property1" => ""),
                            8 => array("name"      => $arrLang["Re-assign"],
                                        "property1" => "")));
    $grid->showFilter($htmlFilter);
    $content = $grid->fetchGrid($arrGrid, $data,$arrLang);
    return $content;
}



function formatData($data){
    $returnData = array();
    foreach ($data as $item){
        $tmpData[0]=$item["username"];
        $tmpData[1]=$item["pinset"];
        $tmpData[2]=$item["pin"];
        $tmpData[3]=$item["active"];
        $tmpData[4]=date("d-M-y",$item["startDate"]);
        if (!empty($item["endDate"])){
            $tmpData[5]=date("d-M-y",$item["endDate"]);
        }else{
            $tmpData[5]="";
        }
        $tmpData[6]="<input type ='checkbox' name='userpinId[]' value='$item[userpin_id]'/>";
        if($item["active"]){
            $tmpData[7]="<a href='?menu=userpinmanagement&userpin_id=$item[userpin_id]&type=changepassword'>Change Password</a>";
        }else{
            $tmpData[7]="&nbsp;";
        }
        $tmpData[8]="<a href='?menu=userpinmanagement&userpin_id=$item[userpin_id]&type=reassign'>Re-assign</a>";
        $returnData[]=$tmpData;
    }
    return $returnData;
}

function getAvailablePinOptions($id){
     global $userPinManagement;
     if(empty($userPinManagement)) $userPinManagement = new userPinManagement();
     $availPins = $userPinManagement->getAvailablePins();
     if (empty($availPins)) return "";
     $html = "<select name='available_$id'>";
     foreach ($availPins as $pin){
         $html.="<option>$pin[pin]</option>";
     }
     $html.="</select>";
     return $html;
}


function getAvailableUsers(){
    $userPinManagement = new UserPinManagement();
    $results = $userPinManagement->getAvailableUsersForPinset($_REQUEST[id]);
    $users = "";
    if (empty($results)) return $users;
    foreach ($results as $userId=>$username){
        $users.="<option value=$userId>$username</option>";
    }
    return $users;
}