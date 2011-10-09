<?php
/**
 * Sitemap generator
 * @author Evgeny Lexunin <lexunin@gmail.com>
 * @link http://www.yiiframework.com/extension/sitemapgenerator/
 * @version 0.7a
 * @license New BSD
 */
/**
 * Requirements:
 * Yii 1.1.8 (other versions not tested)
 * PHP 5.2 or later
 * PHP Reflection extension
 * PHP SimpleXML extension
 * 
 * Syntax of docComment:
 * @sitemap [options separated by spaces]
 * 
 * Options:
 *	route=value				- overrides route value for Yii::app()->createAbsoluteUrl($route,$params),
 *	priority=value			- overrides $default_priority value for sitemap,
 *	lastmod=value			- overrides lastmod value for sitemap (by default is today),
 *	changefreq=value		- overrides $default_changefreq value for sitemap,
 *	loc=value				- overrides loc value for sitemap (disables link generation, not set by default),
 *	dataSource=methodName	- public method of controller for generating urls array formatted data.
 * 
 * Important:
 * If 'loc' option is given, it will override link generation
 * and will be inserted without url normalizing. Http host information
 * will not be added. So if you wish to use it, then you will have to set it as:
 *		loc=http://www.example.com
 * Otherwise, use 'route' option.
 * 
 * dataSource method must return array of urls data (array formatted):
 
array(
	array(
		'route'=>'/site/page',					// or 'loc'=>'http://www.example.com/specialLocation',
		'params'=>array('view'=>'about'),
		'priority'=>0.8,
		'changefreq'=>'monthly',
		'lastmod'=>'2012-12-25',
	),
	array(
		...
	),
	...
);

 * All keys are optional. If not set, then will be used values from docComment options,
 * and then default values.
 */
class SitemapGenerator
{
	public $default_changefreq='monthly';
	public $default_priority=0.8;
	public $default_lastmod;
	public $default_routeStructure='application,modules,controllers';
	
	/**
	 * @var array of aliases to controllers location
	 */
	private $_aliases=array('application.controllers');
	
	private $_xml;
	private $_url_counter=0;
	private $_xml_index;
	private $_sitemap_counter=0;
	
	/**
	 * Construct method
	 */
	public function __construct($aliases=null)
	{
		$xml=<<<XML
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd"
         xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
</urlset> 
XML;
		$xml_index=<<<XMLINDEX
<?xml version='1.0' encoding='UTF-8'?>
<sitemapindex xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/siteindex.xsd"
         xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
</sitemapindex>
XMLINDEX;
		if (!class_exists('SimpleXMLElement'))
			throw new Exception('SimpleXML extension is required.');
		
		if (!class_exists('ReflectionClass'))
			throw new Exception('Reflection extension is required.');
		
		if ($aliases!==null)
			$this->_aliases=$this->configToArray($aliases);
		
		$this->default_lastmod=$this->getDefaultLastmod();
		
		$this->_xml=new SimpleXMLElement($xml);
		$this->_xml_index=new SimpleXMLElement($xml_index);
	}
	
	/**
	 * Returns XML formatted sitemap
	 * @return string XML formatted
	 */
	public function getAsXml()
	{
		$this->scanControllersAliases();
		return $this->_xml->asXML();
	}
	
	/**
	 * Returns XML formatted sitemap index
	 * @return string XML formatted
	 */
	public function getIndexAsXml()
	{
		return $this->_xml_index->asXML();
	}
	
	/**
	 * Renders sitemap index by given array of params
	 * @param array $params
	 */
	public static function renderIndex($params)
	{
		try {
			$class=__CLASS__;
			$map=new $class();
			$map->createSitemapIndex($params);
			$map->disableWebLogRouters();
			header("Content-type: text/xml");
			header('Cache-Control: no-cache, no-store, max-age=0, must-revalidate');
			header('Pragma: no-cache');
			echo $map->getIndexAsXml();
		} catch (Exception $e) {
			Yii::log('SitemapGenerator error: '.$e->getMessage(), CLogger::LEVEL_ERROR, 'application.sitemapGenerator');
			if (YII_DEBUG) throw $e;
		}
		Yii::app()->end();
	}
	
	/**
	 * Creates sitemap index xml file
	 * @param array $sitemaps 
	 */
	public function createSitemapIndex($sitemaps)
	{
		if (!is_array($sitemaps))
			throw new Exception('Sitemaps must be set as array. Current value: '.print_r($params['params'],true));
		
		foreach($sitemaps as $s)
			$this->addSitemap($s);
	}
	
	/**
	 * Renders sitemap.xml
	 * @param array $aliases
	 */
	public static function render($aliases=null,$defaults=array())
	{
		try {
			$class=__CLASS__;
			$map=new $class($aliases);
			$map->setDefaults($defaults);
			$map->disableWebLogRouters();
			header("Content-type: text/xml");
			header('Cache-Control: no-cache, no-store, max-age=0, must-revalidate');
			header('Pragma: no-cache');
			echo $map->getAsXml();
		} catch (Exception $e) {
			Yii::log('SitemapGenerator error: '.$e->getMessage(), CLogger::LEVEL_ERROR, 'application.sitemapGenerator');
			if (YII_DEBUG) throw $e;
		}
		Yii::app()->end();
	}
	
	/**
	 * Renders sitemap.xml gz-encoded
	 * @param array $aliases 
	 */
	public static function renderAsGz($aliases=null,$defaults=array())
	{
		try {
			if (!function_exists('gzencode'))
				throw new Exception('Zlib extension must be enabled.');
			
			$class=__CLASS__;
			$map=new $class($aliases);
			$map->setDefaults($defaults);
			$map->disableWebLogRouters();
			$output=$map->getAsXml();
			@ini_set('zlib.output_compression',0);
			$gzip_output = gzencode($output,9);
			header('Content-Type: text/xml');
			header('Content-Encoding: gzip');
			header('Content-Length: '.strlen($gzip_output));
			header('Cache-Control: no-cache, no-store, max-age=0, must-revalidate');
			header('Pragma: no-cache');
			echo $gzip_output;
		} catch (Exception $e) {
			Yii::log('SitemapGenerator error: '.$e->getMessage(), CLogger::LEVEL_ERROR, 'application.sitemapGenerator');
			if (YII_DEBUG) throw $e;
		}
		Yii::app()->end();
	}
	
	/**
	 * Sets default values for sitemap
	 * @param array $defaults 
	 */
	public function setDefaults($defaults)
	{
		if (!is_array($defaults))
			throw new Exception("Sitemap defaults must be set as an array.");
		
		if (!empty($defaults)) {
			if (isset($defaults['changefreq']))
				$this->default_changefreq=$defaults['changefreq'];
			if (isset($defaults['priority']))
				$this->default_priority=$defaults['priority'];
			if (isset($defaults['lastmod']))
				$this->default_lastmod=$defaults['lastmod'];
			if (isset($defaults['routeStructure']))
				$this->default_routeStructure=$defaults['routeStructure'];
		}
	}
	
	/**
	 * Disables CWebLogRoute and CProfileLogRoute logRouters
	 * for clean XML outputpublic $default_lastmod_format='Y-m-d';
	 */
	public function disableWebLogRouters()
	{
        $log_router = Yii::app()->getComponent('log');
        if ($log_router!==null) {
			$routes=$log_router->getRoutes();
			foreach ($routes as $route)
                if (($route instanceof CWebLogRoute) || ($route instanceof CProfileLogRoute))
                    $route->enabled = false;
		}
	}
	
	/**
	 * Scan all given aliases
	 */
	private function scanControllersAliases()
	{
		if (empty($this->_aliases))
				throw new Exception('Controllers aliases is not set.');
		
		foreach ($this->_aliases as $alias)
			$this->scanControllers($alias);
	}
	
	/**
	 * Scan alias
	 * @param string $alias 
	 */
	private function scanControllers($alias)
	{
		$path=Yii::getPathOfAlias($alias);
		
		if (empty($path))
			throw new Exception("Alias path not founded. Alias: '$alias'");
		
		if (is_dir($path)) {
			$files=scandir(Yii::getPathOfAlias($alias));
			foreach ($files as $file)
				if (($pos=strpos($file,'Controller'))!==false) 
						$this->parseController($alias.'.'.basename($file,'.php'));
		} elseif (is_file($path.'.php')) {
			if (($pos=strpos(basename($path),'Controller'))!==false) 
				$this->parseController($alias);
		} else
			throw new Exception("Alias is not directory or file. Alias: '$alias'");
	}
	
	/**
	 * Parses controllers methods to gain urls data
	 * @param string $alias alias of Controler class file
	 * @return boolean 
	 */
	private function parseController($alias)
	{
		$parts=explode('.',$alias);
		$class=array_pop($parts);
		Yii::import($alias,true);
		$cntr=new ReflectionClass($class);
		$controller_instance=null;
		$methods=$cntr->getMethods();
		
		foreach ($methods as $m)
		{
			$comment=$m->getDocComment();
			if (strpos($comment, '@sitemap')!==false) {
				preg_match('/@sitemap(.*)/u', $comment, $result);
				
															// Parse params
				$params= (!empty($result[1])) ? $this->parseParamsString($result[1]) : array();
				$route=$this->createRoute($alias,$m->name);
				
				if (isset($params['dataSource'])) {			// get dataSource to urls_data
					$data_method=$params['dataSource'];
					if ($controller_instance===null)
						$controller_instance=new $class('tempInstance');
					$params['urls_data']=$controller_instance->{$data_method}();
				}
				
				$this->parseUrls($route,$params);
			}
		}
	}
	
	/**
	 * Creates route by given parameters
	 * @param string $alias to controller class file
	 * @param string $action_method_name of controller
	 * @return string 
	 */
	private function createRoute($alias,$action_method_name)
	{
		if (!function_exists('lcfirst')) {
			function lcfirst($str) { $str{0}=strtolower($str{0}); return $str; }
		}
		$route=explode('.',$alias);
		$action=lcfirst(substr($action_method_name,strlen('action')));
		$controller=lcfirst(substr(array_pop($route),0,-strlen('Controller')));
		$route=array_diff($route,$this->configToArray($this->default_routeStructure));
		$route[]=$controller;
		$route[]=$action;
		return '/'.implode('/',$route);
	}
	
	/**
	 * Parses params string from methods docComment
	 * @param string $string
	 * @return array 
	 */
	private function parseParamsString($string)
	{
		$raw=explode(' ',$string);
		$raw=array_filter($raw);
		$data=array();
		foreach ($raw as $param) {
			list($key,$val)=explode('=',$param);
			
			if (empty($val))
				throw new Exception("Option '$key' cannot be empty.");
			
			$data[$key]=$val;
		}
		return $data;
	}
	
	/**
	 * Returns default lastmod value (current date)
	 * @return string
	 */
	private function getDefaultLastmod()
	{
		return date(DATE_W3C);
	}
	
	/**
	 * Parses and adds urls to current xml sitemap
	 * 
	 * @param string $route
	 *					- parsed route to controller action
	 * 
	 * @param array $data Keys:
	 * route			- given by user route (overrides parsed $route)
	 * params			- given by user array of params for route normalize
	 * 
	 * loc				- given by user 'loc' param (overrides link generation)
	 * priority			- given by user priority
	 * changefreq		- given by user chengefreq
	 * lastmod			- given by user lastmod
	 * 
	 * urls_data		- data array returned by dataSource method of controller
	 * dataSource		- controllers method name to gain 'url_data'
	 */
	private function parseUrls($route,$data)
	{
		$default['route']=isset($data['route']) ? $data['route'] : $route;
		$default['priority']=isset($data['priority']) ? $data['priority'] : $this->default_priority;
		$default['changefreq']=isset($data['changefreq']) ? $data['changefreq'] : $this->default_changefreq;
		if (isset($data['loc'])) $default['loc']=$data['loc'];
		$default['lastmod']=isset($data['lastmod']) ? $data['lastmod'] : $this->default_lastmod;
		$default['params']=array();

		if (isset($data['urls_data']))
			foreach ($data['urls_data'] as $item)
				$this->addUrl(CMap::mergeArray($default, $item));
		else
			$this->addUrl($default);
	}
	
	/**
	 * Adds url to current xml sitemap
	 * @param array $params Keys:
	 * loc			- loc attribute (overrides link generation)
	 * route		- used to generate link (at Yii::app()->createAbsoluteUrl)
	 * params		- user to generate link (at Yii::app()->createAbsoluteUrl)
	 * lastmod		- lastmod attribute
	 * changefreq	- changefreq attribute
	 * priority		- priority attribute
	 */
	private function addUrl($params)
	{
		try {
			/* 
			 * Max urls per file: 
			 * http://www.sitemaps.org/faq.php#faq_sitemap_size
			 */
			if ($this->_url_counter>=50000)
				return;

			if (!is_array($params['params']))
				throw new Exception('Url parameters must be set as array. Current value: '.print_r($params['params'],true));
			if (!isset($params['route']) && !isset($params['loc']))
				throw new Exception('"route" or "loc" options must be set.');
			if (isset($params['route']) && !is_string($params['route']))
				throw new Exception('Url route must be set as string. Current value: '.print_r($params['route'],true));
			if (!is_string($params['changefreq']))
				throw new Exception('Url changefreq must be set as string. Current value: '.print_r($params['changefreq'],true));
			if (isset($params['loc']) && !is_string($params['loc']))
				throw new Exception('Url loc must be set as string. Current value: '.print_r($params['loc'],true));
			if (!is_string($params['lastmod']) && !is_int($params['lastmod']))
				throw new Exception('Url lastmod must be set as string. Current value: '.print_r($params['lastmod'],true));

			$link= !isset($params['loc']) ? Yii::app()->createAbsoluteUrl($params['route'],$params['params']) : $params['loc'] ;
			$xmlurl=$this->_xml->addChild('url');
			$xmlurl->addChild('loc',CHtml::encode($link));
			$xmlurl->addChild('lastmod',$this->formatDatetime($params['lastmod']));
			$xmlurl->addChild('changefreq',$params['changefreq']);
			$xmlurl->addChild('priority',$params['priority']);
			++$this->_url_counter;
		} catch (Exception $e) {
			Yii::log('SitemapGenerator error: '.$e->getMessage(), CLogger::LEVEL_ERROR, 'application.sitemapGenerator');
			if (YII_DEBUG) throw $e;
		}
	}
	
	/**
	 * Adds sitemap to current sitemap-index xml
	 * @param array $params
	 */
	private function addSitemap($params)
	{
		try {
			if ($this->_sitemap_counter>=1000)
				return;

			if (!isset($params['lastmod']))
				$params['lastmod']=$this->default_lastmod;
			if (!isset($params['params']))
				$params['params']=array();

			if (!is_array($params['params']))
				throw new Exception('Url parameters must be set as array. Current value: '.print_r($params['params'],true));
			if (!isset($params['route']) && !isset($params['loc']))
				throw new Exception('"route" or "loc" options must be set.');
			if (isset($params['route']) && !is_string($params['route']))
				throw new Exception('Url route must be set as string. Current value: '.print_r($params['route'],true));
			if (isset($params['loc']) && !is_string($params['loc']))
				throw new Exception('Url loc must be set as string. Current value: '.print_r($params['loc'],true));
			if (!is_string($params['lastmod']) && !is_int($params['lastmod']))
				throw new Exception('Url lastmod must be set as string. Current value: '.print_r($params['lastmod'],true));

			$link= !isset($params['loc']) ? Yii::app()->createAbsoluteUrl($params['route'],$params['params']) : $params['loc'] ;

			$sitemap=$this->_xml_index->addChild('sitemap');
			$sitemap->addChild('loc',CHtml::encode($link));
			$sitemap->addChild('lastmod',$this->formatDatetime($params['lastmod']));
			++$this->_sitemap_counter;
		} catch (Exception $e) {
			Yii::log('SitemapGenerator error: '.$e->getMessage(), CLogger::LEVEL_ERROR, 'application.sitemapGenerator');
			if (YII_DEBUG) throw $e;
		}
	}
	
	/**
	 * Formats given date to W3C datetime format
	 * @param mixed $val
	 * @return string 
	 */
	private function formatDatetime($val)
	{
		try {
			if (is_int($val)) {
				$result=date(DATE_W3C,$val);
			} elseif (is_string($val)) {
				$dt=new DateTime($val);
				$result=$dt->format(DateTime::W3C);
				if ($result===false)
					throw new Exception('Unable to format datetime object. Datetime value: '.$val);
			}
		} catch (Exception $e) {
			throw new Exception('Unable to parse given datetime. Error: '.$e->getMessage());
		}
		return $result;
	}
	
	/**
	 * Formats config data to array.
	 * @param mixed $data
	 * @return array
	 */
	private function configToArray($data)
	{
		if (is_array($data))
			return $data;
		elseif (is_string($data))
			return array_filter(explode(',',$data));
		else
			throw new Exception('Aliases elements must be set as string or an array.');
	}
}