<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\GrammarRegistry\Definition\Json;

use PhpArchitecture\Parser\Grammar\Grammar;
use PhpArchitecture\Parser\Grammar\Region;
use PhpArchitecture\Parser\Grammar\RegionConfig;
use PhpArchitecture\Parser\Grammar\Rule;
use PhpArchitecture\Parser\GrammarRegistry\GrammarFactoryInterface;

class JsonRfc8259 implements GrammarFactoryInterface
{
    public const FORMAT = "json";
    public const VARIANT = "rfc8259";

    public function grammar(): Grammar
    {
        $grammar = new Grammar(static::FORMAT, static::VARIANT);

        $grammar->global->add(
            Rule::token("space", " "),
            Rule::token("tab", "\t"),
            Rule::token("newline", "\n"),
            Rule::token("cr", "\r"),
        );

        $grammar->global->add(
            Rule::token("begin-array", "[")
                ->startRegion('array')
                ->closeWith(
                    Rule::token("end-array", "]"),
                ),
            Rule::token("begin-object", "{")
                ->startRegion('object')
                ->closeWith(
                    Rule::token("end-object", "}"),
                ),
            Rule::token("name-separator", ":"),
            Rule::token("value-separator", ","),
            Rule::token("double-quote", "\"")
                ->startRegion("string")
                ->excludeInheritance()
                ->includeOpenRule()
                ->add(
                    Rule::expr("escape-char", "\\\\[bfnrt\\\\\\\"]")->priority(1),
                    Rule::expr("unescaped", "[^\\x00-\\x1F\\x22\\x5C]+"),
                    Rule::expr("escape-unicode", "\\\\u[0-9a-fA-F]{4}"),
                )
                ->closeWith("double-quote"),
            Rule::keyword("null"),
            Rule::keyword("false"),
            Rule::keyword("true"),
            Rule::token("decimal-point", "."),
            Rule::token("plus", "+"),
            Rule::token("minus", "-"),
            Rule::token("zero", "0"),
            Rule::expr("digit1-9", "[1-9]"),
            Rule::expr("e", "[eE]"),
        );

        $grammar->setRootRegion($grammar->global);
        $grammar->compile();

        return $grammar;
    }
}
