<?php

Class DBDatasourceCache extends Datasource {
	
	var $minVersion = '0.6';
	
	/**
	 * Given some data, this function will compress it using `gzcompress`
	 * and then the result is run through `base64_encode` If this fails,
	 * false is returned otherwise the compressed data
	 *
	 * @param string $data
	 *  The data to compress
	 * @return string|boolean
	 *  The compressed data, or false if an error occurred
	 */
	public function compressData($data){
		if(!$data = base64_encode(gzcompress($data))) return false;
		return $data;
	}

	/**
	 * Given compressed data, this function will decompress it and return
	 * the output.
	 *
	 * @param string $data
	 *  The data to decompress
	 * @return string|boolean
	 *  The decompressed data, or false if an error occurred
	 */
	public function decompressData($data){
		if(!$data = gzuncompress(base64_decode($data))) return false;
		return $data;
	}
	
	/**
	 * Generate a custom Flush array to be serialized.
	 */
	public function getFlushValue($key,$context,$languages){
		$flush = array();
		foreach($languages as $language){
			if ( is_array($context['fields'][$key])){
				if ( $context['fields'][$key]['value-'.$language] )
					$flush[$language][$key] = $context['fields'][$key]['value-'.$language];
				elseif ( !isset( $context['fields'][$key]['value-'.$language]))
					if (!empty($context['fields'][$key])) $flush[$language][$key] = $context['fields'][$key];
			} else 
				if (!empty($context['fields'][$key])) $flush[$language][$key] = $context['fields'][$key];
		}
		return $flush;
	}
	
	private function storeParams(&$param_pool=array()){
		$page = Frontend::Page();
				
		if ($this->dsParamFLUSH != NULL) {
			foreach ($this->dsParamFLUSH as $key => $value){
				$group = false;
				if (strpos($value,'[')!==false) {
					$group = true;
					$value = substr ($value , 1,-1);
				}
				// if ($key == 'id') {var_dump($page->_param[$key]);die;}
				$this->dsParamFLUSH[$key]='';
				$arr = explode(',',$value);
				foreach ($arr as $var){
					// if (strpos($var,':')){var_dump(serialize($this->dsParamFLUSH));die;
						$arr2 = explode(':',$var);
						if ($page->_param[$arr2[0]]!==NULL || $param_pool[$arr2[0]]!==NULL ){
							if ($this->dsParamFLUSH[$key]!='') $this->dsParamFLUSH[$key] .= ',';
							//ds-param tends to be an array eg ids etc etc - in this case take first value from array.
							$poolVar = $param_pool[$arr2[0]];
							if (is_array($param_pool[$arr2[0]])){
								$poolVar = $param_pool[$arr2[0]][0];
							} 
							$this->dsParamFLUSH[$key].=$page->_param[$arr2[0]] . $poolVar;
							if ($group && $this->dsParamFLUSH[$key]!='') break;
							// var_dump($page->_param[$arr2[0]]);
						}
						else {
							if ( $arr2[1] == NULL) continue;
							if ($this->dsParamFLUSH[$key]!='') $this->dsParamFLUSH[$key] .= ',';
							$this->dsParamFLUSH[$key] .=  $arr2[1];
							if ($group && $this->dsParamFLUSH[$key]!='') break;
							// if ($arr2[1]!=1){var_dump(serialize($this->dsParamFLUSH));die;}
						}
					// }elseif ($page->_param[$var]!=NULL){
						// var_dump($page->_param[$var]);
						// if ($this->dsParamFLUSH[$key]!='') $this->dsParamFLUSH[$key] .= ',';
						// $this->dsParamFLUSH[$key].=$page->_param[$var];
						// break;
					// }
				}
				if (empty($this->dsParamFLUSH[$key])) {
					unset($this->dsParamFLUSH[$key]);
					// var_dump($this->dsParamFLUSH);die;
				}
				// if ($key == 'id') {var_dump($this->dsParamFLUSH[$key]);die;}
			}
		} else{
			//noparams in here
		}
		// var_dump($this->dsParamFLUSH);
		// var_dump(serialize($this->dsParamFLUSH));die;
		// var_dump($hash);die;
		return serialize($this->dsParamFLUSH);
		// $id = Symphony::Database()->fetchVar("id",0,"SELECT `id` FROM `tbl_dbdatasourcecache` WHERE `hash` = '{$hash}'");
		// Symphony::Database()->insert(array('id'=>$id,'datasource'=>substr (get_class($this),10),'hash'=>$hash,'size'=>$length, 'params'=>serialize($this->dsParamFLUSH), 'section'=>$this->getSource()), 'tbl_dbdatasourcecache',true);
	}
	
	private function buildCacheFilename(&$filename, &$file_age, &$row) {
		$filename = null;
			
		// get resolved values of each public property of this DS
		// (sort, filters, included elements etc.)
		// dsParamCACHE should not make part of the name 30/05/2011 and will be dynamic so do not put in name
		foreach (get_class_vars(get_class($this)) as $key => $value) {
			// if ($key=='dsParamFILTERS' && $this->dsParamROOTELEMENT!='page-hierarchy') {var_dump(get_class_vars(get_class($this)));die;}
			if (substr($key, 0, 2) == 'ds' && $key != 'dsParamCACHE' && $key != 'dsParamLASTUPDATE' && $key != 'dsParamFLUSH') {
				$value = $this->{$key};
				$filename .= $key . (is_array($value) ? implode($value) : $value);
			}
		}
		
		// if ($this->dsParamROOTELEMENT=='page') {var_dump($this->dsParamFLUSH);die;}
		
		$filename = sprintf(
			"%s/cache/%s-%s-%s.xml",
			MANIFEST,
			get_class($this),
			md5($filename),
			$_GET['language'] // Updated by CSA 08/12/2010 - Also consider LANGUAGE
		);
		
		$hash = md5($filename);
		
		$row = Symphony::Database()->fetchRow(0,"SELECT `id`,`creation`,`expiry`,`data`,`hash` FROM `tbl_dbdatasourcecache` WHERE `hash` = '{$hash}'");
		$time = $row['creation'];
		$expiry = $row["expiry"];
		if ($row == NULL) $row= array('hash'=>$hash);
		
		// var_dump($hash);
		// Manual FLUSH of cache
		if (isset($_GET['flush'])) {
			return false;
		}
		
		// There is no cache || expired || data has since been updated
		if ($row['id'] == NULL || ($expiry!=NULL && time() > $expiry) || ($this->dsParamLASTUPDATE != NULL && $this->dsParamLASTUPDATE > $time)) return false;

		if ($this->dsParamCACHE == 0) return true;
		
		$file_age = (int)(floor(time() - $time));
		
		return ($file_age < ($this->dsParamCACHE * 60));
	}
	
	private function grabResult(&$param_pool=array()) {
		
		$result = $this->grab_xml($param_pool);
		$xml = is_object($result) ? $result->generate(true, 1) : $result;
		
		// Parse DS XML to check for errors. If contains malformed XML such as
		// an unescaped database error, the error is escaped in CDATA
		$doc = new DOMDocument('1.0', 'utf-8');
		
		libxml_use_internal_errors(true);
        $doc->loadXML($xml);            
        
        $errors = libxml_get_errors();
		libxml_clear_errors();
		libxml_use_internal_errors(false);
    
    	// No error, just return the result
        if (empty($errors)) return $result;
        if (!empty($errors)) return $result;
			
		// There's an error, so $doc will be empty
		// Use regex to get the root node			
		// If something's wrong, just push back the broken XML			
		if (!preg_match('/<([^ \/>]+)/', $xml, $matches)) return $result;
			
		$ret = new XMLElement($matches[1]);
		
		// Set the invalid flag
		$ret->setAttribute("xml-invalid", "true");
		
		$errornode = new XMLElement("errors");
		
		// Store the errors
		foreach ($errors as $error) {
			$item = new XMLElement("error", trim($error->message));
			$item->setAttribute('line', $error->line);
			$item->setAttribute('column', $error->column);
			$errornode->appendChild($item);
		}
		
		$ret->appendChild($errornode);
		
		// Return the XML
		$ret->appendChild(new XMLElement('broken-xml', "<![CDATA[" . $xml . "]]>"));
		
		return $ret;									
	}		
	
	public function grab(&$param_pool=array()) {
		
		$status = Symphony::ExtensionManager()->fetchStatus('dbdatasourcecache');
		$version = Symphony::ExtensionManager()->fetchInstalledVersion('dbdatasourcecache');
		// var_dump(version_compare($version, $this->minVersion, '>=')  );die;
		// Check that this DS has a cache time set
		if ($status == EXTENSION_ENABLED && version_compare($version, $this->minVersion, '>=') && isset($this->dsParamCACHE) && is_numeric($this->dsParamCACHE) && $this->dsParamCACHE > -1) {
			$filename = null;
			$row = null;
			$file_age = 0;
			if ($this->buildCacheFilename($filename, $file_age, $row)) {
				// Must be cached get from row
				// HACK: peek at the first line of XML to see if it's a serialised array
				// which contains cached output parameters
				
				$xml = $this->decompressData($row['data']);
				
				// split XML into an array of each line
				$xml_lines = explode("\n",$xml);
				
				// output params are a serialised array on line 1
				$output_params = @unserialize(trim($xml_lines[0]));
				
				// there are cached output parameters
				if (is_array($output_params)) {
					
					// remove line 1 and join XML into a string again
					unset($xml_lines[0]);
					$xml = join('', $xml_lines);
					
					// add cached output params back into the pool
					foreach ($output_params as $key => $value) {
						$param_pool[$key] = $value;
					}
				}
				
				// set cache age in the XML result
				return preg_replace('/cache-age="fresh"/', 'cache-age="'.$file_age.'s"', $xml);
				
			} else {
				// Backup the param pool, and see what's been added
				// If in here create fresh not cached
				$tmp = array();
												
				// Fetch the contents
				$contents = $this->grabResult($tmp);
				
				$output_params = null;
				
				// Push into the params array
				foreach ($tmp as $name => $value) {
					$param_pool[$name] = $value;
				}
				
				if (count($tmp) > 0) $output_params = sprintf("%s\n", serialize($tmp));
				
				// Add an attribute to preg_replace later
				$contents->setAttribute("cache-age", "fresh");
				// $id = $row["id"];
				// var_dump($row["id"] . ' ' . $row['hash']);die;
				$data = $output_params . $contents->generate(true, 1);
				$uncompressedsize = strlen($data);
				$data = $this->compressData($data);
				try{
					$paramData = $this->storeParams($param_pool);
					Symphony::Database()->insert(array('id'=>$row["id"], 'hash'=>$row['hash'], 'creation'=>time(),'expiry'=>NULL, 'data'=>$data,'datasource'=>substr (get_class($this),10),'size'=>strlen($data),'uncompressedsize'=>$uncompressedsize, 'params'=>$paramData, 'section'=>$this->getSource()), 'tbl_dbdatasourcecache',true);
					// echo 'inserted';die;
				}catch (Exception $e) {
					// var_dump(Symphony::Database()->getLastError());die;
					//there must have been a differing version just skip
				}
				
				return $contents;
			}																														
		}
		
		return $this->grabResult($param_pool);
	}			
	
	// The original grab() function from native Data Sources
	public function grab_xml(&$param_pool){
					
		$result = new XMLElement($this->dsParamROOTELEMENT);
			
		try{
			if ($this->getSource() == 'navigation') {
	            include(TOOLKIT . '/data-sources/datasource.navigation.php');
	        } else {
	            include(TOOLKIT . '/data-sources/datasource.section.php');
	        }
		}
		catch(FrontendPageNotFoundException $e){
			// Work around. This ensures the 404 page is displayed and
			// is not picked up by the default catch() statement below
			FrontendPageNotFoundExceptionHandler::render($e);
		}
		catch(Exception $e){
			$result->appendChild(new XMLElement('error', $e->getMessage()));
			return $result;
		}	

		if($this->_force_empty_result) $result = $this->emptyXMLSet();
		
		return $result;
	}
	
} 