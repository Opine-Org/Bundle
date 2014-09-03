<?php
/**
 * Opine\Bundle
 *
 * Copyright (c)2013, 2014 Ryan Mahoney, https://github.com/Opine-Org <ryan@virtuecenter.com>
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
    private $container;
    private $formRoute;
    private $collectionRoute;
    private $cacheFile;

    public function cacheSet ($cache) {
        $this->cache = $cache;
    }

    public function cacheRead () {
        return (array)json_decode(file_get_contents($this->cacheFile), true);
    }

    private function cacheWrite (array &$bundles) {
        $bundles = json_encode($bundles, JSON_PRETTY_PRINT);
        file_put_contents($this->cacheFile, $bundles);
    }

    public function __construct ($root, $container) {
        $this->container = $container;
        $this->formRoute = $container->formRoute;
        $this->collectionRoute = $container->collectionRoute;
        $this->yamlSlow = $container->yamlSlow;
        $this->root = $root;
        $this->cacheFile = $this->root . '/../cache/bundles.json';
    }

    public function bundles ($names=false) {
        if (!file_exists($this->cacheFile)) {
            return [];
        }
        $bundles = $this->cacheRead();
        if ($names === true) {
            return array_keys($bundles);
        }
        return $bundles;
    }

    public function paths () {
        if (!empty($this->cache)) {
            $bundles = $this->cache;
        } else {
            if (!file_exists($this->cacheFile)) {
                return;
            }
            $bundles = $this->cacheRead();
        }
        if (!is_array($bundles)) {
            return;
        }
        foreach ($bundles as $bundleName => $bundle) {
            $bundleRoot = $this->root . '/../bundles/' . $bundleName . '/public';
            $baseCheck = $bundleName;
            if (isset($bundle['route'])) {
                $baseCheck = $bundle['route'];
            }
            $bundleInstance = $this->container->{strtolower($bundleName) . 'Route'};
            if ($bundleInstance === false) {
                throw new \Exception('Bundle: ' . $bundleName . ': not in container');
            }
            $bundleInstance->paths();
        }
    }

    public function build () {
        $configFile = $this->root . '/../bundles/bundles.yml';
        if (!file_exists($configFile)) {
            return;
        }
        if (function_exists('yaml_parse_file')) {
            $config = yaml_parse_file($configFile);
        } else {
            $config = $this->yamlSlow->parse($configFile);
        }
        if ($config == false) {
            throw new \Exception('Can not parse bundles YAML file: ' . $configFile);
        }
        $bundles = $config['bundles'];
        foreach ($bundles as $bundleName => $bundle) {
            $bundleInstance = $this->container->{strtolower($bundleName) . 'Route'};
            $location = $bundleInstance->location();
            $target = $this->root . '/../bundles/' . $bundleName;
            if (!file_exists($target)) {
                symlink($location, $target);
            }
            $this->assetSymlinks($bundleName);
            $bundleRoot = $this->root . '/../bundles/' . $bundleName . '/public';
            if (!method_exists($bundleInstance, 'build')) {
                continue;
            }
            $bundleInstance->build($bundleRoot);
        }
        $this->cacheWrite($bundles);
        return $bundles;
    }

    public function upgrade () {
        $dirFiles = glob($this->root . '/../bundles/*', GLOB_ONLYDIR);
        foreach ($dirFiles as $bundle) {
            $tmp = explode('/', $bundle);
            $bundleName = array_pop($tmp);
            $bundleRoot = $bundle . '/public';
            $bundleInstance = $this->container->{strtolower($bundleName) . 'Route'};
            if (!method_exists($bundleInstance, 'upgrade')) {
                continue;
            }
            $bundleInstance->upgrade($bundleRoot);
        }
    }

    private function assetSymlinks ($bundleName) {
        foreach (['css', 'js', 'layouts', 'partials', 'images', 'fonts', 'helpers'] as $dir) {
            $target = $this->root . '/../bundles/' . $bundleName . '/public/' . $dir;
            if (!file_exists($target)) {
                @mkdir($target, 0700, true);
            }
            if ($dir == 'layouts' || $dir == 'partials') {
                foreach (['collections', 'documents', 'forms'] as $sub) {
                    $targetSub = $target . '/' . $sub;
                    if (!file_exists($targetSub)) {
                        @mkdir ($targetSub, 0700, true);
                    }
                }
            }
            $linkDir = $this->root . '/' . $dir . '/' . $bundleName;
            if (!file_exists($linkDir) && file_exists($target)) {
                @symlink($target, $linkDir);
            }
        }
    }
}