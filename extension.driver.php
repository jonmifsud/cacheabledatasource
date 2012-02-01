<?php

	Class Extension_dbdatasourcecache extends Extension {

		public function about(){
			return array('name' => 'DB Datasource Cache',
                 'version' => '0.7.1',
                 'release-date' => '2012-01-27',
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
			//TODO Replace the check with a file check rather then db as this is not real check
			
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
                        $js = 'var cacheMinutes = '.$match[1].', cacheEnabled = true;';
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
	// $current = precision_timer();
			require_once(TOOLKIT . '/class.datasourcemanager.php');
			require_once(TOOLKIT . '/class.datasource.php');
	
			$sectionid = $context['section']->get('id');
			
			$languages = array('en');
			if (Symphony::Configuration()->get('languages', 'language_redirect'))
				$languages = explode(',', Symphony::Configuration()->get('languages', 'language_redirect') );
			elseif (Symphony::Configuration()->get('language_codes', 'language_redirect'))
				$languages = explode(',', Symphony::Configuration()->get('language_codes', 'language_redirect') );
			
			
			$dsm = new DatasourceManager(Administration::instance());
			$datasources = $dsm->listAll();	
			foreach($datasources as $ds) {
				try {
					$params = array();
					$datasource = $dsm->create($ds['handle'], $params);
				} catch (Exception $e){
					continue;
				}
				if ($datasource instanceOf DBDatasourceCache){
					if ( $datasource->getSource() != $sectionid ) continue;
					if ( !isset($datasource->dsParamFLUSH)) {
						Symphony::Database()->update(array('expiry'=>time()), "tbl_dbdatasourcecache","`datasource`='{$ds['handle']}'");
						
					} else {
						// var_dump($sectionid);die;
						//build string
						$flush = array();
						foreach ($datasource->dsParamFLUSH as $key => $value){
							foreach($languages as $language){
								if ( is_array($context['fields'][$key])){
									if ( $context['fields'][$key]['value-'.$language] )
										$flush[$language][$key] = $context['fields'][$key]['value-'.$language];
									elseif ( !isset( $context['fields'][$key]['value-'.$language]))
										$flush[$language][$key] = $context['fields'][$key];
								} else 
									$flush[$language][$key] = $context['fields'][$key];
							}
						}
						foreach ($flush as $langflush){
							$params = serialize($langflush);
							// $rows = Symphony::Database()->fetch("SELECT `id` FROM `tbl_dbdatasourcecache` WHERE `datasource`='{$ds['handle']}' and `params`='{$params}'");
								
							Symphony::Database()->update(array('expiry'=>time()), "tbl_dbdatasourcecache","`datasource`='{$ds['handle']}' and `params`='{$params}'");
							
							// foreach($rows as $row){
								// Symphony::Database()->update(array('expiry'=>time()), "tbl_dbdatasourcecache","`id`='{$row['id']}'");
							// }
						}
						// var_dump($flush);die;
					}
				}
			}
		
		// var_dump($context);die;
	// echo 'Elapsed time' . precision_timer('stop',$current).'<br/>';die;
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
				`uncompressedsize` INT(11) unsigned NOT NULL,
				`size` INT(11) unsigned NOT NULL,
				`params` VARCHAR(511),
				`hash` VARCHAR(32) UNIQUE NOT NULL,
				`creation` int(14) NOT NULL DEFAULT '0',
				`expiry` int(14) unsigned DEFAULT NULL,
				`data` longtext COLLATE utf8_unicode_ci NOT NULL,
			PRIMARY KEY (`id`),
			KEY `datasource` (`datasource`),
			FULLTEXT KEY `params` (`params`)
			) ENGINE=MyISAM;");
		}
		
		/**
		 * Update
		 */
		public function update()	{
			// Install cacheabledatasource table:
			$version = Symphony::ExtensionManager()->fetchInstalledVersion('dbdatasourcecache');
			if (version_compare($version, '0.2.2', '<')){
				Symphony::Database()->query("TRUNCATE TABLE `tbl_cache`");
				Symphony::Database()->query("DROP TABLE `tbl_dbdatasourcecache`");
			} elseif (version_compare($version, '0.7.1', '<')){
				Symphony::Database()->query("TRUNCATE TABLE `tbl_dbdatasourcecache`");
				Symphony::Database()->query("ALTER TABLE  `tbl_dbdatasourcecache` ENGINE = MYISAM");
				Symphony::Database()->query("ALTER TABLE  `tbl_dbdatasourcecache` ADD FULLTEXT (  `params` )");
			} 
			// $this->uninstall(); 
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
			KEY `datasource` (`datasource`),
			FULLTEXT KEY `params` (`params`)
			) ENGINE=MyISAM;");
			
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