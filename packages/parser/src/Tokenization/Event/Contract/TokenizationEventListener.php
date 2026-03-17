<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Tokenization\Event\Contract;

use PhpArchitecture\Parser\Tokenization\Tokenization;

interface TokenizationEventListener
{
    public function handle(TokenizationEvent $event, Tokenization $context): void;
    public function priority(): int;
}
