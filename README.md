# Slam PHPUnit extensions

[![Build Status](https://travis-ci.org/Slamdunk/phpunit-extensions.svg?branch=master)](https://travis-ci.org/Slamdunk/phpunit-extensions)
[![Code Coverage](https://scrutinizer-ci.com/g/Slamdunk/phpunit-extensions/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/Slamdunk/phpunit-extensions/?branch=master)
[![Packagist](https://img.shields.io/packagist/v/slam/phpunit-extensions.svg)](https://packagist.org/packages/slam/phpunit-extensions)

Extensions for [PHPUnit](https://github.com/sebastianbergmann/phpunit)

## Installation

Execute:

`composer require --dev slam/phpunit-extensions`

## Usage

```php
use PHPUnit\Framework\TestCase;
use Slam\PHPUnit\ClassStandardsTrait;

class ClassStandardsTest extends TestCase
{
    use ClassStandardsTrait;

    /**
     * @dataProvider myFolders
     */
    public function testClassStandards(string $directory, string $namespace = null, array $externalChecks = array())
    {
        $this->assertNull($this->doTestClassStandards($directory, $namespace, $externalChecks));
    }

    public function myFolders()
    {
        return array(
            array('/project/config'),
            array('/project/lib',       'MyNamespace\\'),
            array('/project/tests',     'MyNamespace\\Tests\\'),
            array('/project/dir',       null,                   , array(
                function (array & $tokens, string $filePath) {
                    $this->assertEmpty($tokens, sprintf('"%s" must not contain any PHP code'));
                },
            )),
        );
    }
}
```

## Checks

1. `MyClass::class` aliases must refer to real classes
1. `$$var` indirect variable must be explicit, i.e. `${$var}`
1. Classes must be referret with `::class` keyword instead of strings
1. Interfaces must end with "Interface"
1. Traits must end with "Trait"
1. Abstract classes must start with "Abstract"
1. Exceptions must end with "Exception"
