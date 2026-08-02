<?php

declare(strict_types=1);

namespace CopyrightChecker\Model;

use CopyrightChecker\Model\Comment\CommentWrapperRegistry;
use CopyrightChecker\Model\Preamble\PreambleStripperRegistry;

final class CopyrightFixer
{
    public function __construct(
        private readonly PreambleStripperRegistry $strippers,
        private readonly int $searchWindowLines = 200,
        private readonly CommentWrapperRegistry $wrappers = new CommentWrapperRegistry(),
    ) {
    }

    public function fix(string $content, string $extension, CopyrightTemplate $template): string
    {
        $originalTemplate = $template;
        $template = $this->wrappers->wrap($template, $extension);

        $normalized = str_replace(["\r\n", "\r"], "\n", $content);
        $preambleLength = $this->strippers->forExtension($extension)->getPreambleLength($normalized);
        $preamble = substr($normalized, 0, $preambleLength);
        $rest = substr($normalized, $preambleLength);

        $leadingBlank = '';
        if (preg_match('/\A(?:[ \t]*\n)*/', $rest, $matches)) {
            $leadingBlank = $matches[0];
        }
        $body = substr($rest, strlen($leadingBlank));

        if (str_starts_with($body, $template->getText())) {
            return $normalized;
        }

        $leadingSeparator = $this->leadingSeparator($extension, $preamble);

        // Comment-wrapped blocks (xml/html, delimited by "<!-- -->") are placed directly
        // against the following markup with no blank-line gap; unwrapped blocks (e.g. a PHP
        // "/* */" block) keep a blank-line gap before the following code, as before.
        $trailingSeparator = $template === $originalTemplate ? "\n\n" : "\n";

        $bodyLines = $body === '' ? [] : explode("\n", $body);
        $firstNonBlankLine = $this->findFirstNonBlankLine($bodyLines);

        if ($firstNonBlankLine === $template->getFirstLine()) {
            $endIndex = $this->findClosingLineIndex($bodyLines, $template->getLastLine());

            if ($endIndex !== null) {
                $tailLines = array_slice($bodyLines, $endIndex + 1);

                // Collapse any number of blank lines after the old block down to the single
                // separator added below, so repeated fixes never accumulate blanks.
                while (($tailLines[0] ?? null) === '') {
                    array_shift($tailLines);
                }

                return $preamble . $leadingSeparator . $template->getText() . $trailingSeparator
                    . implode("\n", $tailLines);
            }
        }

        return $preamble . $leadingSeparator . $template->getText() . $trailingSeparator . $body;
    }

    /**
     * The blank-line convention before the template depends on the preamble: a "<?php" tag
     * gets a full blank line after it, while other preambles (e.g. an XML declaration) just
     * get a single newline. Preambles that already end in a newline of their own (shebang
     * lines) or an absent preamble need no separator at all.
     */
    private function leadingSeparator(string $extension, string $preamble): string
    {
        if ($preamble === '' || str_ends_with($preamble, "\n")) {
            return '';
        }

        return strtolower($extension) === 'php' ? "\n\n" : "\n";
    }

    /**
     * @param string[] $bodyLines
     */
    private function findFirstNonBlankLine(array $bodyLines): ?string
    {
        foreach ($bodyLines as $line) {
            if (trim($line) !== '') {
                return $line;
            }
        }

        return null;
    }

    /**
     * @param string[] $bodyLines
     */
    private function findClosingLineIndex(array $bodyLines, string $lastLine): ?int
    {
        $limit = min(count($bodyLines), $this->searchWindowLines);

        for ($i = 0; $i < $limit; $i++) {
            if ($bodyLines[$i] === $lastLine) {
                return $i;
            }
        }

        return null;
    }
}
