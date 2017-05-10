<?php

declare(strict_types=1);

namespace Slam\PHPUnit\Tests;

use PHPUnit\Framework\TestCase;
use Slam\PHPUnit\ClassStandardsTrait;

final class LowercaseCapitalClassStandardsTraitTest extends TestCase
{
    use ClassStandardsTrait;

    public function testDoTestClassStandards()
    {
        $this->assertNull($this->doTestClassStandards(__DIR__ . '/TestAsset/LowercaseCapital', 'Slam\\PHPUnit\\Tests\\TestAsset\\LowercaseCapital\\'));
    }

    private function shouldClassesHaveUppercaseCapital(): bool
    {
        return false;
    }
}
