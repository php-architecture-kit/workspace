# Backup: Old Compiler Architecture (2026-03-29)

This directory contains the old strategy-based compiler architecture that was replaced with the new extension-based architecture.

## What was replaced

### Old Architecture (Strategy-based)
- **GrammarCompiler.php** - Old compiler using strategy pattern
- **Strategy/** - All strategy implementations:
  - `CompilerStrategyInterface.php`
  - `CompilerStrategyProvider.php`
  - `ValidateAndFlattenStrategy.php`
  - `BuildCompiledModelsStrategy.php`
  - `ConvertClosuresToListenersStrategy.php`
  - `TopDownPhaseStrategy.php`
  - `DownTopPhaseStrategy.php`
- **Internal/WorkingRegion.php** - Mutable working region used by strategies
- **TokenizationContextBuilder.php** - Old builder that created context per region (incorrect approach)

### New Architecture (Extension-based)
- **ExtensibleGrammarCompiler.php** - New compiler using extension pattern
- **Extension/** - All extension implementations:
  - `CompilerExtensionInterface.php`
  - `DefaultExtensionProvider.php`
  - `OpenCloseRuleExtension.php` (priority 50)
  - `RuleEventSubscriberExtension.php` (priority 100)
  - `TaggedRuleExtension.php` (priority 200)
  - `DynamicTokenExtension.php` (priority 300)
  - `AncestorInheritanceExtension.php` (priority 500)
  - `GlobalInheritanceExtension.php` (priority 600)
  - `InsideGrammarExtension.php` (priority 700)
  - `DeduplicateEventSubscribersExtension.php` (priority 750)
  - `ConvertClosuresToListenersExtension.php` (priority 800)
- **TokenizationContextCompiler.php** - New compiler that creates ONE context for entire tokenization

## Why the change?

### Problems with old architecture:
1. **Poor separation of concerns** - strategies were doing too much
2. **Hard to extend** - adding new functionality required modifying existing strategies
3. **Incorrect tokenization context** - created context per region instead of one for entire tokenization
4. **Complex dependencies** - strategies had implicit ordering requirements
5. **Hard to test** - strategies were tightly coupled

### Benefits of new architecture:
1. **Single responsibility** - each extension handles one specific concern
2. **Easy to extend** - add new extension, set priority, done
3. **Explicit ordering** - priority-based execution order
4. **Better testability** - extensions are independent and easy to test
5. **Correct tokenization** - ONE context with region switching via event listeners

## Test Results

After migration:
- ✅ 70 tests passing (100%)
- ✅ 124 assertions
- ✅ JSON tokenization working correctly (1317 tokens)
- ✅ All functional and unit tests passing

## Files moved to backup

```
2026-03-29-old-compiler-architecture/
├── GrammarCompiler.php
├── Internal/
│   └── WorkingRegion.php
├── Strategy/
│   ├── BuildCompiledModelsStrategy.php
│   ├── CompilerStrategyInterface.php
│   ├── CompilerStrategyProvider.php
│   ├── ConvertClosuresToListenersStrategy.php
│   ├── DownTopPhaseStrategy.php
│   ├── TopDownPhaseStrategy.php
│   └── ValidateAndFlattenStrategy.php
├── TokenizationContextBuilder.php
└── tests/
    └── Strategy/
        └── ValidateAndFlattenStrategyTest.php
```

## Migration date
2026-03-29

## Can these files be deleted?
Yes, but keep them for reference for at least a few months to ensure the new architecture is stable.
