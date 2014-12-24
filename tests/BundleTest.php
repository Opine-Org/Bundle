<?php
namespace Opine;

use PHPUnit_Framework_TestCase;
use Opine\Config\Service as Config;
use Opine\Container\Service as Container;

class BundleTest extends PHPUnit_Framework_TestCase
{
    private $model;

    public function setup()
    {
        $root = __DIR__.'/../public';
        $testContainer = $root.'/../config/containers/test-container.yml';
        $config = new Config($root);
        $config->cacheSet();
        $container = Container::instance($root, $config, $testContainer);
        $this->model = $container->get('bundleModel');
    }

    public function testBuild()
    {
        $response = json_decode($this->model->build(), true);
        $this->assertTrue(count($response) > 0);
    }

    public function testCacheSet()
    {
        $this->assertTrue(1 === $this->model->cacheSet([
            "Person" => [
                "name" => "Person",
                "modelService" => "personModel",
                "root" => "/var/www/project/vendor/opine/bundle/tests/../public/../vendor/opine/person/src/Person/public",
                "routeFiles" => [
                    "/var/www/project/vendor/opine/bundle/tests/../public/../vendor/opine/person/src/Person/config/routes/route.yml",
                ],
            ],
            "Helper" => [
                "name" => "Helper",
                "modelService" => "helperModel",
                "root" => "/var/www/project/vendor/opine/bundle/tests/../public/../vendor/opine/helper/src/Helper/public",
                "routeFiles" => [
                    "/var/www/project/vendor/opine/bundle/tests/../public/../vendor/opine/helper/src/Helper/config/routes/route.yml",
                ],
            ],
            "Manager" => [
                "name" => "Manager",
                "modelService" => "managerModel",
                "root" => "/var/www/project/vendor/opine/bundle/tests/../public/../vendor/opine/semantic-cm/src/Manager/public",
                "routeFiles" => [
                    "/var/www/project/vendor/opine/bundle/tests/../public/../vendor/opine/semantic-cm/src/Manager/config/routes/route.yml",
                ],
            ],
        ]));

        $this->assertTrue(2 === $this->model->cacheSet());
    }

    public function testBundles()
    {
        $response = $this->model->bundles();
        $this->assertTrue(count($response) > 0);
    }
}
