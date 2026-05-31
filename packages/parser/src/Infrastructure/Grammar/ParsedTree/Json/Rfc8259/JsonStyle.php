<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Rfc8259;

enum JsonStyle: string
{
    case Minified = "minified";
    case Pretty2 = "pretty2";
    case Pretty4 = "pretty4"; # default

    public function indent(): string
    {
        return match ($this) {
            self::Minified => "",
            self::Pretty2 => "  ",
            self::Pretty4 => "    ",
        };
    }
}
