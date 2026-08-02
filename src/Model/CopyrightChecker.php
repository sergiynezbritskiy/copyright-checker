<?php

declare(strict_types=1);

namespace CopyrightChecker\Model;

use CopyrightChecker\Model\Comment\CommentWrapperRegistry;
use CopyrightChecker\Model\Preamble\PreambleStripperRegistry;

final class CopyrightChecker
{
    public function __construct(
        private readonly PreambleStripperRegistry $strippers,
        private readonly CommentWrapperRegistry $wrappers = new CommentWrapperRegistry(),
    ) {
    }

    /**
     * @param CopyrightTemplate[] $templates
     */
    public function check(string $content, string $extension, array $templates): CopyrightCheckResult
    {
        $remainder = $this->stripToRemainder($content, $extension);

        foreach ($templates as $index => $template) {
            $effective = $this->wrappers->wrap($template, $extension);

            if (str_starts_with($remainder, $effective->getText())) {
                return new CopyrightCheckResult(true, $index);
            }
        }

        return new CopyrightCheckResult(false);
    }

    public function stripToRemainder(string $content, string $extension): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $content);
        $offset = $this->strippers->forExtension($extension)->getPreambleLength($normalized);
        $rest = substr($normalized, $offset);

        return (string) preg_replace('/\A(?:[ \t]*\n)*/', '', $rest);
    }
}
