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
            'Checked %d file(s): <info>%d OK</info>, %s, <info>%d fixed</info>, %s.',
            count($results),
            $counts[ScanResult::STATUS_OK],
            $this->formatProblemCount($counts[ScanResult::STATUS_VIOLATION], 'violation(s)'),
            $counts[ScanResult::STATUS_FIXED],
            $this->formatProblemCount($counts[ScanResult::STATUS_FIX_FAILED], 'fix failure(s)'),
        ));
    }

    private function formatProblemCount(int $count, string $label): string
    {
        $tag = $count > 0 ? 'error' : 'info';

        return sprintf('<%1$s>%2$d %3$s</%1$s>', $tag, $count, $label);
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
