# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260605T170657Z`

Base accepted HEAD: `4cc4c34e199d77834513eab45aee0fc3c1d75619`

## Behavior Added

- Extended the native `MarkdownReader` YAML metadata subset to fold unquoted
  plain multiline scalar continuations.
- Covers nested map values, sequence item scalar continuations, reference
  metadata values, and the WordPress YAML metadata handoff smoke.
- Non-blank continuation lines fold to spaces; blank continuation boundaries
  remain visible as newline boundaries for reviewer metadata.

## Source Truth

Pandoc's `yaml_metadata_block` extension treats front matter as YAML before it
is converted into document metadata. YAML plain scalars fold continuation line
breaks rather than preserving raw source newlines. This slice ports only that
bounded scalar-continuation behavior for native metadata handoff.

No local hydrated Pandoc upstream checkout was present under
`.upstream-cache/pandoc`. No Pandoc binary, Cabal build, Haskell runner, or
external YAML parser was executed.

## Verification

- Rework notes:
  `ls -1 /home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md 2>/dev/null`
  - Result: no current Pandoc rework note was present.
- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2946 assertions, 0 failures`.
- Direct pre-edit behavior probe:
  `php <<'PHP' ... MarkdownReader YAML plain continuation probe ... PHP`
  - Result: nested `review.note` metadata retained `Keep reviewer\nplain continuation`.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2959 assertions, 0 failures`.
  - Delta: `+13` focused assertions and `+1` focused PASS case.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`.
- PHP lint:
  `php -l lanes/pandoc/src/MarkdownReader.php`
  `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`
  - Result: no syntax errors detected.

Root harness not run - isolated micro-slice.

## Status Delta

- Manifest mapped native checks: `1466 -> 1467`.
- Lane status `phpPass`: `1012 -> 1013`.
- Added `mappedYamlMetadataPlainMultilineScalarCases: 1`.
- Added `yamlMetadataPlainMultilineScalarAssertions: 13`.

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
sequence items, plain spaced mapping-key parsing, plain colon-bearing flow
keys, or ambiguous top-level field-name diagnostics. It owns only plain
multiline scalar continuation folding in YAML metadata values.

It also does not touch accepted Markdown/HTML reader/writer behavior,
CSL/BibTeX, DOCX/ODT, EPUB3, legacy DOC/CFB, ZIP/OPC, doctemplate, table
geometry, Math/TeX, archive compression, XML/HTML5 DOM, charset/Unicode,
syntax-highlighting, or PDF engine handoff support.

## Follow-Up

Keep multi-document YAML stream handling, writer-side YAML emission, full YAML
schema validation, path-aware tag provenance, quoted ambiguous top-level field
policy, and full upstream runner dependency planning as separate bounded
slices.
