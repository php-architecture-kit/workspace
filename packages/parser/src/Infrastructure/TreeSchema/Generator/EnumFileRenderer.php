<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Generator;

use PhpArchitecture\Parser\Infrastructure\TreeSchema\Generator\Schema\RawChoiceInfo;

/**
 * Renders a PHP backed enum file for ChoiceAttribute(raws) choices.
 */
final class EnumFileRenderer
{
    private const I = '    ';

    /**
     * @param RawChoiceInfo[] $choices
     */
    public function render(string $enumClass, string $namespace, array $choices): string
    {
        $cases = '';
        foreach ($choices as $choice) {
            $cases .= self::I . 'case ' . $choice->caseName . ' = \'' . $choice->tokenName . '\';' . PHP_EOL;
        }

        $code  = '<?php' . PHP_EOL . PHP_EOL;
        $code .= 'declare(strict_types=1);' . PHP_EOL . PHP_EOL;
        $code .= 'namespace ' . $namespace . ';' . PHP_EOL . PHP_EOL;
        $code .= 'enum ' . $enumClass . ': string' . PHP_EOL;
        $code .= '{' . PHP_EOL;
        $code .= $cases;
        $code .= '}' . PHP_EOL;

        return $code;
    }
}
