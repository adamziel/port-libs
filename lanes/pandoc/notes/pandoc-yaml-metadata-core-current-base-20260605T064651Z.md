# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260605T064651Z`

Base accepted HEAD: `46c6d8a993d1695e8d7a02a45b01d28349d0a2a4`

## Behavior Added

- Extended the native `MarkdownReader` YAML metadata subset to handle explicit
  YAML core `!!timestamp` and `!!binary` scalar tags.
- Timestamp values are validated and normalized to bounded ISO-like strings
  for date-only and date-time forms, including quoted values, `Z`, and numeric
  offsets.
- Binary values decode base64 scalar values and literal block scalar payloads
  before WordPress review metadata is exposed.
- Updated the WordPress YAML metadata handoff smoke so imported front matter
  carries timestamp and decoded binary review metadata on the user-visible
  audit path.

## Source Truth

Pandoc's `yaml_metadata_block` extension treats metadata blocks as YAML before
converting values into document metadata. This slice ports only the bounded
explicit timestamp and binary scalar tag behavior needed by native front-matter
metadata handoff. Source-truth reference remains the Pandoc User's Guide
`yaml_metadata_block` contract recorded by earlier YAML notes:
`https://pandoc.org/demo/example2.html#extension-yaml_metadata_block`.

## Verification

- No current-base rework note was present:
  `ls -1 /home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md 2>/dev/null`
  - Result: no output.
- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2636 assertions, 0 failures`.
- Red-first check after adding the timestamp/binary expectation:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: failed as expected with `1 test files, 2640 assertions, 1 failures`;
    `!!timestamp 2026-06-05 06:46:51Z` was still exposed as raw YAML text.
- `php -l lanes/pandoc/src/MarkdownReader.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2651 assertions, 0 failures`.
  - Delta: `+15` focused assertions and `+1` focused PASS case.
- `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`.
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Status Delta

- Manifest mapped native checks: `1179 -> 1180`.
- Lane status `phpPass`: `719 -> 720` for the new focused PASS case.
- `mappedYamlMetadataTimestampBinaryTagCases`: `0 -> 1`.
- `yamlMetadataTimestampBinaryTagAssertions`: `0 -> 15`.

## Dependency Closure

No new support component is needed. This extends the existing native PHP YAML
metadata subset inside `MarkdownReader` and reuses the existing AST and
WordPress block writer. It does not invoke Pandoc, Cabal, Haskell test
binaries, external YAML libraries or parsers, Word, LibreOffice, `zip`,
`unzip`, external template engines, TeX/PDF engines, browser renderers, roff,
Typst, MathJax, KaTeX, or online services.

## Non-Overlap

This patch does not repeat accepted YAML block placement, fenced-code
exclusion, JSON-object metadata, flow-map metadata, multiline flow collection
balancing, flow comments, flow quoted scalars, block-style nested sequence
metadata, compact sequence maps, anchors, aliases, ordinary merge keys,
merge-sequence precedence, explicit scalar `str`/`int`/`float`/`bool`/`null`
tags, explicit set tags, comments outside flow collections, block-scalar
mapping values, quoted scalar folding, empty scalar null semantics, sequence
block-scalar metadata, or explicit mapping-key parsing. It owns only bounded
explicit `!!timestamp` and `!!binary` scalar handling for YAML metadata.

It also does not touch accepted Markdown/HTML reader/writer behavior,
CSL/BibTeX, DOCX/ODT, EPUB3, legacy DOC/CFB, ZIP/OPC, doctemplate, table
geometry, Math/TeX, archive compression, XML/HTML5 DOM, charset/Unicode,
syntax-highlighting, or PDF engine handoff support.

## Follow-Up

Keep complex non-scalar YAML mapping keys, multi-document YAML streams,
writer-side YAML emission, full YAML schema validation, and full upstream
runner dependency planning as separate bounded slices.
