<?php

declare(strict_types=1);

namespace CopyrightChecker\Tests\Unit\Model\Preamble;

use CopyrightChecker\Model\Preamble\NullPreambleStripper;
use CopyrightChecker\Model\Preamble\PhpPreambleStripper;
use CopyrightChecker\Model\Preamble\PreambleStripperRegistry;
use CopyrightChecker\Model\Preamble\ShebangPreambleStripper;
use CopyrightChecker\Model\Preamble\XmlPreambleStripper;
use PHPUnit\Framework\TestCase;

final class PreambleStripperRegistryTest extends TestCase
{
    private PreambleStripperRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new PreambleStripperRegistry();
    }

    public function testReturnsPhpStripperForPhpExtension(): void
    {
        self::assertInstanceOf(PhpPreambleStripper::class, $this->registry->forExtension('php'));
    }

    public function testReturnsXmlStripperForXmlExtension(): void
    {
        self::assertInstanceOf(XmlPreambleStripper::class, $this->registry->forExtension('xml'));
    }

    public function testReturnsShebangStripperForJsAndMjsExtensions(): void
    {
        self::assertInstanceOf(ShebangPreambleStripper::class, $this->registry->forExtension('js'));
        self::assertInstanceOf(ShebangPreambleStripper::class, $this->registry->forExtension('mjs'));
    }

    public function testLookupIsCaseInsensitive(): void
    {
        self::assertInstanceOf(PhpPreambleStripper::class, $this->registry->forExtension('PHP'));
    }

    public function testFallsBackToNullStripperForUnknownExtension(): void
    {
        self::assertInstanceOf(NullPreambleStripper::class, $this->registry->forExtension('css'));
        self::assertInstanceOf(NullPreambleStripper::class, $this->registry->forExtension('unknown'));
    }
}
