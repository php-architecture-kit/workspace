<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Processing\Event\Tokenization\Contract;

use PhpArchitecture\Parser\Processing\Context\TokenizationContext;

interface TokenizationEventListener
{
    public function handle(TokenizationEvent $event, TokenizationContext $context): void;
    public function priority(): int;
}
