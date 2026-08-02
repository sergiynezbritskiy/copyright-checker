<?php

declare(strict_types=1);

namespace CopyrightChecker\Model\Preamble;

interface PreambleStripperInterface
{
    /**
     * @param string $normalizedContent File content with line endings already normalized to "\n".
     * @return int Byte offset right after the preamble (0 if there is none).
     */
    public function getPreambleLength(string $normalizedContent): int;
}
