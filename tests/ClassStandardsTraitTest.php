<?php

declare(strict_types=1);

namespace Slam\PHPUnit\Tests;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use Slam\PHPUnit\ClassStandardsTrait;

final class ClassStandardsTraitTest extends TestCase
{
    use ClassStandardsTrait;

    /**
     * @dataProvider checkClassExistanceDataProvider
     */
    public function testCheckClassExistance(bool $shouldFail, string $code)
    {
        $tokens = token_get_all($code);
        $path = uniqid('./file_');

        if ($shouldFail) {
            $this->expectException(AssertionFailedError::class);
        }

        $this->assertNull($this->checkClassExistance($tokens, $path), $code);
    }

    public function checkClassExistanceDataProvider()
    {
        return array(
            array(false,    '<?php echo stdClass::class;'),
            array(false,    sprintf('<?php echo namespace %s; class MyClass { public function bar() { echo %s::class; } }', __NAMESPACE__, mb_substr(__CLASS__, mb_strlen(__NAMESPACE__) + 1))),
            array(false,    sprintf('<?php echo namespace %s; class MyClass { public function bar() { echo self::class; } }', __NAMESPACE__)),
            array(false,    sprintf('<?php echo namespace %s; use stdClass; class MyClass { public function bar() { echo stdClass::class; } }', __NAMESPACE__)),
            array(false,    sprintf('<?php echo namespace %s; use stdClass as MyAlias; class MyClass { public function bar() { echo MyAlias::class; } }', __NAMESPACE__)),
            array(false,    '<?php echo stdClass::MY_CONSTANT;'),

            array(true,     '<?php echo nonStdClass::class;'),
            array(true,     '<?php echo nonStdClass::   class;'),
            array(true,     sprintf('<?php echo namespace %s; class MyClass { public function bar() { echo stdClass::class; } }', __NAMESPACE__)),
        );
    }
}
