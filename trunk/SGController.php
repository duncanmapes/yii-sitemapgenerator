<?php
class SGController extends CController
{
	public $config=array('sitemap'=>array());
	
	public function actionIndex($mapName='')
	{
		$config=$this->normalizeConfig($this->config);
		$mapName=basename($mapName,'.xml');
		
		if (!is_array($config))
			throw new Exception(Yii::t('sitemapgenerator.msg','Sitemaps configuration must be set as an array.'));
		
		if (!isset($config['sitemap']))
			throw new Exception(Yii::t('sitemapgenerator.msg','Main sitemap file (sitemap.xml) must have configuration.'));
		
		if (!isset($config[$mapName]))
			throw new CHttpException(404,Yii::t('sitemapgenerator.msg','Sitemap file not founded or disabled.'));
		
		$map_config=$config[$mapName];
		require_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'SitemapGenerator.php');
		
		if (isset($map_config['index']) && $map_config['index']) {		// Index sitemap
			unset($config[$mapName]);
			SitemapGenerator::renderIndex($config);
		} else {														// Basic sitemap
			SitemapGenerator::render($map_config['aliases'],$map_config);
		}
	}
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
}