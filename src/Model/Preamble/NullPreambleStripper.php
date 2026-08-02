<?php

declare(strict_types=1);

namespace CopyrightChecker\Model\Preamble;

final class NullPreambleStripper implements PreambleStripperInterface
{
    public function getPreambleLength(string $normalizedContent): int
    {
        return 0;
    }
}
