<?php

declare(strict_types=1);

namespace CopyrightChecker\Config;

use Symfony\Component\Console\Input\InputInterface;

final class ConfigResolver
{
    /**
     * @param array<string, mixed> $fileConfig
     * @param string $configDir The directory containing the config file that produced
     *     $fileConfig -- the base against which its relative `locations`/`exclude` paths
     *     are resolved, since --config-path may point outside --root-dir entirely.
     */
    public function resolve(InputInterface $input, array $fileConfig, string $configDir): Config
    {
        return new Config(
            exclude: $this->resolveExclude($input, $fileConfig, $configDir),
            extension: $this->resolveArrayOption($input, 'extension', $fileConfig),
            template: $this->resolveArrayOption($input, 'template', $fileConfig),
            colors: $this->resolveColors($input, $fileConfig),
            fix: $this->resolveFix($input, $fileConfig),
            fixApply: $this->resolveFixApply($input, $fileConfig),
            locations: $this->resolveLocations($fileConfig, $configDir),
        );
    }

    /**
     * @param array<string, mixed> $fileConfig
     * @return string[]
     */
    private function resolveArrayOption(InputInterface $input, string $option, array $fileConfig): array
    {
        if ($input->hasParameterOption('--' . $option)) {
            /** @var string[] $value */
            $value = (array) $input->getOption($option);

            return $value;
        }

        /** @var string[] $fromFile */
        $fromFile = (array) ($fileConfig[$option] ?? []);

        return $fromFile;
    }

    /**
     * @param array<string, mixed> $fileConfig
     * @return string[]
     */
    private function resolveExclude(InputInterface $input, array $fileConfig, string $configDir): array
    {
        if ($input->hasParameterOption('--exclude')) {
            /** @var string[] $value */
            $value = (array) $input->getOption('exclude');

            return $value;
        }

        /** @var string[] $fromFile */
        $fromFile = (array) ($fileConfig['exclude'] ?? []);

        return array_map(
            fn (string $exclude): string => $this->rebaseExclude($exclude, $configDir),
            $fromFile,
        );
    }

    /**
     * @param array<string, mixed> $fileConfig
     * @return string[]
     */
    private function resolveLocations(array $fileConfig, string $configDir): array
    {
        /** @var string[] $locations */
        $locations = (array) ($fileConfig['locations'] ?? []);

        return array_map(fn (string $location): string => $this->rebase($location, $configDir), $locations);
    }

    /**
     * Bare names (no "/") are intentionally location-independent -- FileScanner matches
     * them against a directory/file of that name at any depth, so they must not be turned
     * into one specific absolute path. Only slash-containing (and not already absolute)
     * values are rebased against the config file's own directory.
     */
    private function rebaseExclude(string $exclude, string $configDir): string
    {
        if (!str_contains($exclude, '/') || $this->isAbsolute($exclude)) {
            return $exclude;
        }

        return rtrim($configDir, '/') . '/' . $exclude;
    }

    private function rebase(string $path, string $configDir): string
    {
        return $this->isAbsolute($path) ? $path : rtrim($configDir, '/') . '/' . $path;
    }

    private function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1;
    }

    /**
     * @param array<string, mixed> $fileConfig
     */
    private function resolveColors(InputInterface $input, array $fileConfig): bool
    {
        if ($input->hasParameterOption('--colors') || $input->hasParameterOption('--no-colors')) {
            return (bool) $input->getOption('colors');
        }

        return (bool) ($fileConfig['colors'] ?? false);
    }

    /**
     * @param array<string, mixed> $fileConfig
     */
    private function resolveFix(InputInterface $input, array $fileConfig): bool
    {
        if ($input->hasParameterOption('--fix')) {
            return true;
        }

        return (bool) ($fileConfig['fix'] ?? false);
    }

    /**
     * @param array<string, mixed> $fileConfig
     */
    private function resolveFixApply(InputInterface $input, array $fileConfig): ?int
    {
        if ($input->hasParameterOption('--fix-apply')) {
            $value = $input->getOption('fix-apply');

            return $value === null ? null : (int) $value;
        }

        $fromFile = $fileConfig['fix-apply'] ?? null;

        return $fromFile === null ? null : (int) $fromFile;
    }
}
