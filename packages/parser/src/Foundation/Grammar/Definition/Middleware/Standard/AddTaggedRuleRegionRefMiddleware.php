<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Grammar\Definition\Middleware\Standard;

use Closure;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Middleware\AddRuleMiddleware;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Model\Technical\TaggedRule;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Region;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;

final class AddTaggedRuleRegionRefMiddleware extends AddRuleMiddleware
{
    public function __construct(
        Region $region,
    ) {
        parent::__construct(
            Closure::fromCallable(
                static function (Rule $rule) use ($region): Rule {
                    if ($rule->definition instanceof TaggedRule) {
                        $rule->definition->setTaggedRulesSourceRegion($region);
                    }

                    return $rule;
                },
            ),
            90,
        );
    }
}
