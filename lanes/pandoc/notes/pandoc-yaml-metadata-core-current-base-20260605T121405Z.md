# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260605T121405Z`

Base accepted HEAD: `3b99ef994373d3fa0c896d104eac78039d1beb66`

## Behavior Added

- Extended the native `MarkdownReader` YAML metadata subset to preserve
  flow-map explicit key-only entries as normalized keys with `null` values.
- Covers scalar keys, quoted scalar keys, sequence-valued keys, map-valued
  keys, and nested reference metadata flow maps such as
  `{? source, ? [source, uri], ? {owner: desk, ticket: 7}, status: approved}`.
- Updated the WordPress YAML metadata handoff smoke so reviewer audit packets
  can keep explicit null-valued source keys visible without external YAML
  libraries.

## Source Truth

Pandoc's `yaml_metadata_block` extension treats front matter as YAML before it
is converted into document metadata. YAML flow mappings permit explicit key
entries with an implicit null value. This slice ports only that bounded key-only
flow mapping behavior needed by native metadata handoff. It follows the lane's
existing policy of normalizing complex YAML metadata keys into stable string
keys for WordPress review packets and does not claim full YAML schema parity.

## Verification

- No current-base rework note was present:
  `ls -1 /home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md 2>/dev/null || true`
  - Result: no output.
- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2813 assertions, 0 failures`.
- Red-first behavior probe before edit:
  `php` stdin snippet using `MarkdownReader` on
  `review: {? source, ? "source:key", ? [source, uri], ? {owner: desk, ticket: 7}, status: approved}`
  - Result before edit: only `status => approved` survived; explicit key-only
    entries were dropped.
- `php -l lanes/pandoc/src/MarkdownReader.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result after implementation: `1 test files, 2830 assertions,
    0 failures`.
  - Delta: `+17` focused assertions and `+1` focused PASS case.
- `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`.
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " valid\n"; }'`
  - Result: both JSON files valid.
- `git diff --check -- lanes/pandoc`
  - Result: no output.

Root harness not run - isolated micro-slice.

## Status Delta

- Manifest mapped native checks: `1346 -> 1347`.
- Lane status `phpPass`: `888 -> 889`.
- Added `mappedYamlMetadataFlowExplicitNullKeyCases: 1`.
- Added `yamlMetadataFlowExplicitNullKeyAssertions: 17`.

## Dependency Closure

No new support component is needed. This extends the existing native PHP YAML
metadata subset inside `MarkdownReader` and reuses the existing AST and
WordPress block writer. It does not invoke Pandoc, Cabal, Haskell test
binaries, external YAML libraries or parsers, Word, LibreOffice, `zip`,
`unzip`, external template engines, TeX/PDF engines, browser renderers, roff,
Typst, MathJax, KaTeX, or online services.

## Non-Overlap

This patch does not repeat accepted YAML block placement, fenced-code
exclusion, JSON-object metadata, ordinary flow-map metadata, multiline flow
collection balancing, flow comments, flow quoted scalars, block-style nested
sequence metadata, compact sequence maps, anchors, aliases, ordinary merge
keys, merge-sequence precedence, explicit scalar tags, explicit integer base
tags, non-specific tags, explicit set tags, ordered `!!omap` / `!!pairs`
metadata handoff, timestamp/binary tags, comments outside flow collections,
scalar block-scalar chomping, quoted scalar folding, empty scalar null
semantics, sequence block-scalar metadata, scalar explicit mapping-key parsing,
explicit sequence-key parsing in mappings, block-form explicit map-key parsing,
explicit key/value entries inside flow maps, explicit mapping keys inside
sequence items, plain spaced mapping-key parsing, or folded block scalars with
more-indented lines. It owns only explicit key-only entries inside YAML flow
maps where the value is implicit null.

It also does not touch accepted Markdown/HTML reader/writer behavior,
CSL/BibTeX, DOCX/ODT, EPUB3, legacy DOC/CFB, ZIP/OPC, doctemplate, table
geometry, Math/TeX, archive compression, XML/HTML5 DOM, charset/Unicode,
syntax-highlighting, or PDF engine handoff support.

## Follow-Up

Keep YAML alias-cycle diagnostics, custom application tag provenance,
multi-document YAML stream handling, writer-side YAML emission, full YAML
schema validation, and full upstream runner dependency planning as separate
bounded slices.
