<?php

declare(strict_types=1);

namespace CopyrightChecker\Console;

use Symfony\Component\Console\Application;

final class ApplicationFactory
{
    public static function create(bool $standalone, ?string $defaultRootDir = null): Application
    {
        $application = new Application('copyright-checker');
        $command = new CheckCommand($standalone, $defaultRootDir);

        $application->add($command);
        $application->setDefaultCommand((string) $command->getName(), true);

        return $application;
    }
}
