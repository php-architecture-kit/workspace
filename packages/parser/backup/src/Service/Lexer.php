<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Service;

use Closure;

class Lexer
{
    /** @var array<string,array{pattern:string,priority:int}> Pre-compiled patterns */
    private array $compiledPatterns = [];

    /** @var array<string,int> Token name to index map for priority */
    private array $tokenIndexMap = [];

    /** @var array<string,DynamicRegex> token name => DynamicRegex */
    private array $dynamicTokensTriggers = [];

    /** @var ?Closure(Token $lastToken):bool */
    private ?Closure $earlyEscapeCallback = null;

    private int $currentRow = 1;
    private int $currentColumn = 1;

    public function __construct(
        private readonly Grammar $grammar,
        private readonly int $chunkSize = 10240, # 10KB
        private readonly int $safeMargin = 1024, # 1KB
        private readonly bool $applyBofEof = true,
    ) {
        $this->compilePatterns();
    }

    /**
     * @param ?Closure(Token $lastToken):bool $earlyEscapeCallback
     */
    public function process(
        StringStream $stream,
        ?Closure $earlyEscapeCallback = null,
        int $currentRow = 1,
        int $currentColumn = 1,
    ): TokenStream {
        $this->earlyEscapeCallback = $earlyEscapeCallback;
        $this->currentRow = $currentRow;
        $this->currentColumn = $currentColumn;

        return new TokenStream($this->doProcess($stream));
    }

    public function getCurrentRow(): int
    {
        return $this->currentRow;
    }

    public function getCurrentColumn(): int
    {
        return $this->currentColumn;
    }

    /**
     * @return array<Token>
     */
    private function doProcess(StringStream $stream): array
    {
        $tokens = $this->applyBofEof ? [Token::bof()] : [];

        $tokens = array_merge(
            $tokens,
            $this->tokenizeContent($stream)
        );

        if ($this->applyBofEof) {
            $tokens[] = Token::eof(
                position: $stream->position(),
                row: $this->currentRow,
                column: $this->currentColumn,
            );
        }

        return $tokens;
    }

    /**
     * @return Token[]
     */
    private function tokenizeContent(
        StringStream $stream
    ): array {
        $tokens = [];

        while (!$stream->isEof()) {
            $content = $stream->getChunk($this->chunkSize);
            $contentSize = strlen($content);
            $pos = 0;
            $endPos = $contentSize - ($contentSize === $this->chunkSize ? $this->safeMargin : 0);

            do {
                $currentPos = $stream->position();
                $bestMatch = $this->findBestMatch($content, $pos);

                if ($bestMatch !== null) {
                    $tokenName = $bestMatch['name'];
                    $value = $bestMatch['value'];
                    $length = strlen($value);

                    $definition = $this->grammar->token($tokenName);

                    if ($definition->innerGrammar !== null) {
                        $innerLexer = new Lexer(grammar: $definition->innerGrammar);
                        $innerTokens = $innerLexer->process(
                            stream: $stream,
                            earlyEscapeCallback: $definition->innerGrammarEscapeCallback,
                            currentRow: $this->currentRow,
                            currentColumn: $this->currentColumn,
                        );
                        $this->currentRow = $innerLexer->getCurrentRow();
                        $this->currentColumn = $innerLexer->getCurrentColumn();

                        $token = Token::withInnerTokens(
                            name: $tokenName,
                            innerTokens: $innerTokens,
                        );

                        $tokens[] = $token;

                        continue 2;
                    }

                    $startRow = $this->currentRow;
                    $startCol = $this->currentColumn;

                    $this->updatePosition($value);

                    $endRow = $this->currentRow === $startRow
                        ? $startRow
                        : (
                            str_ends_with($value, "\n")
                            ? $this->currentRow - 1
                            : $this->currentRow
                        );

                    $stream->advance($length);
                    $pos += $length;

                    $token = Token::default(
                        name: $tokenName,
                        raw: $value,
                        startPosition: $currentPos + $pos,
                        endPosition: $currentPos + $pos + $length - 1,
                        startRow: $startRow,
                        startColumn: $startCol,
                        endRow: $endRow,
                        endColumn: $this->currentColumn,
                    );

                    $tokens[] = $token;
                } else {
                    $char = mb_substr($content, $pos, 1);
                    $charLen = strlen($char);

                    if ($charLen === 0) {
                        break;
                    }

                    $token = Token::unknown(
                        character: $char,
                        position: $currentPos,
                        row: $this->currentRow,
                        column: $this->currentColumn,
                    );

                    $tokens[] = $token;

                    $this->updatePosition($char);
                    $stream->advance($charLen);
                    $pos += $charLen;
                }

                if ($this->earlyEscapeCallback !== null && ($this->earlyEscapeCallback)($token)) {
                    break 2;
                }

                if (array_key_exists($token->name, $this->dynamicTokensTriggers)) {
                    $registrationResult = false;
                    foreach ($this->dynamicTokensTriggers[$token->name] as $dynamicTokenName => $dynamicRegex) {
                        $res = $this->dynamicTokensTriggers[$token->name]->trigger($this->grammar, $token);
                        if ($res === true) {
                            $registrationResult = true;
                        }
                    }

                    if ($registrationResult) {
                        $this->compilePatterns();
                    }
                }
            } while ($pos < $endPos);
        }

        return $tokens;
    }

    /**
     * @return array{name:string,value:string}|null
     */
    private function findBestMatch(string $content, int $pos): ?array
    {
        $bestMatch = null;
        $bestLength = 0;
        $bestPriority = PHP_INT_MIN;
        $bestIndex = PHP_INT_MAX;

        foreach ($this->compiledPatterns as $tokenName => $data) {
            $pattern = $data['pattern'];
            $priority = $data['priority'];

            if (preg_match($pattern, $content, $matches, 0, $pos) === 1) {
                $matchLength = strlen($matches[0]);

                if ($matchLength === 0) {
                    continue;
                }

                $index = $this->tokenIndexMap[$tokenName];
                if (
                    $priority > $bestPriority ||
                    ($priority === $bestPriority && $matchLength > $bestLength) ||
                    ($priority === $bestPriority && $matchLength === $bestLength && $index < $bestIndex)
                ) {
                    $bestMatch = ['name' => $tokenName, 'value' => $matches[0]];
                    $bestLength = $matchLength;
                    $bestPriority = $priority;
                    $bestIndex = $index;
                }
            }
        }

        return $bestMatch;
    }

    private function compilePatterns(): void
    {
        $index = 0;

        $tokens = $this->grammar->tokens();
        usort($tokens, fn($a, $b) => $b->indexPriority - $a->indexPriority);

        foreach ($tokens as $regex) {
            $pattern = $regex->regex;
            $this->compiledPatterns[$regex->name] = [
                'pattern' => $pattern,
                'priority' => $regex->priority,
            ];

            $this->tokenIndexMap[$regex->name] = $index++;
        }

        foreach ($this->grammar->dynamicTokens() as $tokenName => $dynamicRegex) {
            foreach ($dynamicRegex->triggers as $trigger) {
                $this->dynamicTokensTriggers[$trigger][$tokenName] = $dynamicRegex;
            }
        }
    }

    /**
     * @return array{"valueEndRow":int,"valueEndColumn":int}
     */
    private function updatePosition(string $value): array
    {
        $newlines = substr_count($value, "\n");
        $valueLength = strlen($value);

        if ($newlines > 0) {
            $lastNewlinePos = strrpos($value, "\n") ?: 0;
            $lastColumn = $this->currentColumn;
            $this->currentRow += $newlines;
            $this->currentColumn = $valueLength - $lastNewlinePos;

            $endsWithNewline = str_ends_with($value, "\n");
            if ($endsWithNewline) {
                $lastNewlinePos = strrpos(substr($value, 0, -1), "\n");
                if ($lastNewlinePos === false) {
                    return [
                        'valueEndRow' => $this->currentRow - 1,
                        'valueEndColumn' => $lastColumn + $valueLength - 1,
                    ];
                }
            }

            $lastPartLength = $valueLength - $lastNewlinePos - 1;

            return [
                'valueEndRow' => $endsWithNewline ? $this->currentRow - 1 : $this->currentRow,
                'valueEndColumn' => $lastPartLength > 0 ? $lastPartLength : 1,
            ];
        }

        $this->currentColumn += $valueLength;

        return [
            'valueEndRow' => $this->currentRow,
            'valueEndColumn' => $this->currentColumn - 1,
        ];
    }
}
