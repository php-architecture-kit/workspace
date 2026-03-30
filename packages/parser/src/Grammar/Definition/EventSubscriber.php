<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Definition;

use Closure;
use PhpArchitecture\Parser\Grammar\Definition\EventListener\RuleMatchedEventListener;
use PhpArchitecture\Parser\Matching\Event\Contract\ParsingEvent;
use PhpArchitecture\Parser\Matching\Event\Contract\ParsingEventListener;
use PhpArchitecture\Parser\Processing\Event\Tokenization\Contract\TokenizationEvent;
use PhpArchitecture\Parser\Processing\Event\Tokenization\Contract\TokenizationEventListener;
use PhpArchitecture\Parser\Shared\Hash\HashClosure;

class EventSubscriber
{
    use HashClosure;

    /** 
     * @param class-string<TokenizationEvent|ParsingEvent> $eventClassName
     */
    public function __construct(
        public readonly string $eventClassName,
        public readonly Closure|TokenizationEventListener|ParsingEventListener $listener,
        public ?string $onlyForRuleName = null,
        public int $priority = 0,
    ) {}

    /**
     * @param TokenizationEventListener|ParsingEventListener|callable(TokenizationEvent $event, TokenizationContext $context):void $listener
     */
    public static function on(string $eventClassName, TokenizationEventListener|ParsingEventListener|callable $listener): self
    {
        if (is_callable($listener)) {
            $listener = Closure::fromCallable($listener);
        }

        $priority = 0;
        $onlyForRuleName = null;

        if ($listener instanceof RuleMatchedEventListener) {
            $onlyForRuleName = $listener->rule();
        }

        if ($listener instanceof TokenizationEventListener || $listener instanceof ParsingEventListener) {
            $priority = $listener->priority();
        }

        return new self(
            eventClassName: $eventClassName,
            listener: $listener,
            onlyForRuleName: $onlyForRuleName,
            priority: $priority,
        );
    }

    public function onlyForRuleName(string $ruleName): self
    {
        $this->onlyForRuleName = $ruleName;
        return $this;
    }

    public function priority(int $priority): self
    {
        $this->priority = $priority;
        return $this;
    }

    /**
     * Generate unique hash for deduplication.
     * Note: Closures with same code but different scope will have different hashes.
     */
    public function hash(): string
    {
        $listenerHash = match (true) {
            $this->listener instanceof TokenizationEventListener,
            $this->listener instanceof ParsingEventListener => spl_object_hash($this->listener),
            $this->listener instanceof Closure => $this->hashClosure($this->listener),
            default => 'unknown',
        };

        return hash('xxh128', implode('|', [
            $this->eventClassName,
            $listenerHash,
            $this->onlyForRuleName ?? '',
            (string) $this->priority,
        ]));
    }
}
