<?php

declare(strict_types=1);

namespace CopyrightChecker\Console;

use CopyrightChecker\Config\Config;
use CopyrightChecker\Config\ConfigFileLoader;
use CopyrightChecker\Config\ConfigResolver;
use CopyrightChecker\Fix\NonInteractiveAmbiguousFixException;
use CopyrightChecker\Fix\TemplateChoicePrompt;
use CopyrightChecker\Model\CopyrightChecker as CopyrightCheckerModel;
use CopyrightChecker\Model\CopyrightFixer;
use CopyrightChecker\Model\CopyrightTemplate;
use CopyrightChecker\Model\Preamble\PreambleStripperRegistry;
use CopyrightChecker\Report\ConsoleReporter;
use CopyrightChecker\Report\ScanResult;
use CopyrightChecker\Scan\FileScanner;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects") Orchestrates the whole check/fix pipeline by design.
 */
final class CheckCommand extends Command
{
    public function __construct(
        private readonly bool $standalone,
        private readonly ?string $defaultRootDir = null,
    ) {
        parent::__construct('check');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Checks (and optionally fixes) copyright header blocks in source files.')
            ->addArgument(
                'paths',
                InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
                'File(s)/directory(ies) to scan. Falls back to "locations" in .copyright.dist when omitted.',
                [],
            )
            ->addOption(
                'exclude',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Path(s) to exclude.',
                [],
            )
            ->addOption('colors', null, InputOption::VALUE_NEGATABLE, 'Use colors in screen output.', false)
            ->addOption(
                'extension',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Extension(s) to check.',
                [],
            )
            ->addOption(
                'template',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Literal copyright template(s) to look for.',
                [],
            )
            ->addOption('fix', null, InputOption::VALUE_NONE, 'Fix files with incorrect copyright blocks.')
            ->addOption(
                'fix-apply',
                null,
                InputOption::VALUE_REQUIRED,
                '1-based index into the merged --template list to apply when fixing.',
            )
            ->addOption(
                'root-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Project root directory (location of .copyright.dist and base for relative paths).',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $rootDir = $this->resolveRootDir($input);

        if ($rootDir === null) {
            $io->error(
                '--root-dir is required when running copyright-checker standalone (not installed via Composer).',
            );

            return Command::INVALID;
        }

        try {
            $fileConfig = (new ConfigFileLoader())->load($rootDir);
        } catch (RuntimeException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        }

        $config = (new ConfigResolver())->resolve($input, $fileConfig);
        $paths = $this->resolvePaths($input, $config);
        $validationError = $this->validateConfig($config, $paths);

        if ($validationError !== null) {
            $io->error($validationError);

            return Command::INVALID;
        }

        try {
            $templates = $this->buildTemplates($config->template);
        } catch (InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        }

        $output->setDecorated($config->colors);
        $strippers = new PreambleStripperRegistry();

        try {
            $files = (new FileScanner())->scan($paths, $config->extension, $config->exclude, $rootDir);
        } catch (RuntimeException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        }

        $results = $this->checkFiles($files, $templates, $strippers);

        if ($config->fix && $this->hasViolations($results)) {
            try {
                $results = $this->fixViolations($results, $templates, $config->fixApply, $input, $io, $strippers);
            } catch (NonInteractiveAmbiguousFixException $e) {
                $io->error($e->getMessage());

                return Command::INVALID;
            }
        }

        (new ConsoleReporter())->render($output, $results);

        return $this->determineExitCode($results);
    }

    private function resolveRootDir(InputInterface $input): ?string
    {
        $rootDirOption = $input->getOption('root-dir');

        if ($rootDirOption !== null) {
            return rtrim((string) $rootDirOption, '/');
        }

        if ($this->standalone || $this->defaultRootDir === null) {
            return null;
        }

        return rtrim($this->defaultRootDir, '/');
    }

    /**
     * @return string[]
     */
    private function resolvePaths(InputInterface $input, Config $config): array
    {
        /** @var string[] $paths */
        $paths = $input->getArgument('paths');

        return $paths !== [] ? $paths : $config->locations;
    }

    /**
     * @param string[] $paths
     */
    private function validateConfig(Config $config, array $paths): ?string
    {
        if ($paths === []) {
            return 'No file(s)/directory(ies) specified. Pass them as arguments or set "locations" in .copyright.dist.';
        }

        if ($config->extension === []) {
            return 'No file extensions configured. Pass --extension or set "extension" in .copyright.dist.';
        }

        if ($config->template === []) {
            return 'No copyright templates configured. Pass --template or set "template" in .copyright.dist.';
        }

        return null;
    }

    /**
     * @param string[] $rawTemplates
     * @return CopyrightTemplate[]
     */
    private function buildTemplates(array $rawTemplates): array
    {
        return array_map(
            static fn (string $template): CopyrightTemplate => new CopyrightTemplate($template),
            $rawTemplates,
        );
    }

    /**
     * @param string[] $files
     * @param CopyrightTemplate[] $templates
     * @return ScanResult[]
     */
    private function checkFiles(array $files, array $templates, PreambleStripperRegistry $strippers): array
    {
        $checker = new CopyrightCheckerModel($strippers);
        $results = [];

        foreach ($files as $file) {
            $extension = pathinfo($file, PATHINFO_EXTENSION);
            $content = file_get_contents($file);

            if ($content === false) {
                $results[] = new ScanResult($file, ScanResult::STATUS_VIOLATION, null, 'Unable to read file.');

                continue;
            }

            $checkResult = $checker->check($content, $extension, $templates);

            $results[] = $checkResult->matched
                ? new ScanResult($file, ScanResult::STATUS_OK, $checkResult->matchedTemplateIndex)
                : new ScanResult($file, ScanResult::STATUS_VIOLATION);
        }

        return $results;
    }

    /**
     * @param ScanResult[] $results
     */
    private function hasViolations(array $results): bool
    {
        foreach ($results as $result) {
            if ($result->status === ScanResult::STATUS_VIOLATION) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param ScanResult[] $results
     * @param CopyrightTemplate[] $templates
     * @return ScanResult[]
     */
    private function fixViolations(
        array $results,
        array $templates,
        ?int $fixApply,
        InputInterface $input,
        SymfonyStyle $io,
        PreambleStripperRegistry $strippers,
    ): array {
        $chosenTemplate = (new TemplateChoicePrompt())->resolve($templates, $fixApply, $input, $io);
        $fixer = new CopyrightFixer($strippers);

        foreach ($results as $index => $result) {
            if ($result->status !== ScanResult::STATUS_VIOLATION) {
                continue;
            }

            $results[$index] = $this->applyFix($fixer, $result, $chosenTemplate);
        }

        return $results;
    }

    private function applyFix(CopyrightFixer $fixer, ScanResult $result, CopyrightTemplate $template): ScanResult
    {
        $content = file_get_contents($result->path);

        if ($content === false) {
            return new ScanResult($result->path, ScanResult::STATUS_FIX_FAILED, null, 'Unable to read file.');
        }

        $extension = pathinfo($result->path, PATHINFO_EXTENSION);
        $fixed = $fixer->fix($content, $extension, $template);
        $written = file_put_contents($result->path, $fixed);

        if ($written === false) {
            return new ScanResult($result->path, ScanResult::STATUS_FIX_FAILED, null, 'Unable to write file.');
        }

        return new ScanResult($result->path, ScanResult::STATUS_FIXED);
    }

    /**
     * @param ScanResult[] $results
     */
    private function determineExitCode(array $results): int
    {
        foreach ($results as $result) {
            if (in_array($result->status, [ScanResult::STATUS_VIOLATION, ScanResult::STATUS_FIX_FAILED], true)) {
                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }
}
