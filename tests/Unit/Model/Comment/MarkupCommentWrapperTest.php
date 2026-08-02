<?php

declare(strict_types=1);

namespace CopyrightChecker\Tests\Unit\Model\Comment;

use CopyrightChecker\Model\Comment\MarkupCommentWrapper;
use PHPUnit\Framework\TestCase;

final class MarkupCommentWrapperTest extends TestCase
{
    public function testWrapsTextInHtmlCommentDelimiters(): void
    {
        $wrapper = new MarkupCommentWrapper();

        self::assertSame(
            "<!--\n/**\n * Copyright (c) 2026\n */\n-->",
            $wrapper->wrap("/**\n * Copyright (c) 2026\n */"),
        );
    }

    public function testWrapsSingleLineText(): void
    {
        $wrapper = new MarkupCommentWrapper();

        self::assertSame("<!--\n// Copyright\n-->", $wrapper->wrap('// Copyright'));
    }
}
