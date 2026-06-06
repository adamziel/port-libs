# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260606T153653Z`

Base accepted HEAD: `cdc1ad2ba331b2145aba60331aca87c783d5f08e`

## Behavior Added

- Added an additive `meta.abstractBlocks` handoff for YAML `abstract` metadata.
- `meta.abstract` remains the raw YAML scalar/list value for existing template
  and writer behavior.
- The derived `abstractBlocks` value parses the abstract body through the
  native Markdown reader into block AST nodes, with YAML metadata extraction
  disabled for that nested parse.
- The WordPress YAML metadata handoff example now renders those abstract
  blocks through `WordPressBlockWriter` for reviewer output.

## Source Truth

The lane already treats Pandoc YAML metadata as document metadata and preserves
the `abstract` field as a raw scalar. Pandoc default-template resources in this
lane expose `$abstract$` in multiple writer families, so richer conversion needs
a block-level native representation for abstract content rather than only a
plain scalar. This slice ports that bounded support-library contract without
running Pandoc or an external YAML parser.

No local hydrated Pandoc upstream checkout was present under this worktree or
the upstream cache. No Pandoc binary, Cabal build, Haskell runner, or external
YAML parser was executed.

## Verification

- Current-base rework notes:
  `compgen -G "/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md"`
  - Result: no current Pandoc rework note was present.
- Red-first after adding the focused abstract-block test:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 3231 assertions, 1 failures`.
  - Failure: `abstractBlocks` was empty while `meta.abstract` was preserved.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 3238 assertions, 0 failures`.
  - Delta: `+7` focused assertions and `+1` focused PASS case.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`.

Root harness not run - isolated micro-slice.

## Status Delta

- Lane status `phpPass`: `1356 -> 1357`.
- Added one focused native YAML metadata PASS case for Markdown-parsed
  abstract block handoff.

## Dependency Closure

No new support component is needed. This extends the existing native PHP
`MarkdownReader` YAML metadata path and reuses `WordPressBlockWriter` for the
visible WordPress handoff. It does not invoke Pandoc, Cabal, Haskell test
binaries, external YAML libraries or parsers, Word, LibreOffice, `zip`,
`unzip`, external template engines, TeX/PDF engines, browser renderers, roff,
Typst, MathJax, KaTeX, online sanitizers, online services, live provider tests,
or live-service provider tests.

## Non-Overlap

This patch does not repeat accepted YAML block placement, omitted opening
markers, fenced-code exclusion, JSON-object metadata parsing, top-level flow
mapping documents, ordinary flow-map metadata, multiline flow collection
balancing, flow comments, flow quoted scalars, block-style nested sequence
metadata, compact sequence maps, anchors, valid aliases, alias diagnostics,
merge keys, merge-sequence precedence, explicit scalar/core tags, non-specific
tags, explicit set tags, ordered `!!omap` / `!!pairs` metadata handoff,
timestamp/binary tags, scalar block-scalar chomping, quoted scalar folding,
empty scalar null semantics, sequence block-scalar metadata, explicit mapping
keys, explicit sequence/map keys, explicit key/value entries inside flow maps,
explicit key-only entries inside flow maps, explicit mapping keys inside
sequence items, plain spaced mapping-key parsing, plain colon-bearing flow
keys, ambiguous top-level field-name diagnostics, quoted ambiguous field-name
preservation, or plain multiline scalar continuation folding. It owns only the
derived block-level handoff for YAML `abstract` metadata.

## Follow-Up

- Decide whether Markdown writer YAML round-trip output should serialize a
  block-level abstract view directly or continue writing only raw
  `meta.abstract`.
- Add reference-definition-aware abstract block parsing if a later slice needs
  abstract content to resolve references declared outside the abstract scalar.
