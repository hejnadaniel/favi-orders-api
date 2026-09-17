<?php

declare(strict_types=1);

use App\Kernel;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

new Dotenv()->bootEnv(dirname(__DIR__) . '/.env');

$kernel = new Kernel('dev', true);
$kernel->boot();

$doctrine = $kernel->getContainer()->get('doctrine');
assert($doctrine instanceof ManagerRegistry);

return $doctrine->getManager();
