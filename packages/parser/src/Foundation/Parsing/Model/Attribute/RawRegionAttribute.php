<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute;

class RawRegionAttribute extends RawContentAttribute
{
    /**
     * @param array<string,mixed> $meta
     * @param string[] $tags
     */
    public function __construct(
        public ?StructureAttribute $opener,
        public ?StructureAttribute $closer,
        string $content,
        string $name = self::DEFAULT_NAME,
        public ?string $anchorName = null,
        array $meta = [],
        array $tags = [],
    ) {
        parent::__construct($content, $name, $anchorName, $meta, $tags);
    }

    public function getName(): string
    {
        return $this->anchorName ?? $this->name;
    }

    public function __toString(): string
    {
        return implode('', [
            $this->opener === null ? '' : $this->opener->__toString(),
            $this->content,
            $this->closer === null ? '' : $this->closer->__toString(),
        ]);
    }
}
