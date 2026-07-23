<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Foundation\Shared\Hash;

use Closure;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\EventSubscriber;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Region;
use UnitEnum;
use ReflectionClass;

final class RegionHasher
{
    use HashClosure;
    private array $visiting = [];

    /**
     * Structural hash of any value object. `Region` gets its dedicated, circular-ref-safe
     * export (see exportRegion()); everything else (e.g. a compiled `CompiledRegion`) falls
     * through to the generic reflection-based exportObject(), via export()'s dispatch — so this
     * can be used to compare two `Region` definitions, two compiled `CompiledRegion`s, or any
     * other value object, with `meta`/`tags` excluded the same way in both cases (mutable
     * runtime state, not structural content).
     */
    public function hash(object $value): string
    {
        $this->visiting = [];
        return hash('xxh128', $this->export($value));
    }

    public function describe(object $value): string
    {
        $this->visiting = [];
        return $this->export($value);
    }

    private function exportRegion(Region $region): string
    {
        $oid = spl_object_id($region);
        if (isset($this->visiting[$oid])) {
            return '#circular:Region:' . $region->name;
        }
        $this->visiting[$oid] = true;

        // Middleware hash by class name only — $mw->hash() uses hashClosure which calls
        // spl_object_hash() on captured Region instances, making it unstable across grammar instantiations
        $middlewareClasses = [];
        foreach ($region->middlewares as $group) {
            foreach ($group as $mw) {
                $middlewareClasses[] = get_class($mw);
            }
        }
        sort($middlewareClasses);

        $subscriberHashes = array_map(
            fn(EventSubscriber $sub) => $this->exportEventSubscriber($sub),
            $region->eventSubscribers,
        );
        sort($subscriberHashes);

        $props = [
            'config'           => $this->exportObject($region->config),
            'eventSubscribers' => implode(',', $subscriberHashes),
            'middlewareClasses' => implode(',', $middlewareClasses),
            'name'             => $region->name,
            'regions'          => $this->exportArray($region->regions),
            'rules'            => $this->exportArray($region->rules),
        ];

        unset($this->visiting[$oid]);

        return 'Region::' . var_export($props, true);
    }

    private function export(mixed $value): string
    {
        return match(true) {
            $value instanceof Region          => $this->exportRegion($value),
            $value instanceof Closure         => $this->hashClosure($value),
            $value instanceof Grammar         => $value->name . ':' . $value->variant,
            $value instanceof EventSubscriber => $this->exportEventSubscriber($value),
            $value instanceof UnitEnum        => get_class($value) . '::' . $value->name,
            is_object($value)                 => $this->exportObject($value),
            is_array($value)                  => $this->exportArray($value),
            default                           => var_export($value, true),
        };
    }

    private function exportEventSubscriber(EventSubscriber $sub): string
    {
        return hash('xxh128', implode('|', [
            $sub->eventClassName,
            $sub->onlyForRuleName ?? '',
            (string) $sub->priority,
        ]));
    }

    private function exportObject(object $obj): string
    {
        $oid = spl_object_id($obj);
        if (isset($this->visiting[$oid])) {
            return '#circular:' . get_class($obj);
        }
        $this->visiting[$oid] = true;

        $reflection = new ReflectionClass($obj);
        $props = [];
        foreach ($reflection->getProperties() as $prop) {
            if (in_array($prop->getName(), ['meta', 'tags'], true)) {
                continue; // mutable runtime state, not part of grammar structure
            }
            $prop->setAccessible(true);
            if (!$prop->isInitialized($obj)) {
                continue;
            }
            $props[$prop->getName()] = $this->export($prop->getValue($obj));
        }
        ksort($props);
        $result = get_class($obj) . '::' . var_export($props, true);

        unset($this->visiting[$oid]);
        return $result;
    }

    private function exportArray(array $arr): string
    {
        ksort($arr);
        $parts = [];
        foreach ($arr as $k => $v) {
            $parts[] = var_export($k, true) . '=>' . $this->export($v);
        }
        return '[' . implode(',', $parts) . ']';
    }
}
