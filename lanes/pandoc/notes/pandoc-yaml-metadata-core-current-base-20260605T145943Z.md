# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260605T145943Z`

Base accepted HEAD: `81b84babdd61b331d5901a0b90ef852fe876f63a`

## Behavior Added

- Added bounded YAML alias diagnostics to the native `MarkdownReader`
  front-matter parser.
- Unresolved aliases, self-referential aliases, chained unresolved aliases,
  flow-map aliases, and nested metadata aliases now remain visible as
  document-level `yamlMetadataDiagnostics` entries.
- Literal alias scalar values are still preserved in user metadata, so
  WordPress review packets can show the original source value while audit
  tooling can separately flag the alias issue.
- Updated the WordPress YAML metadata handoff smoke so user-visible import
  review packets cover self-referential and unresolved alias diagnostics
  without external YAML libraries.

## Source Truth

Pandoc's `yaml_metadata_block` extension treats front matter as YAML before it
is converted into document metadata. YAML aliases depend on previously defined
anchors; unresolved or self-referential alias-shaped metadata should not be
silently normalized into ordinary values in the bounded PHP handoff. This slice
ports only the safe diagnostic handoff needed by native metadata import and
does not claim full YAML representation-graph parity.

The local hydrated upstream Pandoc checkout was not present at
`.upstream-cache/pandoc`, matching the lane's current upstream-runner
dependency blocker. No Pandoc binary, Cabal build, Haskell runner, or external
YAML parser was executed.

## Verification

- Current-base rework notes:
  `rg --files /home/claude/port-libs/.tmux-team/tmp/handoff-candidates -g 'port-pandoc-*.needs-lane-rework.md'`
  - Result: only stale May 2025 Pandoc rework notes under `stale/`; no
    current-base rework note for this session.
- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2864 assertions, 0 failures`.
- Baseline example smoke:
  `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2893 assertions, 0 failures`.
  - Delta: `+29` focused assertions and `+1` focused PASS case.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`.

Root harness not run - isolated micro-slice.

## Status Delta

- Manifest mapped native checks: `1414 -> 1415`.
- Lane status `phpPass`: `959 -> 960`.
- Added `mappedYamlMetadataAliasDiagnosticCases: 1`.
- Added `yamlMetadataAliasDiagnosticAssertions: 29`.

## Dependency Closure

No new support component is needed. This extends the existing native PHP YAML
metadata subset inside `MarkdownReader` and reuses the existing AST and
WordPress block writer. It does not invoke Pandoc, Cabal, Haskell test
binaries, external YAML libraries or parsers, Word, LibreOffice, `zip`,
`unzip`, external template engines, TeX/PDF engines, browser renderers, roff,
Typst, MathJax, KaTeX, online sanitizers, or online services.

## Non-Overlap

This patch does not repeat accepted YAML block placement, fenced-code
exclusion, JSON-object metadata, ordinary flow-map metadata, multiline flow
collection balancing, flow comments, flow quoted scalars, verbatim flow tag
scanner handling, block-style nested sequence metadata, compact sequence maps,
anchors, valid aliases, ordinary merge keys, merge-sequence precedence,
explicit scalar tags, explicit integer base tags, non-specific tags, explicit
set tags, ordered `!!omap` / `!!pairs` metadata handoff, timestamp/binary tags,
comments outside flow collections, scalar block-scalar chomping, quoted scalar
folding, empty scalar null semantics, sequence block-scalar metadata, scalar
explicit mapping-key parsing, explicit sequence-key parsing in mappings,
block-form explicit map-key parsing, explicit key/value entries inside flow
maps, explicit key-only entries inside flow maps, explicit mapping keys inside
sequence items, plain spaced mapping-key parsing, plain colon-bearing flow
keys, or folded block scalars with more-indented lines. It owns only
diagnostics for alias-shaped YAML metadata values that cannot be resolved
safely.

It also does not touch accepted Markdown/HTML reader/writer behavior,
CSL/BibTeX, DOCX/ODT, EPUB3, legacy DOC/CFB, ZIP/OPC, doctemplate, table
geometry, Math/TeX, archive compression, XML/HTML5 DOM, charset/Unicode,
syntax-highlighting, or PDF engine handoff support.

## Follow-Up

Keep custom application tag provenance, multi-document YAML stream handling,
writer-side YAML emission, full YAML schema validation, and full upstream
runner dependency planning as separate bounded slices.
