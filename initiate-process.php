<?php 

/*****
 * Author: Ethan Gruber
 * Date: June 2024
 * Function: Streamlined process for reading and updating typology projects from Google Sheets to Numishare
 *****/

include "includes.php";

//user-agent must be set in HTTP headers for Wikidata querying
ini_set('user_agent', 'American Numismatic Society');

define("NUMISHARE_SOLR_URL", "http://localhost:8983/solr/numishare/update");
define("EXIST_URL", "http://localhost:8888/exist/rest/db/");
define("INDEX_COUNT", 100);
define("GEONAMES_KEY", "");

//Natural Language Processing alignment via FastAPI
define("NLP_API", "https://numismatics.org/nnlp/extract");
define("NLP_PROJECTS", array('crro', 'ocre', 'agco', 'bigr', 'lco', 'pco', 'pella', 'sco'));

//load JSON projects
$projects_json = file_get_contents('projects.json');
$projects = json_decode($projects_json, false);

$projectExists = false;


//$mode should be 'test' or 'prod', which determines whether the NUDS/XML is written to the console or to eXist-db and indexed into Numishare
$mode = 'prod';

$nomismaUris = array();
$errors = array();
$idsToIndex = array();

$startTime = date(DATE_W3C);

//evaluate arguments
if (isset($argv[1])){
    foreach ($projects->projects as $p){
        if ($p->name == $argv[1]){
            $projectExists = true;
            
            //create project folders if necessary
            if (!file_exists('nuds')) {
                mkdir('nuds', 0777, true);
            }
            if (!file_exists('nuds/' . $p->name)) {
                mkdir('nuds/' . $p->name, 0777, true);
            } else {
                //if the project folder exists, delete everything in it before reinitializing process
                $files = glob('nuds/' . $p->name . '/*'); // get all file names
                foreach($files as $file){ // iterate files
                    if(is_file($file)) {
                        unlink($file); // delete file
                    }
                }
            }
            
            //if the project has been found, then look for parts
            if (property_exists($p, 'parts')){
                if (isset($argv[2])){
                    $partExists = false;
                    
                    foreach ($p->parts as $part){
                        if ($part->name == $argv[2]){
                            $partExists = true;               
                            
                            $project = array("name"=>$p->name, "email"=>$p->email, "uri_space"=>$p->uri_space, "types"=>$part->types, "o"=>$part->o, "r"=>$part->r);
                            
                            //set the ids from the 4th argument, if available
                            if (isset($argv[3])){
                                $project['ids'] = $argv[3];
                            }
                        }
                    }
                    
                    //output message if there is no part with the second argument
                    if ($partExists == false) {
                        echo "No part matching the second argument has been found.\n";
                    }
                } else {
                    echo "No part has been selected.\n";
                }
            } else {
                $project = array("name"=>$p->name, "email"=>$p->email, "uri_space"=>$p->uri_space, "types"=>$p->types, "o"=>$p->o, "r"=>$p->r);
                
                //set the ids from the 3rd argument, if available
                if (isset($argv[2])){
                    $project['ids'] = $argv[2];
                }
            }
        }
    }
    
    if ($projectExists == false) {
        echo "No project matching the first argument has been found.\n";
    }
} else {
    echo "No arguments set, terminating script.\n";
}

//proceed with spreadsheet->NUDS processing
if (isset($project)){    
    //var_dump($project);
    
    $eXist_config_path = '/usr/local/projects/numishare/exist-config.xml';
    
    if (file_exists($eXist_config_path)) {
        $data = generate_json($project['types']);
        $obverses = generate_json($project['o']);
        $reverses = generate_json($project['r']);
        
        //get the eXist-db password from disk
        $eXist_config = simplexml_load_file($eXist_config_path);
        $eXist_url = $eXist_config->url;
        $eXist_credentials = $eXist_config->username . ':' . $eXist_config->password;
        
        $count = 1;
        if (array_key_exists('ids', $project)){
            $ids = explode('&', $project['ids']);
            
            foreach($data as $row){
                if (in_array($row['ID'], $ids)){
                    generate_nuds($row, $project, $eXist_credentials, $obverses, $reverses, $count, $mode);                   
                }
                $count++;
            }
            
        } else {
            echo "Process all\n";
            
            foreach($data as $row){                
                generate_nuds($row, $project, $eXist_credentials, $obverses, $reverses, $count, $mode);
                $count++;
            }
        }
        
        //execute process for remaining ids.
        if ($mode == 'prod'){
            $start = floor(count($idsToIndex) / INDEX_COUNT) * INDEX_COUNT;
            $toIndex = array_slice($idsToIndex, $start);
            
            //POST TO SOLR
            generate_solr_shell_script($toIndex, $project);
            
            $endTime = date(DATE_W3C);
            
            //send email report
            generate_email_report($idsToIndex, $errors, $project, $startTime, $endTime);
        }
    }    
}

?>
