# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260605T195031Z`

Base accepted HEAD: `26420dbc885ef71efa1e50b79eba618d2e4211c9`

## Behavior Added

- Added bounded quote-provenance tracking for top-level YAML metadata field
  names.
- Source-quoted boolean- and number-looking field names such as `"yes"`,
  `"Off"`, `"15"`, `"3.14"`, and `"0o52"` now remain visible as string
  metadata in the native `MarkdownReader` output.
- Plain unquoted top-level ambiguous field names still produce
  `yamlMetadataDiagnostics` and are not promoted into document metadata.
- The same top-level quote provenance is recorded for JSON-object YAML
  metadata documents, whose object member names are already source-quoted.
- The WordPress YAML metadata handoff smoke now proves quoted ambiguous
  reviewer fields remain visible without adding diagnostics.

## Source Truth

Pandoc's `yaml_metadata_block` extension treats the metadata block as YAML
before converting fields into document metadata. Pandoc documents that metadata
field names must not be interpretable as YAML booleans or numbers; quoted keys
are YAML strings, so this slice preserves only the bounded string-key path while
retaining the accepted diagnostic behavior for plain ambiguous keys.

No local hydrated Pandoc upstream checkout was present under this worktree. No
Pandoc binary, Cabal build, Haskell runner, external YAML parser, external
converter, or online service was executed.

## Verification

- Current-base rework notes:
  `ls -1 /home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md 2>/dev/null`
  - Result: no current Pandoc rework note was present.
- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2980 assertions, 0 failures`.
- Red-first after adding the focused quoted-key test:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2982 assertions, 1 failures`.
  - Failure: quoted `"yes"` was omitted as if it were an unquoted ambiguous
    boolean field.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2995 assertions, 0 failures`.
  - Delta: `+15` focused assertions and `+1` focused PASS case.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`.
- PHP lint:
  `php -l lanes/pandoc/src/MarkdownReader.php`
  `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`
  - Result: no syntax errors detected.
- JSON metadata validation:
  `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " valid\n"; }'`
  - Result: both JSON files valid.
- `git diff --check -- lanes/pandoc`
  - Result: no output.

Root harness not run - isolated micro-slice.

## Status Delta

- Manifest mapped native checks: `1511 -> 1512`.
- Lane status `phpPass`: `1058 -> 1059`.
- Added `mappedYamlMetadataQuotedAmbiguousFieldCases: 1`.
- Added `yamlMetadataQuotedAmbiguousFieldAssertions: 15`.

## Dependency Closure

No new support component is needed. This extends the existing native PHP YAML
metadata subset inside `MarkdownReader` and reuses the existing AST and
WordPress YAML handoff example. It does not invoke Pandoc, Cabal, Haskell test
binaries, external YAML libraries or parsers, Word, LibreOffice, `zip`,
`unzip`, external template engines, TeX/PDF engines, browser renderers, roff,
Typst, MathJax, KaTeX, online sanitizers, or online services.

## Non-Overlap

This patch does not repeat accepted YAML block placement, omitted opening
markers, fenced-code exclusion, JSON-object metadata parsing, ordinary flow-map
metadata, multiline flow collection balancing, flow comments, flow quoted
scalars, verbatim flow tag scanner handling, block-style nested sequence
metadata, compact sequence maps, anchors, valid aliases, alias diagnostics,
ordinary merge keys, merge-sequence precedence, explicit scalar/core tags,
explicit integer base tags, non-specific tags, explicit set tags, ordered
`!!omap` / `!!pairs` metadata handoff, timestamp/binary tags, comments outside
flow collections, scalar block-scalar chomping, quoted scalar folding, empty
scalar null semantics, sequence block-scalar metadata, explicit mapping-key
parsing, explicit sequence/map keys, explicit key/value entries inside flow
maps, explicit key-only entries inside flow maps, explicit mapping keys inside
sequence items, plain spaced mapping-key parsing, plain colon-bearing flow
keys, ambiguous plain top-level field-name diagnostics, or plain multiline
scalar continuation folding. It owns only quoted top-level ambiguous field-name
preservation for YAML metadata, plus the directly coupled WordPress smoke.

It also does not touch accepted Markdown/HTML reader/writer behavior,
CSL/BibTeX, DOCX/ODT, EPUB3, legacy DOC/CFB, ZIP/OPC, doctemplate, table
geometry, Math/TeX, archive compression, XML/HTML5 DOM, charset/Unicode,
syntax-highlighting, or PDF engine handoff support.

## Follow-Up

Keep multi-document YAML stream handling, writer-side YAML emission, path-aware
tag provenance, full YAML schema validation, and full upstream runner
dependency planning as separate bounded slices.
