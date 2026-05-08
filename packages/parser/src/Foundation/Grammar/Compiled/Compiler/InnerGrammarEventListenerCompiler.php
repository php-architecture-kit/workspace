<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Grammar\Compiled\Compiler;

use PhpArchitecture\Parser\Foundation\Grammar\Compiled\GrammarCompiler;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\EventListener\Tokenization\RetokenizeRegionEventListener;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\EventSubscriber;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Region;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\TokenRegionEndedEvent;

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
