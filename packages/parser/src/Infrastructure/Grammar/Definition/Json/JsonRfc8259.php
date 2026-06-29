<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Json;

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Defaults;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\GrammarOrigin;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Region;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Technical\Whitespace;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeType;

class JsonRfc8259 extends Whitespace
{
    public const FORMAT = "json";
    public const VARIANT = "rfc8259";

    public const STYLE_MINIFIED = 'minified';
    public const STYLE_PRETTY = 'pretty';

    public function grammar(): Grammar
    {
        parent::grammar();
        $jsonText = (new Region("json"))
            ->setInheritanceFromGlobal()
            ->withRootSequence("-* value -*");

        $this->grammar->global->add(
            $jsonText,
            Rule::token("beginArray", "[", type: NodeType::Structure)
                ->startRegion('array')
                ->enableInheritanceFromGlobal()
                ->add(
                    Rule::token("comma", ",", type: NodeType::Structure),
                )
                ->withRootSequence("beginArray -t* ?(-l* value[item]/c (-* comma -t* -l* value[item]/c)* -t*)[items]/g -l* endArray")
                ->closeWith(
                    Rule::token("endArray", "]", type: NodeType::Structure),
                )
                ->addTag("value"),
            Rule::token("beginObject", "{", type: NodeType::Structure)
                ->startRegion('object')
                ->enableInheritanceFromGlobal()
                ->add(
                    Rule::token("colon", ":", type: NodeType::Structure),
                    Rule::token("comma", ",", type: NodeType::Structure),
                    Rule::seq("member", "string[identifier] -* colon -* value")
                    // ->withDefaults([
                    //     '-.0' => static fn() => '',
                    //     '-.1' => [
                    //         self::STYLE_MINIFIED => static fn() => '',
                    //         self::STYLE_PRETTY => static fn() => ' ',
                    //     ]
                    // ]),
                )
                ->withRootSequence("beginObject -t* ?(-l* member/c (-* comma -t* -l* member/c)* -t*)[members]/g -l* endObject")
                // ->withDefaults([
                //     '-t.0' => [
                //         self::STYLE_MINIFIED => static fn() => '',
                //         self::STYLE_PRETTY => Whitespace::trailingWs(),
                //     ],
                //     '-l.1' => static fn() => '',
                //     '-.2' => [
                //         self::STYLE_MINIFIED => static fn() => '',
                //         self::STYLE_PRETTY => $this->indentationResolver(true),
                //     ],
                //     '-t.3' => [
                //         self::STYLE_MINIFIED => static fn() => '',
                //         self::STYLE_PRETTY => $this->indentationResolver(true),
                //     ],
                // ])
                ->closeWith(
                    Rule::token("endObject", "}", type: NodeType::Structure),
                )
                ->addTag("value"),
            Rule::choice("primitive", ["false", "null", "true", "number", "string"], tags: ["value"], attributeTags: ['r']),
            Rule::keyword("null"),
            Rule::keyword("false"),
            Rule::keyword("true"),

            // string
            Rule::token("doubleQuote", "\"", type: NodeType::Structure)
                ->startRegion('doubleQuotedString', true)
                ->add(
                    Rule::expr("escapeChar", "\\\\[bfnrt\\\\\\\"]")->priority(1),
                    Rule::expr("unescaped", "[^\\x00-\\x1F\\x22\\x5C]+"),
                    Rule::expr("escapeUnicode", "\\\\u[0-9a-fA-F]{4}"),
                )
                ->setNodeType(NodeType::Raw)
                ->closeWith(Rule::token("doubleQuote", "\"", type: NodeType::Structure))
                ->addTag("string"),

            // number
            Rule::token("decimalPoint", ".", tags: ["_number_part"]),
            Rule::token("plus", "+", tags: ["_number_part"]),
            Rule::token("minus", "-", tags: ["_number_part"]),
            Rule::token("zero", "0", tags: ["_number_part"]),
            Rule::expr("digit19", "[1-9]", tags: ["_number_part"]),
            Rule::expr("e", "[eE]", tags: ["_number_part"]),
            Rule::taggedWith("_number_part")
                ->startRegion("number", true)
                ->add(
                    Rule::token("decimalPoint", ".", tags: ["_number_part"]),
                    Rule::token("plus", "+", tags: ["_number_part"]),
                    Rule::token("minus", "-", tags: ["_number_part"]),
                    Rule::token("zero", "0", tags: ["_number_part"]),
                    Rule::expr("digit19", "[1-9]", tags: ["_number_part"]),
                    Rule::expr("e", "[eE]", tags: ["_number_part"]),
                    Rule::seq("digit", "zero|digit19", type: NodeType::Raw),
                    Rule::seq("digit19Seq", "digit19 digit*", type: NodeType::Raw),
                    Rule::seq("exp", "e ?minus|plus digit+", type: NodeType::Raw),
                    Rule::seq("integer", "zero|digit19Seq", type: NodeType::Raw),
                    Rule::seq("frac", "decimalPoint digit+", type: NodeType::Raw),
                )
                ->withRootSequence("?minus[operator] integer ?frac ?exp")
                ->setNodeType(NodeType::Raw)
                ->closeWith(Rule::taggedWith("_number_part"), true, false),
        );

        $this->grammar->setRootRegion($jsonText);

        $this->grammar->stampOrigin(new GrammarOrigin(self::FORMAT, self::VARIANT));

        $this->withIndentationSupport(
            [Defaults::DEFAULT_STYLE, self::STYLE_PRETTY],
            static fn() => "    ",
        );

        $this->grammar->setStyleResolver(
            static function (NodeInterface $rootNode): string {
                return self::STYLE_PRETTY;
            },
        );

        $this->grammar->nodeClassMap = array_merge($this->grammar->nodeClassMap, [
            'json' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Rfc8259\JsonNode::class,
            'object' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Rfc8259\ObjectNode::class,
            'member' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Rfc8259\MemberNode::class,
            'primitive' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Rfc8259\PrimitiveNode::class,
            'array' => \PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Json\Rfc8259\ArrayNode::class,
        ]);

        return $this->grammar;
    }
}
