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
namespace Opine\Bundle;
use Exception;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;
use Symfony\Component\Yaml\Yaml;

class Model {
    public $cache = false;
    private $container;
    private $cacheFile;

    public function cacheSet ($cache) {
        if (empty($cache)) {
            if (!file_exists($this->cacheFile)) {
                return;
            }
            $this->cache = json_decode(file_get_contents($this->cacheFile), true);
            return;
        }
        $this->cache = $cache;
    }

    public function cacheRead () {
        if (!file_exists($this->cacheFile)) {
            return [];
        }
        return (array)json_decode(file_get_contents($this->cacheFile), true);
    }

    private function cacheWrite (array &$bundles) {
        $bundles = json_encode($bundles, JSON_PRETTY_PRINT);
        file_put_contents($this->cacheFile, $bundles);
    }

    public function __construct ($root, $container) {
        $this->container = $container;
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
                throw new Exception('Bundle: ' . $bundleName . ': not in container');
            }
            if (!method_exists($bundleInstance, 'paths')) {
                continue;
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
            $config = Yaml::parse(file_get_contents($configFile));
        }
        if ($config == false) {
            throw new Exception('Can not parse bundles YAML file: ' . $configFile);
        }
        $bundles = $config['bundles'];
        foreach ($bundles as $bundleName => &$bundle) {
            if (!isset($bundle['namespace'])) {
                throw new Exception('Bundle config file missing namespace');
            }
            $bundle['name'] = $bundleName;
            $bundle['route'] = str_replace('\\\\', '\\', ('\\' . $bundle['namespace'] . '\Route'));
            $bundle['routeService'] = strtolower($bundle['name']) . 'Route';
            $bundle['modelService'] = strtolower($bundle['name']) . 'Model';
            if (!class_exists($bundle['route'])) {
                echo 'No Route class: ', $bundle['route'], "\n";
                continue;
            }
            if (!method_exists($bundle['route'], 'location')) {
                echo 'No location method for bundle route class: ', $bundle['route'], "\n";
                continue;
            }
            $bundle['location'] = call_user_func([$bundle['route'], 'location']);
            $target = $this->root . '/../bundles/' . $bundle['name'];
            if (!file_exists($target)) {
               symlink($bundle['location'], $target);
            }
            $bundle['root'] = $bundle['location'] . '/public';
            $this->assets($bundle);
            $bundleModelInstance = $this->container->{$bundle['modelService']};
            if (!method_exists($bundleModelInstance, 'build')) {
                continue;
            }
            $bundleModelInstance->build($bundle['root']);
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

    private function assets ($bundle) {
        foreach (['css', 'js', 'layouts', 'partials', 'images', 'fonts', 'helpers'] as $dir) {
            $src = $bundle['root'] . '/' . $dir;
            if (!file_exists($src)) {
                continue;
            }
            $assets = $this->assetsRead($src);
            foreach ($assets as $asset) {
                $parts = explode('/' . $dir . '/', (string)$asset, 2);
                $dst = $this->root . '/' . $dir . '/' . $bundle['name'] . '/' . $parts[1];
                if (!file_exists(dirname($dst))) {
                    @mkdir(dirname($dst), 0777, true);
                }
                $result = copy((string)$asset, $dst);
                if ($result === false) {
                    echo 'Can not copy: ', (string)$asset, ' ', $dst, "\n";
                }
            }   
        }
    }

    private function assetsRead($folder) {
        $dir = new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($dir);
        $fileList = [];
        foreach ($files as $file) {
            $fileList[] = $file->getPathname();
        }
        return $fileList;
    }
}