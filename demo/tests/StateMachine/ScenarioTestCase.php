<?php

declare(strict_types=1);

namespace PhpArchitecture\Demo\Tests\StateMachine;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Drives a scenario through its HTTP API the way the page does: every call is a separate
 * request, the Execution is persisted in the session in between.
 */
abstract class ScenarioTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    abstract protected function scenario(): string;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->act('reset');
    }

    /**
     * @param array<string,mixed> $payload
     *
     * @return array<string,mixed> snapshot
     */
    protected function act(string $action, array $payload = []): array
    {
        $this->client->jsonRequest('POST', "/state-machine/api/{$this->scenario()}", ['action' => $action, 'payload' => $payload]);
        self::assertResponseIsSuccessful((string) $this->client->getResponse()->getContent());

        return $this->snapshot();
    }

    /** @param array<string,mixed> $payload */
    protected function rejected(string $action, array $payload = []): string
    {
        $this->client->jsonRequest('POST', "/state-machine/api/{$this->scenario()}", ['action' => $action, 'payload' => $payload]);
        self::assertResponseStatusCodeSame(422);

        return json_decode((string) $this->client->getResponse()->getContent(), true)['error'];
    }

    /** @return array<string,mixed> */
    protected function tick(int $seconds): array
    {
        return $this->act('tick', ['seconds' => $seconds]);
    }

    /** @return array<string,mixed> */
    protected function snapshot(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true)['snapshot'];
    }

    /**
     * Boxes holding a pointer.
     *
     * @param array<string,mixed> $snapshot
     *
     * @return list<string>
     */
    protected static function at(array $snapshot): array
    {
        return array_map(static fn (array $pointer): string => $pointer['box'], $snapshot['pointers']);
    }

    /** @param array<string,mixed> $snapshot */
    protected static function logContains(array $snapshot, string $text): bool
    {
        foreach ($snapshot['log'] as $entry) {
            if (str_contains($entry['text'], $text)) {
                return true;
            }
        }

        return false;
    }
}
