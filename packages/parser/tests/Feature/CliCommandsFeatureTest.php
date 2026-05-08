<?php

declare(strict_types=1);

namespace Tests\PhpArchitecture\Parser\Feature;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('feature')]
#[Group('cli')]
final class CliCommandsFeatureTest extends TestCase
{
    private string $projectRoot;
    private string $consolePath;
    private string $grammarClass;

    protected function setUp(): void
    {
        $this->projectRoot = dirname(__DIR__, 4);
        $this->consolePath = $this->projectRoot . '/bin/console';
        $this->grammarClass = 'PhpArchitecture\\Parser\\Infrastructure\\Grammar\\Definition\\Json\\JsonRfc8259';
    }

    private function runCommand(string $command, array $args = []): array
    {
        $cmdParts = [
            'php',
            escapeshellarg($this->consolePath),
            escapeshellarg($command),
        ];

        foreach ($args as $key => $value) {
            if (is_int($key)) {
                $cmdParts[] = escapeshellarg($value);
            } elseif (is_bool($value)) {
                if ($value) {
                    $cmdParts[] = escapeshellarg($key);
                }
            } else {
                $cmdParts[] = escapeshellarg($key);
                $cmdParts[] = escapeshellarg((string) $value);
            }
        }

        $cmd = implode(' ', $cmdParts);
        $output = [];
        $exitCode = 0;

        exec($cmd . ' 2>&1', $output, $exitCode);

        return [
            'exitCode' => $exitCode,
            'output' => implode("\n", $output),
        ];
    }

    #[Test]
    public function parser_grammar_view_command_works_for_json(): void
    {
        $result = $this->runCommand('parser:grammar:view', [
            $this->grammarClass,
        ]);

        self::assertSame(0, $result['exitCode'], "Command failed with output:\n" . $result['output']);
        self::assertStringContainsString('json', $result['output']);
        self::assertStringContainsString('Grammar', $result['output']);
    }

    #[Test]
    public function parser_grammar_compiled_command_works_for_json(): void
    {
        $result = $this->runCommand('parser:grammar:compiled', [
            $this->grammarClass,
        ]);

        self::assertSame(0, $result['exitCode'], "Command failed with output:\n" . $result['output']);
        self::assertStringContainsString('json', $result['output']);
        self::assertStringContainsString('Compiled Grammar', $result['output']);
        self::assertStringContainsString('rfc8259', $result['output']);
    }

    #[Test]
    public function parser_ast_definition_raw_command_works_for_json(): void
    {
        $result = $this->runCommand('parser:ast:definition', [
            $this->grammarClass,
        ]);

        self::assertSame(0, $result['exitCode'], "Command failed with output:\n" . $result['output']);
        self::assertStringContainsString('Raw AST Node Definitions', $result['output']);
    }

    #[Test]
    public function parser_ast_definition_compiled_command_works_for_json(): void
    {
        $result = $this->runCommand('parser:ast:definition', [
            $this->grammarClass,
            '--compiled',
        ]);

        self::assertSame(0, $result['exitCode'], "Command failed with output:\n" . $result['output']);
        self::assertStringContainsString('Compiled AST Node Definitions', $result['output']);
    }

    #[Test]
    public function parser_tokenize_command_works_for_json(): void
    {
        $jsonInput = '{"key": "value"}';
        $tempFile = tempnam(sys_get_temp_dir(), 'parser_test_');
        file_put_contents($tempFile, $jsonInput);

        try {
            $result = $this->runCommand('parser:tokenize', [
                $tempFile,
                $this->grammarClass,
            ]);

            self::assertSame(0, $result['exitCode'], "Command failed with output:\n" . $result['output']);
            self::assertStringContainsString('TOKENIZATION', $result['output']);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    #[Test]
    public function parser_parse_command_works_for_json(): void
    {
        $jsonInput = '{"key": "value"}';
        $tempFile = tempnam(sys_get_temp_dir(), 'parser_test_');
        file_put_contents($tempFile, $jsonInput);

        try {
            $result = $this->runCommand('parser:parse', [
                $tempFile,
                $this->grammarClass,
            ]);

            self::assertSame(0, $result['exitCode'], "Command failed with output:\n" . $result['output']);
            self::assertStringContainsString('Node:', $result['output']);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

}
