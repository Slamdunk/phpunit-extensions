<?php

declare(strict_types=1);

namespace Slam\PHPUnit;

use ReflectionClass;

trait ClassStandardsTrait
{
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
}
