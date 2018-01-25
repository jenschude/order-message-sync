#!/usr/bin/env php
<?php
require __DIR__.'/../vendor/autoload.php';

use Commercetools\IronIO\MessageSync\OrderMessageSyncCommand;
use Symfony\Component\Console\Application;

$application = new Application();

$application->add(new OrderMessageSyncCommand());

$application->run();

