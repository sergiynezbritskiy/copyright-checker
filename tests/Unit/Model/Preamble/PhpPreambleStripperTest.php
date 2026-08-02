<?php

declare(strict_types=1);

namespace CopyrightChecker\Tests\Unit\Model\Preamble;

use CopyrightChecker\Model\Preamble\PhpPreambleStripper;
use PHPUnit\Framework\TestCase;

final class PhpPreambleStripperTest extends TestCase
{
    private PhpPreambleStripper $stripper;

    protected function setUp(): void
    {
        $this->stripper = new PhpPreambleStripper();
    }

    public function testOffsetAfterPhpOpenTag(): void
    {
        $content = "<?php\n/* Copyright */";

        self::assertSame(strlen('<?php'), $this->stripper->getPreambleLength($content));
    }

    public function testOffsetAfterShortEchoTag(): void
    {
        $content = "<?= 'hello';";

        self::assertSame(strlen('<?='), $this->stripper->getPreambleLength($content));
    }

    public function testOffsetAfterShebangThenPhpTag(): void
    {
        $content = "#!/usr/bin/env php\n<?php\n/* Copyright */";

        self::assertSame(strlen("#!/usr/bin/env php\n<?php"), $this->stripper->getPreambleLength($content));
    }

    public function testZeroWhenNoPhpTagFound(): void
    {
        $content = 'just some text, no php tag here';

        self::assertSame(0, $this->stripper->getPreambleLength($content));
    }

    public function testZeroWhenOnlyShebangButNoPhpTag(): void
    {
        $content = "#!/usr/bin/env php\nno tag here";

        self::assertSame(0, $this->stripper->getPreambleLength($content));
    }
}
