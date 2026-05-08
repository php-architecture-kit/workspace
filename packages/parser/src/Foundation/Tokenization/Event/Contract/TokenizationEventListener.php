<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Tokenization\Event\Contract;

use PhpArchitecture\Parser\Foundation\Tokenization\Contract\TokenizationContext;

interface TokenizationEventListener
{
    public function handle(TokenizationEvent $event, TokenizationContext $context): void;
    public function priority(): int;
}
