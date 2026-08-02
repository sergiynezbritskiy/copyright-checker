<?php

declare(strict_types=1);

namespace CopyrightChecker\Config;

use Symfony\Component\Console\Input\InputInterface;

final class ConfigResolver
{
    /**
     * @param array<string, mixed> $fileConfig
     */
    public function resolve(InputInterface $input, array $fileConfig): Config
    {
        return new Config(
            exclude: $this->resolveArrayOption($input, 'exclude', $fileConfig),
            extension: $this->resolveArrayOption($input, 'extension', $fileConfig),
            template: $this->resolveArrayOption($input, 'template', $fileConfig),
            colors: $this->resolveColors($input, $fileConfig),
            fix: $this->resolveFix($input, $fileConfig),
            fixApply: $this->resolveFixApply($input, $fileConfig),
            locations: (array) ($fileConfig['locations'] ?? []),
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
