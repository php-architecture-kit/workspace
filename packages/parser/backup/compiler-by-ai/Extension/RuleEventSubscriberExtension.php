<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Compiled\Extension;

use PhpArchitecture\Parser\Grammar\Definition\Grammar;

/**
 * Moves EventSubscribers from Rule.eventSubscribers to their parent Region.
 * This allows rules to declare their own event handlers inline.
 */
final class RuleEventSubscriberExtension implements CompilerExtensionInterface
{
    public function apply(Grammar $grammar): void
    {
        $allRegions = $grammar->getAllRegions();

        foreach ($allRegions as $region) {
            foreach ($region->rules as $rule) {
                if (empty($rule->eventSubscribers)) {
                    continue;
                }

                foreach ($rule->eventSubscribers as $subscriber) {
                    $region->add($subscriber);
                }
            }
        }
    }

    public function priority(): int
    {
        return 100;
    }
}
