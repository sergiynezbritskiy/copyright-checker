<?php

declare(strict_types=1);

namespace CopyrightChecker\Tests\Unit\Model\Comment;

use CopyrightChecker\Model\Comment\NullCommentWrapper;
use PHPUnit\Framework\TestCase;

final class NullCommentWrapperTest extends TestCase
{
    public function testReturnsTextUnchanged(): void
    {
        $wrapper = new NullCommentWrapper();

        self::assertSame('/* Copyright */', $wrapper->wrap('/* Copyright */'));
    }
}
