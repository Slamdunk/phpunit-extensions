<?php

declare(strict_types=1);

namespace Slam\PHPUnit;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

trait ClassStandardsTrait
{
    private function doTestClassStandards(string $directory, string $namespace = null, array $externalChecks = array())
    {
        $namespaceSeparator = null;
        if ($namespace !== null) {
            $namespaceSeparator = mb_substr($namespace, -1);
            $this->assertContains($namespaceSeparator, array('\\', '_'), 'The namespace must end with a valid separator like "\\" or "_"');
        }

        static $allowedExtensions = array(
            'php' => true,
            'phtml' => true,
        );

        $osDirectory = str_replace('/', DIRECTORY_SEPARATOR, $directory);
        $rdi = new RecursiveDirectoryIterator($osDirectory);
        $rii = new RecursiveIteratorIterator($rdi);

        foreach ($rii as $file) {
            if (! $file->isFile() or ! isset($allowedExtensions[$file->getExtension()])) {
                continue;
            }

            $path = $file->getRealPath();

            $tokens = token_get_all(file_get_contents($path));
            $relativePath = defined('ROOT_PATH') ? str_replace(ROOT_PATH, '', $path) : $path;

            $this->checkClassExistance($tokens, $relativePath);
            $this->checkIndirectVariable($tokens, $relativePath);
            $this->checkClassKeywordUsage($tokens, $relativePath);
            foreach ($externalChecks as $externalCheck) {
                $this->assertInternalType('callable', $externalCheck, 'Only callable accepcted as external checks');
                $externalCheck($tokens, $relativePath);
            }

            if ($namespace === null) {
                continue;
            }

            $className = mb_substr(str_replace($osDirectory, '', $path), 1);
            $className = str_replace('.php', '', $className);
            $className = str_replace(DIRECTORY_SEPARATOR, $namespaceSeparator, $className);
            $className = $namespace . $className;

            if ($this->shouldClassesHaveUppercaseCapital()) {
                $expectedClassName = explode($namespaceSeparator, $className);
                $expectedClassName = array_map('ucfirst', $expectedClassName);
                $expectedClassName = implode($namespaceSeparator, $expectedClassName);

                $this->assertSame($expectedClassName, $className, 'The class and its parent directories must have first letter in uppercase');
            }

            // If class/interface/trait doesn't exist or
            // The name mismatches, don't try to __autoload
            // more than once
            ob_start();
            class_exists($className, true);
            $classOutput = ob_get_clean();

            $this->assertTrue(
                class_exists($className, false) or interface_exists($className, false) or trait_exists($className, false),
                sprintf('The class "%s" doesn\'t exist', $className)
            );

            $this->assertEmpty($classOutput, sprintf('A file associated to the class "%s" produces unexpected output', $className));

            $refClass = new ReflectionClass($className);

            $this->assertSame($className, $refClass->getName(), 'Class name must exactly match directory/file name');

            if ($refClass->isInterface()) {
                $this->assertRegExp('/Interface$/', $className, 'Interfaces must end with "Interface"');

                continue;
            }

            if ($refClass->isTrait()) {
                $this->assertRegExp('/Trait$/', $className, 'Traits must end with "Trait"');

                continue;
            }

            if ($refClass->isAbstract()) {
                $regex = '/%ns%Abstract[^%ns%]+$/';
                $regex = str_replace('%ns%', preg_quote($namespaceSeparator), $regex);

                $this->assertRegExp($regex, $className, 'Abstract classes must start with "Abstract"');
            }

            if ($refClass->isSubclassOf(\Exception::class)) {
                $this->assertRegExp('/Exception$/', $className, 'Exceptions must end with "Exception"');
            }
        }
    }

    private function shouldClassesHaveUppercaseCapital(): bool
    {
        return true;
    }

    private function checkClassExistance(array & $tokens, string $filePath)
    {
        $namespaceOpened = false;
        $namespace = null;
        $uses = array();
        $use = null;
        $aliasPlaceholder = ':';

        $classOpened = false;
        $classParts = array();

        static $collectTypes = array(
            T_NS_SEPARATOR => true,
            T_STRING => true,
        );

        foreach ($tokens as $index => $token) {
            if (! $classOpened) {
                // Namespace gathering
                if ($namespaceOpened === true) {
                    if (! is_array($token) and $token === ';') {
                        $namespaceOpened = false;
                        $namespace = preg_replace('/\s+/', '', $namespace);

                        continue;
                    }
                    $namespace .= is_array($token) ? $token[1] : $token;

                    continue;
                }

                if (is_array($token) and $token[0] === T_NAMESPACE) {
                    $namespaceOpened = true;
                    $namespace = '';

                    continue;
                }

                // Uses gathering
                if (is_string($use)) {
                    if (! is_array($token) and $token === ';') {
                        $use = preg_replace('/\s+/', '', $use);
                        if (mb_strpos($use, $aliasPlaceholder) === false) {
                            $parts = explode('\\', $use);
                            $use .= $aliasPlaceholder;
                            $use .= array_pop($parts);
                        }
                        list($fullQualifiedAlis, $alias) = explode($aliasPlaceholder, $use);
                        $uses[$alias] = $fullQualifiedAlis;
                        $use = null;

                        continue;
                    }
                    $temp = is_array($token) ? $token[1] : $token;
                    if (is_array($token) and $token[0] === T_AS) {
                        $temp = $aliasPlaceholder;
                    }
                    $use .= $temp;

                    continue;
                }

                if (is_array($token) and $token[0] === T_USE) {
                    $use = '';

                    continue;
                }

                // Class opened, namespace and use gathering not needed anymore
                if (is_array($token) and $token[0] === T_CLASS) {
                    $classOpened = true;

                    continue;
                }
            }

            if (is_array($token) and $token[0] === T_DOUBLE_COLON) {
                $nextIndex = 1 + $index;
                if (is_array($tokens[$nextIndex]) and $tokens[$nextIndex][0] === T_WHITESPACE) {
                    ++$nextIndex;
                }
                $nextToken = $tokens[$nextIndex];
                if (! is_array($nextToken) or $nextToken[0] !== T_CLASS) {
                    continue;
                }

                $alias = reset($classParts);
                if ($alias === 'self') {
                    continue;
                }

                if (isset($uses[$alias])) {
                    array_shift($classParts);
                    array_unshift($classParts, $uses[$alias]);
                } elseif ($alias !== '\\' and $namespace !== null) {
                    array_unshift($classParts, '\\');
                    array_unshift($classParts, $namespace);
                }
                $className = implode('', $classParts);

                $this->assertTrue(class_exists($className) or interface_exists($className) or trait_exists($className),
                    sprintf('The alias "%s" references to a non existent class in file:%s.%s:%s', $className, PHP_EOL, $filePath, $nextToken[2])
                );
            }

            if (is_array($token) and isset($collectTypes[$token[0]])) {
                $classParts[] = $token[1];
            } else {
                $classParts = array();
            }
        }
    }

    private function checkIndirectVariable(array & $tokens, string $filePath)
    {
        foreach ($tokens as $index => $token) {
            if ((is_array($token) and $token[0] === T_OBJECT_OPERATOR) or $token === '$') {
                $nextIndex = 1 + $index;
                if (is_array($tokens[$nextIndex]) and $tokens[$nextIndex][0] === T_WHITESPACE) {
                    ++$nextIndex;
                }
                $nextToken = $tokens[$nextIndex];
                if (is_array($nextToken) and $nextToken[0] === T_VARIABLE) {
                    $this->fail(sprintf('Indirect variables must be enclosed in curly braces in file:%s.%s:%s', PHP_EOL, $filePath, $nextToken[2]));
                }
            }
        }
    }

    private function checkClassKeywordUsage(array & $tokens, string $filePath)
    {
        $isNamespaced = false;

        foreach ($tokens as $index => $token) {
            if (is_array($token) and $token[0] === T_NAMESPACE) {
                $isNamespaced = true;

                continue;
            }

            if (! is_array($token) or $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
                continue;
            }

            $importedClass = mb_substr($token[1], 1, -1);
            if (empty($importedClass)) {
                continue;
            }

            if (! class_exists($importedClass) and ! interface_exists($importedClass) and ! trait_exists($importedClass)) {
                continue;
            }

            $refClass = new ReflectionClass($importedClass);
            if ($importedClass !== $refClass->getName() and $refClass->isInternal()) {
                continue;
            }

            $use = $importedClass;
            $class = $importedClass;
            if (mb_strpos($use, '\\') !== false) {
                $class = mb_substr(mb_strrchr($use, '\\'), 1);
            }
            if (! $isNamespaced) {
                $use = null;
                $class = '\\' . $importedClass;
            }

            $this->fail(sprintf('Class "%s" must be written %s"%s::class" in file:%s.%s:%s',
                $importedClass,
                $use !== null ? sprintf('with a "use %s;" and then a ', $use) : '',
                $class,
                PHP_EOL,
                $filePath, $token[2])
            );
        }
    }

    private function checkGoto(array & $tokens, string $filePath)
    {
        foreach ($tokens as $index => $token) {
            if (! is_array($token) or $token[0] !== T_GOTO) {
                continue;
            }

            $this->fail(sprintf('No goto, cmon!%s.%s:%s',
                PHP_EOL,
                $filePath, $token[2])
            );
        }
    }
}
