<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/TestService.php';
$root = __DIR__ . '/../public';
$container = new \Opine\Container($root, $root . '/../container.yml');
$formRoute = $container->formRoute;
$formRoute->paths();