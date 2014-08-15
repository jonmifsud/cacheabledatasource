<?php

	Class Extension_CacheableDatasource extends Extension {

        protected $ttl = 3600;
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
                )
            );
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
            if (!$this->cache) {
                $this->cache = Symphony::ExtensionManager()->getCacheProvider('cacheabledatasource');
            }

            $param_pool = $context['param_pool'];
            $datasource = $context['datasource'];

            $output = $this->getCachedDSOutput($datasource, $param_pool);

            if (!$output) {
                // Store the pool before the DS runs.
                // Prevents the DS from ruining its own hash.
                $output['param_pool'] = $param_pool;

                $result = $datasource->grab($output['param_pool']);
                $output['xml'] = is_object($result) ? $result->generate(true, 1) : $result;
                $this->cacheDSOutput(
                    $output,
                    $datasource,
                    $param_pool
                );
            }

            $context['xml'] = $output['xml'];
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
            return $this->cache->read($hash);
        }

        protected function cacheDSOutput($output, Datasource $datasource, $param_pool) {
            $hash = $this->getHash($datasource, $param_pool);
            return $this->cache->write($hash, $output);
        }

        protected function getHash(Datasource $datasource, $param_pool) {
            $name = $this->getDSHandle($datasource);
            $version = $this->getVersion($datasource);

            $hash = sprintf("ds/%s/%s/%d", $name, md5(serialize($param_pool)), $version);

            return $hash;
        }

        protected function getDSHandle(Datasource $datasource) {
            $about = $datasource->about();
            return Lang::createHandle($about['name']);
        }
	}
