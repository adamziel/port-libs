# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260604T152933Z`

Base accepted HEAD: `1782768c0ac1c501998f3191c1bdf114edeb557b`

## Behavior Added

- Extended the native `MarkdownReader` YAML metadata subset with a metadata-block
  scoped anchor registry.
- Preserved bounded YAML aliases for scalar, list, and map metadata values,
  including aliases inside block sequences and flow maps.
- Added bounded YAML merge-key handling for map values so reviewer defaults and
  citation/reference maps can be merged before explicit keys override them.
- Accepted explicit local tags and tag URI forms by stripping the tag and parsing
  the underlying bounded value.
- Preserved `!!str`, `!str`, and `tag:yaml.org,2002:str` scalar tags as strings
  so revision-like values such as `007` are not coerced to integers.
- Updated the WordPress YAML metadata handoff smoke so reviewer defaults, label
  aliases, and tagged source revisions are exercised on the user-visible import
  path.

## Source Truth

Pandoc's `yaml_metadata_block` contract treats metadata as YAML, including
nested lists, maps, and scalar values, with string metadata interpreted as
Markdown where relevant. This slice ports only the bounded YAML anchor, alias,
merge, and tag behavior needed for native metadata handoff. Source-truth
reference remains the Pandoc User's Guide section recorded by earlier YAML
notes: `https://pandoc.org/demo/example2.html#extension-yaml_metadata_block`.

## Verification

- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2413 assertions, 0 failures`
- `php -l lanes/pandoc/src/MarkdownReader.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2435 assertions, 0 failures`
  - Delta: `+22` focused assertions and `+1` focused PASS line.
- `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `10 test files, 3214 assertions, 0 failures`
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
template engines, TeX/PDF engines, browser renderers, roff, Typst, or online
services.

## Non-Overlap

This patch does not repeat accepted YAML placement, fenced-code exclusion,
JSON-object metadata, flow-map metadata, block-style nested sequence metadata,
Markdown/HTML reader/writer behavior, CSL citation rendering, DOCX/ODT package
parsing, legacy DOC/CFB extraction, ZIP/OPC package primitives, doctemplate
support, table geometry, Math/TeX conversion, archive compression, or PDF
engine handoff planning. It owns only the bounded YAML anchor, alias, merge-key,
and explicit-tag metadata value path.

## Follow-Up

Keep writer-side YAML emission, richer YAML schema/type resolution beyond the
bounded tags handled here, BibTeX/BibLaTeX parsing, CSL style XML/locale
processing, and full upstream runner dependency planning as separate bounded
slices.
