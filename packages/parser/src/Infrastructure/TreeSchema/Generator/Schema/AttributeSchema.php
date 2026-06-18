<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\TreeSchema\Generator\Schema;

use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\NodeAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\OptionalAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawContentAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Raw\RawRegionAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\SequenceAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Structure\StructureAttribute;

/**
 * All data about one attribute needed by the renderer to generate property hook + methods.
 */
final class AttributeSchema
{
    /** @var string[] node names for union types (NodeAttribute / OptionalAttribute / GroupAttribute / ChoiceAttribute-nodes) */
    public array $unionNodeNames = [];

    /** @var string[] choices list for ChoiceAttribute */
    public array $choicesList = [];

    /** @var RawChoiceInfo[] for ChoiceAttribute with raw choices */
    public array $rawChoices = [];

    /** @var StructuralFactoryInfo[] for GroupedAttribute */
    public array $structuralFactories = [];

    /** content node name for GroupedAttribute (e.g. "member") */
    public ?string $groupedContentNodeName = null;

    /** content attribute name in the GroupedAttribute's $attributes array (e.g. "member") */
    public ?string $groupedContentAttrName = null;

    /** for GroupedAttribute: is the content element a ChoiceAttribute wrapping a node? */
    public bool $groupedContentIsChoice = false;

    /** choices of the inner ChoiceAttribute in GroupedAttribute (when groupedContentIsChoice = true) */
    public array $groupedChoicesList = [];

    /** for StructureAttribute: fixed token content (e.g. "{", ",") */
    public ?string $structureContent = null;

    /** for RawRegionAttribute top-level (e.g. MemberNode.identifier): opener/closer */
    public ?string $rawRegionOpenerContent = null;
    public ?string $rawRegionCloserContent = null;
    public ?string $rawRegionOpenerName = null;

    /** for RawContentAttribute/RawRegionAttribute: the raw name (not anchorName) */
    public ?string $rawTokenName = null;

    /** for RawContentAttribute top-level: default content from parse output */
    public ?string $rawDefaultContent = null;

    public function __construct(
        public readonly string $propName,
        public string $attrClass,
        public int $index,
    ) {}

    public function isNodeAttribute(): bool
    {
        return $this->attrClass === NodeAttribute::class;
    }

    public function isOptionalAttribute(): bool
    {
        return $this->attrClass === OptionalAttribute::class;
    }

    public function isGroupAttribute(): bool
    {
        return $this->attrClass === GroupAttribute::class;
    }

    public function isSequenceAttribute(): bool
    {
        return $this->attrClass === SequenceAttribute::class;
    }

    public function isStructureAttribute(): bool
    {
        return $this->attrClass === StructureAttribute::class;
    }

    public function isRawContentAttribute(): bool
    {
        return $this->attrClass === RawContentAttribute::class;
    }

    public function isRawRegionAttribute(): bool
    {
        return $this->attrClass === RawRegionAttribute::class;
    }

    public function isChoiceRaw(): bool
    {
        return ($this->isRawContentAttribute() || $this->isRawRegionAttribute()) && count($this->rawChoices) > 1;
    }

    public function isChoiceNodes(): bool
    {
        return ($this->isNodeAttribute() || $this->isOptionalAttribute()) && !empty($this->choicesList);
    }
}
