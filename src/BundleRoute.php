<?php
/**
 * Opine\Bundle
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
namespace Opine;

class BundleRoute {
    public $cache = false;
    private $slim;
    private $container;
    private $formRoute;

    public function cacheSet ($cache) {
        $this->cache = $cache;
    }

    public function __construct ($container) {
        $this->container = $container;
        $this->slim = $container->slim;
        $this->formRoute = $container->formRoute;
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
            $bundleRoot = $root . '/../bundles/' . $bundle . '/public';
            if ($uriBase != $bundle) {
                continue;
            }
            $this->formRoute->json($bundle);
            $this->formRoute->app($bundleRoot, $bundle);
            $className = $root . '/../bundles/' . $bundle . '/Application.php';
            if (!file_exists($className)) {
                continue;
            }
            require $className;
            $instanceName = $bundle . '\Application';
            $bundleInstance = new $instanceName($this->container, $root, $bundleRoot);
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
            $bundleRoot = $bundle . '/public';
            if (file_exists($bundleRoot . '/../forms')) {
                $this->formRoute->build($bundleRoot, '%dataAPI%', $bundleName);
            }
            $bundleApplication = $bundleRoot . '/../Application.php';
            if (!file_exists($bundleApplication)) {
                continue;
            }
            require_once($bundleApplication);
            $bundleClass = $bundleName . '\Application'; 
            $bundleInstance = new $bundleClass($this->container, $root, $bundleRoot);
            if (!method_exists($bundleInstance, 'build')) {
                continue;
            }
            $bundleInstance->build($bundleRoot);
        }
        $json = json_encode($cache, JSON_PRETTY_PRINT);
        file_put_contents($root . '/../bundles/cache.json', $json);
        return $json;
    }

    public function upgrade ($root) {
        $dirFiles = glob($root . '/../bundles/*', GLOB_ONLYDIR);
        foreach ($dirFiles as $bundle) {
            $tmp = explode('/', $bundle);
            $bundleName = array_pop($tmp);
            $bundleRoot = $bundle . '/public';
            $bundleApplication = $bundleRoot . '/../Application.php';
            if (!file_exists($bundleApplication)) {
                continue;
            }
            require_once($bundleApplication);
            $bundleClass = $bundleName . '\Application'; 
            $bundleInstance = new $bundleClass($this->container, $root, $bundleRoot);
            if (!method_exists($bundleInstance, 'upgrade')) {
                continue;
            }
            $bundleInstance->upgrade($bundleRoot);
        }
    }

    private function assetSymlinks ($root, $bundleName) {
        foreach (['css', 'js', 'layouts', 'partials', 'images', 'fonts', 'helpers'] as $dir) {
            $target = $root . '/../bundles/' . $bundleName . '/public/' . $dir;
            if (!file_exists($target)) {
                mkdir($target);
            }
            if ($dir == 'layouts' || $dir == 'partials') {
                foreach (['collections', 'documents', 'forms'] as $sub) {
                    $targetSub = $target . '/' . $sub;
                    if (!file_exists($targetSub)) {
                        mkdir ($targetSub);
                    }
                }
            }
            $linkDir = $root . '/' . $dir . '/' . $bundleName;
            if (!file_exists($linkDir)) {
                symlink($target, $linkDir);
            }
        }
    }
}