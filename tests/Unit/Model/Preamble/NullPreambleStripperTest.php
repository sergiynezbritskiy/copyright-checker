<?php

declare(strict_types=1);

namespace CopyrightChecker\Tests\Unit\Model\Preamble;

use CopyrightChecker\Model\Preamble\NullPreambleStripper;
use PHPUnit\Framework\TestCase;

final class NullPreambleStripperTest extends TestCase
{
    public function testAlwaysReturnsZero(): void
    {
        $stripper = new NullPreambleStripper();

        self::assertSame(0, $stripper->getPreambleLength(''));
        self::assertSame(0, $stripper->getPreambleLength("<?php\n/* Copyright */"));
        self::assertSame(0, $stripper->getPreambleLength('/* Copyright */'));
    }
}
