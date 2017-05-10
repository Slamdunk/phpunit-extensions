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

    /**
     * @dataProvider doTestClassStandardsDataProvider
     */
    public function testDoTestClassStandards(bool $shouldFail, string $directory, string $namespace = null)
    {
        if ($shouldFail) {
            $this->expectException(AssertionFailedError::class);
        }

        $this->assertNull($this->doTestClassStandards($directory, $namespace));
    }

    public function doTestClassStandardsDataProvider()
    {
        return array(
            array(false,    __DIR__ . '/TestAsset/NonPhpFiles'),
            array(false,    __DIR__ . '/TestAsset/NoNamespace'),
            array(false,    __DIR__ . '/TestAsset/ValidPsr0',           'Slam_PHPUnit_Tests_TestAsset_ValidPsr0_'),
            array(false,    __DIR__ . '/TestAsset/ValidInterface',      'Slam\\PHPUnit\\Tests\\TestAsset\\ValidInterface\\'),
            array(false,    __DIR__ . '/TestAsset/ValidTrait',          'Slam\\PHPUnit\\Tests\\TestAsset\\ValidTrait\\'),

            array(true,     __DIR__ . '/TestAsset/NotAClass'),
            array(true,     __DIR__ . '/TestAsset/ValidInterface',      'UnfinishedNamespace'),
            array(true,     __DIR__ . '/TestAsset/LowercaseCapital',    'Slam\\PHPUnit\\Tests\\TestAsset\\LowercaseCapital\\'),
            array(true,     __DIR__ . '/TestAsset/NonExistentClass',    'Slam\\PHPUnit\\Tests\\TestAsset\\NonExistentClass\\'),
            array(true,     __DIR__ . '/TestAsset/NameMismatch',        'Slam\\PHPUnit\\Tests\\TestAsset\\NameMismatch\\'),
            array(true,     __DIR__ . '/TestAsset/MalformedInterface',  'Slam\\PHPUnit\\Tests\\TestAsset\\MalformedInterface\\'),
            array(true,     __DIR__ . '/TestAsset/MalformedTrait',      'Slam\\PHPUnit\\Tests\\TestAsset\\MalformedTrait\\'),
            array(true,     __DIR__ . '/TestAsset/MalformedAbstract',   'Slam\\PHPUnit\\Tests\\TestAsset\\MalformedAbstract\\'),
            array(true,     __DIR__ . '/TestAsset/MalformedException',  'Slam\\PHPUnit\\Tests\\TestAsset\\MalformedException\\'),
            array(true,     __DIR__ . '/TestAsset/SourceWithOutput',    'Slam\\PHPUnit\\Tests\\TestAsset\\SourceWithOutput\\'),
        );
    }

    public function testDoTestClassStandardsAcceptCustomChecks()
    {
        $this->doTestClassStandards(__DIR__ . '/TestAsset/NoNamespace', null);

        $this->expectException(AssertionFailedError::class);

        $this->doTestClassStandards(__DIR__ . '/TestAsset/NoNamespace', null, array(
            function (array & $tokens, string $filePath) {
                $this->assertFalse(true);
            },
        ));
    }

    public function testDoTestClassStandardsAcceptCallableOnlyAsCustomChecks()
    {
        $this->expectException(AssertionFailedError::class);

        $this->doTestClassStandards(__DIR__ . '/TestAsset/NoNamespace', null, array(
            'not a function',
        ));
    }
}
