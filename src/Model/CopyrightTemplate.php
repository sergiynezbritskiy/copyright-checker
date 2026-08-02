<?php

declare(strict_types=1);

namespace CopyrightChecker\Model;

use InvalidArgumentException;

final class CopyrightTemplate
{
    private readonly string $text;

    /** @var string[] */
    private readonly array $lines;

    public function __construct(string $rawTemplate)
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $rawTemplate);

        if (trim($normalized) === '') {
            throw new InvalidArgumentException('Copyright template must not be blank.');
        }

        $this->text = trim($normalized, "\n");
        $this->lines = explode("\n", $this->text);
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getFirstLine(): string
    {
        return $this->lines[0];
    }

    public function getLastLine(): string
    {
        return $this->lines[count($this->lines) - 1];
    }

    public function getLineCount(): int
    {
        return count($this->lines);
    }

    public function getPreview(int $maxLength = 60): string
    {
        $collapsed = trim((string) preg_replace('/\s+/', ' ', $this->text));

        if (mb_strlen($collapsed) <= $maxLength) {
            return $collapsed;
        }

        return mb_substr($collapsed, 0, max(0, $maxLength - 1)) . '…';
    }
}
