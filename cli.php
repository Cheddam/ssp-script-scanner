<?php

require __DIR__.'/vendor/autoload.php';

use App\Command\ScanCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Dotenv\Dotenv;

$environment = new Dotenv();
$environment->load(__DIR__ . '/.env');

$application = new Application();

$application->add(new ScanCommand());

$application->run();

