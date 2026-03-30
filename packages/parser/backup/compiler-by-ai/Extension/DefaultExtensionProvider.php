<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Compiled\Extension;

/**
 * Provides default set of compiler extensions.
 * Extensions are applied in priority order (lower priority = earlier execution).
 */
final class DefaultExtensionProvider
{
    /**
     * @return CompilerExtensionInterface[]
     */
    public static function getExtensions(): array
    {
        return [
            new OpenCloseRuleExtension(),                 //  50: Process open/close rules (adds rules to regions)
            new RuleEventSubscriberExtension(),           // 100: Move Rule.eventSubscribers to Region
            new TaggedRuleExtension(),                    // 200: Resolve Rule::taggedWith()
            new DynamicTokenExtension(),                  // 300: Process Rule::dynamic()
            new AncestorInheritanceExtension(),           // 500: Apply ancestor inheritance
            new GlobalInheritanceExtension(),             // 600: Apply global inheritance
            new InsideGrammarExtension(),                 // 700: Process insideGrammar
            new DeduplicateEventSubscribersExtension(),   // 750: Deduplicate event subscribers
            new ConvertClosuresToListenersExtension(),    // 800: Convert Closures to EventListeners
        ];
    }
}
