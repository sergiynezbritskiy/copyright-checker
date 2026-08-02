<?php

declare(strict_types=1);

namespace CopyrightChecker\Tests\Unit\Config;

use CopyrightChecker\Config\ConfigFileLoader;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ConfigFileLoaderTest extends TestCase
{
    private string $rootDir;

    protected function setUp(): void
    {
        $this->rootDir = sys_get_temp_dir() . '/copyright-checker-config-test-' . uniqid();
        mkdir($this->rootDir);
    }

    protected function tearDown(): void
    {
        // scandir() (unlike glob('*')) also picks up dotfiles such as .copyright.dist.
        foreach (scandir($this->rootDir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            unlink($this->rootDir . '/' . $item);
        }

        rmdir($this->rootDir);
    }

    public function testReturnsEmptyArrayWhenDefaultConfigFileDoesNotExist(): void
    {
        $config = (new ConfigFileLoader())->load($this->rootDir);

        self::assertSame([], $config);
    }

    public function testLoadsConfigFromDefaultPath(): void
    {
        $this->writeConfig(ConfigFileLoader::FILENAME, "<?php\nreturn ['extension' => ['php']];\n");

        $config = (new ConfigFileLoader())->load($this->rootDir);

        self::assertSame(['extension' => ['php']], $config);
    }

    public function testThrowsWhenConfigFileDoesNotReturnAnArray(): void
    {
        $this->writeConfig(ConfigFileLoader::FILENAME, "<?php\nreturn 'not an array';\n");

        $this->expectException(RuntimeException::class);

        (new ConfigFileLoader())->load($this->rootDir);
    }

    public function testThrowsOnUnknownConfigKeys(): void
    {
        $this->writeConfig(ConfigFileLoader::FILENAME, "<?php\nreturn ['bogus' => true];\n");

        $this->expectException(RuntimeException::class);

        (new ConfigFileLoader())->load($this->rootDir);
    }

    public function testLoadsFromExplicitRelativeConfigPath(): void
    {
        $this->writeConfig('custom.php', "<?php\nreturn ['extension' => ['xml']];\n");

        $config = (new ConfigFileLoader())->load($this->rootDir, 'custom.php');

        self::assertSame(['extension' => ['xml']], $config);
    }

    public function testLoadsFromExplicitAbsoluteConfigPath(): void
    {
        $this->writeConfig('custom.php', "<?php\nreturn ['extension' => ['xml']];\n");

        $config = (new ConfigFileLoader())->load($this->rootDir, $this->rootDir . '/custom.php');

        self::assertSame(['extension' => ['xml']], $config);
    }

    public function testThrowsWhenExplicitConfigPathDoesNotExist(): void
    {
        $this->expectException(RuntimeException::class);

        (new ConfigFileLoader())->load($this->rootDir, 'missing.php');
    }

    private function writeConfig(string $relativePath, string $contents): void
    {
        file_put_contents($this->rootDir . '/' . $relativePath, $contents);
    }
}
