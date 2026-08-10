# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

This is the **workspace repo** for PHP Architecture Kit — a set of independent, framework-agnostic PHP
packages (DDD/clean-architecture building blocks) that are developed together here but published as
separate Composer packages under the `php-architecture-kit/*` namespace. Each published package lives
in `packages/<name>` as a **git submodule** with its own repo, license, README, and composer.json; the
workspace wires them together via a Composer `path` repository (`packages/*`, symlinked) so cross-package
`require` entries resolve to the local checkout instead of Packagist.

Published packages (submodules, listed in `.gitmodules`): `actor`, `clock`, `domain-core`, `graph`,
`uuid`, `technical`, `state-machine`, `lazy-operators`.

Other directories under `packages/` (`address`, `clean-architecture`, `ddd`, `domain-constraint`,
`lazy-valuation`, `parser`) are **not** submodules and are not wired into the root `composer.json`
`require` — treat them as in-workspace/experimental unless told otherwise:
- `parser` is the exception worth knowing well — it's a full grammar-driven parsing engine (see below),
  autoloaded directly from the workspace and exercised by its own test suite and `bin/console` commands,
  it just isn't published as a versioned dependency yet.
- `clean-architecture` is a directory-skeleton template (mostly `.gitkeep` placeholders defining a
  BusinessModule/Shared layout), not implementation code.
- `ddd`, `address`, `domain-constraint`, `lazy-valuation` are design sandboxes; `ddd` even has its own
  nested `.git` (not a registered submodule). Don't assume they build or are covered by CI.

## Package dependency graph

```
clock ──▶ (psr/clock only)
uuid ──▶ psr/clock
actor ──▶ uuid
graph ──▶ uuid                              (PHP ^8.0)
domain-core, technical ──▶ (zero deps)
state-machine ──▶ clock, graph, domain-core, uuid, technical, psr/container   (PHP ^8.4)
parser ──▶ uuid                              (PHP ^8.0)
lazy-operators ──▶ (zero deps)               (PHP ^8.4)
```

**PHP version split matters**: most packages target `^7.4 || ^8.0`, but `state-machine` and
`lazy-operators` require `^8.4` (asymmetric visibility, readonly classes). Don't backport 8.4-only
syntax into the lower-baseline packages.

## Common commands

Run from the workspace root (`workspace/`), after `composer install`:

```bash
composer phpunit                    # run the full test suite (all packages)
bin/phpunit                         # same, direct entrypoint
bin/phpunit packages/parser/tests   # run one package's tests
bin/phpunit --filter TestClassName  # run a single test class/method
composer phpunit:coverage           # coverage (XDEBUG_MODE=coverage), writes to var/coverage/

composer code:fix                   # php-cs-fixer, apply fixes
composer code:fix:dry                # php-cs-fixer, dry-run/diff only
composer code:analyse               # phpstan level 8 against packages/*/src
composer code:analyse:b             # regenerate the phpstan baseline

composer benchmark:uuid             # phpbench for packages/uuid
```

Test suites are wired per-package in `tools/phpunit/phpunit.xml` (only submodule packages + `parser`
have suites registered there — new packages need a `<testsuite>` entry added to be picked up by
`composer phpunit`). phpstan config is `tools/phpstan/phpstan.neon` (level 8, baseline at
`tools/phpstan/baseline.neon`); php-cs-fixer config is `tools/php-cs-fixer/php-cs-fixer.php`.

Each submodule package also has its own `composer.json`/tests and can be worked on standalone from
inside `packages/<name>/`, but running tests through the workspace root is the normal path since that's
what wires the path-repository dependencies together.

## The `parser` package

`packages/parser` is a hand-rolled, grammar-driven parsing engine (tokenizer/lexer with Pratt parsing,
grammar definitions, tree schema generation) used to build format parsers (JSON RFC 8259, JSONC, JSON5,
env/dotenv, ...). It's the most architecturally involved package here — read `packages/parser/docs/`
before making non-trivial changes, especially `docs/cli-commands.md`, `docs/ast-dsl-design.md`, and the
`docs/refactor/` and `docs/plan/` notes for context on in-progress design decisions.

It exposes CLI commands via `bin/console` (registered in the workspace root's `bin/console`):

```bash
bin/console parser:grammar:view <grammar-class> [--region= --show-rules --show-tags]
bin/console parser:grammar:compiled <grammar-class> [--show-patterns --show-sequences]
bin/console parser:tokenize <grammar-class> <input-file> [--format=detailed|simple|stats]
bin/console parser:parse <grammar-class> <input-file> [--format=tree|json|simple]
bin/console parser:tree:generate <input-file>... --grammar=<FQCN> [--output=DIR]
```

Grammar classes implement `GrammarDefinitionInterface`; the built-in example is
`PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Json\JsonRfc8259`. Full option reference is in
`packages/parser/docs/cli-commands.md`.

Layered structure inside `packages/parser/src/`: `Foundation` (grammar/tokenization/matching/parsing
core, framework-agnostic), `Infrastructure` (concrete grammar definitions, tree schema generation),
`Presentation` (CLI controllers/views).

### AI skills for parser work

`.ai/skills/` contains detailed, step-by-step guides for two recurring parser tasks — **read the full
`SKILL.md` (and linked step files) before starting either of these**, don't improvise from memory:

- `PrepareFormatSpec` — multi-phase process for building a new format grammar spec from scratch
  (structure analysis → doc research → format documentation → validation → PHP grammar
  implementation), with explicit stop/checkpoint markers.
- `parser-source-files-messy` — rules for generating the `pretty`/`minified`/`messy`/`messy-2`/`messy-3`
  test fixture files under `assets/parser-source-files/<format>/<variant>/`, including trivia
  (leading/trailing whitespace & comments) requirements and the "no trailing newline" convention for
  `messy-3` files (must be written with `printf '%s'`, verified via `xxd file | tail -1`).

## The `state-machine` package

Graph-based state machine engine built on top of `graph` (transitions are directed edges, nodes are
vertices), with parallel pointers, conditional transitions (`TransitionCondition`), typed `State`
attached to an `Execution`, and pluggable transition/scheduling strategies. Designed to be resumable
across process boundaries (an `Execution` is serialized, reloaded, and re-driven by an external
trigger — HTTP/AMQP/CLI — rather than assuming a long-lived PHP process); see
`docs/workflow-ux-research.md` for the design rationale (Symfony Workflow / Temporal comparison) behind
this model, including the Task ↔ TaskHandler ↔ TransitionCondition resumption contract and the
`StateResolver` selective-persistence layer.

## Workspace layout notes

- `bin/console` at the workspace root wires together the `parser` CLI commands and the `state-machine`
  `PrintStateMachineCommand`.
- `benchmarks/uuid` holds phpbench cases for the `uuid` package (autoloaded under
  `Benchmarks\PhpArchitecture\Uuid\`).
- `.personal/` is scratch/notes content, not part of any package.
