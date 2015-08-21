<?php

    Class Extension_CacheableDatasource extends Extension {

        protected $ttl = 5;
        protected $cache;

        public function getSubscribedDelegates() {
            return array(
                array(
                    'page'      => '/frontend/',
                    'delegate'  => 'DataSourcePreExecute',
                    'callback'  => 'dataSourcePreExecute'
                ),
                array(
                    'page'      => '/system/preferences/',
                    'delegate'  => 'AddCachingOpportunity',
                    'callback'  => 'addCachingOpportunity'
                ),
                array(
                    'page' => '/publish/',
                    'delegate' => 'EntryPreDelete',
                    'callback' => 'entryPreDelete'
                ),
                array(
                    'page' => '/publish/edit/',
                    'delegate' => 'EntryPostEdit',
                    'callback' => 'entryPostEdit'
                ),
                array(
                    'page' => '/publish/new/',
                    'delegate' => 'EntryPostCreate',
                    'callback' => 'entryPostCreate'
                ),
                array(
                    'page' => '/publish/',
                    'delegate' => 'EntriesPostOrder',
                    'callback' => 'entriesPostOrder'
                ),
            );
        }

        // by default it will use ds-datasourcehandle but it can be overwritten for entry-based datasources to be ds-datasourcehandle-entryid
        public function getCacheNamespace($datasource){
            if (method_exists($datasource,'getCacheNamespace')){
                return $datasource->getCacheNamespace();
            } else {
                return 'ds-'.$this->getDSHandle($datasource);
            }
        }

        
        public function entryPreDelete($context){
            // purge before delete as we will lose section context - might purge unnecessarily if something else blocks the entry from being deleted
            $this->purgeCache(current(EntryManager::fetch(current($context['entry_id']))));
        }

        public function entryPostEdit($context){
            $this->purgeCache($context['entry']);
        }

        public function entryPostCreate($context){
            $this->purgeCache($context['entry']);
        }

        public function entriesPostOrder($context){
            //since all entries for ordering are within the same section taking the first one is sufficient
            $entry = EntryManager::fetch(current($context['entry_id']));
            $this->purgeCache(current($entry));
        }

        public function purgeCache($entry){

            if (!$this->cache) {
                $this->cache = Symphony::ExtensionManager()->getCacheProvider('cacheabledatasource');
            }

            $sectionID = $entry->get('section_id');

            //get all datasources where the source is the section id of the entry
            $datasources = DatasourceManager::listAll();
            foreach ($datasources as $datasource) {
                if ($datasource['source'] = $sectionID){
                    $datasourceHandle = Lang::createHandle($datasource['name']);
                    // purge cache for this datasource
                    $this->cache->delete(null,'ds-'.$datasourceHandle);
                    $this->cache->delete(null,'ds-'.$datasourceHandle.'-'.$entry->get('id'));
                }
            }
        }

        public function addCachingOpportunity($context) {
            $current_cache = Symphony::Configuration()->get('cacheabledatasource', 'caching');
            $label = Widget::Label(__('Cacheable Datasource'));

            $options = array();
            foreach($context['available_caches'] as $handle => $cache_name) {
                $options[] = array($handle, ($current_cache == $handle || (!isset($current_cache) && $handle === 'database')), $cache_name);
            }

            $select = Widget::Select('settings[caching][cacheabledatasource]', $options, array('class' => 'picker'));
            $label->appendChild($select);

            $context['wrapper']->appendChild($label);
        }

        public function dataSourcePreExecute(&$context) {
             // return;
            if (!$this->cache) {
                $this->cache = Symphony::ExtensionManager()->getCacheProvider('cacheabledatasource');
            }

            $param_pool = $context['param_pool'];
            $datasource = $context['datasource'];

            if (!$datasource->dsParamCache){
                //no cache time specified so ignore
                return;
            }

            $output = $this->getCachedDSOutput($datasource, $param_pool);

            if (!$output) {
                // send a blank pool to the ds [should only add it's own into pool]
                $output['param_pool'] = array();

                $result = $datasource->grab($output['param_pool']);

                $cacheResult = false;

                if ( is_object($result) ){
                    $result->setAttribute('generated-at',date('c'));
                    $cacheResult = sizeof($result->getChildrenByName('error')) == 0;

                    if (!($cacheResult)){
                        //having no results is pretty standard and we should cache a 'no result message' as this is not an unusual error
                        $cacheResult = $result->getChildByName('error',0)->getValue() == "No records found.";
                    }
                }

                $output['xml'] = is_object($result) ? $result->generate(false) : $result;

                // $output['xml'] = $result;
                if ($cacheResult){
                    $this->cacheDSOutput(
                        serialize($output),
                        $datasource,
                        $output['param_pool'],
                        $datasource->dsParamCache
                    );
                }
            }

            if (!isset($output['param_pool'])){
                $output['param_pool'] = array();
            }

            $output['param_pool'] = array_merge($param_pool,$output['param_pool']);
            
            if (!empty($output['xml'])){
                $xmlOutput = is_object($result) ? $result : XMLElement::convertFromXMLString($datasource->dsParamROOTELEMENT,$output['xml']);
                // $xmlOutput = is_object($result) ? $result : $output['xml'];
            }

            $context['xml'] = $xmlOutput;
            $context['param_pool'] = $output['param_pool'];
        }

        protected function getVersion(Datasource $datasource) {
            $about = $datasource->about();
            $name = Lang::createHandle($about['name']);

            $version = $this->cache->read(sprintf("ds/%s/version", $name));
            if (!$version) {
                $version = 1;
            }

            return $version;
        }

        protected function increaseVersion(Datasource $datasource) {
            $name = $this->getDSName($datasource);

            $version = $this->getVersion($datasource);
            $version++;

            $this->cache->write(
                sprintf("ds/%s/version", $name),
                $version,
                0
            );

            return $version;
        }

        protected function getCachedDSOutput(Datasource $datasource, $param_pool) {
            $hash = $this->getHash($datasource, $param_pool);            
            $cache = $this->cache->read($hash);
            // $cache = $this->cache->read($hash,$this->getCacheNamespace($datasource));
            if ($cache['expiry'] > time())
                return unserialize($cache['data']);
            else return false;
        }

        protected function cacheDSOutput($output, Datasource $datasource, $param_pool, $ttl = null) {
            $hash = $this->getHash($datasource, $param_pool);
            return $this->cache->write($hash, $output, $ttl,$this->getCacheNamespace($datasource));
        }

        protected function getHash(Datasource $datasource, $param_pool) {
            if (isset($datasource->hash)) {
                //if already generated no need to regenerate (eg changing params)
                return $datasource->hash;
            }

            $name = $this->getDSHandle($datasource);
            $version = $this->getVersion($datasource);

            $params = array();

            foreach (get_class_vars(get_class($datasource)) as $key => $value) {
                if (substr($key, 0, 2) == 'ds') {
                    $params[$key] = $datasource->{$key};
                }
            }

            // $hash = sprintf("ds/%s/%s/%d", $name, md5(serialize($params)), $version);

            //temporary due to db limit
            $hash = md5('ds' . $name . serialize($params) . $version);

            $datasource->hash = $hash;

            return $hash;
        }

        protected function getDSHandle(Datasource $datasource) {
            $about = $datasource->about();
            return Lang::createHandle($about['name']);
        }
    }
