<?php

declare(strict_types=1);

namespace CopyrightChecker\Config;

final class Config
{
    /**
     * @param string[] $exclude
     * @param string[] $extension
     * @param string[] $template
     * @param string[] $locations
     */
    public function __construct(
        public readonly array $exclude,
        public readonly array $extension,
        public readonly array $template,
        public readonly bool $colors,
        public readonly bool $fix,
        public readonly ?int $fixApply,
        public readonly array $locations,
    ) {
    }
}
