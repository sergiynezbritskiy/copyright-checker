<?php

declare(strict_types=1);

namespace CopyrightChecker\Tests\Unit\Config;

use CopyrightChecker\Config\ConfigResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

final class ConfigResolverTest extends TestCase
{
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

    public function testCliArrayOptionWhollyReplacesFileArrayWhenPassedAtAll(): void
    {
        $input = $this->createInput(['--extension=xml', 'src']);
        $fileConfig = ['extension' => ['php', 'js']];

        $config = (new ConfigResolver())->resolve($input, $fileConfig);

        self::assertSame(['xml'], $config->extension);
    }

    public function testFileValueUsedWhenCliOptionAbsent(): void
    {
        $input = $this->createInput(['src']);
        $fileConfig = ['extension' => ['php', 'js']];

        $config = (new ConfigResolver())->resolve($input, $fileConfig);

        self::assertSame(['php', 'js'], $config->extension);
    }

    public function testCliExcludeReplacesFileExcludeEntirelyNotUnioned(): void
    {
        $input = $this->createInput(['--exclude=tests', 'src']);
        $fileConfig = ['exclude' => ['vendor', 'var']];

        $config = (new ConfigResolver())->resolve($input, $fileConfig);

        self::assertSame(['tests'], $config->exclude);
    }

    public function testNoColorsOverridesFileColorsTrue(): void
    {
        $input = $this->createInput(['--no-colors', 'src']);
        $fileConfig = ['colors' => true];

        $config = (new ConfigResolver())->resolve($input, $fileConfig);

        self::assertFalse($config->colors);
    }

    public function testColorsFallsBackToFileValueWhenCliOmitsIt(): void
    {
        $input = $this->createInput(['src']);
        $fileConfig = ['colors' => true];

        $config = (new ConfigResolver())->resolve($input, $fileConfig);

        self::assertTrue($config->colors);
    }

    public function testFixApplyFallsBackToFileValueWhenCliOmitsIt(): void
    {
        $input = $this->createInput(['src']);
        $fileConfig = ['fix-apply' => 2];

        $config = (new ConfigResolver())->resolve($input, $fileConfig);

        self::assertSame(2, $config->fixApply);
    }

    public function testFixApplyFromCliOverridesFileValue(): void
    {
        $input = $this->createInput(['--fix-apply=3', 'src']);
        $fileConfig = ['fix-apply' => 2];

        $config = (new ConfigResolver())->resolve($input, $fileConfig);

        self::assertSame(3, $config->fixApply);
    }

    public function testFixFlagFromCliIsTrueRegardlessOfFileValue(): void
    {
        $input = $this->createInput(['--fix', 'src']);
        $fileConfig = ['fix' => false];

        $config = (new ConfigResolver())->resolve($input, $fileConfig);

        self::assertTrue($config->fix);
    }

    public function testFixFalseFallsBackToFileValueWhenCliOmitsIt(): void
    {
        $input = $this->createInput(['src']);
        $fileConfig = ['fix' => true];

        $config = (new ConfigResolver())->resolve($input, $fileConfig);

        self::assertTrue($config->fix);
    }

    public function testLocationsAreReadFromFileConfig(): void
    {
        $input = $this->createInput(['src']);
        $fileConfig = ['locations' => ['src', 'tests']];

        $config = (new ConfigResolver())->resolve($input, $fileConfig);

        self::assertSame(['src', 'tests'], $config->locations);
    }

    public function testLocationsDefaultToEmptyArrayWhenFileConfigOmitsThem(): void
    {
        $input = $this->createInput(['src']);

        $config = (new ConfigResolver())->resolve($input, []);

        self::assertSame([], $config->locations);
    }
}
