<?php

declare(strict_types=1);

// Behat bootstrap: loads environment variables the same way bin/phpunit does,
// because FriendsOfBehat\SymfonyExtension boots the kernel without Dotenv.
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

if (is_file(dirname(__DIR__) . '/.env')) {
    (new Dotenv())->usePutenv()->loadEnv(dirname(__DIR__) . '/.env');
}
