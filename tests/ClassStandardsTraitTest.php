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

    /**
     * @dataProvider checkIndirectVariableDataProvider
     */
    public function testCheckIndirectVariable(bool $shouldFail, string $code)
    {
        $tokens = token_get_all($code);
        $path = uniqid('./file_');

        if ($shouldFail) {
            $this->expectException(AssertionFailedError::class);
        }

        $this->assertNull($this->checkIndirectVariable($tokens, $path), $code);
    }

    public function checkIndirectVariableDataProvider()
    {
        return array(
            array(false,    '<?php echo $var;'),
            array(false,    '<?php echo $ var;'),
            array(false,    '<?php echo ${$var};'),

            array(true,     '<?php echo $$var;'),
        );
    }

    /**
     * @dataProvider checkClassKeywordUsageDataProvider
     */
    public function testCheckClassKeywordUsage(bool $shouldFail, string $code)
    {
        $tokens = token_get_all($code);
        $path = uniqid('./file_');

        if ($shouldFail) {
            $this->expectException(AssertionFailedError::class);
        }

        $this->assertNull($this->checkClassKeywordUsage($tokens, $path), $code);
    }

    public function checkClassKeywordUsageDataProvider()
    {
        return array(
            array(false,    '<?php echo "NonExistentClass";'),
            array(false,    "<?php echo 'NonExistentClass';"),
            array(false,    '<?php echo "";'),
            array(false,    '<?php echo "directory";'),
            array(false,    '<?php echo "datetime";'),
            array(false,    '<?php namespace MyNamespace; echo "MyNamespace\NonExistentClass";'),

            array(true,     '<?php echo "stdClass";'),
            array(true,     "<?php echo 'stdClass';"),
            array(true,     sprintf('<?php echo "%s";', __CLASS__)),
            array(true,     sprintf('<?php echo "%s";', mb_strtolower(__CLASS__))),
            array(true,     '<?php echo "Directory";'),
            array(true,     '<?php echo "DateTime";'),
            array(true,     '<?php namespace MyNamespace; echo "DateTime";'),
        );
    }
}
