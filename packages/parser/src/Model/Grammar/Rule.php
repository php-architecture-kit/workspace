<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Model\Grammar;

use Closure;
use PhpArchitecture\Parser\Model\Grammar\Rules\CallbackRule;
use PhpArchitecture\Parser\Model\Grammar\Rules\Cardinality;
use PhpArchitecture\Parser\Model\Grammar\Rules\RegexRule;
use PhpArchitecture\Parser\Model\Grammar\Rules\RuleDefinition;
use PhpArchitecture\Parser\Model\Grammar\Rules\SequenceNode;
use PhpArchitecture\Parser\Model\Grammar\Rules\SequenceRule;
use PhpArchitecture\Parser\Model\MetaTrait;
use PhpArchitecture\Parser\Model\Token\TokenInterface;

final class Rule
{
    use MetaTrait;

    public const META_ADDED_RULES = 'added_rules';
    public const META_PRIORITY = 'priority';
    public const META_MEMBER = 'member';

    public function __construct(
        public readonly string $name,
        public readonly RuleType $type,
        public RuleDefinition $definition,
    ) {}

    public static function token(string $name, string $token): self
    {
        return new self(
            $name,
            RuleType::Token,
            RegexRule::fromString($token)
        );
    }

    /**
     * @param callable(Rule $rule, TokenInterface $trigger):RegexRule $callback
     */
    public static function dynamicToken(
        string $name, 
        callable $callback
    ): self {
        return new self(
            $name,
            RuleType::DynamicToken,
            new CallbackRule(Closure::fromCallable($callback))
        );
    }

    public static function keyword(string $keyword, bool $caseSensitive = false, ?string $name = null): self
    {
        return new self(
            $name ?? $keyword,
            RuleType::Keyword,
            RegexRule::fromString($keyword, $caseSensitive)
        );
    }

    public static function expr(string $name, string $expression, bool $caseSensitive = false): self
    {
        return new self(
            $name,
            RuleType::Expression,
            RegexRule::fromString($expression, $caseSensitive)
        );
    }

    /**
     * @param string $sequence
     */
    public static function seq(
        string $name,
        string $sequence,
    ): self {
        return (new self(
            $name,
            RuleType::Sequence,
            SequenceRule::fromString($sequence)
        ))->treatAsMember(true);
    }

    /**
     * @param array<self|string> $options
     */
    public static function choice(
        string $name,
        array $options,
        Cardinality $cardinality = Cardinality::ExactlyOne
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

        return (new self(
            $name,
            RuleType::Choice,
            new SequenceRule([new SequenceNode($rulesNames, $cardinality)])
        ))->treatAsMember(true);
    }

    public function priority(int $priority): self
    {
        $this->meta[self::META_PRIORITY] = $priority;

        return $this;
    }

    public function treatAsMember(bool $treatAsMember): self
    {
        $this->meta[self::META_MEMBER] = $treatAsMember;

        return $this;
    }

    public function initRegion(string $name): Region
    {
        return new Region($name, new RegionConfig());
    }
}
