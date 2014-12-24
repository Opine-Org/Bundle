<?php
/**
 * Opine\Bundle\Model
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

use Exception, RecursiveDirectoryIterator, RecursiveIteratorIterator, FilesystemIterator;
use Opine\Interfaces\Route as RouteInterface;

class Model
{
    private $root;
    private $route;
    private $cache;
    private $cacheFile;

    public function __construct($root, RouteInterface $route)
    {
        $this->root = $root;
        $this->route = $route;
        $this->cacheFile = $this->root.'/../var/cache/bundles.json';
    }

    public function cacheSet($cache)
    {
        if (empty($cache)) {
            if (!file_exists($this->cacheFile)) {
                return;
            }
            $this->cache = json_decode(file_get_contents($this->cacheFile), true);

            return;
        }
        $this->cache = $cache;
    }

    private function cacheRead()
    {
        if (!file_exists($this->cacheFile)) {
            return [];
        }

        return (array) json_decode(file_get_contents($this->cacheFile), true);
    }

    private function cacheWrite(array &$bundles)
    {
        $bundles = json_encode($bundles, JSON_PRETTY_PRINT);
        file_put_contents($this->cacheFile, $bundles);
    }

    public function bundles($names = false)
    {
        if (!file_exists($this->cacheFile)) {
            return [];
        }
        $bundles = $this->cacheRead();
        if ($names === true) {
            return array_keys($bundles);
        }

        return $bundles;
    }

    public function build()
    {
        $cmd = 'cd '.$this->root.'/../vendor && find . | egrep \'/src/[a-zA-Z_\-]*/config/routes/[a-zA-Z_\-]*.yml$\'';
        $bundleRoutePaths = explode("\n", trim(str_replace('./', ($this->root.'/../vendor/'), shell_exec($cmd))));
        if (!is_array($bundleRoutePaths) || empty($bundleRoutePaths)) {
            return;
        }
        $bundles = [];
        foreach ($bundleRoutePaths as $path) {
            $matches = [];
            preg_match('/\/src\/([a-zA-Z_\-]*)\/config\/routes\/[a-zA-Z_\-]*.yml$/', $path, $matches);
            $bundleName = $matches[1];
            $routePath = $matches[0];
            if (!isset($bundles[$bundleName])) {
                $bundles[$bundleName] = [
                    'name'         => $bundleName,
                    'modelService' => strtolower($bundleName).'Model',
                    'root'         => str_replace($routePath, '', $path).'/src/'.$bundleName.'/public',
                    'routeFiles'   => [],
                ];
            }
            $bundles[$bundleName]['routeFiles'][] = $path;
            $this->assets($bundles[$bundleName]);
            $this->layoutConfigs($bundles[$bundleName]);
            try {
                $this->route->serviceMethod($bundles[$bundleName]['modelService'].'@?build');
            } catch (Exception $e) {
                echo 'Service: ', $bundles[$bundleName]['modelService'].'@build'.': not in container, maybe on next build', "\n";
            }
        }
        $this->cacheWrite($bundles);

        return $bundles;
    }

    private function assets($bundle)
    {
        foreach (['css', 'js', 'jsx', 'layouts', 'partials', 'images', 'fonts', 'helpers'] as $dir) {
            $src = $bundle['root'].'/'.$dir;
            if (!file_exists($src)) {
                continue;
            }
            $files = $this->folderRead($src);
            foreach ($files as $file) {
                $parts = explode('/'.$dir.'/', (string) $file, 2);
                $dst = $this->root.'/'.$dir.'/'.$bundle['name'].'/'.$parts[1];
                if (!file_exists(dirname($dst))) {
                    @mkdir(dirname($dst), 0777, true);
                }
                $result = copy((string) $file, $dst);
                if ($result === false) {
                    echo 'Can not copy: ', (string) $file, ' ', $dst, "\n";
                }
            }
        }
    }

    private function layoutConfigs($bundle)
    {
        $src = $bundle['root'].'/../config/layouts';
        if (!file_exists($src)) {
            return;
        }
        $files = $this->folderRead($src);
        foreach ($files as $file) {
            $parts = explode('/config/layouts/', (string) $file, 2);
            $dst = $this->root.'/../config/layouts/'.$bundle['name'].'/'.$parts[1];
            if (!file_exists(dirname($dst))) {
                @mkdir(dirname($dst), 0777, true);
            }
            $result = copy((string) $file, $dst);
            if ($result === false) {
                echo 'Can not copy: ', (string) $file, ' ', $dst, "\n";
            }
        }
    }

    private function folderRead($folder)
    {
        $dir = new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($dir);
        $fileList = [];
        foreach ($files as $file) {
            $fileList[] = $file->getPathname();
        }

        return $fileList;
    }
}
