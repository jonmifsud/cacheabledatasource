<?php

	Class Extension_AdvancedCacheableDatasource extends Extension {

		public function about(){
			return array('name' => 'Advanced Cacheable Datasource',
						 'version' => '0.2.4',
						 'release-date' => '2011-11-29',
						 'author' => array('name' => 'Jon Mifsud',
										   'website' => 'http://jonmifsud.com'),
						'description' => 'Create custom Data Sources that implement output caching');

		}
		
		public function fetchNavigation(){			
			return array(
				array(
					'location'	=> __('System'),
					'name'	=> __('Advanced Cacheable Datasource'),
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
			);
		}
		
		public function pageUpdate($context) {
			//get section id
			$sectionid = $context['section']->get('id');
			
			//fetch all cache rows related to this section
			$rows = Symphony::Database()->fetch("SELECT `id`,`params`,`datasource`,`hash` FROM `tbl_advancedcacheabledatasource` WHERE `section`='{$sectionid}'");
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
			Symphony::Database()->query("CREATE TABLE IF NOT EXISTS `tbl_advancedcacheabledatasource` (
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
			$version = Symphony::ExtensionManager()->fetchInstalledVersion('advancedcacheabledatasource');
			if (version_compare($version, '0.2.2', '<')){
				Symphony::Database()->query("TRUNCATE TABLE `tbl_cache`");
				Symphony::Database()->query("DROP TABLE `tbl_cachabledbdatasource`");
			} 
			$this->uninstall(); 
			Symphony::Database()->query("CREATE TABLE IF NOT EXISTS `tbl_advancedcacheabledatasource` (
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
			Symphony::Database()->query("DROP TABLE `tbl_advancedcacheabledatasource`");
		}
		
	}

?>