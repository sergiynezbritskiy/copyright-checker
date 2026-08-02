<?php

declare(strict_types=1);

namespace CopyrightChecker\Model\Comment;

use CopyrightChecker\Model\CopyrightTemplate;

final class CommentWrapperRegistry
{
    /** @var array<string, CommentWrapperInterface> */
    private readonly array $wrappers;

    private readonly CommentWrapperInterface $default;

    public function __construct()
    {
        $markup = new MarkupCommentWrapper();

        $this->wrappers = [
            'xml' => $markup,
            'html' => $markup,
            'htm' => $markup,
        ];

        $this->default = new NullCommentWrapper();
    }

    /**
     * Returns the template as it should actually appear in a file of the given extension,
     * wrapping its raw text in the appropriate comment delimiters when required.
     */
    public function wrap(CopyrightTemplate $template, string $extension): CopyrightTemplate
    {
        $wrapper = $this->wrappers[strtolower($extension)] ?? $this->default;
        $wrapped = $wrapper->wrap($template->getText());

        return $wrapped === $template->getText() ? $template : new CopyrightTemplate($wrapped);
    }
}
