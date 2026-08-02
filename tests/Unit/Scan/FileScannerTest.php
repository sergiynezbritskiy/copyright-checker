<?php

declare(strict_types=1);

namespace CopyrightChecker\Tests\Unit\Scan;

use CopyrightChecker\Scan\FileScanner;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FileScannerTest extends TestCase
{
    private string $rootDir;

    private FileScanner $scanner;

    protected function setUp(): void
    {
        $this->rootDir = sys_get_temp_dir() . '/copyright-checker-scanner-test-' . uniqid();
        mkdir($this->rootDir);
        $this->scanner = new FileScanner();
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->rootDir);
    }

    public function testScansDirectoryRecursivelyByExtension(): void
    {
        $this->touch('src/Foo.php');
        $this->touch('src/Sub/Bar.php');
        $this->touch('src/Baz.txt');

        $result = $this->scanner->scan(['src'], ['php'], [], $this->rootDir);

        self::assertSame([
            $this->rootDir . '/src/Foo.php',
            $this->rootDir . '/src/Sub/Bar.php',
        ], $result);
    }

    public function testExcludesBareNameAtAnyDepthRegardlessOfWhereItsScannedFrom(): void
    {
        // Reproduces the reported bug: --exclude=Test was expected to exclude the "Test"
        // directory *within* the scanned path, not only one sitting directly under root-dir.
        $this->touch('app/code/Vendor/Module/Test/Excluded.php');
        $this->touch('app/code/Vendor/Module/Src/Included.php');

        $result = $this->scanner->scan(['app/code/Vendor/Module'], ['php'], ['Test'], $this->rootDir);

        self::assertSame([$this->rootDir . '/app/code/Vendor/Module/Src/Included.php'], $result);
    }

    public function testExcludesPathContainingSlashRelativeToRootDir(): void
    {
        $this->touch('app/code/Vendor/Module/Test/Excluded.php');
        $this->touch('app/code/Vendor/Module/Src/Included.php');

        $result = $this->scanner->scan(
            ['app/code/Vendor/Module'],
            ['php'],
            ['app/code/Vendor/Module/Test'],
            $this->rootDir,
        );

        self::assertSame([$this->rootDir . '/app/code/Vendor/Module/Src/Included.php'], $result);
    }

    public function testExcludesByAbsolutePathRegardlessOfRootDir(): void
    {
        // An absolute exclude can arise when a config file living outside root-dir has its
        // relative exclude entries rebased against its own directory (see ConfigResolver).
        $this->touch('app/code/Vendor/Module/Test/Excluded.php');
        $this->touch('app/code/Vendor/Module/Src/Included.php');

        $result = $this->scanner->scan(
            ['app/code/Vendor/Module'],
            ['php'],
            [$this->rootDir . '/app/code/Vendor/Module/Test'],
            $this->rootDir,
        );

        self::assertSame([$this->rootDir . '/app/code/Vendor/Module/Src/Included.php'], $result);
    }

    public function testExcludeDoesNotNeedToExistOnDisk(): void
    {
        $this->touch('src/Foo.php');

        $result = $this->scanner->scan(['src'], ['php'], ['does-not-exist', 'vendor'], $this->rootDir);

        self::assertSame([$this->rootDir . '/src/Foo.php'], $result);
    }

    public function testBareNameExcludeDoesNotMatchAsAPrefixOfADifferentName(): void
    {
        $this->touch('var/Cache.php');
        $this->touch('vartest/Foo.php');

        $result = $this->scanner->scan(['.'], ['php'], ['var'], $this->rootDir);

        self::assertSame([$this->rootDir . '/vartest/Foo.php'], $result);
    }

    public function testIndividualFileArgumentsAreAlsoExcluded(): void
    {
        $this->touch('src/Foo.php');
        $this->touch('src/Bar.php');

        $result = $this->scanner->scan(['src/Foo.php', 'src/Bar.php'], ['php'], ['Bar.php'], $this->rootDir);

        self::assertSame([$this->rootDir . '/src/Foo.php'], $result);
    }

    public function testThrowsWhenPathDoesNotExist(): void
    {
        $this->expectException(RuntimeException::class);

        $this->scanner->scan(['does-not-exist'], ['php'], [], $this->rootDir);
    }

    public function testDedupesOverlappingPathArguments(): void
    {
        $this->touch('src/Sub/Foo.php');

        $result = $this->scanner->scan(['src', 'src/Sub'], ['php'], [], $this->rootDir);

        self::assertSame([$this->rootDir . '/src/Sub/Foo.php'], $result);
    }

    private function touch(string $relativePath): void
    {
        $fullPath = $this->rootDir . '/' . $relativePath;
        $dir = dirname($fullPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($fullPath, '<?php');
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
