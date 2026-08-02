<?php

declare(strict_types=1);

namespace CopyrightChecker\Model\Preamble;

final class PhpPreambleStripper implements PreambleStripperInterface
{
    public function __construct(
        private readonly ShebangPreambleStripper $shebangStripper = new ShebangPreambleStripper(),
    ) {
    }

    public function getPreambleLength(string $normalizedContent): int
    {
        $shebangOffset = $this->shebangStripper->getPreambleLength($normalizedContent);
        $rest = substr($normalizedContent, $shebangOffset);

        if (preg_match('/<\?php\b|<\?=/i', $rest, $matches, PREG_OFFSET_CAPTURE)) {
            [$tag, $position] = $matches[0];

            return $shebangOffset + $position + strlen($tag);
        }

        return 0;
    }
}
