# Clean Architecture — directory structure

This directory is the reference layout for every codebase in PHP Architecture Kit: applications,
shared business packages and libraries. The `.gitkeep` tree is the template; this document defines
what each directory means and which dependencies between them are allowed.

> **Status: draft.** The structure itself is settled. The parts marked *(interpretation)* are
> read from the template, not stated by its author yet. Confirm or correct them before
> enforcing the rules with tooling.

## 1. Archetypes

The template covers three kinds of codebase. Each one has its own layout:

| Archetype | Template path | What it is |
|---|---|---|
| **Application** | `src/`, `tests/` | A deployable application made of business modules. |
| **Shared business package** | `vendor/business/shared/` | A package internal to one organization, shared by its applications. It carries business modules and generic modules. |
| **Library** | `vendor/vendor-name/library/` | A domain-agnostic package for anyone to use. **Every `php-architecture-kit/*` package is a library.** |

## 2. Vocabulary

- **Layer**: one of seven directories with a fixed meaning (section 3).
- **BusinessModule**: a bounded context. It has its own model and use cases, and usually its own
  entry points. The template uses `BusinessModule` as a placeholder: real directories are named
  after the context (`Billing`, `Catalog`, …).
- **Module**: a generic, technical capability without a business meaning of its own (identity
  generation, money, notifications, …). Placeholder name as well.
- **Shared**: the area through which modules expose things to other modules. Nothing in `Shared`
  belongs to every module. Each `Shared/<Name>` belongs to the module of that name and is the only
  part of it other modules may see.

## 3. Layers

| Layer | Contains |
|---|---|
| **Foundation** | The same kind of code as Domain (model, value objects, rules, domain exceptions), but **more basic and reusable across domains**. It knows nothing about any particular business module. Other modules' Domain layers build on it. |
| **Domain** | The business model of one module: entities, aggregates, value objects, domain events, domain services, repository interfaces. Specific to its own domain. |
| **Contract** | The published, stable surface of a unit for its consumers: interfaces, DTOs, identifiers and integration events that other modules or packages depend on instead of internals. |
| **Application** | Use cases: commands, queries and their handlers, application services, and the ports they need from Infrastructure. Orchestrates Domain and Foundation, holds no business rules itself. |
| **Infrastructure** | Technical implementations: persistence, adapters to external libraries and services, and framework integration (see 3.1). |
| **Presentation** | Entry points and output: HTTP controllers, CLI commands, views, renderers, view models. |
| **Utility** | *(interpretation)* Technical helpers local to one business module, with no business meaning: formatters, small converters. Business rules go to Domain, reusable building blocks go to `Shared/<Module>/Foundation` or a library. |

### 3.1 Infrastructure sub-areas

| Directory | Contains |
|---|---|
| `Infrastructure/Bridge/<Library>/` | An adapter that implements a port of this unit with an external library (e.g. `Bridge/Ramsey` implements `UuidProvider` with `ramsey/uuid`). |
| `Infrastructure/Integration/<Framework>/` | Wiring for a framework: Symfony bundle, extension and configuration, Laravel service provider. This is the **composition root**: the only place allowed to reference every other layer (section 6). |

A library never puts a framework or an external library into `require`. These dependencies go
into `suggest` (and `require-dev` for tests and static analysis). Unsupported versions go into
`conflict`.

## 4. Which layers each unit may have

The table lists the layers each unit **may** have. None of them is mandatory: a directory is
created when the first class for it appears. The `.gitkeep` files only show what is allowed.

| Unit | Foundation | Domain | Contract | Application | Infrastructure | Presentation | Utility |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| Application: `src/<BusinessModule>/` | | ✓ | | ✓ | ✓ | ✓ | ✓ |
| Application: `src/Shared/<BusinessModule>/` | | | ✓ | ✓ | | | |
| Application: `src/Shared/<Module>/` | ✓ | | | | ✓ | | |
| Shared package: `src/<BusinessModule>/` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Shared package: `src/<Module>/` | ✓ | ✓ | ✓ | ✓ | ✓ | | |
| Shared package: `src/Shared/<BusinessModule>/` | | | ✓ | ✓ | | | |
| Shared package: `src/Shared/<Module>/` | ✓ | | | | ✓ | | |
| Library: `src/` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | |

What follows from the table:

- **In an application, a business module has no Contract or Foundation of its own.** What it offers
  to other modules lives in `Shared/<BusinessModule>/` (Contract, Application). Reusable building
  blocks live in `Shared/<Module>/` (Foundation, Infrastructure).
- *(interpretation)* `Shared/<BusinessModule>/Application` holds the commands, queries and handler
  interfaces that other modules may dispatch. The implementation stays in the owning business module.
- **A shared package's business module has its own Contract and Foundation.** Its Contract is the
  API the package offers to applications. Its Foundation is the base that applications' Domain
  layers extend. `Shared/` inside the package plays the same role as in an application: exchange
  between the package's own modules.
- **A library is a single module.** Layers sit directly under `src/`, with no module level. It has
  no Utility: a library *is* reusable code.

## 5. Directories and namespaces

The namespace follows the directory, PSR-4:

```
Application         src/Billing/Domain/Invoice.php          App\Billing\Domain\Invoice
                    src/Shared/Billing/Contract/InvoiceId.php App\Shared\Billing\Contract\InvoiceId
                    src/Shared/Money/Foundation/Money.php    App\Shared\Money\Foundation\Money
Library             src/Foundation/Uuid.php                  Vendor\Uuid\Foundation\Uuid
                    src/Infrastructure/Bridge/Ramsey/…       Vendor\Uuid\Infrastructure\Bridge\Ramsey\…
```

`tests/` mirrors `src/` one-to-one.

### 5.1 Libraries: implicit Foundation

A library's code is domain-agnostic by definition, so its model is Foundation. A library therefore
**may keep its Foundation code directly in the root namespace**, without a `Foundation/` directory:

```
src/SystemClock.php                         Vendor\Clock\SystemClock               (Foundation)
src/Edge/Edge.php                           Vendor\Graph\Edge\Edge                 (Foundation)
src/Infrastructure/Integration/Symfony/…    Vendor\Clock\Infrastructure\…          (Infrastructure)
```

Rules:

1. **Layer names are reserved at the root of `src/`.** A root directory named `Foundation`,
   `Domain`, `Contract`, `Application`, `Infrastructure`, `Presentation` or `Utility` is that layer.
   Every other file and directory at the root is implicit Foundation.
2. **A library uses one of the two forms, never both.** If it has `src/Foundation/`, nothing else
   besides layer directories sits at the root. If it keeps Foundation at the root, it has no
   `src/Foundation/`.
3. A flat library is **not** moved into `Foundation/` to fit the template. It keeps its namespaces,
   and new layers (e.g. `Infrastructure/Integration/`) are added next to the existing code. Adopting
   the template is therefore never a BC break.
4. Directories below the layer level should not be named after a layer (e.g. `Foundation/Grammar/Contract/`),
   because a reader or a tool cannot tell a sub-namespace from a layer.

## 6. Dependency rules

### 6.1 Between layers

A layer may depend on the layers marked in its row (inside the same unit), on any external library,
and on the Foundation and Contract layers of the units and packages it is allowed to see (6.2).

| From \ To | Foundation | Domain | Contract | Application | Infrastructure | Presentation | Utility |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| **Foundation** | ✓ | | | | | | ✓ |
| **Domain** | ✓ | ✓ | | | | | ✓ |
| **Contract** | ✓ | | ✓ | | | | |
| **Application** | ✓ | ✓ | ✓ | ✓ | | | ✓ |
| **Infrastructure** | ✓ | ✓ | ✓ | ✓ | ✓ | | ✓ |
| **Presentation** | ✓ | | ✓ | ✓ | | ✓ | ✓ |
| **Utility** | | | | | | | ✓ |

- **Infrastructure depends inward and implements ports.** It never depends on Presentation.
- **Presentation reaches use cases through Application**, and through Contract and Foundation types.
  It does not depend on Domain internals or on concrete Infrastructure classes. When it needs a
  service, it asks for an interface from Foundation, Contract or Application.
- **Exception: `Infrastructure/Integration/<Framework>/`** is the composition root. It may reference
  every layer, including Presentation (to register controllers and commands) and concrete
  Infrastructure classes.
- **Exception:** a library's Foundation may use the library's own `Infrastructure/Bridge/*`
  adapters as the defaults it ships, because a library has to work without a framework (e.g. uuid's
  `UuidProviderRegistry` lists its own Ramsey and Symfony providers).
- Utility depends only on itself, so any layer of its business module can use it.

### 6.2 Between units

- A business module **never** depends on another business module directly. It may use
  `Shared/<OtherModule>/Contract` and `Shared/<OtherModule>/Application`.
- Any module may use `Shared/<Module>/Foundation`. Only Infrastructure (and the composition root)
  may use `Shared/<Module>/Infrastructure`.
- An application or package may depend on a library's Foundation, Contract and Application layers
  from any of its own layers allowed to see those layers. It may use a library's Infrastructure
  only from its own Infrastructure, and a library's Integration only from its framework
  configuration.

## 7. Package root

```
src/        PHP code, structured as above
tests/      mirrors src/
assets/     optional: frontend code (TSX) with its own package.json, published to npm
docs/       optional: design notes
tools/      phpunit, phpstan, php-cs-fixer configuration
```

`assets/`, `docs/`, `tests/` and `tools/` are excluded from the Composer dist with
`export-ignore` in `.gitattributes`.
