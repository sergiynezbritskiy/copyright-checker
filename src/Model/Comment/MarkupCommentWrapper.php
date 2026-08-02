<?php

declare(strict_types=1);

namespace CopyrightChecker\Model\Comment;

final class MarkupCommentWrapper implements CommentWrapperInterface
{
    public function wrap(string $templateText): string
    {
        return "<!--\n" . $templateText . "\n-->";
    }
}
