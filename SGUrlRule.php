<?php
/**
 * Sitemap generator controllers action
 * @author Evgeny Lexunin <lexunin@gmail.com>
 * @link http://www.yiiframework.com/extension/sitemapgenerator/
 * @version 0.7a
 * @license New BSD
 */
/*
 * Configuration:
 * @property string $route Route to controller action for sitemap. Default: /site/sitemap
 */
class SGUrlRule extends CBaseUrlRule
{
	public $route='/site/sitemap';
	
	public function parseUrl($manager, $request, $pathInfo, $rawPathInfo)
	{
		if (substr($request->url,0,8)==='/sitemap') {
			$_GET['mapName']=trim($request->url,'/');
			return $this->route;
		} else
			return false;	
	}
	public function createUrl($manager, $route, $params, $ampersand) {
		return false;
	}
}