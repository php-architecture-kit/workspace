<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Presentation\View\Tokenization\DTO;

final class TokenStreamItemViewData
{
    public const TYPE_TOKEN        = 'token';
    public const TYPE_REGION_START = 'region_start';
    public const TYPE_REGION_END   = 'region_end';

    private function __construct(
        public readonly string $type,
        public readonly int $depth,
        public readonly string $regionName,
        public readonly ?TokenViewData $token,
        public readonly ?string $regionTags,
    ) {}

    public static function token(TokenViewData $token, int $depth, string $regionName): self
    {
        return new self(
            type: self::TYPE_TOKEN,
            depth: $depth,
            regionName: $regionName,
            token: $token,
            regionTags: null,
        );
    }

    public static function regionStart(string $name, string $tags, int $depth): self
    {
        return new self(
            type: self::TYPE_REGION_START,
            depth: $depth,
            regionName: $name,
            token: null,
            regionTags: $tags,
        );
    }

    public static function regionEnd(string $name, string $tags, int $depth): self
    {
        return new self(
            type: self::TYPE_REGION_END,
            depth: $depth,
            regionName: $name,
            token: null,
            regionTags: $tags,
        );
    }
}
