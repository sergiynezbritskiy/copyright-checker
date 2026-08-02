<?php

declare(strict_types=1);

namespace CopyrightChecker\Tests\Unit\Model\Comment;

use CopyrightChecker\Model\Comment\CommentWrapperRegistry;
use CopyrightChecker\Model\CopyrightTemplate;
use PHPUnit\Framework\TestCase;

final class CommentWrapperRegistryTest extends TestCase
{
    private CommentWrapperRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new CommentWrapperRegistry();
    }

    public function testWrapsForXmlExtension(): void
    {
        $template = new CopyrightTemplate("/**\n * Copyright\n */");

        $wrapped = $this->registry->wrap($template, 'xml');

        self::assertSame("<!--\n/**\n * Copyright\n */\n-->", $wrapped->getText());
    }

    public function testWrapsForHtmlAndHtmExtensionsCaseInsensitively(): void
    {
        $template = new CopyrightTemplate('// Copyright');

        self::assertSame("<!--\n// Copyright\n-->", $this->registry->wrap($template, 'html')->getText());
        self::assertSame("<!--\n// Copyright\n-->", $this->registry->wrap($template, 'HTM')->getText());
    }

    public function testDoesNotWrapForPhpOrUnknownExtensions(): void
    {
        $template = new CopyrightTemplate('/* Copyright */');

        self::assertSame($template, $this->registry->wrap($template, 'php'));
        self::assertSame($template, $this->registry->wrap($template, 'css'));
    }

    public function testWrappedFirstAndLastLinesAreTheCommentDelimiters(): void
    {
        $template = new CopyrightTemplate("/**\n * Copyright\n */");

        $wrapped = $this->registry->wrap($template, 'xml');

        self::assertSame('<!--', $wrapped->getFirstLine());
        self::assertSame('-->', $wrapped->getLastLine());
    }
}
