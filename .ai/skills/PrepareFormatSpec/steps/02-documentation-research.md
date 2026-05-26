## Step 5: Initial Documentation Search

After analyzing the format structure and tokens, search for official documentation.

### Purpose

- Provides authoritative references for format specification
- Enables verification of token patterns and grammar rules
- Documents the sources used for schema design decisions

### Why This Step Comes After Structure Analysis

Before this step, we could only rely on examples we observed "in the wild". After decomposing the format into structures and tokens, we can now:
- Search for specific features we identified
- Verify our understanding against official specs
- Find edge cases we might have missed

### Actions

Search for official documentation using queries like:
- "{format} RFC specification"
- "{format} official documentation"
- "{format} ECMA standard"

### Structure

Add a section at the very beginning of the file, after the title:

```markdown
# {Format}

## Official Documentation & Specifications

### {Category}

| Document | Description |
|----------|-------------|
| [{Name}]({URL}) | {Brief description} |
```

### Categories to Include

1. **Core Standards** - RFCs, ECMA, ISO standards
2. **Extended Variants** - JSON5, JSONC, etc.
3. **Vendor Documentation** - Microsoft, Apple, Google, GitHub
4. **Community Resources** - Dedicated websites, GitHub repos

---

## Step 6: Group Links by Variant

Organize found links by format variant within the family.

### Structure

```markdown
## Official Documentation & Specifications

### {Variant 1} (e.g., JSON RFC 8259)

| Document | Description |
|----------|-------------|
| ... | ... |

### {Variant 2} (e.g., JSON5)

| Document | Description |
|----------|-------------|
| ... | ... |
```

**Why?** Each variant may have different specifications, vendors, and community resources.

---

## Step 7: Extend Each Variant

For each variant, search for additional documentation:

1. **Version history** - Previous RFCs/standards for the same variant
2. **Vendor implementations** - Microsoft, Apple, Google, GitHub docs
3. **Community specs** - Dedicated websites, GitHub repos
4. **Related standards** - Referenced specifications (e.g., ECMA-262 for IdentifierName)

**Search queries per variant:**
- "{variant} Microsoft documentation"
- "{variant} specification GitHub"
- "{variant} RFC version history"

---

## Step 8: Verify Links with curl

Run curl/wget for each link to verify accessibility. Add status badge to each link.

```bash
curl -s -o /dev/null -w "%{http_code}" {URL}
```

**Add status column to link tables:**
```markdown
| Status | Document | Description |
|--------|----------|-------------|
| ✅ 200 | [Link](url) | Description |
| ❌ 404 | [Link](url) | Description (BROKEN) |
| ⚠️ 301 | [Link](url) | Description (redirect) |
```

**Note:** Do not remove broken links automatically - mark them for user review.

---

## Step 9: Sort Variants and Identify Conflicts

Order variants from most basic to most extended:

```markdown
Variants sorted from most basic to most extended: **JSON** → **JSONC** → **JSON5**
```

**Sorting criteria:**
1. Base standard first (RFC, ECMA)
2. Minimal extensions next (e.g., JSONC adds only comments)
3. Full extensions last (e.g., JSON5 adds many features)

### Identify Variant Conflicts

Some variants may be mutually exclusive or have conflicting features. Document these as separate **extends pipelines**.

**Example: No conflicts (JSON family)**
```
JSON → JSONC → JSON5 (linear extension, no conflicts)
```

**Example: With conflicts (hypothetical)**
```
BaseFormat
├── VariantA → VariantA2 (pipeline 1)
└── VariantB → VariantB2 (pipeline 2, conflicts with A)
```

**When to document conflicts:**
- Different syntax for same feature (mutually exclusive)
- Incompatible parser requirements
- One variant explicitly forbids what another allows

**JSON family status:** No known conflicts. JSONC is a subset of JSON5 features (comments only vs full extensions).

---

## Step 10: Add Variant Descriptions

Add a description paragraph (100-1000 characters) for each variant explaining:
- What it is and how it differs from base
- Why it was created
- Where it is commonly used

**Example:**
```markdown
### JSONC - JSON with Comments

JSONC (JSON with Comments) extends standard JSON by allowing single-line (`//`) 
and multi-line (`/* */`) comments. It was popularized by Microsoft for Visual 
Studio Code configuration files (settings.json, launch.json, tasks.json). 
JSONC is commonly used in developer tooling where human-readable configuration 
with inline documentation is needed.
```

---

## Step 11: Sort Links by Importance/Recency

Within each variant, order links by importance and recency:

**Sorting criteria:**
1. **Current standard first** - RFC 8259 before RFC 7159
2. **Formal specification before overview** - spec.json5.org before json5.org
3. **Actively maintained first** - VS Code docs before community drafts
4. **Obsoleted documents last** - Mark with date and "obsoleted" note

**Add dates to descriptions:**
```markdown
| ✅ 200 | [RFC 8259](url) | Current standard (**Dec 2017**) |
| ✅ 200 | [RFC 7159](url) | Previous spec (Mar 2014, **obsoleted**) |
```

### Guidelines

1. **Prioritize official sources** - RFCs > ECMA > Vendor docs > Community
2. **Include PDF links** - For offline reference
3. **Note obsolete versions** - Mark superseded standards with dates
4. **Link to GitHub repos** - For specification source files
5. **Cover all vendors** - Don't miss Microsoft, VS Code, etc.
6. **Verify all links** - Add status badges
7. **Sort by complexity** - Base to extended variants
8. **Sort by importance** - Current/active first, obsoleted last

---

## Step 12: AI Recommendations

Before asking the user for verification, AI should provide recommendations.

**Add a recommendations section to the {family}.md file:**

```markdown
### AI Recommendations (Step 12 - Requires User Verification)

Based on the criteria from SCHEMA_BUILDING_GUIDE.md:

| Link | Recommendation | Reason |
|------|----------------|--------|
| {Link Name} | ✅ KEEP | {reason} |
| {Link Name} | ⚠️ CONSIDER REMOVING | {reason} |

**Summary:**
- ✅ KEEP: X links
- ⚠️ CONSIDER REMOVING: Y links

**User action required:** Verify links and confirm/override recommendations above.
```

### Understanding Variants vs Versions

- **Variants** - Different flavors of the same format (JSON, JSON5, JSONC)
- **Versions** - Evolution of the same variant (PHP 5.x, 7.x, 8.x)

**Keep documentation for all variants and versions you intend to support.**

### When to REMOVE a Link

- **Superseded whitepaper** - Newer document improves/corrects the old description
- **Duplicate of same content** - Two URLs pointing to identical specification
- **Broken link** - Returns 404 or is inaccessible
- **Too general** - Not specific enough to help schema design
- **Unofficial summary** - When official source exists

### When to KEEP a Link

- **Different variant** - JSON vs JSON5 vs JSONC (keep all)
- **Different version** - YAML 1.0 vs 1.1 vs 1.2 (keep all if supporting multiple)
- **Primary specification** - RFC, ECMA, ISO standards
- **Improved description** - Newer whitepaper that clarifies the format
- **Grammar/token reference** - Useful for lexer design
- **Vendor-specific behavior** - If format behaves differently in specific environments

### Decision Matrix

| Situation | Action |
|-----------|--------|
| RFC 7159 vs RFC 8259 (same format, better description) | Keep RFC 8259, remove RFC 7159 |
| JSON vs JSON5 (different variants) | Keep both |
| YAML 1.1 vs YAML 1.2 (different versions) | Keep both if supporting both |
| Community blog vs Official RFC | Keep RFC, remove blog |
| Two RFCs for same topic (one obsoletes other) | Keep current, mark obsolete |

---

## Step 13: ⛔ STOP - REQUIRE USER VERIFICATION

# ⛔ STOP

**AI MUST STOP HERE AND WAIT FOR USER VERIFICATION.**

Do NOT proceed to the next step without explicit user confirmation.

### Actions Required (User)

1. **Review AI recommendations** - Check if reasoning is correct
2. **Click each link** - Verify it loads correctly
3. **Override if needed** - AI may be wrong about some links
4. **Confirm or modify** - Tell AI what to do

### Verification Checklist

```markdown
## Link Verification

- [ ] All links are accessible (no 404s)
- [ ] No duplicate content between sources
- [ ] Sources are authoritative (official > community)
- [ ] All links are relevant to format implementation
- [ ] Obsolete standards are marked or removed
```

**User command:** Say "execute recommendations" or provide specific overrides to continue.

---

## Step 14: Execute Recommendations on User Request

After user reviews AI recommendations:

1. **User confirms** - AI executes recommended removals
2. **User overrides** - AI adjusts based on user feedback
3. **User already made changes** - AI SKIPS this step entirely

**⚠️ CRITICAL: If the user states they have already made the changes themselves, DO NOT execute any recommendations. Skip directly to Step 15.**

**Command:** User says "execute recommendations" or similar to trigger this step. If user says they already did it - SKIP.

---

## Step 15: Remove AI Recommendations Section

After execution, clean up the temporary AI Recommendations section from the file.

This keeps the documentation clean - only final curated links remain.

---

