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

function _moduleContent(&$smarty, $module_name){

    include_once "libs/paloSantoDB.class.php";
    include_once "libs/paloSantoConfig.class.php";
    include_once "modules/$module_name/configs/default.conf.php";
    include_once "modules/$module_name/libs/phoneBook.class.php";
    include_once "modules/$module_name/libs/entry.class.php";
    include_once "libs/paloSantoGrid.class.php";

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
    global $count;
    global $phoneBook;

    //Create configuration array
    $arrConf = array_merge($arrConf,$arrConfModule);
    $arrConf["module_name"]= $module_name;
    $arrLangModule = array_merge($arrLangModule,$arrLangModule);

    //folder path for custom templates
    $base_dir=dirname($_SERVER['SCRIPT_FILENAME']);
    $templates_dir=(isset($arrConf['templates_dir']))?$arrConf['templates_dir']:'themes';
    $local_templates_dir="$base_dir/modules/$module_name/".$templates_dir.'/'.$arrConf['theme'];

    $phoneBook = new PhoneBook();

    if (isset($_REQUEST["add"])){
         $smarty->assign("name","");
         $smarty->assign("number","");
         $smarty->assign("id","");
         $content= $smarty->fetch("$local_templates_dir/phonebookEntryForm.tpl");
    } else if (isset($_REQUEST["edit"])){
         $entry = $phoneBook->getEntry($_REQUEST["id"]);
         $smarty->assign("name",$entry->name);
         $smarty->assign("number",$entry->number);
         $smarty->assign("id",$entry->id);
         $content= $smarty->fetch("$local_templates_dir/phonebookEntryForm.tpl");
    } else if (isset($_REQUEST["save"])){
         //validdate entries
         $errorMsg = validate($arrLangModule);
         if (empty($errorMsg)){
              if (saveEntry()){
                  $content = getGridContent($smarty, $module_name, $arrLangModule);
              }
              else  {
                  updateFormForRedisplay ($smarty,$arrLangModule["Name or number already exists!"]);
                  $content = $smarty->fetch("$local_templates_dir/phonebookEntryForm.tpl");
              }
         }else{
                 updateFormForRedisplay($smarty,$errorMsg);
                 $content = $smarty->fetch("$local_templates_dir/phonebookEntryForm.tpl");
               }
    } else if (isset($_REQUEST["delete"])){
        //delete all items selected for deletion
        foreach ($_REQUEST["id"] as $id){
           $phoneBook->deleteEntry($id);
        }
        $content = getGridContent($smarty, $module_name, $arrLangModule);
    }else if (isset($_REQUEST["search"])){
        $content = getGridContent($smarty, $module_name, $arrLangModule,$_REQUEST["searchText"]);
    } else{
        $content = getGridContent($smarty, $module_name, $arrLangModule);
    }
    return $content;
}

//Redisplay entry form when error detected!
function updateFormForRedisplay(&$smarty,$errorMsg){
         $smarty->assign("name",$_REQUEST["name"]);
         $smarty->assign("number",$_REQUEST["number"]);
         $smarty->assign("id",$_REQUEST["id"]);
         $smarty->assign("errorMsg",$errorMsg);
}

//validation method
function validate ($arrLangModule){
   $errorMsg = "";
   if (empty($_REQUEST["name"]) or strlen($_REQUEST["name"])<3){
       $errorMsg=$arrLangModule["Name does not appear to be valid"]."<br/>";
   }
   if (empty($_REQUEST["number"])or !is_numeric(preg_replace("/\s*/","",$_REQUEST["number"]))){
       $errorMsg.=$arrLangModule["Number does not appear to be valid"];           
   }
   return $errorMsg;    
}

function saveEntry(){
    global $phoneBook;
    $entry = new Entry($_POST["name"],$_POST["number"],$_POST["id"]);
    return $phoneBook->saveEntry($entry);
}

function getGridContent($smarty, $module_name, $translations, $searchText=null)
{

    if (($_GET['exportcsv']&& $_GET['exportcsv']=='yes') || ($_GET['exportpdf']&& $_GET['exportpdf']=='yes')  || ($_GET['exportspreadsheet']&& $_GET['exportspreadsheet']=='yes')){
        $raw = true;
        $limit  = 99999999;
    } else{
          $raw=false;
          $limit  = 20;
    }
    $data = getFormatedPhoneBookData($module_name,$searchText,$raw);
    $total  = count($data);
    $grid  = new paloSantoGrid($smarty);
    $nav = isset($_GET['nav'])?$_GET['nav']:NULL;
    $start = isset($_GET['start'])?$_GET['start']:NULL;
    $offset = $grid->getOffSet($limit,$total,$nav,$start);
    $start=($total==0) ? 0 : $offset + 1;
    $end    = ($offset+$limit)<=$total ? $offset+$limit : $total;
    //$smarty->assign("url","?menu=".$module_name);

    if ($total <= $limit)
        $dataSlice = $data;
    else $dataSlice = array_slice($data, $offset, $limit);

    $arrGrid = array("title"    => $translations["Phone Book"],
        "url"      => array(
            'menu' => $module_name,
            'nav' => $nav,
            'searchText' =>"$searchText",
            'search'=>''
        ),
        "icon"     => "images/endpoint.png",
        "width"    => "99%",
        "start"    => $start,
        "end"      => $end,
        "total"    => $total,
        "columns"  => array(0 => array("name"      => "<input type='submit' name='delete' value='{$translations['Delete']}' class='button' onclick=\" return confirmSubmit('{$translations["Are you sure you wish to delete these entries?"]}');\" />",
                                       "property1" => ""),
                            1 => array("name"      => $translations["Name"],
                                       "property1" => ""),
                            2 => array("name"      => $translations["Number"],
                                       "property1" => "")));
        //Remove delete column for export to csv,open office etc
        if ($raw){
            $arrGrid["columns"] = array(0 => array("name"      => $translations["Name"],
                                           "property1" => ""),
                                        1 => array("name"      => $translations["Number"],
                                           "property1" => ""));
        }

    $html_filter = "<input type='textbox' size=25 name='searchText' value ='{$searchText}'/> <input type='submit' name='search' value='{$translations['Search']}' class='button' /><br/>";
    $html_filter .= "<input type='submit' name='add' value='{$translations['Add']}' class='button' />";

    $grid->showFilter($html_filter);
    $grid->enableExport();
    $grid->setNameFile_Export($translations["PhoneBook.xls"]);
    //$content  = "<form style='margin-bottom:0;' method='POST' action='?menu=$module_name$urlQueryString'>";
    $content = $grid->fetchGrid($arrGrid, $dataSlice,$translations);
    //$content .= "</form>";
    return $content;
}


function getFormatedPhoneBookData($module_name,$searchText,$raw=false){
    global $phoneBook;
    if (empty($searchText)) $data = $phoneBook->getPhoneList();
    else $data = $phoneBook->getFilteredPhoneList($searchText);
    $returnData = array();
    foreach ($data as $item){
        if (!$raw){
            $tmpData[0]="<input type='checkbox' name='id[]' value='$item[id]'/>";
            $tmpData[1]="<a href='/index.php?menu=$module_name&edit=true&id=$item[id]'>$item[name]</a>";
            $tmpData[2]=$item["number"];
        }else{
            $tmpData[0]=$item["name"];
            $tmpData[1]=$item["number"];
        }
        $returnData[]=$tmpData;
    }
    return $returnData;
}