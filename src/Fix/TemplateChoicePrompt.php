<?php

declare(strict_types=1);

namespace CopyrightChecker\Fix;

use CopyrightChecker\Model\CopyrightTemplate;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class TemplateChoicePrompt
{
    /**
     * @param CopyrightTemplate[] $templates
     */
    public function resolve(
        array $templates,
        ?int $fixApply,
        InputInterface $input,
        SymfonyStyle $io,
    ): CopyrightTemplate {
        $values = array_values($templates);

        if (count($values) === 1) {
            return $values[0];
        }

        if ($fixApply !== null) {
            return $this->byNumber($values, $fixApply);
        }

        if (!$input->isInteractive()) {
            throw new NonInteractiveAmbiguousFixException(
                'Multiple --template values were provided; pass --fix-apply=<number> to choose one non-interactively.',
            );
        }

        $choices = [];
        foreach ($values as $index => $template) {
            $choices[$index + 1] = $template->getPreview();
        }

        $selectedLabel = $io->choice('Which copyright template should be applied to fix violations?', $choices);
        $selectedNumber = (int) array_search($selectedLabel, $choices, true);

        return $this->byNumber($values, $selectedNumber);
    }

    /**
     * @param CopyrightTemplate[] $values
     */
    private function byNumber(array $values, int $number): CopyrightTemplate
    {
        $index = $number - 1;

        if (!isset($values[$index])) {
            throw new NonInteractiveAmbiguousFixException(sprintf(
                'Template number %d is out of range (1-%d).',
                $number,
                count($values),
            ));
        }

        return $values[$index];
    }
}
