<?php
namespace Opine;

use PHPUnit_Framework_TestCase;
use Opine\Config\Service as Config;

class BundleTest extends PHPUnit_Framework_TestCase {

    public function setup () {
        $root = __DIR__ . '/../public';
        $config = new Config();
        $config->cacheSet();
        $container = new Container($root, $config, $root . '/../container.yml');
    }

    public function testSample () {
        $this->assertTrue(true);
    }
}