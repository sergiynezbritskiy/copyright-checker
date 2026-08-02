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
    public function load(string $rootDir, ?string $configPath = null): array
    {
        $path = $this->resolvePath($rootDir, $configPath);

        if (!is_file($path)) {
            if ($configPath !== null) {
                throw new RuntimeException(sprintf('Config file "%s" does not exist.', $path));
            }

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

    private function resolvePath(string $rootDir, ?string $configPath): string
    {
        if ($configPath === null) {
            return rtrim($rootDir, '/') . '/' . self::FILENAME;
        }

        if ($this->isAbsolute($configPath)) {
            return $configPath;
        }

        return rtrim($rootDir, '/') . '/' . $configPath;
    }

    private function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1;
    }
}
