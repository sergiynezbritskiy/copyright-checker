<?php

declare(strict_types=1);

namespace CopyrightChecker\Tests\Unit\Model;

use CopyrightChecker\Model\CopyrightTemplate;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CopyrightTemplateTest extends TestCase
{
    public function testNormalizesCrlfToLf(): void
    {
        $template = new CopyrightTemplate("/*\r\n * Copyright\r\n */");

        self::assertSame("/*\n * Copyright\n */", $template->getText());
    }

    public function testNormalizesLoneCrToLf(): void
    {
        $template = new CopyrightTemplate("/*\r * Copyright\r */");

        self::assertSame("/*\n * Copyright\n */", $template->getText());
    }

    public function testTrimsLeadingAndTrailingBlankLinesOnly(): void
    {
        $template = new CopyrightTemplate("\n\n  /* Copyright */  \n\n");

        self::assertSame('  /* Copyright */  ', $template->getText());
    }

    public function testThrowsOnWhitespaceOnlyTemplate(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CopyrightTemplate("   \n  \n");
    }

    public function testThrowsOnEmptyTemplate(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CopyrightTemplate('');
    }

    public function testFirstAndLastLineForMultilineTemplate(): void
    {
        $template = new CopyrightTemplate("/*\n * Copyright (c) 2026\n */");

        self::assertSame('/*', $template->getFirstLine());
        self::assertSame(' */', $template->getLastLine());
        self::assertSame(3, $template->getLineCount());
    }

    public function testFirstAndLastLineAreTheSameForSingleLineTemplate(): void
    {
        $template = new CopyrightTemplate('// Copyright (c) 2026');

        self::assertSame('// Copyright (c) 2026', $template->getFirstLine());
        self::assertSame('// Copyright (c) 2026', $template->getLastLine());
        self::assertSame(1, $template->getLineCount());
    }

    public function testPreservesInternalIndentationExactly(): void
    {
        $template = new CopyrightTemplate("/*\n    indented line\n */");

        self::assertSame("/*\n    indented line\n */", $template->getText());
    }

    public function testPreviewCollapsesWhitespaceAndTruncates(): void
    {
        $template = new CopyrightTemplate(
            "/*\n * Copyright (c) 2026 Some Very Long Company Name Incorporated\n */",
        );

        $preview = $template->getPreview(20);

        self::assertSame(20, mb_strlen($preview));
        self::assertStringEndsWith('…', $preview);
    }

    public function testPreviewReturnsFullTextWhenShort(): void
    {
        $template = new CopyrightTemplate('// short');

        self::assertSame('// short', $template->getPreview(60));
    }
}
