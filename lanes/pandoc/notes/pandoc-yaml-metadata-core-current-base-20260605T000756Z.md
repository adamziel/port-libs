# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260605T000756Z`

Base accepted HEAD: `23ef3aeaa54ed1b30f19bf25f9b8ec5a5f9f5662`

## Behavior Added

- Extended the native `MarkdownReader` YAML metadata subset to decode bounded
  double-quoted scalar escapes.
- Handles YAML Unicode hex escapes (`\xNN`, `\uNNNN`, `\UNNNNNNNN`), control
  escapes (`\n`, `\t`, `\r`, `\a`, `\b`, `\f`, `\v`, `\e`, `\0`), escaped
  slash/quote/backslash, and YAML `\N`, `\_`, `\L`, and `\P` Unicode aliases.
- Preserves invalid or unsupported escapes literally rather than widening this
  slice into a full YAML validation layer.
- Updated the WordPress YAML metadata handoff smoke so escaped source titles
  and escaped source URI fragments survive into import-review metadata.

## Source Truth

Pandoc's `yaml_metadata_block` contract treats metadata blocks as YAML and
therefore accepts YAML double-quoted scalar escapes. This slice ports only the
bounded scalar escape behavior needed for native front-matter metadata handoff.
Source-truth reference remains the Pandoc User's Guide `yaml_metadata_block`
contract: `https://pandoc.org/demo/example2.html#extension-yaml_metadata_block`.

## Verification

- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2447 assertions, 0 failures`
- `php -l lanes/pandoc/src/MarkdownReader.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2461 assertions, 0 failures`
  - Delta: `+14` focused assertions and `+1` focused PASS line.
- `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 4454 assertions, 0 failures`
- `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`
  - Result: `json ok`
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

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
anchors, aliases, merge keys, explicit tags, comments, or block-scalar
chomping. It owns only bounded YAML double-quoted scalar escape decoding in
metadata values. It also does not touch accepted Markdown/HTML reader/writer
behavior, CSL/BibTeX, DOCX/ODT/EPUB3, legacy DOC/CFB, ZIP/OPC, doctemplate,
table geometry, Math/TeX, archive compression, XML/HTML5 DOM, charset/Unicode,
or PDF engine handoff support.

## Follow-Up

Keep writer-side YAML emission, full YAML schema resolution, custom tag
semantics, multi-line double-quoted scalar continuation, multi-document YAML
stream handling, and full upstream runner dependency planning as separate
bounded slices.
