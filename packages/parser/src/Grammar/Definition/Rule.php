<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Definition;

use Closure;
use PhpArchitecture\Parser\Grammar\Definition\EventListener\Tokenization\EndRegionEventListener;
use PhpArchitecture\Parser\Grammar\Definition\Model\Cardinality;
use PhpArchitecture\Parser\Grammar\Definition\Model\Regex\CallbackRule;
use PhpArchitecture\Parser\Grammar\Definition\Model\Regex\RegexRule;
use PhpArchitecture\Parser\Grammar\Definition\Model\RuleDefinition;
use PhpArchitecture\Parser\Grammar\Definition\Model\RuleType;
use PhpArchitecture\Parser\Grammar\Definition\Model\Sequence\SequenceRule;
use PhpArchitecture\Parser\Grammar\Definition\Model\Technical\TaggedRule;
use PhpArchitecture\Parser\Grammar\Definition\Model\Technical\TechnicalTokenRule;
use PhpArchitecture\Parser\Matching\Event\Contract\ParsingEventListener;
use PhpArchitecture\Parser\Processing\Event\Tokenization\Contract\TokenizationEventListener;
use PhpArchitecture\Parser\Processing\Event\Tokenization\TokenAddedEvent;
use PhpArchitecture\Parser\Processing\Event\Tokenization\TokenMatchedEvent;
use PhpArchitecture\Parser\Processing\Model\Tokenization\Token;
use PhpArchitecture\Parser\Shared\Tags\TagsTrait;

class Rule
{
    use TagsTrait;

    /** @var EventSubscriber[] */
    public private(set) array $eventSubscribers = [];
    /** @var Rule[] */
    public private(set) array $inheritedRuleDefs = [];

    public private(set) int $priority = 0;

    /**
     * @param string[] $tags
     */
    public function __construct(
        public readonly string $name,
        public readonly RuleType $type,
        public RuleDefinition $definition,
        array $tags = []
    ) {
        if (!empty($tags)) {
            $this->addTag(...$tags);
        }
    }

    /** @param string[] $tags */
    public static function token(
        string $name,
        string $token,
        array $tags = []
    ): self {
        return new self(
            $name,
            RuleType::Token,
            RegexRule::fromString(preg_quote($token, '~')),
            $tags
        );
    }

    /** @param string[] $tags */
    public static function keyword(
        string $keyword,
        bool $caseSensitive = false,
        ?string $name = null,
        array $tags = [],
    ): self {
        return new self(
            $name ?? $keyword,
            RuleType::Keyword,
            RegexRule::fromString(preg_quote($keyword, '~'), $caseSensitive),
            $tags,
        );
    }

    /** @param string[] $tags */
    public static function expr(
        string $name,
        string $expression,
        bool $caseSensitive = false,
        array $tags = [],
    ): self {
        return new self(
            $name,
            RuleType::Expression,
            RegexRule::fromString($expression, $caseSensitive),
            $tags,
        );
    }

    /**
     * @param callable(Rule $rule, Token $trigger):RegexRule $builder RegexRule::fromString() is the best way to create a valid regex.
     * @param string[] $listenInRegions
     * @param string[] $tags
     */
    public static function dynamic(
        string $name,
        callable $builder,
        string $triggerRule,
        array $listenInRegions = [CallbackRule::PARENT_REGION],
        array $tags = [],
    ): self {
        return new self(
            $name,
            RuleType::DynamicToken,
            new CallbackRule(Closure::fromCallable($builder), $triggerRule, $listenInRegions),
            $tags,
        );
    }

    public static function taggedWith(string $tag): self
    {
        return new self(
            $tag,
            RuleType::Tag,
            new TaggedRule($tag),
            []
        );
    }

    /**
     * @param "bof"|"eof"|"unknown" $name
     * @param string[] $tags
     */
    public static function technical(string $name, array $tags = []): self
    {
        return new self(
            $name,
            RuleType::Token,
            new TechnicalTokenRule($name),
            $tags,
        );
    }

    /**
     * @param string[] $tags
     */
    public static function seq(string $name, string $sequence, array $tags = []): self
    {
        return new self(
            $name,
            RuleType::Sequence,
            SequenceRule::fromString($sequence),
            $tags,
        );
    }

    /**
     * @param array<self|string> $options
     * @param string[] $tags
     */
    public static function choice(
        string $name,
        array $options,
        Cardinality $cardinality = Cardinality::ExactlyOne,
        array $tags = [],
    ): self {
        $addedRules = [];
        $rulesNames = [];
        foreach ($options as $option) {
            if ($option instanceof self) {
                $addedRules[] = $option;
                $rulesNames[] = $option->name;

                continue;
            }

            $rulesNames[] = $option;
        }

        $instance = (new self(
            $name,
            RuleType::Choice,
            new SequenceRule([new Model\Sequence\SequenceNode($rulesNames, $cardinality)]),
            $tags,
        ));

        $instance->inheritedRuleDefs = $addedRules;

        return $instance;
    }

    /**
     * @param TokenizationEventListener|ParsingEventListener|callable(TokenizationEvent $event, TokenizationContext $context):void $listener
     */
    public function onEvent(string $eventClassName, TokenizationEventListener|ParsingEventListener|callable $listener, int $priority = 0): self
    {
        $this->eventSubscribers[] = EventSubscriber::on($eventClassName, $listener)
            ->priority(
                is_object($listener) && method_exists($listener, 'priority')
                    ? $listener->priority()
                    : $priority
            );

        return $this;
    }

    public function priority(int $priority): self
    {
        $this->priority = $priority;
        return $this;
    }

    public function startRegion(
        string $name,
        bool $includeMatchInRegion = true,
    ): Region {
        return (new Region($name))
            ->openWith($this, $includeMatchInRegion);
    }

    public function closeRegion(
        bool $includeMatchInRegion = true,
        bool $allowIfMatchAlsoStartedRegion = false,
        bool $repeatMatchTokenizationAfterRegionClose = false,
    ): self {
        $this->eventSubscribers[] = EventSubscriber::on(
            $includeMatchInRegion ? TokenAddedEvent::class : TokenMatchedEvent::class,
            new EndRegionEventListener(
                $this,
                false,
                $allowIfMatchAlsoStartedRegion,
                $repeatMatchTokenizationAfterRegionClose,
            )
        );

        return $this;
    }
}
