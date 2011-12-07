<?php

	Class Extension_dbdatasourcecache extends Extension {

		public function about(){
			return array('name' => 'DB Datasource Cache',
                 'version' => '0.7',
                 'release-date' => '2011-12-07',
                 'author' => array(
                     array(
                         'name' => 'Nick Dunn',
                         'website' => 'http://nick-dunn.co.uk'),
                     array(
                         'name' => 'Jonathan Mifsud',
                         'website' => 'http://jonmifsud.com/'),
                     array(
                         'name' => 'Giel Berkers',
                         'website' => 'http://www.gielberkers.com')
                     ),
                'description' => 'Implement caching for datasources'
            );
		}
		
		public function fetchNavigation(){			
			return array(
				array(
					'location'	=> __('System'),
					'name'	=> __('DB Datasource Cache'),
					'link'	=> '/view/'
				)
			);		
		}
		
		// Set the delegates:
		public function getSubscribedDelegates()
		{
			return array(
				array(
					'page' => '/publish/new/',
					'delegate' => 'EntryPostCreate',
					'callback' => 'pageUpdate'
				),
				array(
					'page' => '/publish/edit/',
					'delegate' => 'EntryPostEdit',
					'callback' => 'pageUpdate'
				),
                array(
                    'page' => '/blueprints/datasources/',
                    'delegate' => 'DatasourcePreEdit',
                    'callback' => 'datasourceUpdate'
                ),
                array(
                    'page' => '/blueprints/datasources/',
                    'delegate' => 'DatasourcePreCreate',
                    'callback' => 'datasourceUpdate'
                ),
                array(
                    'page' => '/backend/',
                    'delegate' => 'InitaliseAdminPageHead',
                    'callback' => 'addScriptToHead'
                )
			);
		}

        /**
         * Update the physical datasource, thus editing the file so manual editing is no long needed.
         * @param $context
         * @return void
         */
        public function datasourceUpdate($context)
        {
            // Edit the datasource file if caching is enabled:
            if(isset($_POST['dbdatasourcecache']) && isset($_POST['dbdatasourcecache']['cache']))
            {
                $cacheTime = intval($_POST['dbdatasourcecache']['time']);
                $data = $context['contents'];

                // Include aditional classes:
                $data = preg_replace('/require_once\(TOOLKIT\s?+\.\s?+\'\/class.datasource.php\'\);/', 'require_once(TOOLKIT . \'/class.datasource.php\');
            require_once(EXTENSIONS . \'/dbdatasourcecache/lib/class.dbdatasourcecache.php\');', $data);

                // Adjust the class initilization and the caching time:
                $data = preg_replace('/Class (.*) extends Datasource\s?+{/', 'Class \\1 extends dbdatasourcecache{

                    public $dsParamCACHE = '.$cacheTime.';', $data);

                // Rename the grab()-function to grab_xml():
                $data = str_replace('public function grab(', 'public function grab_xml(', $data);

                $context['contents'] = $data;
            }
        }

        /**
         * Add some JavaScript logic to the head to add the 'cache this datasource'-checkbox to the datasource editor page
         * @param $context
         * @return void
         */
        public function addScriptToHead($context)
        {
            $callback = Administration::instance()->getPageCallback();
            if($callback['driver'] == 'blueprintsdatasources')
            {
                if($callback['context'][0] == 'edit')
                {
                    // Check whether this datasource is a cachable one or not:
                    $ds = Symphony::Database()->fetchRow(0, 'SELECT * FROM `tbl_dbdatasourcecache` WHERE `datasource` = \''.$callback['context'][1].'\';');
                    if($ds != false)
                    {
                        // Get the correct caching time:
                        $content = file_get_contents(DATASOURCES.'/data.'.$callback['context'][1].'.php');
                        preg_match('/public \$dsParamCACHE = (.*);/', $content, $match);
                        if(!empty($match[1]))
                        {
                            $js = 'var cacheMinutes = '.$match[1].', cacheEnabled = true;';
                        } else {
                            // No cache time found. The datasource was probably cached first, and then un-cached afterwards:
                            $js = 'var cacheMinutes = 60, cacheEnabled = false;';
                        }
                    } else {
                        $js = 'var cacheMinutes = 60, cacheEnabled = false;';
                    }
                } else {
                    $js = 'var cacheMinutes = 60, cacheEnabled = false;';
                }
                $tag = new XMLElement('script', $js, array('type'=>'text/javascript'));
			    $context['parent']->Page->addElementToHead($tag);
                $context['parent']->Page->addScriptToHead(URL . '/extensions/dbdatasourcecache/assets/ui.js');
            }
        }

		public function pageUpdate($context) {
			//get section id
			$sectionid = $context['section']->get('id');
			
			//fetch all cache rows related to this section
			$rows = Symphony::Database()->fetch("SELECT `id`,`params`,`datasource`,`hash` FROM `tbl_dbdatasourcecache` WHERE `section`='{$sectionid}'");
			$fieldManager= new FieldManager(Symphony::Engine());
			foreach ($rows as $row){
				$params = unserialize($row['params']);
				//if no params flush everytime probably a list that has to be updated
				if ($params == NULL) Symphony::Database()->update(array('expiry'=>time()), "tbl_cache","`hash`='{$row['hash']}'");
				else{
					foreach ($params as $key => $parameter){
					$parameters = explode(',',$parameter);
						foreach ($parameters as $param){
							$fieldid = $fieldManager->fetchFieldIDFromElementName($key, $sectionid);
							if (is_array($context['fields'][$key])){
								$fielddata = $context['entry']->getData($fieldid);
								foreach ($context['fields'][$key] as $field => $val){
									//get the handle of this entry to compare handle as well as value
									$handle = $fielddata[str_replace('value','handle',$field)];
									// var_dump($handle);
									if($val == $param || $handle == $param)	Symphony::Database()->update(array('expiry'=>time()), "tbl_cache","`hash`='{$row['hash']}'");
								}
							} else {
								if($context['fields'][$key] == $param)	Symphony::Database()->update(array('expiry'=>time()), "tbl_cache","`hash`='{$row['hash']}'");
							}
							 // var_dump($context['fields']['menu-title']);die;
						}
					}
				}
			}
			/*if ($context['section']->get('name')=='Posts'){
				$id = $context['entry']->get('id');
				foreach ($this->urls as $key => $url){
					
					// check if language has data
					$var = 'value-'.$key;
					if( $context['fields']["url-handle"][$var] != ''){
						// language has data in post
						$oldPing = Symphony::Database()->fetch("SELECT `ping_time` FROM `tbl_feedburner` WHERE `id` = '{$id}' AND `lang` = '{$key}'");
						
						if (count($oldPing)==0) {
							// insert in db timestamp automatic
							Symphony::Database()->insert(array('id'=>$id, 'lang'=>$key), 'tbl_feedburner');
							// we can ping this language
							$this->pingFeedburner($this->urls[$key]);
						} else {
							// this post was already pinged
							
							// do nothing unless we want to re-ping because of an update.
						}
					}
				}
			}*/
		}
		
		/**
		 * Installation
		 */
		public function install()	{
			// Install cacheabledatasource table:
			Symphony::Database()->query("CREATE TABLE IF NOT EXISTS `tbl_dbdatasourcecache` (
				`id` INT(11) unsigned NOT NULL AUTO_INCREMENT,
				`datasource` VARCHAR(100),
				`section` INT(11) unsigned NOT NULL,
				`size` INT(11) unsigned NOT NULL,
				`uncompressedsize` INT(11) unsigned NOT NULL,
				`params` VARCHAR(511),
				`hash` VARCHAR(32) UNIQUE NOT NULL,
				`creation` int(14) NOT NULL DEFAULT '0',
				`expiry` int(14) unsigned DEFAULT NULL,
				`data` longtext COLLATE utf8_unicode_ci NOT NULL,
			PRIMARY KEY (`id`),
			KEY `datasource` (`datasource`)
			);");
		}
		
		/**
		 * Update
		 */
		public function update()	{
			// Install cacheabledatasource table:
			$version = Symphony::ExtensionManager()->fetchInstalledVersion('dbdatasourcecache');
			if (version_compare($version, '0.2.2', '<')){
				Symphony::Database()->query("TRUNCATE TABLE `tbl_cache`");
				Symphony::Database()->query("DROP TABLE `tbl_cachabledbdatasource`");
			} 
			$this->uninstall(); 
			Symphony::Database()->query("CREATE TABLE IF NOT EXISTS `tbl_dbdatasourcecache` (
				`id` INT(11) unsigned NOT NULL AUTO_INCREMENT,
				`datasource` VARCHAR(100),
				`section` INT(11) unsigned NOT NULL,
				`uncompressedsize` INT(11) unsigned NOT NULL,
				`size` INT(11) unsigned NOT NULL,
				`params` VARCHAR(511),
				`hash` VARCHAR(32) UNIQUE NOT NULL,
				`creation` int(14) NOT NULL DEFAULT '0',
				`expiry` int(14) unsigned DEFAULT NULL,
				`data` longtext COLLATE utf8_unicode_ci NOT NULL,
			PRIMARY KEY (`id`),
			KEY `datasource` (`datasource`)
			);");
			
			// Symphony::Database()->query("ALTER TABLE  `sym_cacheabledbdatasource` ADD INDEX (`datasource`)");
		}
	
		/**
		 * Uninstallation
		 */
		public function uninstall()	{
			//Drop table 
			Symphony::Database()->query("DROP TABLE `tbl_dbdatasourcecache`");
		}
		
	}

?>