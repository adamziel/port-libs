# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260605T013748Z`

Base accepted HEAD: `a2e7adb1c6fc80ae9058b353b45997b578cbedcf`

## Behavior Added

- Corrected YAML merge-key sequence precedence in the native `MarkdownReader`
  metadata subset.
- For `<<: [*override, *base]`, earlier maps now override later maps, matching
  YAML merge-key semantics while keeping explicit mapping keys higher priority
  than all merged defaults.
- Covers flow merge sequences and block sequence merge forms inside nested
  Pandoc metadata maps.
- Updated the WordPress YAML metadata handoff smoke so reviewer default packets
  preserve merge-sequence precedence before import-review metadata is exposed.

## Source Truth

Pandoc's `yaml_metadata_block` contract treats metadata blocks as YAML before
Pandoc converts metadata values into its document meta shape. This slice ports
only the bounded YAML merge-key sequence precedence needed for native
front-matter metadata handoff. Source-truth reference remains the Pandoc User's
Guide `yaml_metadata_block` contract recorded by earlier YAML notes:
`https://pandoc.org/demo/example2.html#extension-yaml_metadata_block`.

## Verification

- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2486 assertions, 0 failures`
- Red-first check after adding the merge-sequence expectation:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: failed as expected on `status` resolving to `queued` instead of
    `approved` with `1 test files, 2489 assertions, 1 failures`.
- `php -l lanes/pandoc/src/MarkdownReader.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2502 assertions, 0 failures`
  - Delta: `+16` focused assertions and `+1` focused PASS line.
- `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 5322 assertions, 0 failures`
- `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`
  - Result: `json ok`
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output after note creation.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: 507 -> 508.
- Manifest mapped native checks: 982 -> 983.
- `mappedYamlMetadataMergeSequenceCases`: 0 -> 1.
- `yamlMetadataMergeSequenceAssertions`: 0 -> 16.

## Dependency Closure

No new support component is needed. This extends the existing native PHP YAML
metadata subset inside `MarkdownReader` and reuses the existing AST and
WordPress block writer. It does not invoke Pandoc, Cabal, Haskell test
binaries, external YAML libraries, Word, LibreOffice, `zip`, `unzip`, external
template engines, TeX/PDF engines, browser renderers, roff, Typst, MathJax,
KaTeX, or online services.

## Non-Overlap

This patch does not repeat accepted YAML placement, fenced-code exclusion,
JSON-object metadata, flow-map metadata, block-style nested sequence metadata,
anchors, aliases, single-map merge keys, explicit tags, comments, block-scalar
chomping, double-quoted escape decoding, multiline double-quoted scalar
folding, or multiline single-quoted scalar folding. It owns only bounded YAML
merge-key sequence precedence for flow and block merge sequence forms. It also
does not touch accepted Markdown/HTML reader/writer behavior, CSL/BibTeX,
DOCX/ODT/EPUB3, legacy DOC/CFB, ZIP/OPC, doctemplate, table geometry,
Math/TeX, archive compression, XML/HTML5 DOM, charset/Unicode, or PDF engine
handoff support.

## Follow-Up

Keep writer-side YAML emission, full YAML schema resolution, custom tag
semantics, complex flow comments, multi-document YAML streams, and full
upstream runner dependency planning as separate bounded slices.
