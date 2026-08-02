<?php

declare(strict_types=1);

namespace CopyrightChecker\Tests\Unit\Model\Preamble;

use CopyrightChecker\Model\Preamble\XmlPreambleStripper;
use PHPUnit\Framework\TestCase;

final class XmlPreambleStripperTest extends TestCase
{
    private XmlPreambleStripper $stripper;

    protected function setUp(): void
    {
        $this->stripper = new XmlPreambleStripper();
    }

    public function testOffsetAfterXmlDeclaration(): void
    {
        $decl = '<?xml version="1.0" encoding="UTF-8"?>';
        $content = $decl . "\n<!-- Copyright -->";

        self::assertSame(strlen($decl), $this->stripper->getPreambleLength($content));
    }

    public function testOffsetAfterXmlDeclarationWithBom(): void
    {
        $bom = "\xEF\xBB\xBF";
        $decl = '<?xml version="1.0"?>';
        $content = $bom . $decl . "\n<!-- Copyright -->";

        self::assertSame(strlen($bom . $decl), $this->stripper->getPreambleLength($content));
    }

    public function testOffsetForBomOnlyWithoutDeclaration(): void
    {
        $bom = "\xEF\xBB\xBF";
        $content = $bom . '<!-- Copyright -->';

        self::assertSame(strlen($bom), $this->stripper->getPreambleLength($content));
    }

    public function testZeroWhenNoBomOrDeclaration(): void
    {
        $content = '<!-- Copyright -->';

        self::assertSame(0, $this->stripper->getPreambleLength($content));
    }
}
