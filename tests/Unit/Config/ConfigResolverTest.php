<?php

declare(strict_types=1);

namespace CopyrightChecker\Tests\Unit\Config;

use CopyrightChecker\Config\Config;
use CopyrightChecker\Config\ConfigResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

final class ConfigResolverTest extends TestCase
{
    private const CONFIG_DIR = '/project/config-dir';

    /**
     * @param string[] $argv
     */
    private function createInput(array $argv): ArgvInput
    {
        $definition = new InputDefinition([
            new InputArgument('paths', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, '', []),
            new InputOption('exclude', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, '', []),
            new InputOption('colors', null, InputOption::VALUE_NEGATABLE, '', false),
            new InputOption('extension', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, '', []),
            new InputOption('template', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, '', []),
            new InputOption('fix', null, InputOption::VALUE_NONE),
            new InputOption('fix-apply', null, InputOption::VALUE_REQUIRED),
            new InputOption('root-dir', null, InputOption::VALUE_REQUIRED),
        ]);

        return new ArgvInput(array_merge(['bin/copyright'], $argv), $definition);
    }

    /**
     * @param array<string, mixed> $fileConfig
     */
    private function resolve(ArgvInput $input, array $fileConfig, string $configDir = self::CONFIG_DIR): Config
    {
        return (new ConfigResolver())->resolve($input, $fileConfig, $configDir);
    }

    public function testCliArrayOptionWhollyReplacesFileArrayWhenPassedAtAll(): void
    {
        $input = $this->createInput(['--extension=xml', 'src']);
        $fileConfig = ['extension' => ['php', 'js']];

        $config = $this->resolve($input, $fileConfig);

        self::assertSame(['xml'], $config->extension);
    }

    public function testFileValueUsedWhenCliOptionAbsent(): void
    {
        $input = $this->createInput(['src']);
        $fileConfig = ['extension' => ['php', 'js']];

        $config = $this->resolve($input, $fileConfig);

        self::assertSame(['php', 'js'], $config->extension);
    }

    public function testCliExcludeReplacesFileExcludeEntirelyNotUnioned(): void
    {
        $input = $this->createInput(['--exclude=tests', 'src']);
        $fileConfig = ['exclude' => ['vendor', 'var']];

        $config = $this->resolve($input, $fileConfig);

        self::assertSame(['tests'], $config->exclude);
    }

    public function testCliSuppliedExcludeIsNotRebasedAgainstConfigDir(): void
    {
        // CLI-supplied values are relative to --root-dir (resolved later by FileScanner),
        // not to wherever the config file happens to live -- they must pass through as-is.
        $input = $this->createInput(['--exclude=app/code/Foo', 'src']);

        $config = $this->resolve($input, []);

        self::assertSame(['app/code/Foo'], $config->exclude);
    }

    public function testFileExcludeContainingSlashIsRebasedAgainstConfigDir(): void
    {
        $input = $this->createInput(['src']);
        $fileConfig = ['exclude' => ['app/code/Vendor/Module/Test']];

        $config = $this->resolve($input, $fileConfig);

        self::assertSame([self::CONFIG_DIR . '/app/code/Vendor/Module/Test'], $config->exclude);
    }

    public function testFileExcludeBareNameIsNotRebased(): void
    {
        // Bare names match at any depth (see FileScanner) -- rebasing them into one
        // absolute path would break that entirely, so they must be left untouched.
        $input = $this->createInput(['src']);
        $fileConfig = ['exclude' => ['vendor', 'Test']];

        $config = $this->resolve($input, $fileConfig);

        self::assertSame(['vendor', 'Test'], $config->exclude);
    }

    public function testFileExcludeAlreadyAbsoluteIsLeftUnchanged(): void
    {
        $input = $this->createInput(['src']);
        $fileConfig = ['exclude' => ['/elsewhere/app/code/Test']];

        $config = $this->resolve($input, $fileConfig);

        self::assertSame(['/elsewhere/app/code/Test'], $config->exclude);
    }

    public function testNoColorsOverridesFileColorsTrue(): void
    {
        $input = $this->createInput(['--no-colors', 'src']);
        $fileConfig = ['colors' => true];

        $config = $this->resolve($input, $fileConfig);

        self::assertFalse($config->colors);
    }

    public function testColorsFallsBackToFileValueWhenCliOmitsIt(): void
    {
        $input = $this->createInput(['src']);
        $fileConfig = ['colors' => true];

        $config = $this->resolve($input, $fileConfig);

        self::assertTrue($config->colors);
    }

    public function testFixApplyFallsBackToFileValueWhenCliOmitsIt(): void
    {
        $input = $this->createInput(['src']);
        $fileConfig = ['fix-apply' => 2];

        $config = $this->resolve($input, $fileConfig);

        self::assertSame(2, $config->fixApply);
    }

    public function testFixApplyFromCliOverridesFileValue(): void
    {
        $input = $this->createInput(['--fix-apply=3', 'src']);
        $fileConfig = ['fix-apply' => 2];

        $config = $this->resolve($input, $fileConfig);

        self::assertSame(3, $config->fixApply);
    }

    public function testFixFlagFromCliIsTrueRegardlessOfFileValue(): void
    {
        $input = $this->createInput(['--fix', 'src']);
        $fileConfig = ['fix' => false];

        $config = $this->resolve($input, $fileConfig);

        self::assertTrue($config->fix);
    }

    public function testFixFalseFallsBackToFileValueWhenCliOmitsIt(): void
    {
        $input = $this->createInput(['src']);
        $fileConfig = ['fix' => true];

        $config = $this->resolve($input, $fileConfig);

        self::assertTrue($config->fix);
    }

    public function testLocationsAreRebasedAgainstConfigDir(): void
    {
        $input = $this->createInput(['src']);
        $fileConfig = ['locations' => ['src', 'tests']];

        $config = $this->resolve($input, $fileConfig);

        self::assertSame(
            [self::CONFIG_DIR . '/src', self::CONFIG_DIR . '/tests'],
            $config->locations,
        );
    }

    public function testAbsoluteLocationIsLeftUnchanged(): void
    {
        $input = $this->createInput(['src']);
        $fileConfig = ['locations' => ['/elsewhere/src']];

        $config = $this->resolve($input, $fileConfig);

        self::assertSame(['/elsewhere/src'], $config->locations);
    }

    public function testLocationsDefaultToEmptyArrayWhenFileConfigOmitsThem(): void
    {
        $input = $this->createInput(['src']);

        $config = $this->resolve($input, []);

        self::assertSame([], $config->locations);
    }
}
