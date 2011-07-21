
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
    include_once "modules/$module_name/libs/phoneBookRates.class.php";
    include_once "modules/$module_name/libs/rateEntry.class.php";
    include_once "libs/paloSantoGrid.class.php";
    include_once "libs/paloSantoForm.class.php";
    include_once "libs/misc.lib.php";

    //Get translations
    $lang=get_language();
    $base_dir=dirname($_SERVER['SCRIPT_FILENAME']);
    $lang_file="modules/$module_name/lang/$lang.lang";
    if (file_exists("$base_dir/$lang_file")) include_once "$lang_file";
    else include_once "modules/$module_name/lang/en.lang";

    //global variables
    //the usual suspect for language and configuration
    global $arrConf;
    global $arrConfModule;
    global $arrLang;
    global $arrLangModule;
    global $phoneBookRates;

    //Create configuration array
    $arrConf = array_merge($arrConf,$arrConfModule);
    $arrConf["module_name"]= $module_name;
    $arrLang = array_merge($arrLang,$arrLangModule);

    //folder path for custom templates
    $base_dir=dirname($_SERVER['SCRIPT_FILENAME']);
    $templates_dir=(isset($arrConf['templates_dir']))?$arrConf['templates_dir']:'themes';
    $local_templates_dir="$base_dir/modules/$module_name/".$templates_dir.'/'.$arrConf['theme'];

    //process response based on returned variables
    if (isset($_REQUEST["add"])){
            $content = displayForm($smarty,$local_templates_dir);
    } else if(isset($_REQUEST["save"])){
            $content = saveAddForm($smarty, $local_templates_dir);
    } else if (isset($_REQUEST["delete"])){
             if (empty($phoneBookRates)) $phoneBookRates = new phoneBookRates();
            //delete all items selected for deletion
            foreach ($_REQUEST["rateId"] as $id){
               $phoneBookRates->deleteEntry($id);
            }
            $content = getGrid($smarty, $local_templates_dir);
    } else if (isset($_REQUEST["edit"])){
           if (empty($phoneBookRates)) $phoneBookRates = new phoneBookRates();
           $entry = $phoneBookRates->getEntry($_REQUEST["id"]);
           $params = array (
             "pattern" =>$entry->pattern,
             "rate" => number_format($entry->rate,2),
             "id" => $entry->id);
           $content = displayForm($smarty, $local_templates_dir, $params);
    } else{
            $content = getGrid($smarty,$local_templates_dir);
    }
    return $content;
}

function getGrid(&$smarty,$local_templates_dir){

    global $arrLang;

   //Create filter form
    $formElements = array(
        "pattern" =>  array("LABEL"                  => $arrLang["Pattern"],
                            "REQUIRED"               => "no",
                            "INPUT_TYPE"             => "TEXT",
                            "INPUT_EXTRA_PARAM"      => "",
                            "VALIDATION_TYPE"        => "ereg",
                            "VALIDATION_EXTRA_PARAM" => "^[\*|[:alnum:]@_\.,/\-]+$"),
    );

    //set param defaults
    $filterParams = array(
        'pattern' => '',
    );

    //populate params with values from submitted form
    //only one here but may add more later
    foreach (array_keys($filterParams) as $param) {
        if (isset($_REQUEST["$param"]))
            $filterParams["$param"] = $_REQUEST["$param"];
    }
    
    $smarty->assign("Search",$arrLang["Search"]);
    $smarty->assign("Add",$arrLang["Add"]);

    $paloForm = new paloForm($smarty, $formElements);

    if (!$paloForm->validateForm($filterParams)) {
        $smarty->assign(array(
            'mb_title' => _tr('Validation Error'),
            'mb_message' => '<b>' . _tr('The following fields contain errors') . ':</b><br/>' .
            implode(', ', array_keys($paloForm->arrErroresValidacion)),
        ));
    }
    $htmlFilter = $paloForm->fetchForm("$local_templates_dir/filter.tpl", $arrLang["Add Rate"],$filterParams);

    //Create the grid!
    global $phoneBookRates;
    $phoneBookRates= new phoneBookRates();
    $limit  =20;
    $grid  = new paloSantoGrid($smarty);


    //build up url array for use in the navigation buttons for form resubmission
    $url = array("pattern"=>$filterParams['pattern']);
    //convert dates to correct format from d m y to one mysql expects
    $total  = $phoneBookRates->getTotalRecords($filterParams);
    $nav = isset($_GET['nav'])?$_GET['nav']:NULL;
    $start = isset($_GET['start'])?$_GET['start']:NULL;
    $offset = $grid->getOffSet($limit,$total,$nav,$start);
    $start=($total==0) ? 0 : $offset + 1;
    $end = ($offset + $limit) <= $total ? $offset + $limit : $total;
    $raw=false;
    if (($_GET['exportcsv']&& $_GET['exportcsv']=='yes') || ($_GET['exportpdf']&& $_GET['exportpdf']=='yes')  || ($_GET['exportspreadsheet']&& $_GET['exportspreadsheet']=='yes')){
        $limit = 9999999999;
        $raw=true;
   }
    $data= formatData($phoneBookRates->getReportData($filterParams,$limit,$offset),$raw);


    //Set remaining grid configs
    $arrGrid = array("title"    => $arrLang["Rates"],
        "url"      => $url,
        "start" => $start,
        "total"    =>$total,
        "end"      =>$end,
        "icon"     => "images/endpoint.png",
        "width"    => "99%",
        "columns"  => array(0 => array("name"      => "<input type='submit' name='delete' value='{$arrLang['Delete']}' class='button' onclick=\" return confirmSubmit('{$arrLang["Are you sure you wish to delete these entries?"]}');\" />",
                                       "property1" => ""),
                            1 => array("name"      => $arrLang["Edit"],
                                       "property1" => ""),
                            2 => array("name"      => $arrLang["Rate/Min"],
                                       "property1" => "")));
    $grid->showFilter($htmlFilter);
    $grid->enableExport();
    $grid->setNameFile_Export($arrLang["RateReport.xls"]);
    //change column headings if outputing to external doc.
    if ($raw) {
        $arrGrid["columns"]=array (0 => array("name" => "","property1" => ""),1 => array("name" => "Pattern","property1" => ""),2 => array("name" => "Rate","property1" => ""));
    }
    $content = $grid->fetchGrid($arrGrid, $data,$arrLang);    
    return $content;    
}

function formatData($data,$raw=false){
    $returnData = array();
    foreach ($data as $item){
      if($raw){
          $tmpData[0]="";
      }else{
          if ($item["pattern"]!="default")
              $tmpData[0]="<input type='checkbox' name='rateId[]' value='$item[id]'/>";
              else $tmpData[0]="NA";
      }
      if ($raw) $tmpData[1]="$item[pattern]";
      else $tmpData[1]="<a href='/index.php?menu=rates&edit=true&id=$item[id]'>$item[pattern]</a>";
      $tmpData[2]=number_format($item["amount"],2);
      $returnData[]=$tmpData;
    }
    return $returnData;
}

function saveAddForm(&$smarty,$local_templates_dir){

    global $phoneBookRates;

    //populate params with values from submitted form
    //only one here but may add more later
    $params = array(
        'pattern' => '',
        'rate' =>'',
        'id'=>''
    );

    foreach (array_keys($params) as $param) {
        if (!empty ($_REQUEST["$param"]))
         $params["$param"] = $_REQUEST["$param"];
    }

    $addForm = getAddForm($smarty);
    //validate form


    if (!$addForm->validateForm($params)) {
        $smarty->assign(array(
            'mb_title' => _tr('Validation Error'),
            'mb_message' => '<b>' . _tr('The following fields contain errors') . ':</b><br/>' .
            implode(', ', array_keys($addForm->arrErroresValidacion)),
        ));
        return $addForm->fetchForm("$local_templates_dir/rateEntryForm.tpl", $arrLang["Add Rate"],$params);
    }

    //Save results and return grid
    if (empty($phoneBookRates)) $phoneBookRates = new phoneBookRates();
    $entry = new RateEntry($_REQUEST['pattern'],$_REQUEST['rate'],$_REQUEST["id"]);
    $phoneBookRates->saveEntry($entry);
    //reset pattern variable as used in grid form too
    $_REQUEST["pattern"]='';
    return getGrid($smarty, $local_templates_dir);
}


function getAddForm(&$smarty,$extraParam=null){

    global $arrLang;
    //Create filter form
    $formElements = array(
        "pattern" =>  array("LABEL"                  => $arrLang["Pattern"],
                            "REQUIRED"               => "yes",
                            "INPUT_TYPE"             => "TEXT",
                            "INPUT_EXTRA_PARAM"      => "",
                            "VALIDATION_TYPE"        => "ereg",
                            "VALIDATION_EXTRA_PARAM" => "^[0-9]+$"),

        "rate" =>     array("LABEL" =>$arrLang["Rate/Min"],
                            "REQUIRED"               => "yes",
                            "INPUT_TYPE"             => "TEXT",
                            "INPUT_EXTRA_PARAM"      => "",
                            "VALIDATION_TYPE"        => "ereg",
                            "VALIDATION_EXTRA_PARAM" => "^[0-9]+(\.[0-9]{1,2})?$"),
        "id" =>        array("LABEL" => "id",
                            "REQUIRED"               => "no",
                            "INPUT_TYPE"             => "HIDDEN"));
    //parameter to disabled editing of pattern if it is the default rate!
    if (!empty($extraParam)){
        $formElements["pattern"]["INPUT_EXTRA_PARAM"] =array("readonly"=>"true");
    }
    $addForm =  new paloForm($smarty, $formElements);

    $smarty->assign("Save",$arrLang["Save"]);
    $smarty->assign("Cancel",$arrLang["Cancel"]);
    return $addForm;
}

function displayForm($smarty,$local_templates_dir,$params = null){
    //disabled the pattern field if the pattern is the default cost entry!
    global $arrLang;
    if (strcmp($params["pattern"],"default")==0) {
        $addForm = getAddForm($smarty,"disabled");
    }else{
        // ok, get normal form.
        $addForm = getAddForm($smarty);
    }
    //set param defaults
    if (empty($params)) $params = array(
        'pattern' => '',
        'rate' =>'',
        'id' =>''
    );
    $content = $addForm->fetchForm("$local_templates_dir/rateEntryForm.tpl", $arrLang["Add Rate"],$params);
    return $content;

}