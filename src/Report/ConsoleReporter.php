<?php

declare(strict_types=1);

namespace CopyrightChecker\Report;

use Symfony\Component\Console\Output\OutputInterface;

final class ConsoleReporter
{
    /**
     * @param ScanResult[] $results
     */
    public function render(OutputInterface $output, array $results): void
    {
        $counts = [
            ScanResult::STATUS_OK => 0,
            ScanResult::STATUS_VIOLATION => 0,
            ScanResult::STATUS_FIXED => 0,
            ScanResult::STATUS_FIX_FAILED => 0,
        ];

        foreach ($results as $result) {
            ++$counts[$result->status];

            if ($result->status === ScanResult::STATUS_OK && !$output->isVerbose()) {
                continue;
            }

            $output->writeln(sprintf('%s %s', $this->formatStatus($result->status), $result->path));

            if ($result->error !== null) {
                $output->writeln(sprintf('    <comment>%s</comment>', $result->error));
            }
        }

        $output->writeln('');
        $output->writeln(sprintf(
            'Checked %d file(s): <info>%d OK</info>, <error>%d violation(s)</error>, '
                . '<info>%d fixed</info>, <error>%d fix failure(s)</error>.',
            count($results),
            $counts[ScanResult::STATUS_OK],
            $counts[ScanResult::STATUS_VIOLATION],
            $counts[ScanResult::STATUS_FIXED],
            $counts[ScanResult::STATUS_FIX_FAILED],
        ));
    }

    private function formatStatus(string $status): string
    {
        return match ($status) {
            ScanResult::STATUS_OK => '<info>[OK]</info>',
            ScanResult::STATUS_VIOLATION => '<error>[VIOLATION]</error>',
            ScanResult::STATUS_FIXED => '<info>[FIXED]</info>',
            ScanResult::STATUS_FIX_FAILED => '<error>[FIX FAILED]</error>',
            default => '[' . $status . ']',
        };
    }
}
