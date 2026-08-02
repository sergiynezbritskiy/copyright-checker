<?php

declare(strict_types=1);

namespace CopyrightChecker\Tests\Unit\Model;

use CopyrightChecker\Model\CopyrightChecker;
use CopyrightChecker\Model\CopyrightTemplate;
use CopyrightChecker\Model\Preamble\PreambleStripperRegistry;
use PHPUnit\Framework\TestCase;

final class CopyrightCheckerTest extends TestCase
{
    private CopyrightChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new CopyrightChecker(new PreambleStripperRegistry());
    }

    public function testMatchesExactTemplateAfterPhpTag(): void
    {
        $template = new CopyrightTemplate("/*\n * Copyright (c) 2026\n */");
        $content = "<?php\n\n/*\n * Copyright (c) 2026\n */\n\nclass Foo {}\n";

        $result = $this->checker->check($content, 'php', [$template]);

        self::assertTrue($result->matched);
        self::assertSame(0, $result->matchedTemplateIndex);
    }

    public function testRejectsSingleCharacterDifference(): void
    {
        $template = new CopyrightTemplate("/*\n * Copyright (c) 2026\n */");
        $content = "<?php\n/*\n * Copyright (c) 2027\n */\nclass Foo {}\n";

        $result = $this->checker->check($content, 'php', [$template]);

        self::assertFalse($result->matched);
        self::assertNull($result->matchedTemplateIndex);
    }

    public function testMatchesCorrectIndexAmongMultipleTemplates(): void
    {
        $templateA = new CopyrightTemplate('// Template A');
        $templateB = new CopyrightTemplate('// Template B');
        $content = "<?php\n// Template B\nclass Foo {}\n";

        $result = $this->checker->check($content, 'php', [$templateA, $templateB]);

        self::assertTrue($result->matched);
        self::assertSame(1, $result->matchedTemplateIndex);
    }

    public function testSkipsLeadingBlankLinesBeforeTemplate(): void
    {
        $template = new CopyrightTemplate('// Copyright');
        $content = "<?php\n\n\n// Copyright\nclass Foo {}\n";

        $result = $this->checker->check($content, 'php', [$template]);

        self::assertTrue($result->matched);
    }

    public function testCrlfFileContentStillMatchesLfAuthoredTemplate(): void
    {
        $template = new CopyrightTemplate("/*\n * Copyright\n */");
        $content = "<?php\r\n/*\r\n * Copyright\r\n */\r\nclass Foo {}\r\n";

        $result = $this->checker->check($content, 'php', [$template]);

        self::assertTrue($result->matched);
    }

    public function testEmptyTemplateListNeverMatchesAndDoesNotThrow(): void
    {
        $content = "<?php\n// anything\n";

        $result = $this->checker->check($content, 'php', []);

        self::assertFalse($result->matched);
        self::assertNull($result->matchedTemplateIndex);
    }

    public function testMatchesTemplateWrappedInHtmlCommentForXmlFiles(): void
    {
        $template = new CopyrightTemplate("/**\n * Copyright (c) 2026 Sergiy Nezbritskiy\n */");
        $content = '<?xml version="1.0"?>'
            . "\n<!--\n/**\n * Copyright (c) 2026 Sergiy Nezbritskiy\n */\n-->\n<root/>\n";

        $result = $this->checker->check($content, 'xml', [$template]);

        self::assertTrue($result->matched);
    }

    public function testDoesNotMatchUnwrappedTemplateInXmlFile(): void
    {
        $template = new CopyrightTemplate("/**\n * Copyright (c) 2026\n */");
        $content = '<?xml version="1.0"?>' . "\n/**\n * Copyright (c) 2026\n */\n<root/>\n";

        $result = $this->checker->check($content, 'xml', [$template]);

        self::assertFalse($result->matched);
    }

    public function testMatchesTemplateWrappedInHtmlCommentForHtmlFiles(): void
    {
        $template = new CopyrightTemplate('Copyright (c) 2026');
        $content = "<!--\nCopyright (c) 2026\n-->\n<html></html>\n";

        $result = $this->checker->check($content, 'html', [$template]);

        self::assertTrue($result->matched);
    }
}
