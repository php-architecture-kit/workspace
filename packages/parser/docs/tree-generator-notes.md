# Notatki do stworzenia Tree Generatora

## Braki w Grammar

Grammar musi zostać rozszerzone o: 
- style, 
- wartości domyślne,

## Właściwości klasy

Właściwości klasy to hook properties stworzone na podstawie listy atrybutów dostępnej po sparsowaniu plików. Po zmerge'owaniu grammar z powstałego w wyniku parsed tree, należy je rozszerzyć na podstawie CompiledGrammar o inne dostępne alternatywy na poszczególnych node'ach.

> Czyli compiled grammar twierdzi, że na pozycji JsonNode.propertyName mogą być LeadingWsNode|TrailingWsNode|EmptyLineNode|InlineWsNode, to mimo tego, że przykładowe pliki pokazały nam tylko wariant z EmptyLineNode|LeadingWsNode, to i tak stwierdzamy, że dozwolone są wszystkie 4.

Nazwy właściwości wynikają w pierwszej kolejności z anchorName atrybutu, następnie z name.

Właściwości ustawiamy w kolejności występowania w sequence.

## Metody kreacyjne

Oryginalny __construct musi pozostać niedotknięty dla każdego node'a i atrybutu. Proces parsowania korzysta z konstruktorów, aby odtworzyć node'y z string'a.

Dla celów budowania parsed tree od strony np. AST, będziemy korzystać z metody 
create(). 

### create

Metoda statyczna create dla root node'a zawiera tylko argumenty niezbędne do utworzenia node'a i tworzy node poprzez stworzenie swojej instancji z atrybutami odpowiadającymi tym z listy właściwości.

### metody atrybutów

API klasy node'a musi udostępniać metody dostępowe i pozwalające na modyfikacje node'ów. Przy czym jak najwięcej logiki tych metod powinno znaleźć się w stałych implementacjach atrybutów jeśli to możliwe.

```php
class JsonNode extends Node
{
    /** @var GroupAttribute<LeadingWsNode|TrailingWsNode|EmptyLineNode|InlineWsNode> */
    public GroupAttribute $trivia0 { get => $this->attributes[0]; }

    /** @var ChoiceAttribute<ObjectNode|ArrayNode|PrimitiveNode> */
    public ChoiceAttribute $value { get => $this->attributes[1]; }

    /** @var GroupAttribute<LeadingWsNode|TrailingWsNode|EmptyLineNode|InlineWsNode> */
    public GroupAttribute $trivia1 { get => $this->attributes[2]; }

    public static function create(): self
    {
        $node = new self(
            name: 'json',
            attributes: [
                new GroupAttribute('trivia0', []),
                new ChoiceAttribute('value', ['object', 'array', 'primitive'], null),
                new GroupAttribute('trivia1', [])
            ],
            parent: null,
        );

        return $node;
    }
}
```

#### Metody atrybutu GroupAttribute

```php
    public function addNodeToPropertyName(LeadingWsNode|TrailingWsNode|EmptyLineNode|InlineWsNode $node, Placement $placement = Placement::After, int $offset = -1): self
    {
        $this->propertyName->addNode($node->setParent($this), $placement, $offset);

        return $this;
    }

    /**
     * @return array<LeadingWsNode|TrailingWsNode|EmptyLineNode|InlineWsNode>
     */
    public function getNodesFromPropertyName(?callable $filter = null): array
    {
        return $this->propertyName->getNodes($filter);
    }

    public function removeNodeFromPropertyNameByOffset(int $offset): self
    {
        $this->propertyName->removeNodeByOffset($offset);

        return $this;
    }

    /**
     * @param callable(NodeInterface):bool $filter true - stay, false - remove
     */
    public function removeNodeFromPropertyNameByFilter(callable $filter): self
    {
        $this->propertyName->removeNodeByFilter($filter);

        return $this;
    }
```

#### Metody atrybutu ChoiceAttribute, którego choices to NodeInterface, a cardinality = 1

to tak jakbyśmy mieli NodeAttribute, ale zagnieżdżony w ChoiceAttribute

```php
    public function getNodePropertyName(): null|ObjectNode|ArrayNode|PrimitiveNode
    {
        /** @var ?NodeAttribute $attribute */
        $attribute = $this->propertyName->selected;

        return $attribute?->node;
    }

    public function setNodePropertyName(ObjectNode|ArrayNode|PrimitiveNode $propertyName): self
    {
        $this->propertyName->setSelected(NodeAttribute::fromNode($propertyName->setParent($this)));

        return $this;
    }
```
