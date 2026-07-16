# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260605T163438Z`

Base accepted HEAD: `f8c365b5b1fd87bac0411884c446464b5c9c15f7`

## Behavior Added

- Added bounded diagnostics for top-level YAML metadata field names that Pandoc
  documents as invalid because YAML can interpret them as booleans or numbers.
- `MarkdownReader` now omits top-level `yes`, `True`, `15`, `0x2A`, and
  equivalent boolean-/number-looking metadata fields from document `meta`
  instead of silently promoting them or hitting PHP integer-key coercion.
- The omitted fields are exposed as document-level `yamlMetadataDiagnostics`
  entries with `field`, `reason: ambiguous-field-name`, and `interpretedAs`
  provenance for WordPress import review queues.
- Nested reviewer-map keys are preserved, so source audit maps can still carry
  literal keys such as `true` or `15` when they are not Pandoc metadata fields.
- Updated the WordPress YAML metadata handoff smoke to expose the ambiguous
  field-name diagnostics without external YAML libraries.

## Source Truth

Pandoc's User Guide `yaml_metadata_block` section says YAML metadata fields are
added to document metadata, fields ending in `_` are ignored, and field names
must not be interpretable as YAML numbers or boolean values. Source:
`https://pandoc.org/demo/example2.html#extension-yaml_metadata_block`.

This slice ports only that top-level field-name boundary for the native PHP
handoff. It does not claim full YAML schema validation or representation-graph
parity.

No local hydrated Pandoc upstream checkout was present under
`.upstream-cache/pandoc`. No Pandoc binary, Cabal build, Haskell runner, or
external YAML parser was executed.

## Verification

- Rework notes:
  `ls -1 /home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md 2>/dev/null || true`
  - Result: no current Pandoc rework note was present.
- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2930 assertions, 0 failures`.
- Red-first after adding the focused test:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2930 assertions, 1 failures`.
  - Failure shape: `str_ends_with(): Argument #1 ($haystack) must be of type string, int given`.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2946 assertions, 0 failures`.
  - Delta: `+16` focused assertions and `+1` focused PASS case.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`.

Root harness not run - isolated micro-slice.

## Status Delta

- Manifest mapped native checks: `1454 -> 1455`.
- Lane status `phpPass`: `999 -> 1000`.
- Added `mappedYamlMetadataAmbiguousFieldNameCases: 1`.
- Added `yamlMetadataAmbiguousFieldNameAssertions: 16`.

## Dependency Closure

No new support component is needed. This extends the existing native PHP YAML
metadata subset inside `MarkdownReader` and reuses the existing AST and
WordPress YAML handoff example. It does not invoke Pandoc, Cabal, Haskell test
binaries, external YAML libraries or parsers, Word, LibreOffice, `zip`,
`unzip`, external template engines, TeX/PDF engines, browser renderers, roff,
Typst, MathJax, KaTeX, online sanitizers, or online services.

## Non-Overlap

This patch does not repeat accepted YAML block placement, fenced-code
exclusion, JSON-object metadata, ordinary flow-map metadata, multiline flow
collection balancing, flow comments, flow quoted scalars, verbatim flow tag
scanner handling, block-style nested sequence metadata, compact sequence maps,
anchors, valid aliases, alias diagnostics, ordinary merge keys,
merge-sequence precedence, explicit scalar/core tags, explicit integer base
tags, non-specific tags, explicit set tags, ordered `!!omap` / `!!pairs`
metadata handoff, timestamp/binary tags, comments outside flow collections,
scalar block-scalar chomping, quoted scalar folding, empty scalar null
semantics, sequence block-scalar metadata, explicit mapping-key parsing,
explicit sequence/map keys, explicit key/value entries inside flow maps,
explicit key-only entries inside flow maps, explicit mapping keys inside
sequence items, plain spaced mapping-key parsing, or plain colon-bearing flow
keys. It owns only ambiguous top-level YAML metadata field-name diagnostics and
the integer-key coercion guard needed to report them safely.

It also does not touch accepted Markdown/HTML reader/writer behavior,
CSL/BibTeX, DOCX/ODT, EPUB3, legacy DOC/CFB, ZIP/OPC, doctemplate, table
geometry, Math/TeX, archive compression, XML/HTML5 DOM, charset/Unicode,
syntax-highlighting, or PDF engine handoff support.

## Follow-Up

Keep multi-document YAML stream handling, writer-side YAML emission, full YAML
schema validation, path-aware tag provenance, quoted ambiguous top-level field
policy, and full upstream runner dependency planning as separate bounded
slices.
