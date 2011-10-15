<?php
/**
 * Sitemap generator controllers action
 * @author Evgeny Lexunin <lexunin@gmail.com>
 * @link http://www.yiiframework.com/extension/sitemapgenerator/
 * @version 0.7a
 * @license New BSD
 */
class SGAction extends CAction
{	
	public function run($mapName)
	{
		if (method_exists(Yii::app()->controller, 'sitemaps'))
			$config=Yii::app()->controller->sitemaps();
		else
			$config=array('sitemap.xml'=>array());
		
		if (!is_array($config))
			throw new Exception(Yii::t('sitemapgenerator.msg','Controllers sitemaps() method must return configuration as an array.'));
		
		if (!isset($config[$mapName]))
				throw new CHttpException(404,Yii::t('sitemapgenerator.msg','Sitemap file not founded.'));
		
		$map_config=$config[$mapName];
		require_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'SitemapGenerator.php');
		
		if (isset($map_config['index']) && $map_config['index']) {		// Index sitemap
			unset($config[$mapName]);
			foreach ($config as $key=>$cfg)
			{
				if (isset($config[$key]['enabled']) && !$config[$key]['enabled']) {
					unset ($config[$key]);
					continue;
				}
				$config[$key]['loc']=Yii::app()->createAbsoluteUrl('/'.$key);
			}
				
			SitemapGenerator::renderIndex($config);
		} else {														// Basic sitemap
			if (isset($map_config['enabled']) && !$map_config['enabled'])
				throw new CHttpException(404,Yii::t('sitemapgenerator.msg','Sitemap file disabled.'));
			
			SitemapGenerator::render($map_config['aliases'],$map_config);
		}
	}
}