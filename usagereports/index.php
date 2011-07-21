<?php
/* 
  +----------------------------------------------------------------------+
  | Copyright (c) 2011 Jumping Bean                                      |
  +----------------------------------------------------------------------+
  | Unit 3 Appian Place, 373 Kent Avenue, Ferndale, South Africa         |
  | Tel:011 781 8014                                                     |
  | http://www.jumpingbean.co.za      http://www.ip-pbx.co.za            |
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

function exception_handler($exception){
    print_r($exception->getMessage());
}

function _moduleContent(&$smarty, $module_name){

    include_once "libs/paloSantoDB.class.php";
    include_once "libs/paloSantoCDR.class.php";
    include_once "libs/paloSantoConfig.class.php";
    include_once "modules/$module_name/configs/default.conf.php";
    include_once "libs/paloSantoGrid.class.php";
    include_once "libs/paloSantoForm.class.php";
    include_once "libs/misc.lib.php";
    include_once "modules/$module_name/libs/phoneBookReport.class.php";
    include_once "modules/phonebook/libs/phoneBook.class.php";
    include_once "modules/phonebook/libs/entry.class.php";
    include_once "modules/rates/libs/phoneBookRates.class.php";
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
    global $count;
    global $phoneBookReport;

    //Create configuration array
    $arrConf = array_merge($arrConf,$arrConfModule);
    $arrConf["module_name"]= $module_name;
    $arrLang = array_merge($arrLang,$arrLangModule);

    //folder path for custom templates
    $base_dir=dirname($_SERVER['SCRIPT_FILENAME']);
    $templates_dir=(isset($arrConf['templates_dir']))?$arrConf['templates_dir']:'themes';
    $local_templates_dir="$base_dir/modules/$module_name/".$templates_dir.'/'.$arrConf['theme'];

    $content = getGridContent($smarty, $module_name, $arrLang, $local_templates_dir);
 
    return $content;
}

function getGridContent($smarty, $module_name, $translations, $local_templates_dir){

    $limit  =20;
    $grid  = new paloSantoGrid($smarty);
    $smarty->assign("Filter",$translations["Filter"]);
    $smarty->assign("Clear",$translations["Clear"]);

    //Create filter form
    $formElements = array(
        "date_start"  => array("LABEL"                  => $translations["Start Date"],
                            "REQUIRED"               => "yes",
                            "INPUT_TYPE"             => "DATE",
                            "INPUT_EXTRA_PARAM"      => "",
                            "VALIDATION_TYPE"        => "ereg",
                            "VALIDATION_EXTRA_PARAM" => "^[[:digit:]]{1,2}[[:space:]]+[[:alnum:]]{3}[[:space:]]+[[:digit:]]{4}$"),
        "date_end"    => array("LABEL"                  => $translations["End Date"],
                            "REQUIRED"               => "yes",
                            "INPUT_TYPE"             => "DATE",
                            "INPUT_EXTRA_PARAM"      => "",
                            "VALIDATION_TYPE"        => "ereg",
                            "VALIDATION_EXTRA_PARAM" => "^[[:digit:]]{1,2}[[:space:]]+[[:alnum:]]{3}[[:space:]]+[[:digit:]]{4}$"),
        "extension" =>  array("LABEL"                  => $translations["Extension"],
                            "REQUIRED"               => "no",
                            "INPUT_TYPE"             => "TEXT",
                            "INPUT_EXTRA_PARAM"      => array("size"=>10),
                            "VALIDATION_TYPE"        => "ereg",
                            "VALIDATION_EXTRA_PARAM" => "^[\*|[:alnum:]@_\.,/\-]+$"),
        "accountcode"=>  array("LABEL"                  => $translations["Pin Code"],
                            "REQUIRED"               => "no",
                            "INPUT_TYPE"             => "TEXT",
                            "INPUT_EXTRA_PARAM"      => array("size"=>10),
                            "VALIDATION_TYPE"        => "ereg",
                            "VALIDATION_EXTRA_PARAM" => "^[\*|[:alnum:]@_\.,/\-]+$"),
        "excludeknownnumbers"=>  array("LABEL"       => $translations["Exclude Known Numbers"],
                            "REQUIRED"               => "no",
                            "INPUT_TYPE"             => "CHECKBOX",
                            "INPUT_EXTRA_PARAM"      => ""),
        "summarise"=>  array("LABEL"                  => $translations["Summarise"],
                            "REQUIRED"               => "no",
                            "INPUT_TYPE"             => "CHECKBOX",
                            "INPUT_EXTRA_PARAM"      => ""));

    //set param defaults
    $dateArray = getDate();
    $day = $dateArray["mday"]-1;
    $filterParams = array(
        'date_start' => date("d M Y", strtotime("-$day day")),
        'date_end' => date("d M Y"),
        'extension' => '',
        'accountcode'=>'',
        'excludeknownnumbers' =>'',
        'summarise'=>''
    );

    //populate params with values from submitted form
    foreach (array_keys($filterParams) as $param) {
        if (isset($_REQUEST["$param"]))
            $filterParams["$param"] = $_REQUEST["$param"];
    }
    
    $filterForm = new paloForm($smarty, $formElements);
    $htmlFilter = $filterForm->fetchForm("$local_templates_dir/filter.tpl","",$filterParams);

    if (!$filterForm->validateForm($filterParams)) {
        $smarty->assign(array(
            'mb_title' => _tr('Validation Error'),
            'mb_message' => '<b>' . _tr('The following fields contain errors') . ':</b><br/>' .
            implode(', ', array_keys($filterForm->arrErroresValidacion)),
        ));
    }

    //Get the data for grid!
    global $phoneBookReport;
    $phoneBookReport= new phoneBookReport();
    
    //build up url array for use in the navigation buttons for form resubmission
    //probably a better way to do this but don't have time to debug right now.
    $url = array("date_start"=>$filterParams['date_start'], "date_end"=>$filterParams['date_end'],
        "extension"=>$filterParams['extension'],"accountcode"=>$filterParams['accountcode'],
        "excludeknownnumbers"=>$filterParams['excludeknownnumbers'],"summarise"=>$filterParams["summarise"]);


    //convert dates to correct foramt from d m y to one mysql expects
    $filterParams['date_start'] = translateDate($filterParams['date_start']).' 00:00:00';
    $filterParams['date_end'] = translateDate($filterParams['date_end']).' 23:59:59';
    $total  = $phoneBookReport->getTotal($filterParams);
    $nav = isset($_GET['nav'])?$_GET['nav']:NULL;
    $start = isset($_GET['start'])?$_GET['start']:NULL;
    $offset = $grid->getOffSet($limit,$total,$nav,$start);
    $start=($total==0) ? 0 : $offset + 1;
    $end = ($offset + $limit) <= $total ? $offset + $limit : $total;
    if (($_GET['exportcsv']&& $_GET['exportcsv']=='yes') || ($_GET['exportpdf']&& $_GET['exportpdf']=='yes')  || ($_GET['exportspreadsheet']&& $_GET['exportspreadsheet']=='yes')){
        $limit = 9999999999;
   }

    $data= formatData($phoneBookReport->getReportData($filterParams,$limit,$offset),$filterParams["summarise"]);

    //Set remaining grid configs
    $arrGrid = array("title"    => $translations["Usage Report"],
        "url"      => $url,
        "start" => $start,
        "total"    =>$total,
        "end"      =>$end,
        "icon"     => "images/endpoint.png",
        "width"    => "99%",
        "columns"  => array(0 => array("name"      => $translations["Name"],
                                       "property1" => ""),
                            1 => array("name"      => $translations["Number"],
                                       "property1" => ""),
                            2 => array("name"      => $translations["Pin Code"],
                                       "property1" => ""),
                            3 => array("name"      => $translations["User"],
                                       "property1" => ""),
                            4 => array("name"      => $translations["Extension"],
                                       "property1" => ""),
                            5 => array("name"      => $translations["Count"],
                                       "property1" => ""),
                            6 => array("name"      => $translations["Time(sec)"],
                                       "property1" => ""),
                            7 => array("name"      => $translations["Cost"],
                                       "property1" => "")));
    if (($filterParams["summarise"]=="on")){
        $arrGrid["columns"] =array(
                                    0 => array("name"      => $translations["Name"],
                                               "property1" => ""),
                                    1 => array("name"      => $translations["Pin Code"],
                                               "property1" => ""),
                                    2 => array("name"      => $translations["User"],
                                               "property1" => ""),
                                    3 => array("name"      => $translations["Count"],
                                               "property1" => ""),
                                    4 => array("name"      => $translations["Time(sec)"],
                                               "property1" => ""),
                                    5 => array("name"      => $translations["Cost"],
                                               "property1" => ""));
    }
    $grid->showFilter($htmlFilter);
    $grid->enableExport();
    $grid->setNameFile_Export($translations["UsageReport.xls"]);

    $content = $grid->fetchGrid($arrGrid, $data,$translations);
    return $content;
}


function formatData($data,$summarise){
    $returnData = array();
    if ($summarise=="on"){
        foreach ($data as $item){
            $tmpData[0]=$item["name"];
            $tmpData[1]=$item["accountcode"];
            $tmpData[2]=$item["username"];
            $tmpData[3]=$item["total"];
            $tmpData[4]=$item["duration"];
            $tmpData[5]=number_format($item["cost"],2);
            $returnData[]=$tmpData;
        }
    }else{
        foreach ($data as $item){
            $tmpData[0]=$item["name"];
            $tmpData[1]=$item["dst"];
            $tmpData[2]=$item["accountcode"];
            $tmpData[3]=$item["username"];
            $tmpData[4]=$item["src"];
            $tmpData[5]=$item["total"];
            $tmpData[6]=$item["duration"];
            $tmpData[7]=number_format($item["cost"],2);
            $returnData[]=$tmpData;
        }
  }
  return $returnData;
}
