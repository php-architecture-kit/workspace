<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Grammar\Compiled;

use PhpArchitecture\Parser\Foundation\Grammar\Compiled\Compiler\DynamicTokenCompiler;
use PhpArchitecture\Parser\Foundation\Grammar\Compiled\Compiler\GrammarCompilerInterface;
use PhpArchitecture\Parser\Foundation\Grammar\Compiled\Compiler\GrammarPrecompilerInterface;
use PhpArchitecture\Parser\Foundation\Grammar\Compiled\Compiler\InnerGrammarEventListenerCompiler;
use PhpArchitecture\Parser\Foundation\Grammar\Compiled\Compiler\InnerGrammarInheritanceCompiler;
use PhpArchitecture\Parser\Foundation\Grammar\Compiled\Compiler\InRuleDeclaredEventSubscribersCompiler;
use PhpArchitecture\Parser\Foundation\Grammar\Compiled\Compiler\NodeTypeToTagCompiler;
use PhpArchitecture\Parser\Foundation\Grammar\Compiled\Compiler\PrattEventListenerCompiler;
use PhpArchitecture\Parser\Foundation\Grammar\Compiled\Compiler\RegionInheritanceCompiler;
use PhpArchitecture\Parser\Foundation\Grammar\Compiled\Compiler\RegionOpenerCloserCompiler;
use PhpArchitecture\Parser\Foundation\Grammar\Compiled\Compiler\RegionPrecompilerInterface;
use PhpArchitecture\Parser\Foundation\Grammar\Compiled\Compiler\RuleCompilerInterface;
use PhpArchitecture\Parser\Foundation\Grammar\Compiled\Compiler\RuleToPatternCompiler;
use PhpArchitecture\Parser\Foundation\Grammar\Compiled\Compiler\RuleToSequenceCompiler;
use PhpArchitecture\Parser\Foundation\Grammar\Compiled\Compiler\TaggedRuleBasedEventSubscribersCompiler;
use PhpArchitecture\Parser\Foundation\Grammar\Compiled\Compiler\TagToChoiceCompiler;
use PhpArchitecture\Parser\Foundation\Grammar\Compiled\Model\CompiledEventSubscriber;
use PhpArchitecture\Parser\Foundation\Grammar\Compiled\Model\CompiledGrammar;
use PhpArchitecture\Parser\Foundation\Grammar\Compiled\Model\CompiledRegion;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Definition;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Region;
use PhpArchitecture\Parser\Foundation\Matching\Event\Contract\MatchingEventListener;
use PhpArchitecture\Parser\Foundation\Tokenization\Contract\TokenizationContext;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\Contract\TokenizationEvent;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\Contract\TokenizationEventListener;
use PhpArchitecture\Parser\Foundation\AST\Definition\FormatDefinition;
use PhpArchitecture\Parser\Foundation\AST\Definition\NodeDefinition;
use PhpArchitecture\Parser\Foundation\Matching\Model\Sequence;
use PhpArchitecture\Parser\Foundation\Matching\Model\SequenceLibrary;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\Pattern;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\PatternLibrary;
use Closure;

class GrammarCompiler
{
    /** @var GrammarPrecompilerInterface[] */
    private array $grammarPrecompilers = [];

    /** @var RegionPrecompilerInterface[] */
    private array $regionPrecompilers = [];

    /** @var GrammarCompilerInterface[] */
    private array $grammarCompilers = [];

    /** @var RuleCompilerInterface[] */
    private array $ruleCompilers = [];

    public function __construct()
    {
        $regionInheritanceCompiler = new RegionInheritanceCompiler();

        $this->grammarPrecompilers = [
            $regionInheritanceCompiler,
            new DynamicTokenCompiler(),
        ];

        $this->regionPrecompilers = [
            new NodeTypeToTagCompiler(),
            new InnerGrammarInheritanceCompiler($this),
            new InRuleDeclaredEventSubscribersCompiler(),
            new InnerGrammarEventListenerCompiler($this),
        ];

        $this->grammarCompilers = [
            new RegionOpenerCloserCompiler(),
            $regionInheritanceCompiler,
            new PrattEventListenerCompiler(),
            new TaggedRuleBasedEventSubscribersCompiler(),
            new TagToChoiceCompiler(),
        ];

        $this->ruleCompilers = [
            new RuleToPatternCompiler(),
            new RuleToSequenceCompiler(),
        ];
    }

    public function compile(Grammar $definition): CompiledGrammar
    {
        $grammar = $this->precompile($definition);

        foreach ($this->grammarCompilers as $compiler) {
            $compiler->compileGrammar($grammar);
        }

        $compiledRegions = [];
        foreach ($grammar->getAllRegions() as $region) {
            $compiledRegions[$region->name] = $this->compileRegion($region);
        }

        return new CompiledGrammar(
            $grammar->name,
            $grammar->variant,
            $grammar->requireBofEof,
            $grammar->rootRegion->name,
            $compiledRegions,
        );
    }

    public function precompile(Grammar $definition): Grammar
    {
        $grammar = $this->deepCloneGrammar($definition);

        foreach ($this->grammarPrecompilers as $precompiler) {
            $precompiler->precompileGrammar($grammar);
        }

        foreach ($grammar->getAllRegions() as $region) {
            $this->precompileRegion($region);
        }

        return $grammar;
    }

    private function deepCloneGrammar(Grammar $grammar): Grammar
    {
        $cloned = new Grammar($grammar->name, $grammar->variant);
        $cloned->requireBofEof = $grammar->requireBofEof;

        $this->copyRegionContents($grammar->global, $cloned->global);

        if (isset($grammar->rootRegion)) {
            $allRegions = $cloned->getAllRegions();
            foreach ($allRegions as $region) {
                if ($region->name === $grammar->rootRegion->name) {
                    $cloned->setRootRegion($region);
                    break;
                }
            }
        }

        return $cloned;
    }

    private function copyRegionContents(Region $source, Region $target): void
    {
        foreach ($source->rules as $rule) {
            $target->add($rule);
        }

        foreach ($source->eventSubscribers as $subscriber) {
            $target->add($subscriber);
        }

        foreach ($source->regions as $childRegion) {
            $clonedChild = new Region(
                $childRegion->name,
                clone $childRegion->config,
            );
            $this->copyRegionContents($childRegion, $clonedChild);
            $target->add($clonedChild);
        }

        foreach ($source->getMetaAll() as $key => $value) {
            $target->setMeta($key, $value);
        }

        foreach ($source->getAllTags() as $tag) {
            $target->addTag($tag);
        }

        $target->config->rootSequence = $source->config->rootSequence;
    }

    private function precompileRegion(Region $region): void
    {
        foreach ($this->regionPrecompilers as $precompiler) {
            $precompiler->precompileRegion($region);
        }
    }

    private function compileRegion(Region $region): CompiledRegion
    {
        $patterns = [];
        $sequences = [];
        $rootSequence = null;

        foreach ($region->rules as $rule) {
            foreach ($this->ruleCompilers as $compiler) {
                if (!$compiler->supports($rule)) {
                    continue;
                }

                $compiled = $compiler->compileRule($rule);

                if ($compiled instanceof Pattern) {
                    $patterns[] = $compiled;
                } elseif ($compiled instanceof Sequence) {
                    $sequences[] = $compiled;
                }

                break;
            }
        }

        if ($region->config->rootSequence) {
            $rootSequence = (new RuleToSequenceCompiler())->compileSequence(
                $region->name,
                $region->config->rootSequence,
                0,
                $region->tags,
                [],
            );
        }

        // Enrich sequences with NodeType from Rules/Regions/Tags
        $enricher = new Compiler\SequenceNodeEnricher();
        $sequences = $enricher->enrichSequences($sequences, $region);
        if ($rootSequence !== null) {
            $rootSequence = $enricher->enrichSequence($rootSequence, $region);
        }

        $compiledEventSubscribers = [];
        foreach ($region->eventSubscribers as $subscriber) {
            $listener = $subscriber->listener;

            if ($listener instanceof Closure) {
                $listener = new class($listener, $subscriber->priority) implements TokenizationEventListener {
                    public function __construct(
                        private readonly Closure $closure,
                        private readonly int $priorityValue,
                    ) {}

                    public function handle(TokenizationEvent $event, TokenizationContext $context): void
                    {
                        ($this->closure)($event, $context);
                    }

                    public function priority(): int
                    {
                        return $this->priorityValue;
                    }
                };
            }

            if ($listener instanceof TokenizationEventListener || $listener instanceof MatchingEventListener) {
                $compiledEventSubscribers[$subscriber->hash()] = new CompiledEventSubscriber(
                    $subscriber->eventClassName,
                    $listener,
                    $subscriber->onlyForRuleName,
                    $subscriber->priority,
                );
            }
        }

        $definition = $region->config->definition !== null
            ? $this->compileDefinition($region->config->definition)
            : null;

        $meta = $region->getMetaAll();
        if (!empty($region->config->possibleNames)) {
            $meta[CompiledRegion::META_POSSIBLE_NAMES] = $region->config->possibleNames;
        }

        return new CompiledRegion(
            $region->name,
            $compiledEventSubscribers,
            new PatternLibrary($patterns),
            new SequenceLibrary($sequences, $rootSequence),
            $definition,
            $region->getAllTags(),
            $meta,
        );
    }

    private function compileDefinition(Definition $definition): NodeDefinition
    {
        return new NodeDefinition($definition->name, [], [], [], new FormatDefinition(name: 'default'), []);
    }
}
