<?php

declare(strict_types=1);

namespace CopyrightChecker\Tests\Unit\Model\Preamble;

use CopyrightChecker\Model\Preamble\ShebangPreambleStripper;
use PHPUnit\Framework\TestCase;

final class ShebangPreambleStripperTest extends TestCase
{
    private ShebangPreambleStripper $stripper;

    protected function setUp(): void
    {
        $this->stripper = new ShebangPreambleStripper();
    }

    public function testOffsetAfterShebangLine(): void
    {
        $content = "#!/usr/bin/env node\n// Copyright";

        self::assertSame(strlen("#!/usr/bin/env node\n"), $this->stripper->getPreambleLength($content));
    }

    public function testZeroWhenNoShebang(): void
    {
        $content = "// Copyright\nconsole.log('hi');";

        self::assertSame(0, $this->stripper->getPreambleLength($content));
    }

    public function testWholeContentWhenShebangHasNoTrailingNewline(): void
    {
        $content = '#!/usr/bin/env node';

        self::assertSame(strlen($content), $this->stripper->getPreambleLength($content));
    }
}
