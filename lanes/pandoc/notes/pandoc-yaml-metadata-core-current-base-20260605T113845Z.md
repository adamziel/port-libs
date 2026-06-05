# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260605T113845Z`

Base accepted HEAD: `89a11bb00511a4462d0b1ef3a6d6ce448526fe47`

## Behavior Added

- Extended the native `MarkdownReader` YAML metadata subset to recognize
  explicit `!!omap` and `!!pairs` tags.
- Maps block-form and flow-form ordered mappings into ordered `key` / `value`
  entries so duplicate metadata keys remain visible to import audit tooling.
- Preserves ordered-pair values through anchors/aliases and nested sequence
  items.
- Updated the WordPress YAML metadata handoff smoke to cover ordered review
  metadata and duplicate reviewer keys.

## Source Truth

Pandoc's `yaml_metadata_block` extension treats front matter as YAML before it
is converted into document metadata. This slice ports only the bounded YAML
ordered mapping and pairs tag behavior needed by native metadata handoff. It
does not claim full YAML schema parity.

## Verification

- No current-base rework note was present:
  `ls -1 /home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md 2>/dev/null || true`
  - Result: no output.
- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2779 assertions, 0 failures`.
- Red-first check after adding ordered-pair expectations:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: failed as expected with `1 test files, 2783 assertions,
    1 failures`; `!!omap` entries were still exposed as ordinary maps without
    ordered `key` / `value` entries.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result after implementation: `1 test files, 2813 assertions,
    0 failures`.
  - Delta: `+34` focused assertions and `+1` focused PASS case.
- `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`.
- `php -l lanes/pandoc/src/MarkdownReader.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`
  - Result: no syntax errors.
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " valid\n"; }'`
  - Result: both JSON files valid.
- `git diff --check -- lanes/pandoc`
  - Result: no output.

Root harness not run - isolated micro-slice.

## Status Delta

- Manifest mapped native checks: `1328 -> 1329`.
- Lane status `phpPass`: `871 -> 872`.
- Added `mappedYamlMetadataOrderedPairCases: 1`.
- Added `yamlMetadataOrderedPairAssertions: 34`.

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
keys, merge-sequence precedence, existing decimal explicit scalar tags,
explicit integer base tags, explicit set tags, timestamp/binary tags, comments
outside flow collections, scalar block-scalar chomping, quoted scalar folding,
empty scalar null semantics, sequence block-scalar metadata, scalar explicit
mapping-key parsing, explicit sequence-key parsing in mappings, block-form
explicit map-key parsing, explicit keys inside flow maps, explicit mapping
keys inside sequence items, plain spaced mapping-key parsing, folded block
scalars with more-indented lines, or bare non-specific tag pass-through. It
owns only explicit `!!omap` and `!!pairs` ordered metadata handoff.

It also does not touch accepted Markdown/HTML reader/writer behavior,
CSL/BibTeX, DOCX/ODT, EPUB3, legacy DOC/CFB, ZIP/OPC, doctemplate, table
geometry, Math/TeX, archive compression, XML/HTML5 DOM, charset/Unicode,
syntax-highlighting, or PDF engine handoff support.

## Follow-Up

Keep YAML alias-cycle diagnostics, custom application tag provenance,
multi-document YAML stream handling, writer-side YAML emission, full YAML
schema validation, and full upstream runner dependency planning as separate
bounded slices.
