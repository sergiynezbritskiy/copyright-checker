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

            // Only a leading block that looks like an (outdated) copyright header is replaced;
            // any other block (e.g. an "@noinspection" docblock) is kept below the new header.
            if (
                $endIndex !== null
                && $this->looksLikeCopyright(array_slice($bodyLines, 0, $endIndex + 1), $template)
            ) {
                $bodyLines = array_slice($bodyLines, $endIndex + 1);

                // Collapse any number of blank lines after the old block down to the single
                // separator added below, so repeated fixes never accumulate blanks.
                while (($bodyLines[0] ?? null) === '') {
                    array_shift($bodyLines);
                }
            }
        }

        $bodyLines = $this->removeDuplicateBlock($bodyLines, $template);

        return $preamble . $leadingSeparator . $template->getText() . $trailingSeparator
            . implode("\n", $bodyLines);
    }

    /**
     * A block is considered a copyright header when it mentions copyright/licensing, or
     * shares at least one content line (other than the delimiters) with the template.
     *
     * @param string[] $blockLines
     */
    private function looksLikeCopyright(array $blockLines, CopyrightTemplate $template): bool
    {
        $text = implode("\n", $blockLines);
        if (preg_match('/copyright|licen[cs]e|\(c\)|\x{00A9}/iu', $text) === 1) {
            return true;
        }

        $templateLines = array_slice(explode("\n", $template->getText()), 1, -1);
        $contentLines = array_filter(
            array_slice($blockLines, 1, -1),
            static fn (string $line): bool => trim($line, " \t*/#") !== '',
        );

        return array_intersect($contentLines, $templateLines) !== [];
    }

    /**
     * Removes an exact copy of the template found further down within the search window
     * (e.g. a correct header that sat below another docblock), together with the blank
     * lines that follow it, so the header is effectively moved to the top.
     *
     * @param string[] $bodyLines
     * @return string[]
     */
    private function removeDuplicateBlock(array $bodyLines, CopyrightTemplate $template): array
    {
        $templateLines = explode("\n", $template->getText());
        $count = count($templateLines);
        $limit = min(count($bodyLines), $this->searchWindowLines) - $count;

        for ($i = 0; $i <= $limit; $i++) {
            if (array_slice($bodyLines, $i, $count) !== $templateLines) {
                continue;
            }

            $end = $i + $count;
            while (($bodyLines[$end] ?? null) === '') {
                $end++;
            }
            array_splice($bodyLines, $i, $end - $i);

            return $bodyLines;
        }

        return $bodyLines;
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

        return in_array(strtolower($extension), ['php', 'phtml'], true) ? "\n\n" : "\n";
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
