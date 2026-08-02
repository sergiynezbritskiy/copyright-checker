<?php

declare(strict_types=1);

namespace CopyrightChecker\Tests\Functional;

use CopyrightChecker\Console\CheckCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class CheckCommandTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/copyright-checker-test-' . uniqid();
        mkdir($this->tmpDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tmpDir);
    }

    private function createTester(): CommandTester
    {
        $application = new Application('copyright-checker-test');
        $command = new CheckCommand(standalone: true);
        $application->add($command);

        return new CommandTester($command);
    }

    public function testCleanPassWithNoViolations(): void
    {
        file_put_contents($this->tmpDir . '/Foo.php', "<?php\n// Copyright\n\nclass Foo {}\n");

        $tester = $this->createTester();
        $exitCode = $tester->execute([
            'paths' => [$this->tmpDir],
            '--root-dir' => $this->tmpDir,
            '--extension' => ['php'],
            '--template' => ['// Copyright'],
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('1 OK', $tester->getDisplay());
        self::assertStringNotContainsString('[OK]', $tester->getDisplay());
    }

    public function testOkFilesAreShownWithVerboseFlag(): void
    {
        file_put_contents($this->tmpDir . '/Foo.php', "<?php\n// Copyright\n\nclass Foo {}\n");

        $tester = $this->createTester();
        $tester->execute(
            [
                'paths' => [$this->tmpDir],
                '--root-dir' => $this->tmpDir,
                '--extension' => ['php'],
                '--template' => ['// Copyright'],
            ],
            ['verbosity' => OutputInterface::VERBOSITY_VERBOSE],
        );

        self::assertStringContainsString('[OK]', $tester->getDisplay());
    }

    public function testFixWithFixApplyRewritesStaleHeader(): void
    {
        $file = $this->tmpDir . '/Foo.php';
        file_put_contents($file, "<?php\n// Old copyright\n\nclass Foo {}\n");

        $tester = $this->createTester();
        $exitCode = $tester->execute([
            'paths' => [$this->tmpDir],
            '--root-dir' => $this->tmpDir,
            '--extension' => ['php'],
            '--template' => ['// New copyright'],
            '--fix' => true,
            '--fix-apply' => '1',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('// New copyright', (string) file_get_contents($file));
    }

    public function testNoPathArgumentsFallsBackToLocationsInConfigFile(): void
    {
        mkdir($this->tmpDir . '/src');
        file_put_contents($this->tmpDir . '/src/Foo.php', "<?php\n// Copyright\n\nclass Foo {}\n");
        file_put_contents($this->tmpDir . '/.copyright.dist', <<<'PHP'
            <?php
            return [
                'extension' => ['php'],
                'template' => ['// Copyright'],
                'locations' => ['src'],
            ];
            PHP);

        $tester = $this->createTester();
        $exitCode = $tester->execute(['--root-dir' => $this->tmpDir]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('1 OK', $tester->getDisplay());
    }

    public function testLocationsInConfigFileOutsideRootDirAreRelativeToTheConfigFile(): void
    {
        // Reproduces the reported scenario: the PHP app lives in an "html" subdirectory
        // (--root-dir), but --config-path points at a .copyright.dist sitting one level up,
        // outside root-dir entirely. "locations" in that file must resolve relative to
        // wherever the file itself lives, not to --root-dir.
        $outerDir = $this->tmpDir;
        $appDir = $outerDir . '/html';
        mkdir($appDir . '/src', 0777, true);
        file_put_contents($appDir . '/src/Foo.php', "<?php\n// Copyright\n\nclass Foo {}\n");
        file_put_contents($outerDir . '/.copyright.dist', <<<'PHP'
            <?php
            return [
                'extension' => ['php'],
                'template' => ['// Copyright'],
                'locations' => ['html/src'],
            ];
            PHP);

        $tester = $this->createTester();
        $exitCode = $tester->execute([
            '--root-dir' => $appDir,
            '--config-path' => $outerDir . '/.copyright.dist',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('1 OK', $tester->getDisplay());
    }

    public function testNoPathArgumentsAndNoLocationsIsAUsageError(): void
    {
        file_put_contents($this->tmpDir . '/.copyright.dist', <<<'PHP'
            <?php
            return [
                'extension' => ['php'],
                'template' => ['// Copyright'],
            ];
            PHP);

        $tester = $this->createTester();
        $exitCode = $tester->execute(['--root-dir' => $this->tmpDir]);

        self::assertSame(Command::INVALID, $exitCode);
    }

    public function testConfigPathOverridesDefaultDistFile(): void
    {
        mkdir($this->tmpDir . '/src');
        file_put_contents($this->tmpDir . '/src/Foo.php', "<?php\n// Copyright\n\nclass Foo {}\n");

        // A default .copyright.dist that would fail (no extension/template configured),
        // to prove --config-path is what actually got read, not the default file.
        file_put_contents($this->tmpDir . '/.copyright.dist', "<?php\nreturn [];\n");
        file_put_contents($this->tmpDir . '/custom-config.php', <<<'PHP'
            <?php
            return [
                'extension' => ['php'],
                'template' => ['// Copyright'],
                'locations' => ['src'],
            ];
            PHP);

        $tester = $this->createTester();
        $exitCode = $tester->execute([
            '--root-dir' => $this->tmpDir,
            '--config-path' => 'custom-config.php',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('1 OK', $tester->getDisplay());
    }

    public function testMissingConfigPathIsAUsageError(): void
    {
        $tester = $this->createTester();
        $exitCode = $tester->execute([
            'paths' => [$this->tmpDir],
            '--root-dir' => $this->tmpDir,
            '--config-path' => 'does-not-exist.php',
        ]);

        self::assertSame(Command::INVALID, $exitCode);
    }

    public function testMissingExtensionIsAUsageError(): void
    {
        file_put_contents($this->tmpDir . '/Foo.php', "<?php\nclass Foo {}\n");

        $tester = $this->createTester();
        $exitCode = $tester->execute([
            'paths' => [$this->tmpDir],
            '--root-dir' => $this->tmpDir,
            '--template' => ['// Copyright'],
        ]);

        self::assertSame(Command::INVALID, $exitCode);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;

            if (is_dir($path)) {
                $this->removeDirectory($path);

                continue;
            }

            unlink($path);
        }

        rmdir($dir);
    }
}
