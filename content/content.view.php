<?php

	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(TOOLKIT . '/class.datasourcemanager.php');
	require_once(TOOLKIT . '/class.datasource.php');

	Class contentExtensionDbdatasourcecacheView extends AdministrationPage{

		protected $_cachefiles = array();

		function __construct(&$parent){
			parent::__construct($parent);
			$this->setTitle(__('Symphony') .' &ndash; ' . __('DB Datasource Cache'));
		}
		
		// This seems retarded, but it's effiecient
		private function __preliminaryFilenameCheck($filename) {
			// Stop at 't' because it's not a valid hash character
			return ($filename{0} == 'd' && $filename{0} == 'a' && $filename{0} == 't');	
		}
		
		// Build a list of all DS-cache files
		private function __buildCacheFileList() {
			// if ($this->_cachefiles != null) return $this->_cachefiles;
			
			// if (!$oDirHandle = opendir(CACHE)) trigger_error("Panic! DS cache doesn't exists");
			
				// Check some initial characters
				$caches = Symphony::Database()->fetch("SELECT `datasource`,sum(`size`) size_tot,sum(`uncompressedsize`) uncompressedsize_tot, count(`datasource`) as nb FROM `tbl_dbdatasourcecache` group by 1");
				
				foreach($caches as $cache){
					$this->_cachefiles[$cache['datasource']] = array(
						'count' =>  $cache['nb'],
						'size' => $cache['size_tot'],
						'uncompressedsize' => $cache['uncompressedsize_tot']
						// 'files' => array($cache['hash']),
						// 'last-modified' => $row['creation']
					);	
				}
      	  	
      	  	return $this->_cachefiles;			
		}
		
		// Build a list of all DS-cache files
		private function __getCacheFileList($datasource) {
			// if ($this->_cachefiles != null) return $this->_cachefiles;
			
			// if (!$oDirHandle = opendir(CACHE)) trigger_error("Panic! DS cache doesn't exists");
			
				// Check some initial characters
				$caches = Symphony::Database()->fetch("SELECT `hash` FROM `tbl_dbdatasourcecache` where `datasource` = '{$datasource}'");
				$files = null;
				foreach($caches as $cache){
					if (!isset($files)) {
						$files = array($cache['hash']);
					}
					else {
						array_push(	$files,$cache['hash']);
					}			
				}
      	  	
      	  	return $files;			
		}
		
		private function __clearCache($handles) {
			foreach ($handles as $handle) {
				$files = $this->__getCacheFileList($handle);
				if (isset($files)) {		
					/*foreach($files as $file) {
						// unlink($file);
						//symphony will automatically clear up expired cache after some time unless this is re-filled
						//not deleting so if immediately updating symphony will only update instead of re-create row
						// Symphony::Database()->update(array('expiry'=>time(),'data'=>''), "tbl_cache","`hash`='{$file}'");
						
						// just delete as we might have too many rows
						Symphony::Database()->delete("tbl_cache","`hash`='{$file}'");
						Symphony::Database()->delete("tbl_dbdatasourcecache","`hash`='{$file}'");
						// var_dump($file);
					}*/
					Symphony::Database()->delete("tbl_dbdatasourcecache","`datasource`='{$handle}'");
				}					
			}
			// die;
		}
	
		function view(){
			
			$this->setPageType('table');
			$this->appendSubheading(__('DB Datasource Cache'));
			
			$aTableHead = array(
				array('Data Source', 'col'),
				array('Lifetime', 'col'),
				array('Cache Files', 'col'),
				array('Size', 'col'),
				array('Uncompressed Size', 'col'),
			);
			
			$dsm = new DatasourceManager(Administration::instance());
			$cacheable = new Cacheable(Administration::instance()->Database());
			
			$datasources = $dsm->listAll();	
			
			// read XML from "Cacheable Datasource" extension
			$cachedata = $this->__buildCacheFileList();
			
			$aTableBody = array();

			if(!is_array($datasources) || empty($datasources)){
				$aTableBody = array(
					Widget::TableRow(array(Widget::TableData(__('None Found.'), 'inactive', NULL, count($aTableHead))))
				);
			} else {
				
				$params = array();
				
				foreach($datasources as $ds) {
						if ($ds['handle'] == 'article_search' || $ds['handle'] == 'keyword_articles') continue;		
			// var_dump ($ds['handle']);
					try {
						$datasource = $dsm->create($ds['handle'], $params);
					} catch (Exception $e){
						continue;
					}
					
			// var_dump ($ds['handle']);
					$has_files = false;
					$has_size = false;
					
					$name = Widget::TableData($ds['name']);
					$name->appendChild(Widget::Input("items[{$ds['handle']}]", null, 'checkbox'));
					
			// var_dump ($ds['handle']);
					// if data source is using Cacheable Datasource
					if ($datasource instanceOf dbdatasourcecache){
						
						$lifetime = Widget::TableData($datasource->dsParamCACHE . ' ' . ($datasource->dsParamCACHE == 1 ? __('minute') : __('minutes')));
						
						$has_files = isset($cachedata[$ds['handle']]['count']);
						$files = Widget::TableData(
							($has_files ? $cachedata[$ds['handle']]['count'] . ' ' . ($cachedata[$ds['handle']]['count'] == 1 ? __('file') : __('files')) : __('None')),
							($has_files ? NULL : 'inactive')
						);
						
						$has_size = isset($cachedata[$ds['handle']]['size']);
						if ($has_size) {
							if ($cachedata[$ds['handle']]['size'] < 1024) {
								$size_str = $cachedata[$ds['handle']]['size'] . "b";
							} else {
								$size_str = floor($cachedata[$ds['handle']]['size']/1024) . "kb";
							}
						} else {
							$size_str = __('None');
						}
						
						$size = Widget::TableData(
							$size_str,
							($has_size ? NULL : 'inactive')
						);
						
						$has_size = isset($cachedata[$ds['handle']]['uncompressedsize']);
						if ($has_size) {
							if ($cachedata[$ds['handle']]['uncompressedsize'] < 1024) {
								$size_str = $cachedata[$ds['handle']]['uncompressedsize'] . "b";
							} else if ($cachedata[$ds['handle']]['uncompressedsize'] < 1024 * 1024) {
								$size_str = floor($cachedata[$ds['handle']]['uncompressedsize']/1024) . "kb";
							} else {
								$size_str = floor($cachedata[$ds['handle']]['uncompressedsize']/(1024*1024)) . "mb";
							}
						} else {
							$size_str = __('None');
						}
						$uncompressedsize = Widget::TableData(
							$size_str,
							($has_size ? NULL : 'inactive')
						);
						
					/*	$last_modified = $cachedata[$ds['handle']]['last-modified'];
						$expires = Widget::TableData(__('None'), 'inactive');
											
			// var_dump ($ds['handle']);
						if ($last_modified) {
							$file_age = (int)(floor(time() - $last_modified));
							$expires_at = $last_modified + ($datasource->dsParamCACHE * 60);
							$expires_in = (int)(($expires_at - time()) / 60);
							
							if ($datasource->dsParamCACHE == -1) {
								$expires = Widget::TableData('Always Expired');
							} else if ($datasource->dsParamCACHE == 0) {
								$expires = Widget::TableData('Never Expires');
							} else if ($file_age > ($datasource->dsParamCACHE * 60)) {
								$expires = Widget::TableData('Expired');
							} else if($expires_in == 0) {
								$expires = Widget::TableData(__('Cache expires in') . ' ' . ($expires_at - time()) . 's');
							} else {
								$expires = Widget::TableData(__('Cache expires in') . ' ' . $expires_in . ' ' . ($expires_in == 1 ? __('minute') : __('minutes')));
							}
							
						}*/
						
						$aTableBody[] = Widget::TableRow(array($name, $lifetime, $files, $size, $uncompressedsize));

					}

				}
			}
						
			$table = Widget::Table(
				Widget::TableHead($aTableHead), 
				NULL, 
				Widget::TableBody($aTableBody),
				'selectable'
			);

			$this->Form->appendChild($table);
			
			$tableActions = new XMLElement('div');
			$tableActions->setAttribute('class', 'actions');
			
			$options = array(
				array(null, false, __('With Selected...')),
				array('clear', false, __('Clear Cache'))							
			);
			
			$tableActions->appendChild(Widget::Select('with-selected', $options));
			$tableActions->appendChild(Widget::Input('action[apply]', __('Apply'), 'submit'));
			
			$this->Form->appendChild($tableActions);
			// var_dump ('done');die;
		}
		
		function __actionIndex(){
			$checked = @array_keys($_POST['items']);
			if(is_array($checked) && !empty($checked)){
				switch($_POST['with-selected']) {
					case 'clear':								
						$this->__clearCache($checked);
						redirect(Administration::instance()->getCurrentPageURL());
					break;
				}
			}
		}
	
	}
	
?>