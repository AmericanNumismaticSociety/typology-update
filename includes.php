<?php 

/***** FUNCTIONS *****/
function generate_nuds($row, $project, $eXist_credentials, $obverses, $reverses, $count, $mode){
    
    $indexable = false;
    
    $uri_space = $project['uri_space'];
    
    $recordId = trim($row['ID']);
    
    
    if (strlen($recordId) > 0 && ($mode == 'test' || $mode == 'prod')){
        echo "Processing {$recordId}\n";
        $doc = new XMLWriter();
        
        
        if ($mode == 'test'){
            $doc->openUri('php://output');
        } elseif ($mode == 'prod'){            
            $doc->openUri('nuds/' . $project['name'] . '/'. $recordId . '.xml');
        }
        
        $doc->setIndent(true);
        //now we need to define our Indent string,which is basically how many blank spaces we want to have for the indent
        $doc->setIndentString("    ");
        
        $doc->startDocument('1.0','UTF-8');
        
        $doc->startElement('nuds');
        $doc->writeAttribute('xmlns', 'http://nomisma.org/nuds');
        $doc->writeAttribute('xmlns:xs', 'http://www.w3.org/2001/XMLSchema');
        $doc->writeAttribute('xmlns:xlink', 'http://www.w3.org/1999/xlink');
        $doc->writeAttribute('xmlns:tei', 'http://www.tei-c.org/ns/1.0');
        $doc->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $doc->writeAttribute('xsi:schemaLocation', 'http://nomisma.org/nuds http://nomisma.org/nuds.xsd');
        $doc->writeAttribute('recordType', 'conceptual');
        
        //control
        $doc->startElement('control');
            $doc->writeElement('recordId', $recordId);
            
            //insert typeNumber just to capture the num.                      
            $doc->startElement('otherRecordId');
                $doc->writeAttribute('localType', 'typeNumber');
                if (array_key_exists('Type Number', $row) && strlen($row['Type Number']) > 0){
                    $doc->text(trim($row['Type Number']));
                } else {
                    $doc->text(get_typeNumber($recordId, $project));
                }                
            $doc->endElement();
            
            //hierarchy
            if (array_key_exists('Parent ID', $row) && strlen($row['Parent ID']) > 0){
                $doc->startElement('otherRecordId');
                    $doc->writeAttribute('semantic', 'skos:broader');
                    $doc->text(trim($row['Parent ID']));
                $doc->endElement();
                
            } else {
                //insert a sortID
                $doc->startElement('otherRecordId');
                    $doc->writeAttribute('localType', 'sortId');
                    if (array_key_exists('sortId', $row) && strlen($row['sortId']) > 0){
                        $doc->text($row['sortId']);
                    } else {
                        $doc->text(number_pad(intval($count), 5));
                    }
                $doc->endElement();
            }            
            
            if (array_key_exists('Matching URI', $row) && strlen(trim($row['Matching URI'])) > 0){
                $uris = explode('|', trim($row['Matching URI']));
                
                foreach ($uris as $uri){
                    $uri = trim($uri);
                    $doc->startElement('otherRecordId');
                        $doc->writeAttribute('semantic', 'skos:exactMatch');
                        $doc->text($uri);
                    $doc->endElement();
                }
            }
            
            if (array_key_exists('replaces ID', $row) && strlen(trim($row['replaces ID'])) > 0){
                $replaces = explode('|', $row['replaces ID']);
                
                foreach ($replaces as $replacesID){
                    $replacesID = trim($replacesID);
                    $doc->startElement('otherRecordId');
                        $doc->writeAttribute('semantic', 'dcterms:replaces');
                        $doc->text($replacesID);
                    $doc->endElement();
                    $doc->startElement('otherRecordId');
                        $doc->writeAttribute('semantic', 'skos:exactMatch');
                        $doc->text($uri_space . $replacesID);
                    $doc->endElement();
                }
            }
            
            //handle semantic relation with other record
            if (array_key_exists('isReplacedBy ID', $row) && strlen($row['isReplacedBy ID']) > 0){
                $replaces = explode('|', $row['isReplacedBy ID']);
                
                $doc->writeElement('publicationStatus', 'deprecatedType');
                
                //if there is more than one replacement, then the status is cancelledSplit, otherwise cancelledReplaced
                if (count($replaces) > 1) {
                    $doc->writeElement('maintenanceStatus', 'cancelledSplit');
                } else {
                    $doc->writeElement('maintenanceStatus', 'cancelledReplaced');
                }
                
                foreach ($replaces as $deprecatedID){
                    $deprecatedID = trim($deprecatedID);
                    $doc->startElement('otherRecordId');
                        $doc->writeAttribute('semantic', 'dcterms:isReplacedBy');
                        $doc->text($deprecatedID);
                    $doc->endElement();
                    
                    //only insert skos:exactMatch if there is a 1:1 relationship
                    if (count($replaces) == 1) {
                        $doc->startElement('otherRecordId');
                            $doc->writeAttribute('semantic', 'skos:exactMatch');
                            $doc->text($uri_space . $deprecatedID);
                        $doc->endElement();
                    }
                }
            } else {
                //set the maintenanceStatus and publicationStatus values
                if (array_key_exists('maintenanceStatus', $row) && $row['maintenanceStatus'] == 'cancelled') {
                    $doc->writeElement('publicationStatus', 'deprecatedType');
                    $doc->writeElement('maintenanceStatus', 'cancelled');
                } elseif (array_key_exists('Parent ID', $row) && strlen($row['Parent ID']) > 0) {
                    $doc->writeElement('publicationStatus', 'approvedSubtype');
                    $doc->writeElement('maintenanceStatus', 'derived');
                    $indexable = true;
                } else {
                    $doc->writeElement('publicationStatus', 'approved');
                    $doc->writeElement('maintenanceStatus', 'derived');
                    $indexable = true;
                }
            }
            
            $doc->startElement('maintenanceAgency');
                $doc->writeElement('agencyName', 'American Numismatic Society');
            $doc->endElement();
            
            //maintenanceHistory
            $doc->startElement('maintenanceHistory');
                $doc->startElement('maintenanceEvent');
                    $doc->writeElement('eventType', 'derived');
                    $doc->startElement('eventDateTime');
                        $doc->writeAttribute('standardDateTime', date(DATE_W3C));
                        $doc->text(date(DATE_RFC2822));
                    $doc->endElement();
                    $doc->writeElement('agentType', 'machine');
                    $doc->writeElement('agent', 'PHP');
                    $doc->writeElement('eventDescription', 'Generated from CSV from ANS Curatorial Google Drive.');
                    $doc->writeElement('source', $project['types']);
                $doc->endElement();
            $doc->endElement();
            
            //rightsStmt
            $doc->startElement('rightsStmt');
                $doc->writeElement('copyrightHolder', 'American Numismatic Society');
                $doc->startElement('license');
                    $doc->writeAttribute('xlink:type', 'simple');
                    $doc->writeAttribute('xlink:href', 'http://opendatacommons.org/licenses/odbl/');
                $doc->endElement();
            $doc->endElement();
            
            //semanticDeclaration
            $doc->startElement('semanticDeclaration');
                $doc->writeElement('prefix', 'dcterms');
                $doc->writeElement('namespace', 'http://purl.org/dc/terms/');
            $doc->endElement();
            
            $doc->startElement('semanticDeclaration');
                $doc->writeElement('prefix', 'nmo');
                $doc->writeElement('namespace', 'http://nomisma.org/ontology#');
            $doc->endElement();
            
            $doc->startElement('semanticDeclaration');
                $doc->writeElement('prefix', 'skos');
                $doc->writeElement('namespace', 'http://www.w3.org/2004/02/skos/core#');
            $doc->endElement();
        //end control
        $doc->endElement();
        
        
        //start descMeta
        $doc->startElement('descMeta');
        
        /***** TITLE *****/
        $doc->startElement('title');
            $doc->writeAttribute('xml:lang', 'en');
            if (array_key_exists('Title', $row)){
                $doc->text($row['Title']);
            } else {
                $doc->text(get_title($recordId, $project));
            }            
        $doc->endElement();
        
        /***** NOTES *****/
        //look for any column where the first four letters begin with lower-case 'note'
        $hasNotes = false;
        foreach ($row as $k=>$v){
            if (substr(strtolower($k), 0, 4) == 'note' && strlen(trim($v)) > 0){
                $hasNotes = true;
            }
        }
        
        if ($hasNotes == true){
            $doc->startElement('noteSet');
                foreach ($row as $k=>$v){
                    if (substr(strtolower($k), 0, 4) == 'note' && strlen(trim($v)) > 0){
                        $doc->startElement('note');
                            if (preg_match('/^Note\s\((.*)\)$/', $k, $matches)){
                                if (isset($matches[1])) {
                                    $doc->writeAttribute('localType', $matches[1]);
                                }
                            }
                        
                            $doc->writeAttribute('xml:lang', 'en');
                            $doc->text(trim($v));
                        $doc->endElement();    
                    }
                }
            $doc->endElement();
        }
        
        /***** SUBJECTS *****/
        $hasSubjects = false;
        foreach ($row as $k=>$v){
        	if (substr(strtolower($k), 0, 7) == 'subject' && strlen(trim($v)) > 0){
        		$hasSubjects= true;
        	}
        }
        
        if ($hasSubjects == true){
        	$doc->startElement('subjectSet');
        	foreach ($row as $k=>$v){
        		if (substr(strtolower($k), 0, 7) == 'subject' && strlen(trim($v)) > 0){
        			$localType = strlen($k) > 7 ? $k : null;
        			
        			$vals = explode('|', trim($v));
        			foreach ($vals as $val){
        				$val = trim($val);
        				
        				$doc->startElement('subject');
	        				if (isset($localType)){
	        					$doc->writeAttribute('localType', $localType);
	        					
	        					if (preg_match('/^https?:\/\//', $val)){
	        						$uri = $val;
	        						$content = processUri($uri);
	        						
	        						$doc->writeAttribute('xlink:type', 'simple');
	        						$doc->writeAttribute('xlink:href', $uri);
	        						$doc->text($content['label']);
	        					} else {
	        						$doc->text($v);
	        					}
	        				}
        				$doc->endElement();
        			}
        		}
        	}
        	$doc->endElement();
        }
        
        
        /***** TYPEDESC *****/
        $doc->startElement('typeDesc');
        
        //objectType
        if (array_key_exists('Object Type URI', $row) && strlen($row['Object Type URI']) > 0){
            $vals = explode('|', $row['Object Type URI']);
            foreach ($vals as $val){
                if (substr($val, -1) == '?'){
                    $uri = substr($val, 0, -1);
                    $uncertainty = true;
                    $content = processUri($uri);
                } else {
                    $uri =  $val;
                    $uncertainty = false;
                    $content = processUri($uri);
                }
                
                $doc->startElement('objectType');
                    $doc->writeAttribute('xlink:type', 'simple');
                    $doc->writeAttribute('xlink:href', $uri);
                    if($uncertainty == true){
                        $doc->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
                    }
                    $doc->text($content['label']);
                $doc->endElement();
            }
        }
        
        //sort dates
        if (array_key_exists('From Date', $row) && array_key_exists('To Date', $row)){
            
            //evaluate textual "fuzzy dates" first
            if (array_key_exists('Textual Date', $row) && strlen($row['Textual Date']) > 0){
                $doc->startElement('date');
                    if (strlen($row['From Date']) > 0) {
                        $fromDate = intval(trim($row['From Date']));
                        $doc->writeAttribute('notBefore', number_pad($fromDate, 4));
                    }
                    if (strlen($row['To Date']) > 0){
                        $toDate = intval(trim($row['To Date']));
                        $doc->writeAttribute('notAfter', number_pad($toDate, 4));
                    }
                    $doc->text(trim($row['Textual Date']));
                $doc->endElement();
                
            } else {
                //otherwise evaluate  just the integer values for dates
                if (strlen($row['From Date']) > 0 || strlen($row['To Date']) > 0){
                    if (($row['From Date'] == $row['To Date']) || (strlen($row['From Date']) > 0 && strlen($row['To Date']) == 0)){
                        if (is_numeric(trim($row['From Date']))){
                            
                            $fromDate = intval(trim($row['From Date']));
                            $doc->startElement('date');
                                $doc->writeAttribute('standardDate', number_pad($fromDate, 4));
                                if (array_key_exists('From Date Certainty', $row) && strlen($row['From Date Certainty']) > 0){
                                    $doc->writeAttribute('certainty', 'http://nomisma.org/id/' . $row['From Date Certainty']);
                                }
                                $doc->text(get_date_textual($fromDate));
                            $doc->endElement();
                        }
                    } else {
                        $fromDate = intval(trim($row['From Date']));
                        $toDate= intval(trim($row['To Date']));
                        
                        //only write date if both are integers
                        if (is_int($fromDate) && is_int($toDate)){
                            $doc->startElement('dateRange');
                                $doc->startElement('fromDate');
                                    $doc->writeAttribute('standardDate', number_pad($fromDate, 4));
                                    if (array_key_exists('From Date Certainty', $row) && strlen($row['From Date Certainty']) > 0){
                                        $doc->writeAttribute('certainty', 'http://nomisma.org/id/' . $row['From Date Certainty']);
                                    }
                                    $doc->text(get_date_textual($fromDate));
                                $doc->endElement();
                                $doc->startElement('toDate');
                                    $doc->writeAttribute('standardDate', number_pad($toDate, 4));
                                    if (array_key_exists('To Date Certainty', $row) && strlen($row['To Date Certainty']) > 0){
                                        $doc->writeAttribute('certainty', 'http://nomisma.org/id/' . $row['To Date Certainty']);
                                    }
                                    $doc->text(get_date_textual($toDate));
                                $doc->endElement();
                            $doc->endElement();
                        }
                    }
                }            
            }
        }
        
        if (array_key_exists('Date on Object', $row) && strlen($row['Date on Object']) > 0){
        	$doc->startElement('dateOnObject');
        		$doc->startElement('date');        			
        			if (preg_match('/^\d{4}$/', $row['Date on Object'])){
        				$doc->writeAttribute('standardDate', $row['Date on Object']);
        			}        		
        			$doc->text($row['Date on Object']);
        		$doc->endElement();
        	$doc->endElement();
        }
        
        if (array_key_exists('Denomination URI', $row) && strlen($row['Denomination URI']) > 0){
            $vals = explode('|', $row['Denomination URI']);
            foreach ($vals as $val){
                if (substr($val, -1) == '?'){
                    $uri = substr($val, 0, -1);
                    $uncertainty = true;
                    $content = processUri($uri);
                } else {
                    $uri =  $val;
                    $uncertainty = false;
                    $content = processUri($uri);
                }
                
                $doc->startElement('denomination');
                    $doc->writeAttribute('xlink:type', 'simple');
                    $doc->writeAttribute('xlink:href', $uri);
                    if($uncertainty == true){
                        $doc->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
                    }
                    $doc->text($content['label']);
                $doc->endElement();
            }
        }
        
        if (array_key_exists('Manufacture URI', $row) && strlen($row['Manufacture URI']) > 0){
            $vals = explode('|', $row['Manufacture URI']);
            foreach ($vals as $val){
                if (substr($val, -1) == '?'){
                    $uri = substr($val, 0, -1);
                    $uncertainty = true;
                    $content = processUri($uri);
                } else {
                    $uri =  $val;
                    $uncertainty = false;
                    $content = processUri($uri);
                }
                
                $doc->startElement('manufacture');
                    $doc->writeAttribute('xlink:type', 'simple');
                    $doc->writeAttribute('xlink:href', $uri);
                    if($uncertainty == true){
                        $doc->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
                    }
                    $doc->text($content['label']);
                $doc->endElement();
            }
        }
        
        
        if (array_key_exists('Material URI', $row) && strlen($row['Material URI']) > 0){
            $vals = explode('|', $row['Material URI']);
            foreach ($vals as $val){
                if (substr($val, -1) == '?'){
                    $uri = substr($val, 0, -1);
                    $uncertainty = true;
                    $content = processUri($uri);
                } else {
                    $uri =  $val;
                    $uncertainty = false;
                    $content = processUri($uri);
                }
                
                $doc->startElement('material');
                    $doc->writeAttribute('xlink:type', 'simple');
                    $doc->writeAttribute('xlink:href', $uri);
                    if($uncertainty == true){
                        $doc->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
                    }
                    $doc->text($content['label']);
                $doc->endElement();
            }
        }
        
        if (array_key_exists('Shape URI', $row) && strlen($row['Shape URI']) > 0){
            $vals = explode('|', $row['Shape URI']);
            foreach ($vals as $val){
                if (substr($val, -1) == '?'){
                    $uri = substr($val, 0, -1);
                    $uncertainty = true;
                    $content = processUri($uri);
                } else {
                    $uri =  $val;
                    $uncertainty = false;
                    $content = processUri($uri);
                }
                
                $doc->startElement('shape');
                    $doc->writeAttribute('xlink:type', 'simple');
                    $doc->writeAttribute('xlink:href', $uri);
                    if($uncertainty == true){
                        $doc->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
                    }
                    $doc->text($content['label']);
                $doc->endElement();
            }
        }
        
        //authority
        if (array_key_exists('Authority URI', $row) || array_key_exists('Stated Authority URI', $row) || array_key_exists('Issuer URI', $row) || array_key_exists('Artist URI', $row) || array_key_exists('Maker URI', $row) || array_key_exists('Authenticity URI', $row)){
            $doc->startElement('authority');
            if (array_key_exists('Authority URI', $row) && strlen($row['Authority URI']) > 0){
                $vals = explode('|', $row['Authority URI']);
                foreach ($vals as $val){
                    if (substr($val, -1) == '?'){
                        $uri = substr($val, 0, -1);
                        $uncertainty = true;
                        $content = processUri($uri);
                    } else {
                        $uri =  $val;
                        $uncertainty = false;
                        $content = processUri($uri);
                    }
                    $role = 'authority';
                    
                    $doc->startElement($content['element']);
                        $doc->writeAttribute('xlink:type', 'simple');
                        $doc->writeAttribute('xlink:role', $role);
                        $doc->writeAttribute('xlink:href', $uri);
                        if($uncertainty == true){
                            $doc->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
                        }
                        $doc->text($content['label']);
                    $doc->endElement();
                }
            }
            
            if (array_key_exists('Stated Authority URI', $row) && strlen($row['Stated Authority URI']) > 0){
                $vals = explode('|', $row['Stated Authority URI']);
                foreach ($vals as $val){
                    if (substr($val, -1) == '?'){
                        $uri = substr($val, 0, -1);
                        $uncertainty = true;
                        $content = processUri($uri);
                    } else {
                        $uri =  $val;
                        $uncertainty = false;
                        $content = processUri($uri);
                    }
                    $role = 'statedAuthority';
                    
                    $doc->startElement($content['element']);
                        $doc->writeAttribute('xlink:type', 'simple');
                        $doc->writeAttribute('xlink:role', $role);
                        $doc->writeAttribute('xlink:href', $uri);
                        if($uncertainty == true){
                            $doc->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
                        }
                        $doc->text($content['label']);
                    $doc->endElement();
                }
            }
            
            if (array_key_exists('Issuer URI', $row) && strlen($row['Issuer URI']) > 0){
                $vals = explode('|', $row['Issuer URI']);
                foreach ($vals as $val){
                    if (substr($val, -1) == '?'){
                        $uri = substr($val, 0, -1);
                        $uncertainty = true;
                        $content = processUri($uri);
                    } else {
                        $uri =  $val;
                        $uncertainty = false;
                        $content = processUri($uri);
                    }
                    $role = 'issuer';
                    
                    $doc->startElement($content['element']);
                        $doc->writeAttribute('xlink:type', 'simple');
                        $doc->writeAttribute('xlink:role', $role);
                        $doc->writeAttribute('xlink:href', $uri);
                        if($uncertainty == true){
                            $doc->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
                        }
                        $doc->text($content['label']);
                    $doc->endElement();
                }
            }
            
            if (array_key_exists('Artist URI', $row) && strlen($row['Artist URI']) > 0){
                $vals = explode('|', $row['Artist URI']);
                foreach ($vals as $val){
                    if (substr($val, -1) == '?'){
                        $uri = substr($val, 0, -1);
                        $uncertainty = true;
                        $content = processUri($uri);
                    } else {
                        $uri =  $val;
                        $uncertainty = false;
                        $content = processUri($uri);
                    }
                    $role = 'artist';
                    
                    $doc->startElement($content['element']);
                        $doc->writeAttribute('xlink:type', 'simple');
                        $doc->writeAttribute('xlink:role', $role);
                        $doc->writeAttribute('xlink:href', $uri);
                        if($uncertainty == true){
                            $doc->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
                        }
                        $doc->text($content['label']);
                    $doc->endElement();
                }
            }
            
            if (array_key_exists('Maker URI', $row) && strlen($row['Maker URI']) > 0){
                $vals = explode('|', $row['Maker URI']);
                foreach ($vals as $val){
                    if (substr($val, -1) == '?'){
                        $uri = substr($val, 0, -1);
                        $uncertainty = true;
                        $content = processUri($uri);
                    } else {
                        $uri =  $val;
                        $uncertainty = false;
                        $content = processUri($uri);
                    }
                    $role = 'maker';
                    
                    $doc->startElement($content['element']);
                        $doc->writeAttribute('xlink:type', 'simple');
                        $doc->writeAttribute('xlink:role', $role);
                        $doc->writeAttribute('xlink:href', $uri);
                        if($uncertainty == true){
                            $doc->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
                        }
                        $doc->text($content['label']);
                    $doc->endElement();
                }
            }
            
            if (array_key_exists('Authenticity URI', $row) && strlen($row['Authenticity URI']) > 0){
                $vals = explode('|', $row['Authenticity URI']);
                foreach ($vals as $val){
                    if (substr($val, -1) == '?'){
                        $uri = substr($val, 0, -1);
                        $uncertainty = true;
                        $content = processUri($uri);
                    } else {
                        $uri =  $val;
                        $uncertainty = false;
                        $content = processUri($uri);
                    }
                    
                    $doc->startElement('authenticity');
                        $doc->writeAttribute('xlink:type', 'simple');
                        $doc->writeAttribute('xlink:href', $uri);
                        if($uncertainty == true){
                            $doc->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
                        }
                        $doc->text($content['label']);
                    $doc->endElement();
                }
            }
            $doc->endElement();
        }
        
        //geography
        //mint
        if ((array_key_exists('Mint URI', $row) && strlen($row['Mint URI']) > 0) || (array_key_exists('Region URI', $row) && strlen($row['Region URI']) > 0)){
            $doc->startElement('geographic');
            if (array_key_exists('Mint URI', $row) && strlen($row['Mint URI']) > 0){
                $vals = explode('|', $row['Mint URI']);
                foreach ($vals as $val){
                    if (substr($val, -1) == '?'){
                        $uri = substr($val, 0, -1);
                        $uncertainty = true;
                        $content = processUri($uri);
                    } else {
                        $uri =  $val;
                        $uncertainty = false;
                        $content = processUri($uri);
                    }
                    
                    $doc->startElement('geogname');
                        $doc->writeAttribute('xlink:type', 'simple');
                        $doc->writeAttribute('xlink:role', 'mint');
                        $doc->writeAttribute('xlink:href', $uri);
                        if($uncertainty == true){
                            $doc->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
                        }
                        $doc->text($content['label']);
                    $doc->endElement();
                    
                    unset($uncertainty);
                }
            }
            
            if (array_key_exists('Region URI', $row) && strlen($row['Region URI']) > 0){
                $vals = explode('|', $row['Region URI']);
                foreach ($vals as $val){
                    if (substr($val, -1) == '?'){
                        $uri = substr($val, 0, -1);
                        $uncertainty = true;
                        $content = processUri($uri);
                    } else {
                        $uri =  $val;
                        $uncertainty = false;
                        $content = processUri($uri);
                    }
                    
                    $doc->startElement('geogname');
                        $doc->writeAttribute('xlink:type', 'simple');
                        $doc->writeAttribute('xlink:role', 'region');
                        $doc->writeAttribute('xlink:href', $uri);
                        if($uncertainty == true){
                            $doc->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
                        }
                        $doc->text($content['label']);
                    $doc->endElement();
                    
                    unset($uncertainty);
                }
            }
            $doc->endElement();
        }
        
        //obverse        
        if (array_key_exists('Obverse Type Code', $row) || array_key_exists('Obverse Legend', $row) || array_key_exists('Obverse Portrait URI', $row) || array_key_exists('Obverse Deity URI', $row)) {
            $doc->startElement('obverse');
            
            //legend
            render_legend($doc, $row, 'Obverse');
            
            //multilingual type descriptions            
            if (array_key_exists('Obverse Type Code', $row) && strlen($row['Obverse Type Code']) > 0) {        
                $key = $row['Obverse Type Code'];
                $doc->startElement('type');
                    foreach ($obverses as $desc){
                        if ($desc['code'] == $key){
                            foreach ($desc as $k=>$v){
                                if ($k != 'code'){
                                    if (strlen($v) > 0){
                                        $doc->startElement('description');
                                            $doc->writeAttribute('xml:lang', $k);
                                            $doc->text(trim($v));
                                        $doc->endElement();
                                    }
                                }
                            }
                            break;
                        }
                    }
                $doc->endElement();    
            }
            
            //try single obverse symbol column first before looking for O: columns
            if (array_key_exists('Obverse Symbol', $row) && strlen($row['Obverse Symbol']) > 0){
                $doc->startElement('symbol');
                    parse_symbol($doc, trim($row['Obverse Symbol']));
                $doc->endElement();
            } else {
                //symbols
                foreach ($row as $k=>$v){
                    //reverse symbols are preceded with R:
                    if (substr($k, 0, 2) == 'O:'){
                        if (strlen(trim($v)) > 0){
                            $position = trim(str_replace('O:', '', $k));
                            $position = strpos($position, '_') !== FALSE ? substr($position, 0, strpos($position, '_')) : $position;
                            
                            $doc->startElement('symbol');
                                //differentiate before positions and semantic types
                                if ($position == 'officinaMark' || $position == 'mintMark'){
                                    $doc->writeAttribute('localType', $position);
                                } else {
                                    $doc->writeAttribute('position', $position);
                                }
                                parse_symbol($doc, trim($v));
                            $doc->endElement();
                        }
                    }
                }
            }
            
            //deity
            if (array_key_exists('Obverse Deity URI', $row) && strlen($row['Obverse Deity URI']) > 0){
                $vals = explode('|', $row['Obverse Deity URI']);
                foreach ($vals as $val){
                    $val = trim($val);
                    if (substr($val, -1) == '?'){
                        $uri = substr($val, 0, -1);
                        $uncertainty = true;
                        $content = processUri($uri);
                    } else {
                        $uri =  $val;
                        $uncertainty = false;
                        $content = processUri($uri);
                    }
                    
                    $doc->startElement($content['element']);
                        $doc->writeAttribute('xlink:type', 'simple');
                        $doc->writeAttribute('xlink:role', 'deity');
                        $doc->writeAttribute('xlink:href', $uri);
                        if($uncertainty == true){
                            $doc->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
                        }
                        $doc->text($content['label']);
                    $doc->endElement();
                }
            }
            
            //portrait
            if (array_key_exists('Obverse Portrait URI', $row) && strlen($row['Obverse Portrait URI']) > 0){
                $vals = explode('|', $row['Obverse Portrait URI']);
                foreach ($vals as $val){
                    if (substr($val, -1) == '?'){
                        $uri = substr($val, 0, -1);
                        $uncertainty = true;
                        $content = processUri($uri);
                    } else {
                        $uri =  $val;
                        $uncertainty = false;
                        $content = processUri($uri);
                    }
                    $role = 'portrait';
                    
                    $doc->startElement('persname');
                        $doc->writeAttribute('xlink:type', 'simple');
                        $doc->writeAttribute('xlink:role', $role);
                        $doc->writeAttribute('xlink:href', $uri);
                        if($uncertainty == true){
                            $doc->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
                        }
                        $doc->text($content['label']);
                    $doc->endElement();
                }
            }
            
            
            //end obverse
            $doc->endElement();
        }
        
        //reverse
        if (array_key_exists('Reverse Type Code', $row) || array_key_exists('Reverse Legend', $row) || array_key_exists('Reverse Portrait URI', $row) || array_key_exists('Reverse Deity URI', $row)) {
            $doc->startElement('reverse');
            
            //legend
            render_legend($doc, $row, 'Reverse');
            
            //multilingual type descriptions
            if (array_key_exists('Reverse Type Code', $row) && strlen($row['Reverse Type Code']) > 0) {
                $key = $row['Reverse Type Code'];
                $doc->startElement('type');
                    foreach ($reverses as $desc){
                        if ($desc['code'] == $key){
                            foreach ($desc as $k=>$v){
                                if ($k != 'code'){
                                    if (strlen($v) > 0){
                                        $doc->startElement('description');
                                            $doc->writeAttribute('xml:lang', $k);
                                            $doc->text(trim($v));
                                        $doc->endElement();
                                    }
                                }
                            }
                            break;
                        }
                    }
                $doc->endElement();    
            }
            
            //portrait
            if (array_key_exists('Reverse Portrait URI', $row) && strlen($row['Reverse Portrait URI']) > 0){
                $vals = explode('|', $row['Reverse Portrait URI']);
                foreach ($vals as $val){
                    if (substr($val, -1) == '?'){
                        $uri = substr($val, 0, -1);
                        $uncertainty = true;
                        $content = processUri($uri);
                    } else {
                        $uri =  $val;
                        $uncertainty = false;
                        $content = processUri($uri);
                    }
                    $role = 'portrait';
                    
                    $doc->startElement('persname');
                        $doc->writeAttribute('xlink:type', 'simple');
                        $doc->writeAttribute('xlink:role', $role);
                        $doc->writeAttribute('xlink:href', $uri);
                        if($uncertainty == true){
                            $doc->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
                        }
                        $doc->text($content['label']);
                    $doc->endElement();
                }
            }
            
            //deity
            if (array_key_exists('Reverse Deity URI', $row) && strlen($row['Reverse Deity URI']) > 0){
                $vals = explode('|', $row['Reverse Deity URI']);
                foreach ($vals as $val){
                    $val = trim($val);
                    if (substr($val, -1) == '?'){
                        $uri = substr($val, 0, -1);
                        $uncertainty = true;
                        $content = processUri($uri);
                    } else {
                        $uri =  $val;
                        $uncertainty = false;
                        $content = processUri($uri);
                    }
                    
                    $doc->startElement($content['element']);
                        $doc->writeAttribute('xlink:type', 'simple');
                        $doc->writeAttribute('xlink:role', 'deity');
                        $doc->writeAttribute('xlink:href', $uri);
                        if($uncertainty == true){
                            $doc->writeAttribute('certainty', 'http://nomisma.org/id/uncertain_value');
                        }
                        $doc->text($content['label']);
                    $doc->endElement();
                }
            }
            
            //symbols
            foreach ($row as $k=>$v){
                //reverse symbols are preceded with R:
                if (substr($k, 0, 2) == 'R:'){
                    if (strlen(trim($v)) > 0){
                        $position = trim(str_replace('R:', '', $k));
                        $position = strpos($position, '_') !== FALSE ? substr($position, 0, strpos($position, '_')) : $position;
                        
                        $doc->startElement('symbol');
                            //differentiate before positions and semantic types
                            if ($position == 'officinaMark' || $position == 'mintMark' || $position == 'controlMark'){
                                $doc->writeAttribute('localType', $position);
                            } else {
                                $doc->writeAttribute('position', $position);
                            }
                            parse_symbol($doc, trim($v));
                        $doc->endElement();
                    }
                }
            }
            
            //end reverse
            $doc->endElement();
        }
        
        //Explicit Type Series
        if (array_key_exists('Type Series URI', $row) && strlen(trim($row['Type Series URI'])) > 0){
            $uri = $row['Type Series URI'];
            $content = processUri($uri);
            
            $doc->startElement('typeSeries');
                $doc->writeAttribute('xlink:type', 'simple');               
                $doc->writeAttribute('xlink:href', $uri);
                $doc->text($content['label']);
            $doc->endElement();
        }
        
        //end typeDesc
        $doc->endElement();
        
        /***** REFDESC *****/
        //evaluate any column beginning with the string 'Reference'
        $references = array();        
        foreach ($row as $k=>$v){
            if (strpos($k, 'Reference') === 0){
                if (preg_match('/^Reference\s\((.*)\)$/', $k, $matches)){
                    $ref = trim($v);
                    
                    if (strlen($ref) > 0 && isset($matches[1])) {                        
                        $references[] = array('refCode'=>$matches[1], 'value'=>$ref);
                    }
                } else {
                    $ref = trim($v);
                    
                    if (strlen($ref) > 0) {
                        $references[] = array('value'=>$v);
                    }
                }
            }
        }
        
        //create references to previous volumes        
        if ((array_key_exists('Deprecated ID', $row) && strlen($row['Deprecated ID']) > 0) || (array_key_exists('Matching URI', $row) && strlen($row['Matching URI']) > 0) || count($references) > 0){
            $doc->startElement('refDesc');
            
            if (array_key_exists('Deprecated ID', $row) && strlen($row['Deprecated ID']) > 0){
                $replaces = explode('|', $row['Deprecated ID']);
               
                foreach ($replaces as $deprecatedID){
                    $deprecatedID = trim($deprecatedID);
                    $doc->startElement('reference');
                        $doc->writeAttribute('xlink:type', 'simple');
                        $doc->writeAttribute('xlink:href', $uri_space . $deprecatedID);
                        $doc->text(get_title($deprecatedID, $project));
                    $doc->endElement();
                }                
            }
            
            if (array_key_exists('Matching URI', $row) && strlen($row['Matching URI']) > 0){
                $matches = explode('|', $row['Matching URI']);
                
                foreach ($matches as $uri){
                    $uri = trim($uri);
                    
                    //get title via RDF
                    $content = processUri($uri);
                    
                    $doc->startElement('reference');
                        $doc->writeAttribute('xlink:type', 'simple');
                        $doc->writeAttribute('xlink:href', $uri);
                        $doc->text($content['label']);
                    $doc->endElement();
                }
            }
            
            //parse text-based, not URI references, linking them to Nomisma IDs for type series, if applicable
            foreach ($references as $reference) {
                $doc->startElement('reference');
                    
                if (array_key_exists('refCode', $reference)) {
                    $refMetadata = parse_refCode($reference['refCode']);
                    
                    //if the reference code returns an array of metadata, then generate TEI, otherwise output the text of the column value
                    if (isset($refMetadata)){
                        $doc->startElement('tei:title');
                        	if (array_key_exists('typeSeries', $refMetadata)){
                        		$doc->writeAttribute('key', $refMetadata['typeSeries']);
                        	}
                            $doc->text($refMetadata['title']);
                        $doc->endElement();
                        $doc->startElement('tei:idno');
                            $doc->text($reference['value']);
                        $doc->endElement();
                    } else {
                        $doc->text($reference['value']);
                    }
                    
                } else {
                    $doc->text($reference['value']);
                }  
                    
                $doc->endElement();
            }
            
            $doc->endElement();
        }
        
        //end descMeta
        $doc->endElement();
        //close NUDS
        $doc->endElement();
        
        //close file
        $doc->endDocument();
        $doc->flush();
        
        
        //initiate the put_to_exist process
        if ($mode == 'prod') {            
            put_to_exist($recordId, $project, $eXist_credentials, $indexable);
        } 
        
    } else {
        echo "No ID.\n";
    }
}

/***** PUBLICATION AND REPORTING FUNCTIONS *****/
function put_to_exist($recordId, $project, $eXist_credentials, $indexable) {
    GLOBAL $errors;
    GLOBAL $idsToIndex;
    
    $fileName = 'nuds/' . $project['name'] . '/'. $recordId . '.xml';
    $datetime = date(DATE_W3C);
    
    //read file back into memory for PUT to eXist
    if (($readFile = fopen($fileName, 'r')) === FALSE){
        $errors[] = "Unable to read {$fileName} for putting to eXist-db.\n";
    } else {
        //PUT xml to eXist
        $putToExist=curl_init();
        
        //set curl opts
        curl_setopt($putToExist,CURLOPT_URL, EXIST_URL . $project['name'] . '/objects/' . $recordId . '.xml');
        curl_setopt($putToExist,CURLOPT_HTTPHEADER, array("Content-Type: text/xml; charset=utf-8"));
        curl_setopt($putToExist,CURLOPT_CONNECTTIMEOUT,2);
        curl_setopt($putToExist,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($putToExist,CURLOPT_PUT,1);
        curl_setopt($putToExist,CURLOPT_INFILESIZE,filesize($fileName));
        curl_setopt($putToExist,CURLOPT_INFILE,$readFile);
        curl_setopt($putToExist,CURLOPT_USERPWD,$eXist_credentials);
        $response = curl_exec($putToExist);
        
        $http_code = curl_getinfo($putToExist,CURLINFO_HTTP_CODE);
        
        //error and success logging
        if (curl_error($putToExist) === FALSE){
            $errors[] = "{$recordId} failed to upload to eXist at {$datetime}\n";
        } else {
            if ($http_code == '201'){
                echo "Writing {$recordId}.\n";
                
                //if file was successfully PUT to eXist, add the accession number to the array for Solr indexing, but only if it isn't a deprecated type
                
                if ($indexable == true) {
                    
                    echo "Indexing {$recordId}\n";
                    
                    $idsToIndex[] = $recordId;
                    
                    //index records into Solr in increments of the INDEX_COUNT constant
                    if (count($idsToIndex) > 0 && count($idsToIndex) % INDEX_COUNT == 0 ){
                        $start = count($idsToIndex) - INDEX_COUNT;
                        $toIndex = array_slice($idsToIndex, $start, INDEX_COUNT);
                        
                        //POST TO SOLR
                        generate_solr_shell_script($toIndex, $project);
                    }
                } else {
                    echo "Not indexing {$recordId}\n";    
                }                
            }
        }
        //close eXist curl
        curl_close($putToExist);
        
        //close files and delete
        fclose($readFile);
        unlink($fileName);
    }
}

//generate a shell script to activate batch ingestion
function generate_solr_shell_script($array, $project){
    $uniqid = uniqid();
    
    //urlencode the identifiers URL parameter
    $identifiers = urlencode(implode('|', $array));
    
    $solrDocUrl = 'http://localhost:8080/orbeon/numishare/' . $project['name'] . '/ingest?identifiers=' . $identifiers;
    
    //generate content of bash script
    $sh = "#!/bin/sh\n";
    $sh .= "curl {$solrDocUrl} > /tmp/{$uniqid}.xml\n";
    $sh .= "curl " . NUMISHARE_SOLR_URL . " --data-binary @/tmp/{$uniqid}.xml -H 'Content-type:text/xml; charset=utf-8'\n";
    $sh .= "curl " . NUMISHARE_SOLR_URL . " --data-binary '<commit/>' -H 'Content-type:text/xml; charset=utf-8'\n";
    $sh .= "rm /tmp/{$uniqid}.xml\n";
    
    $shFileName = '/tmp/' . $uniqid . '.sh';
    $file = fopen($shFileName, 'w');
    if ($file){
        fwrite($file, $sh);
        fclose($file);
        
        echo "Posting to Solr.\n";
        
        //execute script
        shell_exec('sh /tmp/' . $uniqid . '.sh > /dev/null 2>/dev/null &');
        //commented out the line below because PHP seems to delete the file before it has had a chance to run in the shell
        //unlink('/tmp/' . $uniqid . '.sh');
    } else {
        echo "Unable to read {$uniqid}.sh\n";
    }
}

//send an email report
function generate_email_report ($idsToIndex, $errors, $project, $startTime, $endTime){
    $to = 'database@numismatics.org,egruber@numismatics.org,' . $project['email'];
    $subject = "Error report for " . $project['name'];
    $body = "Error Report for " . $project['name'] . "\n\n";
    $body .= "Successful objects: " . count($idsToIndex) . "\n";
    $body .= "Errors: " . count($errors) . "\n\n";
    $body .= "Start Time: {$startTime}\n";
    $body .= "End Time: {$endTime}\n\n";
    $body .= "The following accession numbers failed to process:\n\n";
    foreach ($errors as $error){
        $body .= $error . "\n";
    }
    $body .= "\nNote that records with errors were not published to Numishare. Please review the relevant spreadsheets.\n";
    
    echo "Sending email report.\n";
    
    mail($to, $subject, $body);
}

/***** FUNCTIONS FOR PROCESSING SYMBOLS INTO EPIDOC TEI *****/
//parse symbol text into TEI
function parse_symbol($doc, $text){
    
    $doc->startElement('tei:div');
    $doc->writeAttribute('type', 'edition');
    
    //split into two pieces: three forward slashes indicates one symbol is above the other
    if (strpos($text, '///') !== FALSE){
        $positions = explode('///', $text);
        
        foreach ($positions as $k=>$pos){
            $pos = trim($pos);
            
            $doc->startElement('tei:ab');
            if ($k == 0) {
                $rend = 'above';
            } else {
                $rend = 'below';
            }
            
            $doc->writeAttribute('rend', $rend);
            parse_split($doc, $pos, 2);
            $doc->endElement();
        }
    } elseif ($text == '[no monogram]') {
        //semantically encode intentional blank space for subtypes
        $doc->startElement('tei:ab');
            $doc->writeElement('tei:space');
        $doc->endElement();
    } elseif ($text == '[unclear]') {
        //semantically encode intentional blank space for subtypes
        $doc->startElement('tei:ab');
            $doc->writeElement('tei:unclear');
        $doc->endElement();
    } else {
        parse_split($doc, $text, 1);
    }
    
    $doc->endElement();
}

//parse segments separated by ||, a high-level conditional split between options
function parse_split ($doc, $text, $level){
    if (strpos($text, '||') !== FALSE){
        $choices = explode('||', $text);
        
        //begin choice element
        $doc->startElement('tei:choice');
        foreach ($choices as $choice){
            $choice = trim($choice);
            parse_horizontal($doc, $choice, $level);
        }
        $doc->endElement();
    } else {
        parse_horizontal($doc, $text, $level);
    }
}

//parse segments separated by |, which signifies side-by-side glyphs
function parse_horizontal ($doc, $text, $level){
    if (strpos($text, '|') !== FALSE){
        $horizontal = explode('|', $text);
        
        $doc->startElement('tei:ab');        
            foreach ($horizontal as $seg){
                $seg = trim($seg);
                
                if ($level == 1){
                    parse_conditional($doc, $seg, true);
                } else {
                    $doc->startElement('tei:seg');
                        parse_conditional($doc, $seg, true);
                    $doc->endElement();
                }
            }
        $doc->endElement();
    } else {
        //no horizontal configuration, so parse ' or '
        parse_conditional($doc, $text, false);
    }
}

//split choices separated by ' or '
function parse_conditional($doc, $text, $parent){
    if (strpos($text, ' or ') !== FALSE){
        $choices = explode(' or ', $text);
        
        //begin choice element
        $doc->startElement('tei:choice');
        foreach ($choices as $choice){
            $choice = trim($choice);
            
            parse_seg($doc, $choice, true);
        }
        $doc->endElement();
    } else {
        parse_seg($doc, $text, $parent);
    }
}

//parse an atomized seg into a monogram glyph, seg, or just cdata
function parse_seg($doc, $seg, $parent){
    
    if (preg_match('/(.*)\s?\((.*)\)$/', $seg, $matches)){
        write_seg_tei($doc, trim($matches[1]), trim($matches[2]), $parent);
    } else {
        write_seg_tei($doc, trim($seg), null, $parent);
    }
}

function write_seg_tei ($doc, $seg, $rend, $parent){    
    if (preg_match('/^(https?:\/\/.*)/', $seg, $matches)){
        $uri = trim($matches[1]);
        $content = processUri($uri);
        
        
        if ($parent == false){
            $doc->startElement('tei:ab');
        }
        //insert a single monogram into an ab, if applicable
        $doc->startElement('tei:am');
            $doc->startElement('tei:g');
                $doc->writeAttribute('type', $content['type']);
                if (isset($rend)){
                    if ($rend == '?'){
                        $doc->writeAttribute('rend', 'unclear');
                    } else {
                        $doc->writeAttribute('rend', $rend);
                    }
                }
                
                
                $doc->writeAttribute('ref', $uri);
                $doc->text($content['label']);
            //end tei:g
            $doc->endElement();
        //end tei:am
        $doc->endElement();
        
        if ($parent == false){
            $doc->endElement();
        }
    } else {
        //if there are parent TEI elements, then use tei:seg, otherwise tei:ab (tei:seg cannot appear directly in tei:div)
        
        if ($parent == true){
            if (isset($rend)){
                if ($rend == '?'){
                    $doc->startElement('tei:seg');
                        $doc->writeElement('tei:unclear', $seg);
                    $doc->endElement();
                } else {
                    $doc->startElement('tei:seg');
                        $doc->writeAttribute('rend', $rend);
                        $doc->text($seg);
                    $doc->endElement();
                }
            } else {
                $doc->writeElement('tei:seg', $seg);
            }
            
        } else {
            if (isset($rend)){
                if ($rend == '?'){
                    $doc->startElement('tei:ab');
                        $doc->writeElement('tei:unclear', $seg);
                    $doc->endElement();
                } else {
                    $doc->startElement('tei:ab');
                        $doc->writeAttribute('rend', $rend);
                        $doc->text($seg);
                    $doc->endElement();
                }
            } else {
                $doc->writeElement('tei:ab', $seg);
            }
        }
    }
}

//result the 3-letter ISO code for language based on the script code embedded in the legend column
function parse_scriptCode($scriptCode){
        
    switch ($scriptCode){
        case 'Grek':
            $lang = 'grc';
            break;
        case 'Latn': 
            $lang = 'lat';
            break;
        case 'Phnx':
            $lang = 'phn';
            break;
        default:
            $lang = 'null';
    }
    
    return array('scriptCode'=>$scriptCode, 'lang'=>$lang);
}

//parse the reference code from the parentheses of the Reference column header
function parse_refCode($refCode){
    
    switch ($refCode){
        case 'Bopearachchi':
            $metadata = array('title'=>'Bopearachchi', 'typeSeries'=>'http://nomisma.org/id/bopearachchi-1991');
            break;
        case 'Mitchiner':
            $metadata = array('title'=>'Mitchiner', 'typeSeries'=>'http://nomisma.org/id/mitchiner-1976');
            break;
        //LCO Yehud
        case 'TJC':
            $metadata = array('title'=> "TJC", 'typeSeries'=> 'https://donum.numismatics.org/bib/160000');
            break;
        case 'Hendin (2010)':
            $metadata = array('title'=> 'Hendin (2010)', 'typeSeries'=> 'https://zenon.dainst.org/Record/003002648');
            break;
        //LCO Samaria
        case 'AJC I, Supplement I':
            $metadata = array('title'=> 'Thompson, M., Mørkholm, O., and Kraay, C. M. 1973. An Inventory of Greek Coin Hoards (Supplement I). American Numismatic Society. New York ', 'typeSeries'=> 'https://donum.numismatics.org/bib/20683');
            break;
        case 'Alram 1986':
            $metadata = array('title'=> 'Alram, M. 1986. Nomina Propia Iranica in Numis. Iranisches Personennamenbuch IV. Vienna.', 'typeSeries'=> 'https://search.worldcat.org/de/title/610372319');
            break;
        case 'Babelon 1893':
            $metadata = array('title'=> 'Babelon, E. 1893. Les perses achéménides, les satrapes et les dynastes tributaires de leur empire Cypre & Phénicie. Paris.', 'typeSeries'=> 'https://search.worldcat.org/de/title/889853653');
            break;
        case 'Babelon 1910':
            $metadata = array('title'=> 'Babelon, E. 1910. Traité des monnaies grecques et romaines. Deuxième partie. Description historique. Vol. II. Paris.', 'typeSeries'=> 'https://search.worldcat.org/de/title/1068297698');
            break;
        case 'BMC Palestine':
            $metadata = array('title'=> 'Hill, G.F. 1914. Catalogue of the Greek Coins in the British Museum – Palestine. London.', 'typeSeries'=> 'https://search.worldcat.org/es/title/7439486');
            break;
        case 'Bodzek 2022':
            $metadata = array('title'=> 'Bodzek, J. 2022. A Note on a Samarian Coin Type. A Royal Horseman? Gephyra 24: 143–149.', 'typeSeries'=> 'https://zenon.dainst.org/Record/003031520');
            break;
        case 'CH':
            $metadata = array('title'=> 'Coin Hoards');
            break;
        case 'CHL':
            $metadata = array('title'=> 'Meshorer, Y., Bijovsky, G. and Fischer-Bossert, W. 2013. Coins of the Holy Land: The Abraham and Marian Sofaer Collection at the American Numismatic Society and the Israel Museum. Ed. by D. Hendin and A. Meadows. New York.', 'typeSeries'=> 'https://donum.numismatics.org/bib/182209');
            break;
        case 'CNG':
            $metadata = array('title'=> 'Classical Numismatic Group, LLC', 'typeSeries'=> 'https://www.cngcoins.com/');
            break;
        case 'CS':
            $metadata = array('title'=> 'Meshorer, Y. and Qedar, S. 1991. The Coinage of Samaria of the Fourth Century BCE. Los Angeles and Jerusalem.', 'typeSeries'=> 'https://donum.numismatics.org/bib/52910');
            break;
        case 'Deutsch and Heltzer 1997':
            $metadata = array('title'=> 'Deutsch, R. and Heltzer, M. 1997. Numismatic Evidence from the Persian Period from the Sharon Plain. Transeuphraténe 13: 17–20.', 'typeSeries'=> 'https://donum.numismatics.org/bib/148751');
            break;
        case 'Farhi 2016':
            $metadata = array('title'=> 'Farhi, Y. 2016. Khirbet Qeiyafa 5. Excavation Report 2007–2013. The Numismatic Finds: Coins and Related Objects. Jerusalem.', 'typeSeries'=> 'https://search.worldcat.org/de/title/503449971');
            break;
        case 'Gitler 2013':
            $metadata = array('title'=> 'Gitler, H., 2013. Samarian Coin Types Inspired by Athenian Iconography, in P. van Alfen and R. Witschonke eds., Essays in Honour of Roberto Russo. Zürich and London. Pp. 65–72.', 'typeSeries'=> 'https://donum.numismatics.org/bib/189963');
            break;
        case 'GT':
            $metadata = array('title'=> 'Gitler, H. and Tal, O. 2006. Coins with the Aramaic Legend ŠHRW and Other Unrecorded Samarian Issues. Swiss Numismatic Review 85: 47–68.', 'typeSeries'=> 'https://zenon.dainst.org/Record/000736003');
            break;
        case 'Harrison 1982':
            $metadata = array('title'=> 'Harrison, C.M. 1982. Coins of the Persian Satraps. Ph.D. Dissertation. University of Pennsylvania.', 'typeSeries'=> 'https://zenon.dainst.org/Record/001605064');
            break;
        case 'IAA':
            $metadata = array('title'=> 'Israel Antiquities Authority', 'typeSeries'=> 'https://www.iaa.org.il/en/');
            break;
        case 'IMJ':
            $metadata = array('title'=> 'Israel Museum, Jerusalem', 'typeSeries'=> 'https://www.imj.org.il/');
            break;
        case 'JC Samaria':
            $metadata = array('title'=> 'Gitler, H., Jeselsohn, D., Johananoff, M. and Tal, O. 2024. The Jeselsohn Collection of Coins of the Holy Land, vol. I: Persian and Early Hellenistic Coinage. Jerusalem.', 'typeSeries'=> 'https://donum.numismatics.org/bib/220521');
            break;
        case 'Johananoff 2021':
            $metadata = array('title'=> 'Johananoff, M. 2021. From Sidon to Samaria: Fourth-Century BCE Autonomous Coins of Samaria with Sidonian Motifs. Israel Numismatic Research 16: 3–34.', 'typeSeries'=> 'https://donum.numismatics.org/bib/211671');
            break;
        case 'Johananoff et al. 2022':
            $metadata = array('title'=> 'Johananoff, M., Ashkenazi, D., Cohen, M., Gitler, H. and Tal, O. 2022. Typological and Metallurgical Analysis of a Repeated Overstrike on a ‘Lyre Player’ Coin Type Attributed to Samaria. Israel Numismatic Research 17: 3–26.', 'typeSeries'=> 'https://donum.numismatics.org/bib/215347');
            break;
        case 'Johananoff 2023':
            $metadata = array('title'=> 'Johananoff, M. 2023. A Case of Competing Attributions: Small Anepigraphic Levantine Silver Coins with A Female Head and an Eagle on a Thunderbolt. Notae Numismaticae 18: 31–47.', 'typeSeries'=> 'https://search.worldcat.org/de/title/10485778668');
            break;
        case 'Kraay 1978':
            $metadata = array('title'=> 'Kraay, C. 1978. Some Notes on the Abu Shusheh ‘Hoard’. Israel Exploration Journal 28: 190–192.', 'typeSeries'=> 'https://search.worldcat.org/de/title/9973422887');
            break;
        case 'Lambert 1932':
            $metadata = array('title'=> 'Lambert, C. 1932. Egypto-Arabian, Phoenician, and other Coins of the Fourth Century B.C. Found in Palestine. Quarterly of the Department of Antiquities in Palestine 2: 1–10.', 'typeSeries'=> 'https://donum.numismatics.org/bib/142662');
            break;
        case 'Moysey':
            $metadata = array('title'=> 'Moysey, R.A. 1989. Observations on the Numismatic Evidence Relating to the Great Satrapal Revolt of 362/1 B.C. Revue des Études Anciennes 91/1–2: 107–139.', 'typeSeries'=> 'https://donum.numismatics.org/bib/4075');
            break;
        case 'MQ':
            $metadata = array('title'=> 'Meshorer, Y. and Qedar, S. 1999. Samarian Coinage. Publication of the Israel Numismatic Society (Numismatic Studies and Researches 9). Jerusalem.', 'typeSeries'=> 'https://donum.numismatics.org/bib/171355');
            break;
        case 'NAC':
            $metadata = array('title'=> 'Numismatica Ars Classica NAC AG', 'typeSeries'=> 'https://www.arsclassicacoins.com/');
            break;
        case 'Narkiss 1938':
            $metadata = array('title'=> 'Narkiss, M. 1938. Coins of Palestine, Part Two: The Coins of the Gentiles. Jerusalem (Hebrew).', 'typeSeries'=> 'https://donum.numismatics.org/bib/27844');
            break;
        case 'Newell 1938':
            $metadata = array('title'=> 'Newell, E.T. 1938. Miscellanea numismatica: Cyrene to India (American Numismatic Society, Numismatic Notes and Monographs 82). New York.', 'typeSeries'=> 'https://donum.numismatics.org/bib/97810');
            break;
        case 'NH':
            $metadata = array('title'=> 'Gitler, H. and Tal, O. 2019. The Nablus 1968 Hoard: A Study of Monetary Circulation in the Late Fourth and Early Third Centuries BCE Southern Levant (Numismatic Notes and Monographs 171). New York.', 'typeSeries'=> 'https://donum.numismatics.org/bib/204378');
            break;
        case 'Rynearson 2000':
            $metadata = array('title'=> 'Rynearson, P. 2000. Descriptive Catalogue of Coins. In: Wallak Samuels, C., Rynearson, P. and Meshorer, Y., eds. The Numismatic Legacy of the Jews, as Depicted by a Distinguished American Collection. New York. Pp. 151–211.', 'typeSeries'=> 'https://donum.numismatics.org/bib/165002');
            break;
        case 'SH':
            $metadata = array('title'=> 'Samaria Hoard ');
            break;
        case 'SHC':
            $metadata = array('title'=> 'Maxim Shick Collection');
            break;
        case 'Six 1895':
            $metadata = array('title'=> 'Six, J.P. 1888. Monnaies grecques inédites et incertaines. VII. Sabacès satrape d’Égypte, 333. Numismatic Chronicle (Third Series) 8: 97–137.', 'typeSeries'=> 'https://search.worldcat.org/es/title/9974350294');
            break;
        case 'SNG ANS':
            $metadata = array('title'=> 'Sylloge Nummorum Graecorum: The Collection of the American Numismatic Society, Part 6, Palestine-South Arabia. New York 1981.', 'typeSeries'=> 'https://zenon.dainst.org/Record/000251121');
            break;
        case 'SNG Cop. Suppl.':
            $metadata = array('title'=> 'Sylloge Nummorum Graecorum Copenhagen Supplement, Acquisitions 1942–1996. Copenhagen 2002.', 'typeSeries'=> 'https://zenon.dainst.org/Record/000068047');
            break;
        case 'Spaer 1979':
            $metadata = array('title'=> 'Spaer, A. 1979. A Coin of Jeroboam? Israel Exploration Journal 29: 218.', 'typeSeries'=> 'https://donum.numismatics.org/bib/130933');
            break;
        case 'YC':
            $metadata = array('title'=> 'Gitler, H., Lorber, C. and Fontanille, J.-P. 2023. The Yehud Coinage: A Die Study and Classification of the Provincial Silver Coinage of Judah. Publication of the Israel Numismatic Society (Numismatic Studies and Researches 12). Jerusalem.', 'typeSeries'=> 'https://donum.numismatics.org/bib/220028');
            break;
        case 'YR':
            $metadata = array('title'=> 'Ronen, Y. 2007. Twenty Unrecorded Samarian Coins. Israel Numismatic Research 2: 29–33.', 'typeSeries'=> 'https://donum.numismatics.org/bib/178125');
            break;
        case 'Zlotnik 2016':
            $metadata = array('title'=> 'Zlotnik, Y. 2016. Samarian Coin Types and Their Denominations. Israel Numismatic Research 11: 3–10.', 'typeSeries'=> 'https://donum.numismatics.org/bib/196688');
            break;
        case 'CSC':
            $metadata = array('title'=> 'Gitler, H., Johananoff, M. and Tal, O. 2025. A Corpus of Samarian Coinage. Jerusalem.');
            break;
        default:
            $metadata = array('title'=>$refCode);
    }
    
    return $metadata;
}

function render_legend($doc, $row, $side){
    //if the legend is encoded in a single column, then process that
    if (array_key_exists($side . ' Legend', $row) && strlen(trim($row[$side . ' Legend'])) > 0){
        $legend = trim($row[$side . ' Legend']);
        
        $doc->startElement('legend');
        if ((array_key_exists($side . ' Legend Orientation', $row) && strlen($row[$side . ' Legend Orientation']) > 0) || (array_key_exists($side . ' Legend Tranliteration', $row) && strlen($row[$side . ' Legend Tranliteration']) > 0)){
            //edition
            $doc->startElement('tei:div');
                $doc->writeAttribute('type', 'edition');
                $doc->startElement('tei:ab');
                    if (array_key_exists($side . ' Legend Orientation', $row) && strlen($row[$side . ' Legend Orientation']) > 0){
                        $doc->writeAttribute('rend', trim($row[$side . ' Legend Orientation']));
                    }
                    $doc->text($legend);
                $doc->endElement();
            $doc->endElement();
            
            //transliteration
            if (array_key_exists($side . ' Legend Transliteration', $row) && strlen(trim($row[$side . ' Legend Transliteration'])) > 0){
                $doc->startElement('tei:div');
                    $doc->writeAttribute('type', 'transliteration');
                    $doc->startElement('tei:ab');
                        $doc->text(trim($row[$side . ' Legend Transliteration']));
                    $doc->endElement();
                $doc->endElement();
            }
        } elseif (strpos($legend, '<hi') !== FALSE){
            //rendering for legibility, ligatures, etc.
            
            $legend = str_replace('hi', 'tei:hi', $legend);
            
            $doc->startElement('tei:div');
                $doc->writeAttribute('type', 'edition');
                $doc->startElement('tei:ab');
                    $doc->writeRaw($legend);
                $doc->endElement();
            $doc->endElement();
        } else {
            $doc->text($legend);
        }
        
        $doc->endElement();
        
        unset($legend);
    } else {
        //otherwise, iterate through columns to find any Reverse Legend Column with a script code (ISO 15924) via regex
        $legends = array();
        
        foreach ($row as $k=>$v){
            if (preg_match('/^' . $side . '\sLegend\s\(([A-Z][a-z]{3})\)$/', $k, $matches)){
                $legend = trim($v);
                
                if (strlen($legend) > 0) {
                    $legends[] = array('scriptCode'=>$matches[1], 'value'=>$legend);
                }
            }
        }
        
        if (count($legends) > 0){
            $doc->startElement('legend');
                if (count($legends) == 1) {
                    $lang = parse_scriptCode($legends[0]['scriptCode']);
                    $doc->writeAttribute('scriptCode', $lang['scriptCode']);
                    $doc->writeAttribute('xml:lang', $lang['lang']);
                    $doc->text($legends[0]['value']);
                } else {
                    $doc->startElement('tei:div');
                        $doc->writeAttribute('type', 'edition');
                        //include orientation if applicable
                        if (array_key_exists($side . ' Legend Orientation', $row) && strlen($row[$side . ' Legend Orientation']) > 0){
                            $doc->writeAttribute('rend', trim($row[$side . ' Legend Orientation']));
                        }
                
                        foreach ($legends as $legend){
                            $lang = parse_scriptCode($legend['scriptCode']);
                            
                            //the EpiDoc guidelines concatenate the language code with the script code with a hyphen separator, https://epidoc.stoa.org/gl/latest/trans-foreigntext.html
                            $doc->startElement('tei:div');
                                $doc->writeAttribute('type', 'textpart');
                                $doc->writeAttribute('xml:lang', $lang['lang'] . '-' . $lang['scriptCode']);
                                $doc->startElement('tei:ab');
                                    $doc->text($legend['value']);
                                $doc->endElement();
                            $doc->endElement();
                        }
                    $doc->endElement();
                        
                    //transliteration
                    if (array_key_exists($side . ' Legend Transliteration', $row) && strlen(trim($row[$side . ' Legend Transliteration'])) > 0){
                        $doc->startElement('tei:div');
                            $doc->writeAttribute('type', 'transliteration');
                            $doc->startElement('tei:ab');
                                $doc->text(trim($row[$side . ' Legend Transliteration']));
                            $doc->endElement();
                        $doc->endElement();
                    }
                }
            $doc->endElement();
        }
        
        unset($legends);
    }
}

//parse the recordId and construct the typeNumber
function get_typeNumber($recordId, $project){
    if ($project['name'] == 'aod'){
        $typeNumber = $recordId;
    } elseif ($project['name'] == 'ocre'){
        $typeNumber = explode('.', $recordId)[3];
    } elseif ($project['name'] == 'crro') {
        $typeNumber = str_replace('.', '/', str_replace('rrc-', '', $recordId));
    } elseif ($project['name'] == 'agco') {
        $typeNumber = explode('.', $recordId)[2];
    } elseif ($project['name'] == 'pco'){
        $typeNumber = explode('.', $recordId)[2];
    } elseif ($project['name'] == 'sco'){
        $typeNumber = str_replace('sc.1.', '', $recordId);
    } elseif ($project['name'] == 'lco'){
        $typeNumber = str_replace('lco.yhd.', '', $recordId);
    } elseif ($project['name'] == 'bigr'){
        $idPieces = explode('.', $recordId);
        
        if (isset($idPieces[4])) {
            $typeNumber = $idPieces[2] . '.' . $idPieces[3] . '.' . $idPieces[4];
        } elseif (isset($idPieces[3])) {
            $typeNumber = $idPieces[2] . '.' . $idPieces[3];
        } else {
            $typeNumber = $idPieces[2];
        }
    } else {
        $typeNumber = "Update get_typeNumber() function";
    }
    
    return $typeNumber;
}

//function for automatically generating the title based on the recordId
function get_title($recordId, $project){
    if ($project['name'] == 'aod'){
        $title = "AoD " . $recordId;
    } elseif ($project['name'] == 'ocre'){
        //parsing of the OCRE title is more complex and is handled by a standalone function
        $title = get_ocre_title($recordId);
    } elseif ($project['name'] == 'crro'){
        $title = str_replace('.', '/', str_replace('rrc-', 'RRC ', $recordId));
    } elseif ($project['name'] == 'agco') {
        $title = 'Newell Demetrius Poliorcetes, no. ' . explode('.', $recordId)[2];
    } elseif ($project['name'] == 'lco') {
        $title = str_replace("lco.yhd.", "Yehud Coinage ", $recordId);
    } elseif ($project['name'] == 'pco'){
        $pieces = explode('.', $recordId);
        switch ($pieces[1]){
            case '1_1':
                $vol = 'Vol. I, Part 1';
                break;
            case '1_2':
                $vol = 'Vol. I, Part II';
                break;
        }
        
        $title = 'Coins of the Ptolemaic Empire ' . $vol . ', no. '. $pieces[2];
    } elseif ($project['name'] == 'sco') {
      $pieces = explode('.', $recordId);
      
      if ((int) $pieces[2] >= 1296) {
          $part = '2';
      } else {
          $part = '1';
      }
      
      $title = 'Seleucid Coins (part ' . $part . ') ' . str_replace('sc.1.', '', $recordId);
    } elseif ($project['name'] == 'bigr'){
        $name = '';
        
        $pieces = explode('.', $recordId);
        
        if (isset($pieces[4])) {
            $typeNumber = $pieces[2] . '.' . $pieces[3] . '.' . $pieces[4];
        } elseif (isset($pieces[3])) {
            $typeNumber = $pieces[2] . '.' . $pieces[3];
        } else {
            $typeNumber = $pieces[2];
        }
        
        $authority = $pieces[1];
        
        switch ($authority){
            case 'diodotus_i_ii':
                $name = 'Diodotus I or Diodotus II';
                break;
            case 'heliocles_laodice':
                $name = 'Heliocles and Laodice';
                break;
            case 'agathocleia_strato_i':
                $name = 'Strato I and Agathocleia';
                break;
            case 'lysias_antialcidas':
                $name = 'Lysias and Antialcidas';
                break;
            case 'hermaeus_calliope':
                $name = 'Hermaeus and Calliope';
                break;
            case 'strato_ii_iii':
                $name = 'Strato II and Strato III';
                break;
            default:
                $namePieces = explode('_', $authority);
                $newName = array();
                
                foreach ($namePieces as $frag){
                    if (preg_match('/^i/', $frag)){
                        $newName[] = str_replace('i', 'I', $frag);
                    } else {
                        $newName[] = ucfirst($frag);
                    }
                }
                
                $name = implode(' ', $newName);
        }
        
        $title = "Bactrian and Indo-Greek Coinage {$name} {$typeNumber}";
    } else {
        $title = "Update get_title() function";
    }
    
    return $title;
}

//parse the ID sequence to create a title for OCRE specifically
function get_ocre_title($recordId){
    $pieces = explode('.', $recordId);
    switch ($pieces[1]) {
        case '1':
            $vol = 'I';
            break;
        case '1(2)':
            $vol = 'I (second edition)';
            break;
        case '2':
            $vol = 'II';
            break;
        case '2_1(2)':
            $vol = 'II, Part 1 (second edition)';
            break;
        case '2_3(2)':
            $vol = 'II, Part 3 (second edition)';
            break;
        case '3':
            $vol = 'III';
            break;
        case '4':
            $vol = 'IV';
            break;
        case '5':
            $vol = 'V';
            break;
        case '6':
            $vol = 'VI';
            break;
        case '7':
            $vol = 'VII';
            break;
        case '8':
            $vol = 'VIII';
            break;
        case '9':
            $vol = 'IX';
            break;
        case '10':
            $vol = 'X';
            break;
    }
    
    switch ($pieces[2]) {
        case 'aug':
            $auth = 'Augustus';
            break;
        case 'tib':
            $auth = 'Tiberius';
            break;
        case 'gai':
            $auth = 'Gaius/Caligula';
            break;
        case 'cl':
            $auth = 'Claudius';
            break;
        case 'ner':
            if ($pieces[1] == '1(2)'){
                $auth = 'Nero';
            } else if ($pieces[1] == '2'){
                $auth = 'Nerva';
            }
            break;
        case 'clm':
            $auth = 'Clodius Macer';
            break;
        case 'cw':
            $auth = 'Civil Wars';
            break;
        case 'gal':
            $auth = 'Galba';
            break;
        case 'ot':
            $auth = 'Otho';
            break;
        case 'vit':
            $auth = 'Vitellius';
            break;
        case 'ves':
            $auth = 'Vespasian';
            break;
        case 'tit':
            $auth = 'Titus';
            break;
        case 'dom':
            $auth = 'Domitian';
            break;
        case 'anys':
            $auth = 'Anonymous';
            break;
        case 'tr':
            $auth = 'Trajan';
            break;
        case 'hdn':
            $auth = 'Hadrian';
            break;
        case 'ant':
            $auth = 'Antoninus Pius';
            break;
        case 'm_aur':
            $auth = 'Marcus Aurelius';
            break;
        case 'com':
            $auth = 'Commodus';
            break;
        case 'pert':
            $auth = 'Pertinax';
            break;
        case 'dj':
            $auth = 'Didius Julianus';
            break;
        case 'pn':
            $auth = 'Pescennius Niger';
            break;
        case 'ca':
            $auth = 'Clodius Albinus';
            break;
        case 'ss':
            $auth = 'Septimius Severus';
            break;
        case 'crl':
            $auth = 'Caracalla';
            break;
        case 'ge':
            $auth = 'Geta';
            break;
        case 'mcs':
            $auth = 'Macrinus';
            break;
        case 'el':
            $auth = 'Elagabalus';
            break;
        case 'sa':
            $auth = 'Severus Alexander';
            break;
        case 'max_i':
            $auth = 'Maximinus Thrax';
            break;
        case 'pa':
            $auth = 'Caecilia Paulina';
            break;
        case 'mxs':
            $auth = 'Maximus';
            break;
        case 'gor_i':
            $auth = 'Gordian I';
            break;
        case 'gor_ii':
            $auth = 'Gordian II';
            break;
        case 'balb':
            $auth = 'Balbinus';
            break;
        case 'pup':
            $auth = 'Pupienus';
            break;
        case 'gor_iii_caes':
            $auth = 'Gordian III (Caesar)';
            break;
        case 'gor_iii':
            $auth = 'Gordian III';
            break;
        case 'ph_i':
            $auth = 'Philip I';
            break;
        case 'pac':
            $auth = 'Pacatianus';
            break;
        case 'jot':
            $auth = 'Jotapianus';
            break;
        case 'mar_s':
            $auth = 'Mar. Silbannacus';
            break;
        case 'spon':
            $auth = 'Sponsianus';
            break;
        case 'tr_d':
            $auth = 'Trajan Decius';
            break;
        case 'tr_g':
            $auth = 'Trebonianus Gallus';
            break;
        case 'vo':
            $auth = 'Volusian';
            break;
        case 'aem':
            $auth = 'Aemilian';
            break;
        case 'uran_ant':
            $auth = 'Uranius Antoninus';
            break;
        case 'val_i':
            $auth = 'Valerian';
            break;
        case 'val_i-gall':
            $auth = 'Valerian and Gallienus';
            break;
        case 'val_i-gall-val_ii-sala':
            $auth = 'Valerian, Gallienus, Valerian II, and Salonina';
            break;
        case 'marin':
            $auth = 'Mariniana';
            break;
        case 'gall(1)':
            $auth = 'Gallienus (joint reign)';
            break;
        case 'gall_sala(1)':
            $auth = 'Gallienus and Salonina';
            break;
        case 'gall_sals':
            $auth = 'Gallienus and Saloninus';
            break;
        case 'sala(1)':
            $auth = 'Salonina';
            break;
        case 'val_ii':
            $auth = 'Valerian II';
            break;
        case 'sals':
            $auth = 'Saloninus';
            break;
        case 'qjg':
            $auth = 'Quintus Julius Gallienus';
            break;
        case 'gall(2)':
            $auth = 'Gallienus';
            break;
        case 'gall_sala(2)':
            $auth = 'Gallienus and Salonina (2)';
            break;
        case 'sala(2)':
            $auth = 'Salonina (2)';
            break;
        case 'cg':
            $auth = 'Claudius Gothicus';
            break;
        case 'qu':
            $auth = 'Quintillus';
            break;
        case 'aur':
            $auth = 'Aurelian';
            break;
        case 'aur_seva':
            $auth = 'Aurelian and Severina';
            break;
        case 'seva':
            $auth = 'Severina';
            break;
        case 'tac':
            $auth = 'Tacitus';
            break;
        case 'fl':
            $auth = 'Florian';
            break;
        case 'intr':
            $auth = 'Anonymous';
            break;
        case 'pro':
            $auth = 'Probus';
            break;
        case 'car':
            $auth = 'Carus';
            break;
        case 'dio':
            $auth = 'Diocletian';
            break;
        case 'post':
            $auth = 'Postumus';
            break;
        case 'lae':
            $auth = 'Laelianus';
            break;
        case 'mar':
            $auth = 'Marius';
            break;
        case 'vict':
            $auth = 'Victorinus';
            break;
        case 'tet_i':
            $auth = 'Tetricus I';
            break;
        case 'cara':
            $auth = 'Carausius';
            break;
        case 'cara-dio-max_her':
            $auth = 'Carausius issuing for Diocletian/Maximian';
            break;
        case 'all':
            $auth = 'Allectus';
            break;
        case 'mac_ii':
            $auth = 'Macrianus Minor';
            break;
        case 'quit':
            $auth = 'Quietus';
            break;
        case 'zen':
            $auth = 'Zenobia';
            break;
        case 'vab':
            $auth = 'Vabalathus';
            break;
        case 'reg':
            $auth = 'Regalianus';
            break;
        case 'dry':
            $auth = 'Dryantilla';
            break;
        case 'aurl':
            $auth = 'Aureolus';
            break;
        case 'dom_g':
            $auth = 'Domitianus of Gaul';
            break;
        case 'sat':
            $auth = 'Saturninus';
            break;
        case 'bon':
            $auth = 'Bonosus';
            break;
        case 'jul_i':
            $auth = 'Sabinus Julianus';
            break;
        case 'ama':
            $auth = 'Amandus';
            break;
        case 'lon':
            $auth = 'Londinium';
            break;
        case 'tri':
            $auth = 'Treveri';
            break;
        case 'lug':
            $auth = 'Lugdunum';
            break;
        case 'tic':
            $auth = 'Ticinum';
            break;
        case 'aq':
            $auth = 'Aquileia';
            break;
        case 'rom':
            $auth = 'Rome';
            break;
        case 'ost':
            $auth = 'Ostia';
            break;
        case 'carth':
            $auth = 'Carthage';
            break;
        case 'sis':
            $auth = 'Siscia';
            break;
        case 'serd':
            $auth = 'Serdica';
            break;
        case 'her':
            $auth = 'Heraclea';
            break;
        case 'nic':
            $auth = 'Nicomedia';
            break;
        case 'cyz':
            $auth = 'Cyzicus';
            break;
        case 'anch':
            $auth = 'Antioch';
            break;
        case 'alex':
            $auth = 'Alexandria';
            break;
        case 'ar':
            $auth = 'Arelate';
            break;
        case 'thes':
            $auth = 'Thessalonica';
            break;
        case 'sir':
            $auth = 'Sirmium';
            break;
        case 'cnp':
            $auth = 'Constantinople';
            break;
        case 'amb':
            $auth = 'Amiens';
            break;
        case 'med':
            $auth = 'Mediolanum';
            break;
        case 'arc_e':
            $auth = 'Arcadius';
            break;
        case 'theo_ii_e':
            $auth = 'Theodosius II (East)';
            break;
        case 'marc_e':
            $auth = 'Marcian';
            break;
        case 'leo_i_e':
            $auth = 'Leo I (East)';
            break;
        case 'leo_ii_e':
            $auth = 'Leo II';
            break;
        case 'leo_ii-zen_e':
            $auth = 'Leo II and Zeno';
            break;
        case 'zeno(1)_e':
            $auth = 'Zeno';
            break;
        case 'bas_e':
            $auth = 'Basiliscus';
            break;
        case 'bas-mar_e':
            $auth = 'Basiliscus and Marcus';
            break;
        case 'zeno(2)_e':
            $auth = 'Zeno (East)';
            break;
        case 'leon_e':
            $auth = 'Leontius';
            break;
        case 'hon_w':
            $auth = 'Honorius';
            break;
        case 'pr_att_w':
            $auth = 'Priscus Attalus';
            break;
        case 'con_iii_w':
            $auth = 'Constantine III';
            break;
        case 'max_barc_w':
            $auth = 'Maximus of Barcelona';
            break;
        case 'jov_w':
            $auth = 'Jovinus';
            break;
        case 'theo_ii_w':
            $auth = 'Theodosius II (West)';
            break;
        case 'joh_w':
            $auth = 'Johannes';
            break;
        case 'valt_iii_w':
            $auth = 'Valentinian III';
            break;
        case 'pet_max_w':
            $auth = 'Petronius Maximus';
            break;
        case 'marc_w':
            $auth = 'Marcian';
            break;
        case 'av_w':
            $auth = 'Avitus';
            break;
        case 'leo_i_w':
            $auth = 'Leo I (West)';
            break;
        case 'maj_w':
            $auth = 'Majorian';
            break;
        case 'lib_sev_w':
            $auth = 'Libius Severus';
            break;
        case 'anth_w':
            $auth = 'Anthemius';
            break;
        case 'oly_w':
            $auth = 'Olybrius';
            break;
        case 'glyc_w':
            $auth = 'Glycereius';
            break;
        case 'jul_nep_w':
            $auth = 'Julius Nepos';
            break;
        case 'bas_w':
            $auth = 'Basilicus';
            break;
        case 'rom_aug_w':
            $auth = 'Romulus Augustulus';
            break;
        case 'odo_w':
            $auth = 'Odoacar';
            break;
        case 'zeno_w':
            $auth = 'Zeno (West)';
            break;
        case 'visi':
            $auth = 'Visigoths';
            break;
        case 'gallia':
            $auth = 'Burgundians or Franks';
            break;
        case 'spa':
            $auth = 'Suevi';
            break;
        case 'afr':
            $auth = 'Non-Imperial African';
            break;
    }
    
    if (strpos($pieces[3], '_') === FALSE){
        $num = $pieces[3];
    } else {
        $tokens = explode('_', $pieces[3]);
        $num = $tokens[0];
        unset($tokens[0]);
        $num .= ' (' . implode(' ', $tokens) . ')';
    }
    
    //subtypes
    $subtype = '';
    if (isset($pieces[4])){
        $subtype = ': Subtype ' . $pieces[4];
    }
    
    $title = 'RIC ' . $vol . ' ' . $auth . ' ' . $num . $subtype;
    return $title;
}

//validate Nomisma or Monogram/Symbol URI and return the label
function processUri($uri){
    GLOBAL $nomismaUris;
    $content = array();
    $uri = trim($uri);
    $type = '';
    $label = '';
    $node = '';
    
    //if the $uri variable matches an actual URI pattern, then perform associated lookups    
    if (preg_match('/^https?:\/\//', $uri)){
    	//if the key exists, then formulate the XML response
    	if (array_key_exists($uri, $nomismaUris)){
    		$type = $nomismaUris[$uri]['type'];
    		$label = $nomismaUris[$uri]['label'];
    		if (isset($nomismaUris[$uri]['parent'])){
    			$parent = $nomismaUris[$uri]['parent'];
    		}
    	} else {
    		//perform Wikidata Query
    		if (preg_match('/^http:\/\/www\.wikidata\.org\/entity\/Q[0-9]+$/', $uri)){
    			//echo "Wikidata URI found\n";
    			$nomismaUris[$uri] = query_wikidata($uri);
    		} else {
    			//look the URI up in Nomisma or assume a Numishare system
    			$file_headers = @get_headers($uri);
    			
    			//only get RDF if the ID exists
    			if (strpos($file_headers[0], '200') !== FALSE){
    				$xmlDoc = new DOMDocument();
    				$xmlDoc->load($uri . '.rdf');
    				$xpath = new DOMXpath($xmlDoc);
    				$xpath->registerNamespace('skos', 'http://www.w3.org/2004/02/skos/core#');
    				$xpath->registerNamespace('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
    				$xpath->registerNamespace('org', 'http://www.w3.org/ns/org#');
    				$type = $xpath->query("/rdf:RDF/*")->item(0)->nodeName;
    				$label = $xpath->query("descendant::skos:prefLabel[@xml:lang='en']")->item(0)->nodeValue;
    				
    				if (!isset($label)){
    					echo "Error with {$uri}\n";
    				}
    				
    				//get the parent, if applicable
    				$parents = $xpath->query("descendant::org:organization");
    				if ($parents->length > 0){
    					$nomismaUris[$uri] = array('label'=>$label,'type'=>$type, 'parent'=>$parents->item(0)->getAttribute('rdf:resource'));
    					$parent = $parents->item(0)->getAttribute('rdf:resource');
    				} else {
    					$nomismaUris[$uri] = array('label'=>$label,'type'=>$type);
    				}
    			} else {
    				//otherwise output the error
    				echo "Error: {$uri} not found.\n";
    				$nomismaUris[$uri] = array('label'=>$uri,'type'=>null);
    			}
    		}
    	}
    	
    	//determine the NUDS element by the RDF type
    	switch($type){
    		case 'nmo:Authenticity':
    			$content['element'] = 'authenticity';
    			$content['label'] = $label;
    			break;
    		case 'nmo:Mint':
    		case 'nmo:Region':
    			$content['element'] = 'geogname';
    			$content['label'] = $label;
    			if (isset($parent)){
    				$content['parent'] = $parent;
    			}
    			break;
    		case 'nmo:Material':
    			$content['element'] = 'material';
    			$content['label'] = $label;
    			break;
    		case 'nmo:Denomination':
    			$content['element'] = 'denomination';
    			$content['label'] = $label;
    			break;
    		case 'nmo:Manufacture':
    			$content['element'] = 'manufacture';
    			$content['label'] = $label;
    			break;
    		case 'nmo:Monogram':
    		case 'crm:E37_Mark':
    			$content['element'] = 'symbol';
    			$content['label'] = $label;
    			$content['type'] = $type;
    			break;
    		case 'nmo:ObjectType':
    			$content['element'] = 'objectType';
    			$content['label'] = $label;
    			break;
    		case 'nmo:Shape':
    			$content['element'] = 'shape';
    			$content['label'] = $label;
    			break;
    		case 'nmo:TypeSeriesItem':
    			$content['element'] = 'typeSeries';
    			$content['label'] = $label;
    		case 'rdac:Family':
    			$content['element'] = 'famname';
    			$content['label'] = $label;
    			break;
    		case 'foaf:Organization':
    		case 'foaf:Group':
    		case 'nmo:Ethnic':
    			$content['element'] = 'corpname';
    			$content['label'] = $label;
    			break;
    		case 'foaf:Person':
    			$content['element'] = 'persname';
    			$content['label'] = $label;
    			$content['role'] = 'portrait';
    			if (isset($parent)){
    				$content['parent'] = $parent;
    			}
    			break;
    		case 'wordnet:Deity':
    			$content['element'] = 'persname';
    			$content['role'] = 'deity';
    			$content['label'] = $label;
    			break;
    		case 'crm:E4_Period':
    			$content['element'] = 'periodname';
    			$content['label'] = $label;
    			break;
    		default:
    			$content['element'] = 'ERR';
    			$content['label'] = $label;
    	}
    	return $content;
    }
    
    
}

function query_wikidata($uri){
    $query = "PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>
PREFIX skos: <http://www.w3.org/2004/02/skos/core#>
PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
PREFIX wd: <http://www.wikidata.org/entity/>
PREFIX wdt: <http://www.wikidata.org/prop/direct/>
CONSTRUCT {
  ?uri skos:prefLabel ?label ;
       rdf:type ?type
  }
WHERE {
  BIND (<%URI%> as ?uri)
   ?uri rdfs:label ?label ;
        wdt:P31 ?type .
}";
    
    $query_url = "https://query.wikidata.org/sparql?query=" . urlencode(str_replace('%URI%', $uri, $query));
    
    $xmlDoc = new DOMDocument();
    $xmlDoc->load($query_url);
    $xpath = new DOMXpath($xmlDoc);
    $xpath->registerNamespace('skos', 'http://www.w3.org/2004/02/skos/core#');
    $xpath->registerNamespace('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
    
    $types = $xpath->query("descendant::rdf:type");
    $label = $xpath->query("descendant::skos:prefLabel[@xml:lang='en']")->item(0)->nodeValue;
    
    //evaluate whether the concept is a human
    $isHuman = false;
    foreach ($types as $type){
        if ($type->getAttribute('rdf:resource') == 'http://www.wikidata.org/entity/Q5') {
            $isHuman = true;
            break;
        }
    }
    
    if ($isHuman == true) {
        $type = 'foaf:Person';
    } else {
        $type = 'foaf:Organization';
    }
    
    return array('label'=>$label,'type'=>$type);
}

function get_date_textual($year){
    $textual_date = '';
    //display start date
    if($year < 0){
        $textual_date .= abs($year) . ' BCE';
    } elseif ($year > 0) {
        $textual_date .= $year;
        if ($year <= 600){
            $textual_date .= ' CE';
        }
    }
    return $textual_date;
}

//pad integer value from Filemaker to create a year that meets the xs:gYear specification
function number_pad($number,$n) {
    if ($number > 0){
        $gYear = str_pad((int) $number,$n,"0",STR_PAD_LEFT);
    } elseif ($number < 0) {
        $gYear = '-' . str_pad((int) abs($number),$n,"0",STR_PAD_LEFT);
    }
    return $gYear;
}

function generate_json($doc){
    $keys = array();
    $geoData = array();
    
    $data = csvToArray($doc, ',');
    
    // Set number of elements (minus 1 because we shift off the first row)
    $count = count($data) - 1;
    
    //Use first row for names
    $labels = array_shift($data);
    
    foreach ($labels as $label) {
        $keys[] = $label;
    }
    
    // Bring it all together
    for ($j = 0; $j < $count; $j++) {
        $d = array_combine($keys, $data[$j]);
        $geoData[$j] = $d;
    }
    return $geoData;
}

// Function to convert CSV into associative array
function csvToArray($file, $delimiter) {
    if (($handle = fopen($file, 'r')) !== FALSE) {
        $i = 0;
        while (($lineArray = fgetcsv($handle, 4000, $delimiter, '"')) !== FALSE) {
            for ($j = 0; $j < count($lineArray); $j++) {
                $arr[$i][$j] = $lineArray[$j];
            }
            $i++;
        }
        fclose($handle);
    }
    return $arr;
}

?>