<?php

declare(strict_types=1);

namespace CopyrightChecker\Model\Preamble;

final class ShebangPreambleStripper implements PreambleStripperInterface
{
    public function getPreambleLength(string $normalizedContent): int
    {
        if (!str_starts_with($normalizedContent, '#!')) {
            return 0;
        }

        $newlinePos = strpos($normalizedContent, "\n");

        return $newlinePos === false ? strlen($normalizedContent) : $newlinePos + 1;
    }
}
