<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Technical;

use Closure;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\EventSubscriber;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\GrammarOrigin;
use PhpArchitecture\Parser\Foundation\Shared\Meta\MetaInterface;
use PhpArchitecture\Parser\Foundation\Tokenization\Contract\TokenizationContext;
use PhpArchitecture\Parser\Foundation\Tokenization\Event\TokenRegionEndedEvent;

class Indentation extends Whitespace
{
    public const FORMAT = "technical";
    public const VARIANT = "indentation";

    public const META_INDENT_TEXT = 'indentation.text';
    public const META_INDENT_WIDTH = 'indentation.width';
    public const META_INDENT_LEVEL = 'indentation.level';
    public const META_INDENT_DELTA = 'indentation.delta';
    public const META_INDENT_MISMATCH = 'indentation.mismatch';
    public const CONTEXT_INDENT_STACK = 'indentation.stack';

    public function grammar(): Grammar
    {
        $grammar = parent::grammar();
        $regions = $grammar->getAllRegions();

        $regions['whitespace']->addEventSubscriber(
            EventSubscriber::on(TokenRegionEndedEvent::class, $this->indentationTrackingListener())
                ->priority(-100),
        );

        $grammar->stampOrigin(new GrammarOrigin(self::FORMAT, self::VARIANT), false, ['whitespace']);

        return $grammar;
    }

    /**
     * Width of one indentation unit found in $rawText. Each character (space or
     * tab) counts as one unit — no tab-stop/column expansion. Override in a
     * subclass for format-specific semantics (e.g. rejecting tabs entirely).
     */
    protected function measureIndentWidth(string $rawText): int
    {
        return mb_strlen($rawText);
    }

    /**
     * Resets the running indent-level stack, e.g. when a consuming grammar
     * enters a nested indentation-scoped sub-region (embedded language block).
     */
    public function resetIndentStack(TokenizationContext $context, int $baseline = 0): void
    {
        $context->setMeta(self::CONTEXT_INDENT_STACK, [$baseline]);
    }

    public static function indentLevelOf(MetaInterface $nodeOrRegion): ?int
    {
        return $nodeOrRegion->getMeta(self::META_INDENT_LEVEL);
    }

    /**
     * Not static, unlike Whitespace's own rename listener: it must dispatch to
     * $this->measureIndentWidth() so a subclass override is actually honored.
     *
     * Runs at priority -100, strictly after Whitespace's priority-0 rename
     * listener on the same 'whitespace' region and the same event (higher
     * priority runs first, so a lower number than Whitespace's default runs
     * later), so it only ever sees the region under its final resolved name.
     * Filtering on 'leadingWs' excludes emptyLine/trailingWs/inlineWs for
     * free — a run of whitespace only counts as indentation when it truly
     * starts a fresh line (i.e. was preceded by a real newline), which
     * Whitespace's own renaming logic already determined.
     */
    private function indentationTrackingListener(): Closure
    {
        return function (TokenRegionEndedEvent $event, TokenizationContext $context): void {
            if ($event->region->name !== 'leadingWs') {
                return;
            }

            $rawText = (string) $event->region;
            if ($rawText === '') {
                // Artifact of `bof` itself being tagged `_ws`: when a document
                // starts with zero leading whitespace, the region contains only
                // the raw-empty bof token yet still gets renamed to leadingWs.
                // Not real indentation — a genuine column-0 line never opens a
                // whitespace region at all, since there's no token to trigger it.
                return;
            }

            $width = $this->measureIndentWidth($rawText);

            $stack = $context->getMeta(self::CONTEXT_INDENT_STACK, [0]);
            $delta = 0;
            $mismatch = false;

            if ($width > end($stack)) {
                $stack[] = $width;
                $delta = 1;
            } elseif ($width < end($stack)) {
                while (count($stack) > 1 && $width < end($stack)) {
                    array_pop($stack);
                    $delta--;
                }
                if (end($stack) !== $width) {
                    // Dedent doesn't land on any previously pushed level; resync
                    // to the nearest enclosing level below $width and flag it.
                    // A consuming grammar decides whether this is an error.
                    $mismatch = true;
                }
            }

            $context->setMeta(self::CONTEXT_INDENT_STACK, $stack);

            $event->region->setMeta(self::META_INDENT_TEXT, $rawText);
            $event->region->setMeta(self::META_INDENT_WIDTH, $width);
            $event->region->setMeta(self::META_INDENT_LEVEL, count($stack) - 1);
            $event->region->setMeta(self::META_INDENT_DELTA, $delta);
            $event->region->setMeta(self::META_INDENT_MISMATCH, $mismatch);
        };
    }
}
