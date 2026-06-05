# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260605T010830Z`

Base accepted HEAD: `45191b59b4fb1c20807d32f46883380ec91d8f21`

## Behavior Added

- Extended the native `MarkdownReader` YAML metadata subset to fold multiline
  single-quoted scalars.
- Preserves YAML doubled single quotes as one apostrophe while keeping
  backslashes literal, so `\n`, `\source`, and URI/path fragments inside
  single quotes are not decoded as double-quoted escapes.
- Applies to root metadata fields, block sequence scalar values, nested map
  values, sequence-of-map reference titles, and flow list/map scalar values.
- Updated the WordPress YAML metadata handoff smoke so reviewer notes using
  single quotes, literal `#` markers, and backslash paths survive the import
  review packet.

## Source Truth

Pandoc's `yaml_metadata_block` contract treats metadata blocks as YAML and
interprets string scalars as Markdown where relevant. This slice ports the
bounded YAML single-quoted scalar behavior needed for native front-matter
metadata handoff. Source-truth reference remains the Pandoc User's Guide
`yaml_metadata_block` contract recorded by earlier YAML notes:
`https://pandoc.org/demo/example2.html#extension-yaml_metadata_block`.

## Verification

- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2472 assertions, 0 failures`
- Red-first check after adding the single-quoted scalar expectation:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: failed on the expected folded title value with `1 test files,
    2473 assertions, 1 failures`
- `php -l lanes/pandoc/src/MarkdownReader.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2486 assertions, 0 failures`
  - Delta: `+14` focused assertions and `+1` focused PASS line.
- `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 5007 assertions, 0 failures`
- `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`
  - Result: `json ok`
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: 489 -> 490.
- Manifest mapped native checks: 962 -> 963.
- `mappedYamlMetadataSingleQuotedMultilineCases`: 0 -> 1.
- `yamlMetadataSingleQuotedMultilineAssertions`: 0 -> 14.

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
anchors, aliases, merge keys, explicit tags, comments, block-scalar chomping,
single-line double-quoted escape decoding, or multiline double-quoted scalar
folding. It owns only bounded YAML multiline single-quoted scalar folding,
doubled single-quote decoding, and literal backslash/hash handling in metadata
values. It also does not touch accepted Markdown/HTML reader/writer behavior,
CSL/BibTeX, DOCX/ODT/EPUB3, legacy DOC/CFB, ZIP/OPC, doctemplate, table
geometry, Math/TeX, archive compression, XML/HTML5 DOM, charset/Unicode, or
PDF engine handoff support.

## Follow-Up

Keep writer-side YAML emission, full YAML schema resolution, custom tag
semantics, complex flow comments, multi-document YAML streams, and full
upstream runner dependency planning as separate bounded slices.
