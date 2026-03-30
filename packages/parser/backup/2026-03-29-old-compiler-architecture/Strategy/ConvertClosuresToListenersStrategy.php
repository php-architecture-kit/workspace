<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Compiled\Strategy;

use Closure;
use PhpArchitecture\Parser\Grammar\Compiled\Internal\WorkingRegion;
use PhpArchitecture\Parser\Grammar\Definition\EventSubscriber;
use PhpArchitecture\Parser\Matching\Event\Contract\ParsingEvent;
use PhpArchitecture\Parser\Matching\Event\Contract\ParsingEventListener;
use PhpArchitecture\Parser\Matching\MatcherContext;
use PhpArchitecture\Parser\Processing\Context\TokenizationContext;
use PhpArchitecture\Parser\Processing\Event\Tokenization\Contract\TokenizationEvent;
use PhpArchitecture\Parser\Processing\Event\Tokenization\Contract\TokenizationEventListener;

final class ConvertClosuresToListenersStrategy implements CompilerStrategyInterface
{
    /**
     * @param array<string, WorkingRegion> $input
     * @return array<string, WorkingRegion>
     */
    public function execute(mixed $input): array
    {
        foreach ($input as $working) {
            $convertedSubscribers = [];
            
            foreach ($working->eventSubscribers as $subscriber) {
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
            
            $working->eventSubscribers = $convertedSubscribers;
        }

        return $input;
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
}
