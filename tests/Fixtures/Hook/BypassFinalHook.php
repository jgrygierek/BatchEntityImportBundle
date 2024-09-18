<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Fixtures\Hook;

use DG\BypassFinals;
use PHPUnit\Runner\BeforeFirstTestHook;

final class BypassFinalHook implements BeforeFirstTestHook
{
    public function executeBeforeFirstTest(): void
    {
        BypassFinals::enable(bypassReadOnly: false);
    }
}
