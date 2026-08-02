<?php

declare(strict_types=1);

namespace CopyrightChecker\Model\Preamble;

final class XmlPreambleStripper implements PreambleStripperInterface
{
    private const BOM = "\xEF\xBB\xBF";

    public function getPreambleLength(string $normalizedContent): int
    {
        $offset = 0;

        if (str_starts_with($normalizedContent, self::BOM)) {
            $offset += strlen(self::BOM);
        }

        $rest = substr($normalizedContent, $offset);

        if (preg_match('/\A<\?xml\b.*?\?>/s', $rest, $matches)) {
            $offset += strlen($matches[0]);
        }

        return $offset;
    }
}
