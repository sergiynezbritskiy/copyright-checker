<?php

declare(strict_types=1);

namespace CopyrightChecker\Model\Comment;

interface CommentWrapperInterface
{
    /**
     * Wraps a copyright template's raw text in the comment delimiters appropriate for a
     * given file type, so the same template text can be authored once and reused for file
     * types that need different comment syntax around it.
     */
    public function wrap(string $templateText): string;
}
