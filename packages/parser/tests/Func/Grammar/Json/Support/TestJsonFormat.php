<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tests\Func\Grammar\Json\Support;

enum TestJsonFormat
{
    case Minified;
    case Pretty2;
    case Pretty4;

    public function indentUnit(): string
    {
        return match ($this) {
            self::Minified => '',
            self::Pretty2 => '  ',
            self::Pretty4 => '    ',
        };
    }
}
