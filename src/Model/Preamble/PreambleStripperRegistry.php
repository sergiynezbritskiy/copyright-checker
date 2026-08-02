<?php

declare(strict_types=1);

namespace CopyrightChecker\Model\Preamble;

final class PreambleStripperRegistry
{
    /** @var array<string, PreambleStripperInterface> */
    private readonly array $strippers;

    private readonly PreambleStripperInterface $default;

    public function __construct()
    {
        $shebang = new ShebangPreambleStripper();

        $this->strippers = [
            'php' => new PhpPreambleStripper($shebang),
            'xml' => new XmlPreambleStripper(),
            'js' => $shebang,
            'mjs' => $shebang,
        ];

        $this->default = new NullPreambleStripper();
    }

    public function forExtension(string $extension): PreambleStripperInterface
    {
        return $this->strippers[strtolower($extension)] ?? $this->default;
    }
}
