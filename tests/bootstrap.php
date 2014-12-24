<?php
date_default_timezone_set('UTC');
session_start();
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/TestService.php';
require_once __DIR__.'/ContactRequests.php';
$root = __DIR__.'/../public';
$config = new \Opine\Config\Service($root);
$config->cacheSet();
$container = \Opine\Container\Service::instance($root, $config, $root.'/../config/containers/test-container.yml');
$formRoute = $container->get('formRoute');
$formRoute->paths();
