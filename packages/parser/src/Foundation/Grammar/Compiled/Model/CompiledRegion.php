<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Grammar\Compiled\Model;

use PhpArchitecture\Parser\Foundation\AST\Definition\NodeDefinition;
use PhpArchitecture\Parser\Foundation\Matching\Model\SequenceLibrary;
use PhpArchitecture\Parser\Foundation\Shared\Meta\MetaInterface;
use PhpArchitecture\Parser\Foundation\Shared\Meta\MetaTrait;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\PatternLibrary;

final class CompiledRegion implements MetaInterface
{
    use MetaTrait;

    public const string META_POSSIBLE_NAMES = 'possibleNames';

    /**
     * @param array<string,CompiledEventSubscriber> $eventSubscribers
     * @param string[] $tags
     * @param array<string,mixed> $meta
     */
    public function __construct(
        public readonly string $name,
        public readonly array $eventSubscribers,
        public readonly PatternLibrary $patternLibrary,
        public readonly SequenceLibrary $sequenceLibrary,
        public readonly ?NodeDefinition $definition = null,
        public readonly array $tags = [],
        array $meta = [],
    ) {
        $this->initMeta($meta);
    }
}
