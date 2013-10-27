<?php
/**
 * virtuecenter\bundle
 *
 * Copyright (c)2013 Ryan Mahoney, https://github.com/virtuecenter <ryan@virtuecenter.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
namespace Bundle;

class BundleRoute {
	public $cache = false;
	private $slim;
	private $container;

	public function __construct ($slim, $container) {
		$this->slim = $slim;
		$this->container = $container;
	}

	public function app ($root) {
		if (!empty($this->cache)) {
			$bundles = $this->cache;
		} else {
			$cacheFile = $root . '/../bundles/cache.json';
			if (!file_exists($cacheFile)) {
				return;
			}
			$bundles = (array)json_decode(file_get_contents($cacheFile), true);
		}
		if (!is_array($bundles)) {
			return;
		}

		$uriBase = $this->slim->request->getResourceUri();
		if (substr_count($uriBase, '/') > 0) {
			$uriBase = explode('/', trim($uriBase, '/'))[0];
		}
		
		foreach ($bundles as $bundle) {
			if ($uriBase != $bundle) {
				continue;
			}
			$className = $root . '/../bundles/' . $bundle . '/Application.php';
			if (!file_exists($className)) {
				continue;
			}
			require $className;
			$instanceName = $bundle . '\Application';
			$bundleInstance = new $instanceName($this->slim, $this->container);
			$bundleInstance->app();
		}
	}

	public function build ($root) {
		$cache = [];
		$dirFiles = glob($root . '/../bundles/*', GLOB_ONLYDIR);
		foreach ($dirFiles as $bundle) {
			$tmp = explode('/', $bundle);
			$bundleName = array_pop($tmp);
			$cache[] = $bundleName;
			$this->assetSymlinks($root, $bundleName);
		}
		$json = json_encode($cache, JSON_PRETTY_PRINT);
		file_put_contents($root . '/../bundles/cache.json', $json);
	}

	private function assetSymlinks ($root, $bundleName) {
		foreach (['css', 'js', 'layouts', 'partials', 'images', 'fonts', 'helpers'] as $dir) {
			$target = $root . '/../bundles/' . $bundleName . '/public/' . $dir;
			if (!file_exists($target)) {
				continue;
			}
			$linkDir = $root . '/' . $dir . '/' . $bundleName;
			if (!file_exists($linkDir)) {
				symlink($target, $linkDir);
			}
		}
	}
}