<?php

declare(strict_types=1);

namespace CopyrightChecker\Config;

use RuntimeException;

final class ConfigFileLoader
{
    public const FILENAME = '.copyright.dist';

    private const ALLOWED_KEYS = ['exclude', 'extension', 'template', 'colors', 'fix', 'fix-apply', 'locations'];

    /**
     * @return array<string, mixed>
     */
    public function load(string $rootDir): array
    {
        $path = rtrim($rootDir, '/') . '/' . self::FILENAME;

        if (!is_file($path)) {
            return [];
        }

        $config = require $path;

        if (!is_array($config)) {
            throw new RuntimeException(sprintf('%s must return an array.', $path));
        }

        $unknownKeys = array_diff(array_keys($config), self::ALLOWED_KEYS);

        if ($unknownKeys !== []) {
            throw new RuntimeException(sprintf(
                '%s contains unknown configuration key(s): %s',
                $path,
                implode(', ', $unknownKeys),
            ));
        }

        return $config;
    }
}
