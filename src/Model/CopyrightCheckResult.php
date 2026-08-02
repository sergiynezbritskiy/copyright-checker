<?php

declare(strict_types=1);

namespace CopyrightChecker\Model;

final class CopyrightCheckResult
{
    public function __construct(
        public readonly bool $matched,
        public readonly ?int $matchedTemplateIndex = null,
    ) {
    }
}
