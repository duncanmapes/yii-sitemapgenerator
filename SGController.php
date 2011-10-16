<?php
/**
 * Sitemap generator
 * @author Evgeny Lexunin <lexunin@gmail.com>
 * @link http://www.yiiframework.com/extension/sitemapgenerator/
 * @link http://code.google.com/p/yii-sitemapgenerator/
 * @version 0.8a
 * @license New BSD
 */
class SGController extends CController
{
	public $config=array('sitemap'=>array());
	public $disable_weblogroutes=true;
	public $weblogroutes=array('CWebLogRoute','CProfileLogRoute','YiiDebugToolbarRoute');
	
	public function actionIndex($mapName='')
	{
		$config=$this->normalizeConfig($this->config);
		$mapName=basename($mapName,'.xml');
		
		try {
			if (!is_array($config))
				throw new Exception(Yii::t('sitemapgenerator.msg','Sitemaps configuration must be set as an array.'));

			if (!isset($config['sitemap']))
				throw new Exception(Yii::t('sitemapgenerator.msg','Main sitemap file (sitemap.xml) must have configuration.'));

			if (!isset($config[$mapName]))
				throw new CHttpException(404,Yii::t('sitemapgenerator.msg','Sitemap file not founded or disabled.'));

			$map_config=$config[$mapName];
			require_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'SitemapGenerator.php');

				// GZencode mode
			if (isset($map_config['gzencode']) && $map_config['gzencode']) {
				if (!function_exists('gzencode'))
					throw new Exception(Yii::t('sitemapgenerator.msg','Zlib extension must be enabled.'));
				$gzmode=true;
				@ini_set('zlib.output_compression',0);
				ob_start();
			}
			
			$this->setHeaders();
			if ($this->disable_weblogroutes)
				$this->disableWebLogRoutes();
			if (isset($map_config['index']) && $map_config['index']) { // Index sitemap
				unset($config[$mapName]);
				$this->renderIndex($config);
			} else {									// Basic sitemap
				$this->renderNormal($map_config);
			}
			
				// GZencode mode output
			if ($gzmode) {
				$output=ob_get_clean();
				$gzip_output = gzencode($output,9);
				header('Content-Encoding: gzip');
				header('Content-Length: '.strlen($gzip_output));
				echo $gzip_output;
			}
			
		} catch (Exception $e) {
			SitemapGenerator::logExceptionError($e);
			if (YII_DEBUG) throw $e;
		}
	}
	
	/**
	 * Disables WebLogRouters
	 */
	private function disableWebLogRoutes()
	{
        $log_router = Yii::app()->getComponent('log');
        if ($log_router!==null) {
			$routes=$log_router->getRoutes();
			foreach ($routes as $route)
				foreach ($this->weblogroutes as $route_class)
					if ($route instanceof $route_class) {
						$route->enabled = false;
						break;
					}
		}
	}
	
	/**
	 * Normalizes sitemaps config
	 * @param array $config
	 * @return array 
	 */
	private function normalizeConfig($config)
	{
		$nc=array();
		foreach ($config as $k=>$v)
		{
			if (isset($config[$k]['enabled']) && !$config[$k]['enabled'])
				continue;
			$nk=basename($k,'.xml');
			$nc[$nk]=$v;
			$nc[$nk]['loc']=Yii::app()->createAbsoluteUrl('/'.$nk.'.xml');
		}
			
		return $nc;
	}
	/**
	 * Sets xml and cache headers
	 */
	private function setHeaders()
	{
		header("Content-type: text/xml");
		header('Cache-Control: no-cache, no-store, max-age=0, must-revalidate');
		header('Pragma: no-cache');
	}
	/**
	 * Renders sitemap-index file
	 * @param array $config 
	 */
	private function renderIndex($config)
	{
		$map=new SitemapGenerator;
		$map->createSitemapIndex($config);
		echo $map->getIndexAsXml();
	}
	/**
	 * Renders sitemap file
	 * @param array $config 
	 */
	private function renderNormal($config)
	{
		$map=new SitemapGenerator($config['aliases']);
		$map->setDefaults($config);
		echo $map->getAsXml();
	}
}