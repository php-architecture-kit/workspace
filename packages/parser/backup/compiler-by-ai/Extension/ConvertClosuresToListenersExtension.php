<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Compiled\Extension;

use Closure;
use PhpArchitecture\Parser\Grammar\Definition\EventSubscriber;
use PhpArchitecture\Parser\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Matching\Event\Contract\ParsingEvent;
use PhpArchitecture\Parser\Matching\Event\Contract\ParsingEventListener;
use PhpArchitecture\Parser\Matching\MatcherContext;
use PhpArchitecture\Parser\Processing\Context\TokenizationContext;
use PhpArchitecture\Parser\Processing\Event\Tokenization\Contract\TokenizationEvent;
use PhpArchitecture\Parser\Processing\Event\Tokenization\Contract\TokenizationEventListener;

/**
 * Converts Closure event listeners to anonymous classes implementing proper interfaces.
 * This must run after all other extensions that add event subscribers.
 */
final class ConvertClosuresToListenersExtension implements CompilerExtensionInterface
{
    public function apply(Grammar $grammar): void
    {
        $allRegions = $grammar->getAllRegions();

        foreach ($allRegions as $region) {
            $convertedSubscribers = [];
            
            foreach ($region->eventSubscribers as $subscriber) {
                if ($subscriber->listener instanceof Closure) {
                    $convertedSubscribers[] = new EventSubscriber(
                        eventClassName: $subscriber->eventClassName,
                        listener: $this->convertClosureToListener($subscriber->listener, $subscriber->eventClassName),
                        onlyForRuleName: $subscriber->onlyForRuleName,
                        priority: $subscriber->priority,
                    );
                } else {
                    $convertedSubscribers[] = $subscriber;
                }
            }
            
            // Use reflection to replace private(set) property
            $reflection = new \ReflectionProperty($region, 'eventSubscribers');
            $reflection->setValue($region, $convertedSubscribers);
        }
    }

    /**
     * @param class-string<TokenizationEvent|ParsingEvent> $eventClassName
     */
    private function convertClosureToListener(Closure $closure, string $eventClassName): TokenizationEventListener|ParsingEventListener
    {
        if (is_subclass_of($eventClassName, TokenizationEvent::class)) {
            return new class($closure) implements TokenizationEventListener {
                public function __construct(private readonly Closure $closure) {}
                
                public function handle(TokenizationEvent $event, TokenizationContext $context): void
                {
                    ($this->closure)($event, $context);
                }
                
                public function priority(): int
                {
                    return 0;
                }
            };
        }

        return new class($closure) implements ParsingEventListener {
            public function __construct(private readonly Closure $closure) {}
            
            public function handle(ParsingEvent $event, MatcherContext $context): void
            {
                ($this->closure)($event, $context);
            }
            
            public function priority(): int
            {
                return 0;
            }
        };
    }

    public function priority(): int
    {
        return 800;
    }
}
