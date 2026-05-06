<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Compiled\Compiler;

use PhpArchitecture\Parser\Grammar\Compiled\GrammarCompiler;
use PhpArchitecture\Parser\Grammar\Definition\EventListener\Tokenization\RetokenizeRegionEventListener;
use PhpArchitecture\Parser\Grammar\Definition\EventSubscriber;
use PhpArchitecture\Parser\Grammar\Definition\Region;
use PhpArchitecture\Parser\Processing\Event\Tokenization\TokenRegionEndedEvent;

class InnerGrammarEventListenerCompiler implements RegionPrecompilerInterface
{
    public function __construct(
        private readonly GrammarCompiler $compiler,
    ) {}

    public function precompileRegion(Region $region): void
    {
        if ($region->config->retokenizeWithInnerGrammar === false || $region->config->innerGrammar === null) {
            return;
        }

        $region->addEventSubscriber(
            EventSubscriber::on(
                TokenRegionEndedEvent::class,
                new RetokenizeRegionEventListener(
                    $region->name,
                    $this->compiler->compile($region->config->innerGrammar),
                ),
            ),
        );
    }
}
