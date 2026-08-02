<?php

declare(strict_types=1);

namespace CopyrightChecker\Report;

final class ScanResult
{
    public const STATUS_OK = 'OK';
    public const STATUS_VIOLATION = 'VIOLATION';
    public const STATUS_FIXED = 'FIXED';
    public const STATUS_FIX_FAILED = 'FIX_FAILED';

    public function __construct(
        public readonly string $path,
        public readonly string $status,
        public readonly ?int $matchedTemplateIndex = null,
        public readonly ?string $error = null,
    ) {
    }
}
