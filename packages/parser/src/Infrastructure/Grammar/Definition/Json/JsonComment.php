<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Json;

use InvalidArgumentException;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\GrammarOrigin;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Region;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeType;
use PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Technical\Whitespace;

class JsonComment extends Whitespace
{
    public const FORMAT = "json";
    public const VARIANT = "comment";

    /**
     * @param "lineComment"|"blockComment" $rootRegion
     */
    public function __construct(
        public readonly string $rootRegion,
    ) {
        if (!in_array($rootRegion, ['lineComment', 'blockComment'])) {
            throw new InvalidArgumentException("Invalid rootRegion provided. Only `lineComment` and `blockComment` are allowed");
        }
    }

    public function grammar(): Grammar
    {
        parent::grammar();

        $this->grammar->requireBofEof = false;

        if ($this->rootRegion === 'lineComment') {
            $region = (new Region("lineComment"))
                ->setInheritanceFromGlobal()
                ->add(
                    Rule::token("lineCommentStart", "//", type: NodeType::Structure),
                    Rule::expr("word", "\S+")
                        ->priority(-1),
                )
                ->withRootSequence("lineCommentStart ?inlineWs[leadingWs]/r ?(word (inlineWs/r word)*)[content]/r ?inlineWs[trailingWs]/r");

            $this->grammar->global->add($region);
            $this->grammar->setRootRegion($region);
        }

        if ($this->rootRegion === 'blockComment') {
            $region = (new Region('blockComment'))
                ->setInheritanceFromGlobal()
                ->add(
                    Rule::token("blockCommentStart", "/*", type: NodeType::Structure),
                    Rule::token("blockCommentEnd", "*/", type: NodeType::Structure)
                        ->priority(2),
                    Rule::token("asterisk", "*", ['-', '-l', '-t'], NodeType::Structure)
                        ->priority(1),
                    Rule::expr("word", "\S+")
                        ->priority(-1),
                    Rule::seq("commentStartLine", "blockCommentStart ?asterisk ?inlineWs[leadingWs]/r ?(word (inlineWs/r word)*)[content]/r -t+"),
                    Rule::seq("commentEmptyLine", "-l* ?asterisk -t+")->priority(1),
                    Rule::seq("commentMidLine", "-l* ?asterisk ?inlineWs[leadingWs]/r ?(word (inlineWs/r word)*)[content]/r -t+"),
                    Rule::seq("commentEndLine", "-l* ?asterisk ?inlineWs[leadingWs]/r ?(word (inlineWs/r word)*)[content]/r ?inlineWs[trailingWs]/r blockCommentEnd"),
                    Rule::seq("singleLine", "blockCommentStart ?asterisk ?inlineWs[leadingWs]/r ?(word (inlineWs/r word)*)[content]/r ?inlineWs[trailingWs]/r blockCommentEnd"),
                    Rule::seq("multiLine", "commentStartLine commentEmptyLine|commentMidLine* commentEndLine"),
                )
                ->withRootSequence("singleLine|multiLine")
                ->setNodeType(NodeType::Node)
                ->addTag("comment", "-", "-l", "-t");

            $this->grammar->global->add($region);
            $this->grammar->setRootRegion($region);
        }

        $this->grammar->stampOrigin(new GrammarOrigin(self::FORMAT, self::VARIANT));

        return $this->grammar;
    }
}
