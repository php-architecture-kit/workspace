# Grammar Spec Building Guide

This guide describes the step-by-step process of building a format grammar spec from scratch.

**Instructions for AI:** Follow the phases below sequentially. Each phase is defined in a separate file — read it fully before executing any step within that phase.

**⚠️ CRITICAL: EXECUTION RULES**

1. **Auto-continue by default** - If a step does NOT contain ⛔ STOP marker, automatically proceed to the next step
2. **Stop only at checkpoints** - Steps marked with ⛔ STOP require user confirmation before continuing
3. **One step at a time** - Execute exactly ONE step before moving to the next (no batching like "Steps 5-11")
4. **Report progress** - Briefly report completion after each step, then continue or wait as appropriate

This ensures efficient progress while maintaining user control at critical decision points.

---

## Phases

| Phase | Steps | File | Purpose |
|-------|-------|------|---------|
| 1 | 1–4 | [steps/01-structure-analysis.md](steps/01-structure-analysis.md) | Create the comprehensive example and document format structure groups with tokens |
| 2 | 5–15 | [steps/02-documentation-research.md](steps/02-documentation-research.md) | Find official specs, verify links, organize by variant — **⛔ STOP at Step 13** |
| 3 | 16–21 | [steps/03-format-documentation.md](steps/03-format-documentation.md) | Document encoding, variant examples, features tables — **⛔ STOP at Steps 19 and 21** |
| 4 | 22–24 | [steps/04-validation.md](steps/04-validation.md) | Validate example coverage, root values, token patterns — **⛔ STOP after Step 24** |
| 5 | 25–29 | [steps/05-implementation.md](steps/05-implementation.md) | Create test data files and PHP grammar classes |

---

## How to Start

Read the file for the current phase, then execute its steps one by one.

Start with Phase 1: [steps/01-structure-analysis.md](steps/01-structure-analysis.md)
