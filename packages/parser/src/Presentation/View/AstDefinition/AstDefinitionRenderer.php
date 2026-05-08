<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Presentation\View\AstDefinition;

use Closure;
use ReflectionClass;
use ReflectionEnum;
use ReflectionFunction;
use ReflectionNamedType;
use ReflectionObject;
use ReflectionProperty;
use Symfony\Component\Console\Output\OutputInterface;
use BackedEnum;
use Throwable;
use UnitEnum;

/**
 * Generic AST definition renderer without semantic knowledge of classes.
 * Uses reflection to dump structure 1:1 as Definition classes.
 */
class AstDefinitionRenderer
{
    /** @var array<string, true> */
    private array $visitedObjects = [];

    /** @var array<string, string> */
    private array $objectReferences = [];
    private int $objectCounter = 0;

    public function __construct(
        private readonly OutputInterface $output,
        private readonly bool $showClosureBody = true,
        private readonly int $maxDepth = 10,
        private readonly string $projectRootDir = '/home/patryk_baszak/development/github/php-architecture-kit/workspace',
    ) {}

    public function render(object $rootDefinition, string $definitionName, ?string $sourceInfo = null): void
    {
        $this->visitedObjects = [];
        $this->objectReferences = [];
        $this->objectCounter = 0;

        $this->renderRoot($definitionName, $sourceInfo, $rootDefinition);
    }

    private function renderRoot(string $definitionName, ?string $sourceInfo, object $object): void
    {
        $className = $object::class;
        $shortClassName = $this->getShortClassName($className);

        $sourceSuffix = $sourceInfo !== null ? ' <fg=gray>from ' . $sourceInfo . '</>' : '';
        $this->writeln('<fg=bright-white>' . $definitionName . '</> <fg=cyan>(' . $shortClassName . ')</>' . $sourceSuffix, 0);

        $reflClass = new ReflectionClass($object);

        foreach ($reflClass->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $propName = $property->getName();
            $value = $property->getValue($object);

            $this->renderValue($propName, $value, 1);
        }
    }

    private function renderObject(string $name, object $object, int $depth, bool $isReference = false): void
    {
        if ($depth > $this->maxDepth) {
            $this->writeln($name . ': <fg=yellow>[max depth reached]</>', $depth);
            return;
        }

        $objectId = $this->getObjectId($object);

        // Check if object already visited - cycle detection
        if (isset($this->visitedObjects[$objectId])) {
            $ref = $this->objectReferences[$objectId] ?? 'unknown';
            $this->writeln($name . ': <fg=cyan>[ref: ' . $ref . ']</>', $depth);
            return;
        }

        // Mark as visited and assign reference
        $this->visitedObjects[$objectId] = true;
        $this->objectCounter++;
        $refName = $name . '#' . $this->objectCounter;
        $this->objectReferences[$objectId] = $refName;

        $className = $object::class;
        $shortClassName = $this->getShortClassName($className);

        $indent = str_repeat('  ', $depth);
        $refSuffix = $isReference ? ' <fg=cyan>(ref)</>' : '';
        $this->output->writeln($indent . '<fg=yellow>' . $name . '</>: <fg=cyan>' . $shortClassName . '</> <fg=gray>(' . $className . ')' . $refSuffix);

        $reflClass = new ReflectionClass($object);

        foreach ($reflClass->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $propName = $property->getName();
            $value = $property->getValue($object);

            $this->renderValue($propName, $value, $depth + 1);
        }
    }

    private function renderValue(string $name, mixed $value, int $depth): void
    {
        $indent = str_repeat('  ', $depth);
        $keyColored = '<fg=yellow>' . $name . '</>';

        if ($value === null) {
            $this->output->writeln($indent . $keyColored . ': <fg=gray>null</>');
            return;
        }

        if (is_bool($value)) {
            $this->output->writeln($indent . $keyColored . ': <fg=bright-cyan>' . ($value ? 'true' : 'false') . '</>');
            return;
        }

        if (is_int($value) || is_float($value)) {
            $this->output->writeln($indent . $keyColored . ': <fg=bright-blue>' . $value . '</>');
            return;
        }

        if (is_string($value)) {
            $display = strlen($value) > 50 ? substr($value, 0, 47) . '...' : $value;
            $this->output->writeln($indent . $keyColored . ': <fg=bright-green>"' . $display . '"</>');
            return;
        }

        if (is_array($value)) {
            if (empty($value)) {
                $this->output->writeln($indent . $keyColored . ': <fg=gray>[]</>');
                return;
            }

            $this->output->writeln($indent . $keyColored . ': <fg=magenta>array[' . count($value) . ']</>');
            foreach ($value as $key => $item) {
                $this->renderValue('[' . $key . ']', $item, $depth + 1);
            }
            return;
        }

        if ($value instanceof Closure) {
            $this->renderClosure($name, $value, $depth);
            return;
        }

        if ($value instanceof BackedEnum) {
            $this->output->writeln($indent . $keyColored . ': <fg=bright-cyan>' . $value->name . '</> = <fg=bright-green>"' . $value->value . '"</>');
            return;
        }

        if ($value instanceof UnitEnum) {
            $this->output->writeln($indent . $keyColored . ': <fg=bright-cyan>' . $value->name . '</>');
            return;
        }

        if (is_object($value)) {
            $this->renderObject($name, $value, $depth);
            return;
        }

        // Fallback for other types
        $type = gettype($value);
        $this->output->writeln($indent . $keyColored . ': <fg=red>[' . $type . ']</>');
    }

    private function renderClosure(string $name, Closure $closure, int $depth): void
    {
        try {
            $reflFunc = new ReflectionFunction($closure);

            $params = [];
            foreach ($reflFunc->getParameters() as $param) {
                $paramStr = '';
                $type = $param->getType();
                if ($type instanceof ReflectionNamedType) {
                    $paramStr .= $type->getName() . ' ';
                }
                $paramStr .= '$' . $param->getName();
                if ($param->isOptional() && $param->isDefaultValueAvailable()) {
                    $default = $param->getDefaultValue();
                    $paramStr .= ' = ' . var_export($default, true);
                }
                $params[] = $paramStr;
            }

            $signature = 'fn(' . implode(', ', $params) . ')';

            $file = $reflFunc->getFileName();
            $line = $reflFunc->getStartLine();
            $location = $file !== false ? ' at ' . $this->makeRelativePath($file) . ':' . $line : '';

            $indent = str_repeat('  ', $depth);
            $this->output->writeln($indent . '<fg=yellow>' . $name . '</>: <fg=magenta>' . $signature . '</> <fg=gray>' . $location . '</>');

            if ($this->showClosureBody && $file !== false && $reflFunc->getEndLine() !== false) {
                $this->renderClosureBody($reflFunc, $depth + 1);
            }
        } catch (Throwable $e) {
            $indent = str_repeat('  ', $depth);
            $this->output->writeln($indent . '<fg=yellow>' . $name . '</>: <fg=magenta>Closure</> <fg=red>[error: ' . $e->getMessage() . ']</>');
        }
    }

    private function renderClosureBody(ReflectionFunction $reflFunc, int $depth): void
    {
        $file = $reflFunc->getFileName();
        $startLine = $reflFunc->getStartLine();
        $endLine = $reflFunc->getEndLine();

        if ($file === false || $startLine === false || $endLine === false) {
            return;
        }

        try {
            $lines = file($file);
            if ($lines === false) {
                return;
            }

            // Get function lines (0-indexed)
            $bodyLines = array_slice($lines, $startLine - 1, $endLine - $startLine + 1);

            // Remove function declaration at start and brace at end
            $firstLine = array_key_first($bodyLines);
            $lastLine = array_key_last($bodyLines);

            // Find start of function body
            $bodyStart = 0;
            for ($i = $firstLine; $i <= $lastLine; $i++) {
                if (strpos($bodyLines[$i], '{') !== false) {
                    $bodyStart = $i + 1;
                    break;
                }
            }

            // Find end of function body
            $bodyEnd = $lastLine;
            for ($i = $lastLine; $i >= $firstLine; $i--) {
                $trimmed = rtrim($bodyLines[$i]);
                if (str_ends_with($trimmed, '}')) {
                    $bodyEnd = $i - 1;
                    break;
                }
            }

            if ($bodyStart <= $bodyEnd) {
                $body = array_slice($bodyLines, $bodyStart - $firstLine, $bodyEnd - $bodyStart + 1);
                $bodyText = implode('', $body);

                // Indent matching depth
                $indent = str_repeat('  ', $depth + 1);
                $lines = explode("\n", rtrim($bodyText));

                $this->writeln('<fg=gray>{</>', $depth + 1);
                foreach ($lines as $line) {
                    if (trim($line) === '') {
                        continue;
                    }
                    $this->output->writeln($indent . '<fg=gray>' . rtrim($line) . '</>');
                }
                $this->writeln('<fg=gray>}</>', $depth + 1);
            }
        } catch (Throwable $e) {
            $this->writeln('<fg=gray>[unable to read closure body: ' . $e->getMessage() . ']</>', $depth + 1);
        }
    }

    private function writeln(string $text, int $indentLevel): void
    {
        $indent = str_repeat('  ', $indentLevel);
        $this->output->writeln($indent . $text);
    }

    private function getObjectId(object $object): string
    {
        return spl_object_id($object) . ':' . $object::class;
    }

    private function getShortClassName(string $className): string
    {
        $parts = explode('\\', $className);
        return end($parts);
    }

    private function makeRelativePath(string $absolutePath): string
    {
        if (str_starts_with($absolutePath, $this->projectRootDir)) {
            return substr($absolutePath, strlen($this->projectRootDir) + 1);
        }
        return $absolutePath;
    }
}
