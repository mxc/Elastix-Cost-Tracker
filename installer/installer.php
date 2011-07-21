<?php

$rootDir = "/var/www/html";

require_once("$rootDir/libs/paloSantoInstaller.class.php");
require_once("$rootDir/libs/paloSantoDB.class.php");
require_once("$rootDir/libs/paloSantoACL.class.php");
require_once("$rootDir/libs/paloSantoConfig.class.php");

$tmpDir  = '/tmp/new_module';

$sql  = "$tmpDir/installer/costtracker.sql";

$installer = new Installer();
$return = 1;

if (file_exists($sql))
{
    //Get the username/password to setup new costtracker database
    $config = new paloConfig("/etc", "amportal.conf", "=", "[[:space:]]*=[[:space:]]*");
    $arrAMP = $config->leer_configuracion(false);
 
    $return = $installer->createNewDatabaseMySQL($sql,'costtracker',
            array("user"=>$arrAMP['AMPDBUSER']['valor'],"password"=>$arrAMP['AMPDBPASS']['valor'],
                  "locate"=>$arrAMP['AMPDBHOST']['valor']));

    if ($return) die("Error creating mysql costtracker database");

   $connStr = $arrAMP['AMPDBENGINE']['valor']."://".
             $arrAMP['AMPDBUSER']['valor']. ":".
             $arrAMP['AMPDBPASS']['valor']. "@".
             $arrAMP['AMPDBHOST']['valor']."/costtracker";

    $conn = new paloDB($connStr);
    if(!empty($conn->errMsg)) {
        echo "DATABASE ERROR: $conn->errMsg <br>";
    }
    //Make sure we have a default rate in the rates table!
    $sql = "Select amount from rate where pattern like 'default'";
    $result = $conn->getFirstRowQuery($sql,true);
    if (empty($result)){
        $sql = "Insert into rate (pattern,amount) values ('default',1)";
        $conn->genQuery($sql);
    }
    //Make sure we have a default unknown user
    $sql = "Select id from ctuser where username like 'Unknown'";
    $result = $conn->getFirstRowQuery($sql,true);
    if (empty($result)){
        $sql = "Insert into ctuser (acluser_id,username,lname,active,foundDate) values (-1,'Unknown','Unknown',1,".time().")";
        $conn->genQuery($sql);
    }

    //Create cron job to create report table!
    $command  = "@daily /usr/bin/php $rootDir/modules/usagereports/cron.php\n";
    exec("crontab -l > /tmp/user.cron");
    $cronsJobs = file_get_contents('/tmp/user.cron');
    $cronsJobs = trim(str_replace($command,'', $cronsJobs));
    if (strpos($cronsJobs, 'no crontab') === false) {
    	$cronsJobs = "$cronsJobs\n$command";
    } else {
    	$cronsJobs = "$command";
    }
    file_put_contents('/tmp/user.cron', $cronsJobs);
    exec("crontab /tmp/user.cron");

    //call cron.php to populate table!
    echo '$rootDir/modules/usagereports/cron.php';
}

exit($return);
?>
