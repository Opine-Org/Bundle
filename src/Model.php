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

class Model
{
    private $root;
    private $route;
    private $cache;
    private $cacheFile;

    public function __construct($root)
    {
        $this->root = $root;
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
            if ($path == '') {
                continue;
            }
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
        }
        $this->cacheWrite($bundles);

        return $bundles;
    }
}
