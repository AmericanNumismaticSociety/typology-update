<?php 

/*****
 * Author: Ethan Gruber
 * Date: October 2022
 * Function: Streamlined process for reading and updating typology projects from Google Sheets to Numishare
 *****/

include "includes.php";

//load JSON projects
$projects_json = file_get_contents('projects.json');
$projects = json_decode($projects_json, false);

$projectExists = false;

$nomismaUris = array();
$errors = array();

//evaluate arguments
if (isset($argv[1])){
    foreach ($projects->projects as $p){
        if ($p->name == $argv[1]){
            $projectExists = true;
            
            //if the project has been found, then look for parts
            if (array_key_exists('parts', $p)){
                if (isset($argv[2])){
                    $partExists = false;
                    
                    foreach ($p->parts as $part){
                        if ($part->name == $argv[2]){
                            $partExists = true;               
                            
                            $project = array("name"=>$p->name, "uri_space"=>$p->uri_space, "types"=>$part->types, "o"=>$part->o, "r"=>$part->r);
                            
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
                $project = array("name"=>$p->name, "uri_space"=>$p->uri_space, "types"=>$p->types, "o"=>$p->o, "r"=>$p->r);
                
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
    echo "true\n";
    
    var_dump($project);
    
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
                    generate_nuds($row, $project, $obverses, $reverses, $count);
                }
                $count++;
            }
            
        } else {
            echo "Process all\n";
            
            foreach($data as $row){                
                generate_nuds($row, $project, $obverses, $reverses, $count);
                $count++;
            }
        }
    }
    
}

?>