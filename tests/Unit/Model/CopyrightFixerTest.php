<?php

declare(strict_types=1);

namespace CopyrightChecker\Tests\Unit\Model;

use CopyrightChecker\Model\CopyrightFixer;
use CopyrightChecker\Model\CopyrightTemplate;
use CopyrightChecker\Model\Preamble\PreambleStripperRegistry;
use PHPUnit\Framework\TestCase;

final class CopyrightFixerTest extends TestCase
{
    private CopyrightFixer $fixer;

    protected function setUp(): void
    {
        $this->fixer = new CopyrightFixer(new PreambleStripperRegistry());
    }

    public function testPrependsTemplateWhenNoHeaderExists(): void
    {
        $template = new CopyrightTemplate("/*\n * Copyright (c) 2026\n */");
        $content = "<?php\n\nclass Foo {}\n";

        $fixed = $this->fixer->fix($content, 'php', $template);

        self::assertSame("<?php\n\n/*\n * Copyright (c) 2026\n */\n\nclass Foo {}\n", $fixed);
    }

    public function testAddsBlankLineBetweenPhpTagAndCopyrightBlock(): void
    {
        $template = new CopyrightTemplate(
            "/**\n * Copyright (c) 2026 Sergiy Nezbritskiy\n * Licensed under the MIT License.\n */",
        );
        $content = "<?php\n\nclass Foo {}\n";

        $fixed = $this->fixer->fix($content, 'php', $template);

        self::assertSame(
            "<?php\n\n/**\n * Copyright (c) 2026 Sergiy Nezbritskiy\n * Licensed under the MIT License."
                . "\n */\n\nclass Foo {}\n",
            $fixed,
        );
    }

    public function testFixingAlreadyCorrectContentIsNoOp(): void
    {
        $template = new CopyrightTemplate("/*\n * Copyright (c) 2026\n */");
        $content = "<?php\n\n/*\n * Copyright (c) 2026\n */\n\nclass Foo {}\n";

        $fixed = $this->fixer->fix($content, 'php', $template);

        self::assertSame($content, $fixed);
    }

    public function testFixingTwiceInARowIsByteIdenticalAfterFirstFix(): void
    {
        $template = new CopyrightTemplate("/*\n * Copyright (c) 2026\n */");
        $content = "<?php\n\nclass Foo {}\n";

        $firstFix = $this->fixer->fix($content, 'php', $template);
        $secondFix = $this->fixer->fix($firstFix, 'php', $template);

        self::assertSame($firstFix, $secondFix);
    }

    public function testReplacesOutdatedBlockDelimitedByMatchingFirstAndLastLines(): void
    {
        $template = new CopyrightTemplate("/*\n * Copyright (c) 2026\n */");
        $content = "<?php\n/*\n * Copyright (c) 2020\n * Old company name\n */\n\nclass Foo {}\n";

        $fixed = $this->fixer->fix($content, 'php', $template);

        self::assertSame("<?php\n\n/*\n * Copyright (c) 2026\n */\n\nclass Foo {}\n", $fixed);
    }

    public function testFallsBackToPrependWhenClosingLineNotFoundWithinWindow(): void
    {
        $fixer = new CopyrightFixer(new PreambleStripperRegistry(), searchWindowLines: 5);
        $template = new CopyrightTemplate("/*\n * Copyright (c) 2026\n */");

        // Old block opens with "/*" (matches the template's first line) but its closing "*/"
        // sits well past the small search window, so it must NOT be treated as a match.
        $lines = ['/*'];
        for ($i = 0; $i < 10; $i++) {
            $lines[] = ' * filler line ' . $i;
        }
        $lines[] = ' */';
        $body = implode("\n", $lines) . "\nclass Foo {}\n";
        $content = "<?php\n" . $body;

        $fixed = $fixer->fix($content, 'php', $template);

        self::assertSame("<?php\n\n/*\n * Copyright (c) 2026\n */\n\n" . $body, $fixed);
    }

    public function testWindowBoundIsEnforced(): void
    {
        $fixer = new CopyrightFixer(new PreambleStripperRegistry(), searchWindowLines: 3);
        $template = new CopyrightTemplate("/*\n * Copyright (c) 2026\n */");

        // The closing line sits exactly one line past the window edge (checked indices 0..2).
        $lines = ['/*', ' * line 1', ' * line 2', ' */'];
        $body = implode("\n", $lines) . "\ntail;\n";
        $content = "<?php\n" . $body;

        $fixed = $fixer->fix($content, 'php', $template);

        self::assertSame("<?php\n\n/*\n * Copyright (c) 2026\n */\n\n" . $body, $fixed);
    }

    public function testSingleLineTemplateFallsBackToPrependWhenOldLineDiffers(): void
    {
        // Known limitation: when a template's first and last line are the same line (a
        // single-line template), there's no separate stable delimiter to anchor a replace --
        // the whole line IS the variable content. An old, differently-worded single-line
        // header therefore can't be detected as "outdated" and replaced in place; the new
        // line is prepended above the untouched old one instead.
        $template = new CopyrightTemplate('// Copyright (c) 2026');
        $content = "<?php\n// Copyright (c) 2020\n\nclass Foo {}\n";

        $fixed = $this->fixer->fix($content, 'php', $template);

        self::assertSame("<?php\n\n// Copyright (c) 2026\n\n// Copyright (c) 2020\n\nclass Foo {}\n", $fixed);
    }

    public function testPreservesPhpPreambleVerbatim(): void
    {
        $template = new CopyrightTemplate('// Copyright');
        $content = "#!/usr/bin/env php\n<?php\n\nclass Foo {}\n";

        $fixed = $this->fixer->fix($content, 'php', $template);

        self::assertSame("#!/usr/bin/env php\n<?php\n\n// Copyright\n\nclass Foo {}\n", $fixed);
    }

    public function testPreservesXmlPreambleVerbatimAndWrapsBareTemplateInHtmlComment(): void
    {
        // Templates are authored without comment markers of their own for xml/html --
        // the fixer wraps them in "<!-- -->" automatically (see CommentWrapperRegistry).
        $template = new CopyrightTemplate('Copyright');
        $content = '<?xml version="1.0" encoding="UTF-8"?>' . "\n<root/>\n";

        $fixed = $this->fixer->fix($content, 'xml', $template);

        self::assertSame(
            '<?xml version="1.0" encoding="UTF-8"?>' . "\n<!--\nCopyright\n-->\n<root/>\n",
            $fixed,
        );
    }

    public function testWrapsMultilineTemplateInHtmlCommentForXmlFiles(): void
    {
        $template = new CopyrightTemplate(
            "/**\n * Copyright (c) 2026 Sergiy Nezbritskiy\n * Licensed under the MIT License.\n */",
        );
        $content = '<?xml version="1.0"?>' . "\n<root/>\n";

        $fixed = $this->fixer->fix($content, 'xml', $template);

        self::assertSame(
            '<?xml version="1.0"?>'
                . "\n<!--\n/**\n * Copyright (c) 2026 Sergiy Nezbritskiy\n * Licensed under the MIT License."
                . "\n */\n-->\n<root/>\n",
            $fixed,
        );
    }

    public function testNoBlankLineBetweenXmlCommentAndFollowingContent(): void
    {
        // Reproduces the reported issue: a Magento-style config.xml with no blank line
        // between the closing "-->" of the wrapped comment and the root element.
        $template = new CopyrightTemplate(
            "/**\n * Copyright (c) 2026 Sergiy Nezbritskiy\n * Licensed under the MIT License.\n */",
        );
        $configTag = '<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" '
            . 'xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/config.xsd">';
        $content = '<?xml version="1.0"?>' . "\n" . $configTag . "\n</config>\n";

        $fixed = $this->fixer->fix($content, 'xml', $template);

        $expected = '<?xml version="1.0"?>'
            . "\n<!--\n/**\n * Copyright (c) 2026 Sergiy Nezbritskiy\n * Licensed under the MIT License."
            . "\n */\n-->\n" . $configTag . "\n</config>\n";
        self::assertSame($expected, $fixed);
    }

    public function testReplacesOutdatedWrappedBlockInXmlFile(): void
    {
        $template = new CopyrightTemplate("/**\n * Copyright (c) 2026\n */");
        $content = '<?xml version="1.0"?>' . "\n<!--\n/**\n * Copyright (c) 2020\n */\n-->\n\n<root/>\n";

        $fixed = $this->fixer->fix($content, 'xml', $template);

        self::assertSame(
            '<?xml version="1.0"?>' . "\n<!--\n/**\n * Copyright (c) 2026\n */\n-->\n<root/>\n",
            $fixed,
        );
    }

    public function testFixingAlreadyWrappedXmlContentIsNoOp(): void
    {
        $template = new CopyrightTemplate("/**\n * Copyright (c) 2026\n */");
        $content = '<?xml version="1.0"?>' . "\n<!--\n/**\n * Copyright (c) 2026\n */\n-->\n<root/>\n";

        $fixed = $this->fixer->fix($content, 'xml', $template);

        self::assertSame($content, $fixed);
    }

    public function testWrapsTemplateInHtmlCommentForHtmlFiles(): void
    {
        $template = new CopyrightTemplate('Copyright (c) 2026');
        $content = "<html>\n<body></body>\n</html>\n";

        $fixed = $this->fixer->fix($content, 'html', $template);

        self::assertSame("<!--\nCopyright (c) 2026\n-->\n<html>\n<body></body>\n</html>\n", $fixed);
    }

    public function testPreservesShebangPreambleVerbatim(): void
    {
        $template = new CopyrightTemplate('// Copyright');
        $content = "#!/usr/bin/env node\nconsole.log('hi');\n";

        $fixed = $this->fixer->fix($content, 'js', $template);

        self::assertSame("#!/usr/bin/env node\n// Copyright\n\nconsole.log('hi');\n", $fixed);
    }

    public function testCollapsesMultipleBlankLinesAfterReplacedBlockToExactlyOne(): void
    {
        $template = new CopyrightTemplate("/*\n * New\n */");
        $content = "<?php\n/*\n * Old\n */\n\n\nclass Foo {}\n";

        $fixed = $this->fixer->fix($content, 'php', $template);

        self::assertSame("<?php\n\n/*\n * New\n */\n\nclass Foo {}\n", $fixed);
    }
}
