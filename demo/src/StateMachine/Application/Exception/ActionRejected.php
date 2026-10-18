<?php

declare(strict_types=1);

namespace PhpArchitecture\Demo\StateMachine\Application\Exception;

use DomainException;

/** The user tried something the simulated world does not allow right now (e.g. taking a card that is not there). */
final class ActionRejected extends DomainException
{
}
