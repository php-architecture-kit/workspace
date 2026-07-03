<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Git\Gitmodules;

use PhpArchitecture\Parser\Foundation\Parsing\Contract\NodeInterface;
use PhpArchitecture\Parser\Foundation\Parsing\Contract\Placement;
use PhpArchitecture\Parser\Foundation\Parsing\Model\Attribute\Node\GroupAttribute;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeOrigin;
use PhpArchitecture\Parser\Foundation\Parsing\Model\SequenceNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\EmptyLineNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\InlineWsNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\LeadingWsNode;
use PhpArchitecture\Parser\Infrastructure\Grammar\ParsedTree\Technical\Whitespace\TrailingWsNode;

class GitmodulesNode extends SequenceNode
{
    /** @var GroupAttribute<EmptyLineNode|LineCommentNode|LeadingWsNode|TrailingWsNode|InlineWsNode> */
    public GroupAttribute $trivia { get => $this->attributes[0]; }

    /** @var GroupAttribute<SectionNode> */
    public GroupAttribute $sections { get => $this->attributes[1]; }

    public static function create(): self
    {
        return new self(
            name: 'gitmodules',
            origin: NodeOrigin::Sequence,
            attributes: [
                new GroupAttribute('trivia', []),
                new GroupAttribute('sections', []),
            ],
            parent: null,
        );
    }

    public function addNodeToTrivia(EmptyLineNode|LineCommentNode|LeadingWsNode|TrailingWsNode|InlineWsNode $node, Placement $placement = Placement::After, int $offset = -1): self
    {
        $this->trivia->addNode($node->setParent($this), $placement, $offset);
        return $this;
    }

    /** @return array<EmptyLineNode|LineCommentNode|LeadingWsNode|TrailingWsNode|InlineWsNode> */
    public function getNodesFromTrivia(?callable $filter = null): array
    {
        return $this->trivia->getNodes($filter);
    }

    public function removeNodeFromTriviaByOffset(int $offset): self
    {
        $this->trivia->removeNodeByOffset($offset);
        return $this;
    }

    /** @param callable(NodeInterface):bool $filter true - stay, false - remove */
    public function removeNodeFromTriviaByFilter(callable $filter): self
    {
        $this->trivia->removeNodeByFilter($filter);
        return $this;
    }

    public function addNodeToSections(SectionNode $node, Placement $placement = Placement::After, int $offset = -1): self
    {
        $this->sections->addNode($node->setParent($this), $placement, $offset);
        return $this;
    }

    /** @return array<SectionNode> */
    public function getNodesFromSections(?callable $filter = null): array
    {
        return $this->sections->getNodes($filter);
    }

    public function removeNodeFromSectionsByOffset(int $offset): self
    {
        $this->sections->removeNodeByOffset($offset);
        return $this;
    }

    /** @param callable(SectionNode):bool $filter true - stay, false - remove */
    public function removeNodeFromSectionsByFilter(callable $filter): self
    {
        $this->sections->removeNodeByFilter($filter);
        return $this;
    }
}
