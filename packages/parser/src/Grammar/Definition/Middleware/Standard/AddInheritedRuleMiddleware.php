<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Grammar\Definition\Middleware\Standard;

use Closure;
use PhpArchitecture\Parser\Grammar\Definition\Middleware\AddRuleMiddleware;
use PhpArchitecture\Parser\Grammar\Definition\Region;
use PhpArchitecture\Parser\Grammar\Definition\Rule;

final class AddInheritedRuleMiddleware extends AddRuleMiddleware
{
    public function __construct(
        Region $region,
    ) {
        parent::__construct(
            Closure::fromCallable(
                static function (Rule $rule) use ($region): Rule {
                    foreach ($rule->inheritedRuleDefs as $newRule) {
                        $region->addRule($newRule, true);
                    }

                    return $rule;
                },
            ),
            100,
        );
    }
}
