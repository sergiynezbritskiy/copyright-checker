<?php

declare(strict_types=1);

namespace CopyrightChecker\Tests\Unit\Report;

use CopyrightChecker\Report\ConsoleReporter;
use CopyrightChecker\Report\ScanResult;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

final class ConsoleReporterTest extends TestCase
{
    public function testOkResultsAreHiddenAtNormalVerbosity(): void
    {
        $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL);
        $results = [new ScanResult('src/Foo.php', ScanResult::STATUS_OK)];

        (new ConsoleReporter())->render($output, $results);

        self::assertStringNotContainsString('src/Foo.php', $output->fetch());
    }

    public function testOkResultsAreShownAtVerboseVerbosity(): void
    {
        $output = new BufferedOutput(OutputInterface::VERBOSITY_VERBOSE);
        $results = [new ScanResult('src/Foo.php', ScanResult::STATUS_OK)];

        (new ConsoleReporter())->render($output, $results);

        self::assertStringContainsString('src/Foo.php', $output->fetch());
    }

    public function testViolationsAreAlwaysShownRegardlessOfVerbosity(): void
    {
        $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL);
        $results = [new ScanResult('src/Bad.php', ScanResult::STATUS_VIOLATION)];

        (new ConsoleReporter())->render($output, $results);

        self::assertStringContainsString('src/Bad.php', $output->fetch());
    }

    public function testFixedAndFixFailedAreAlwaysShownRegardlessOfVerbosity(): void
    {
        $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL);
        $results = [
            new ScanResult('src/Fixed.php', ScanResult::STATUS_FIXED),
            new ScanResult('src/Broken.php', ScanResult::STATUS_FIX_FAILED, null, 'Unable to write file.'),
        ];

        (new ConsoleReporter())->render($output, $results);

        $display = $output->fetch();
        self::assertStringContainsString('src/Fixed.php', $display);
        self::assertStringContainsString('src/Broken.php', $display);
        self::assertStringContainsString('Unable to write file.', $display);
    }

    public function testSummaryCountsIncludeOkResultsEvenWhenHidden(): void
    {
        $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL);
        $results = [
            new ScanResult('src/Foo.php', ScanResult::STATUS_OK),
            new ScanResult('src/Bar.php', ScanResult::STATUS_OK),
            new ScanResult('src/Bad.php', ScanResult::STATUS_VIOLATION),
        ];

        (new ConsoleReporter())->render($output, $results);

        self::assertStringContainsString('2 OK', $output->fetch());
    }
}
