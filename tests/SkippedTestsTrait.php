<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests;

use Knp\DoctrineBehaviors\DoctrineBehaviorsBundle;
use PHPUnit\Framework\Assert;

/**
 * @mixin Assert
 */
trait SkippedTestsTrait
{
    public function markKnpLabsTestAsSkipped(): void
    {
        if (!$this->isKnpLabsDoctrineBehaviorsInstalled()) {
            $this->markTestSkipped('KnpLabs Translatable module is not installed.');
        }
    }

    public function isKnpLabsDoctrineBehaviorsInstalled(): bool
    {
        return \class_exists(DoctrineBehaviorsBundle::class);
    }
}
