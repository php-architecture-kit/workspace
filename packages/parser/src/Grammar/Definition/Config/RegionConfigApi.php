<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Definition\Config;

use PhpArchitecture\Parser\Grammar\Definition\Definition;
use PhpArchitecture\Parser\Grammar\Definition\EventListener\Tokenization\EndRegionEventListener;
use PhpArchitecture\Parser\Grammar\Definition\EventListener\Tokenization\StartRegionEventListener;
use PhpArchitecture\Parser\Grammar\Definition\EventSubscriber;
use PhpArchitecture\Parser\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Grammar\Definition\Middleware\GrammarMiddleware;
use PhpArchitecture\Parser\Grammar\Definition\Model\Sequence\SequenceRule;
use PhpArchitecture\Parser\Grammar\Definition\Region;
use PhpArchitecture\Parser\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Processing\Event\Tokenization\TokenAddedEvent;
use PhpArchitecture\Parser\Processing\Event\Tokenization\TokenMatchedEvent;
use PhpArchitecture\Parser\Processing\Model\Parsing\NodeType;

/**
 * @mixin Region
 */
trait RegionConfigApi
{
    public readonly RegionConfig $config;

    public function openWith(
        Rule $openRule,
        bool $includeOpenRuleMatch = true
    ): self {
        $this->config->opener = EventSubscriber::on(
            $includeOpenRuleMatch ? TokenMatchedEvent::class : TokenAddedEvent::class,
            new StartRegionEventListener(
                $this,
                $openRule,
            ),
        );

        if ($includeOpenRuleMatch) {
            $this->addRule($openRule);
        }

        return $this;
    }

    public function closeWith(
        Rule $closeRule,
        bool $negated = false,
        bool $includeCloseRuleMatch = true,
        bool $allowedByOpenRuleMatch = false,
    ): self {
        $this->config->closer = EventSubscriber::on(
            $includeCloseRuleMatch ? TokenAddedEvent::class : TokenMatchedEvent::class,
            new EndRegionEventListener(
                $closeRule,
                $negated,
                $allowedByOpenRuleMatch,
                $negated && !$includeCloseRuleMatch,
            ),
        );

        $this->addRule($closeRule);

        return $this;
    }

    public function withRootSequence(false|string $sequence, bool $applyAddRuleMiddlewares = true): self
    {
        if ($sequence === false) {
            $this->config->rootSequence = null;
            return $this;
        }

        if ($applyAddRuleMiddlewares === false) {
            $this->config->rootSequence = SequenceRule::fromString($sequence, false);
            return $this;
        }

        $rule = Rule::seq($this->name, $sequence);
        foreach ($this->middlewares[GrammarMiddleware::ADD_RULE] ?? [] as $middleware) {
            /** @var Rule $rule */
            $rule = $middleware->handle($rule);
        }

        assert($rule->definition instanceof SequenceRule);
        $this->config->rootSequence = $rule->definition;

        return $this;
    }

    public function setInheritanceFromGlobal(int $scope = Region::RULES | Region::REGIONS | Region::EVENT_SUBSCRIBERS): self
    {
        $this->config->inheritanceFromGlobal = $scope;
        return $this;
    }

    public function enableInheritanceFromGlobal(int $scopeToEnable = Region::RULES | Region::REGIONS | Region::EVENT_SUBSCRIBERS): self
    {
        $this->config->inheritanceFromGlobal |= $scopeToEnable;
        return $this;
    }

    public function disableInheritanceFromGlobal(?int $scopeToDisable = null): self
    {
        $this->config->inheritanceFromGlobal = $scopeToDisable ? $this->config->inheritanceFromGlobal & ~$scopeToDisable : Region::NONE;
        return $this;
    }

    public function setInheritanceFromAncestor(int $scope = Region::RULES | Region::REGIONS | Region::EVENT_SUBSCRIBERS): self
    {
        $this->config->inheritanceFromAncestor = $scope;
        return $this;
    }

    public function enableInheritanceFromAncestor(int $scopeToEnable = Region::RULES | Region::REGIONS | Region::EVENT_SUBSCRIBERS): self
    {
        $this->config->inheritanceFromAncestor |= $scopeToEnable;
        return $this;
    }

    public function disableInheritanceFromAncestor(?int $scopeToDisable = null): self
    {
        $this->config->inheritanceFromAncestor = $scopeToDisable ? $this->config->inheritanceFromAncestor & ~$scopeToDisable : Region::NONE;
        return $this;
    }

    public function retokenizedByInnerGrammar(
        Grammar $grammar
    ): self {
        $this->config->innerGrammar = $grammar;
        $this->config->retokenizeWithInnerGrammar = true;

        $this->config->innerGrammarMergeOverrideSource = null;
        $this->config->innerGrammarMergeScope = null;
        $this->config->innerGrammarMergeMiddlewaresScope = null;

        return $this;
    }

    public function withMergedInnerGrammar(
        Grammar $grammar,
        bool $overrideSourceGrammar = Region::MERGE_DEFAULT_OVERRIDE,
        int $mergeScope = Region::MERGE_DEFAULT_SCOPE,
        int $mergeMiddlewares = Region::MERGE_DEFAULT_MIDDLEWARES,
    ): self {
        $this->config->innerGrammar = $grammar;
        $this->config->innerGrammarMergeOverrideSource = $overrideSourceGrammar;
        $this->config->innerGrammarMergeScope = $mergeScope;
        $this->config->innerGrammarMergeMiddlewaresScope = $mergeMiddlewares;

        $this->config->retokenizeWithInnerGrammar = false;

        return $this;
    }

    public function setNodeType(NodeType $type): self
    {
        $this->config->nodeType = $type;

        return $this;
    }

    public function asAstNode(string $name): Definition
    {
        $def = new Definition($name, [$this]);
        $this->config->definition = $def;

        return $def;
    }
}
