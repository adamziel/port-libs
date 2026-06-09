# Pandoc YAML Metadata Core Current Base

Date: 2026-06-09 UTC
Micro-slice: `pandoc-yaml-metadata-core-current-base-20260609T025738Z`
Base accepted HEAD: `f3cb4f0219cafa35ccd839e4b1e650317d63e7bb`

## Behavior

`MarkdownWriter` now treats timestamp-looking YAML metadata strings as
ambiguous plain scalars and emits them quoted. This keeps native front-matter
round trips from normalizing reviewer source dates such as `2026-6-3` to
`2026-06-03` or `2026-6-3T4:05:06Z` to a padded timestamp.

The covered writer paths include top-level metadata fields, nested mapping
values, and sequence items. Non-timestamp strings such as
`release-2026-6-4` still remain plain when otherwise safe.

## Evidence

- Rework notes: no `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md` files existed before editing.
- Red probe before the fix:
  - `source-date: 2026-6-3` emitted by `MarkdownWriter` round-tripped through
    `MarkdownReader` as `2026-06-03`.
- Syntax:
  - `php -l lanes/pandoc/src/MarkdownWriter.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`
  - Result: no syntax errors.
- Focused lane test:
  - `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 4474 assertions, 0 failures`.
- WordPress smoke:
  - `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`.
- JSON validation:
  - `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `2189 -> 2190`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator:
  `2602 -> 2603`.
- Added manifest counters:
  - `mappedYamlMetadataWriterTimestampScalarCases: 1`
  - `yamlMetadataWriterTimestampScalarAssertions: 18`

## Non-Overlap

This does not repeat accepted YAML reader timestamp parsing, explicit
`!!timestamp` and `!!binary` tags, YAML 1.2 boolean/numeric/integer policy,
sexagesimal writer quoting, special-float writer quoting, comment-looking
writer quoting, colon-indicator writer quoting, block scalar writer emission,
anchors, aliases, merge diagnostics, ordered-pair diagnostics, or explicit-key
provenance. It owns only writer-side quoting for timestamp-looking string
metadata.

## Dependency Closure

No new support component is needed. This reuses the native PHP
`MarkdownWriter` YAML metadata emitter, `MarkdownReader` round-trip parser,
focused `MarkdownReaderTest.php`, and the existing WordPress YAML metadata
handoff example.

No Pandoc binary, Cabal solver/build/test command, Haskell runner, external
YAML parser, Word, LibreOffice, zip/unzip, external template engine, TeX/PDF
engine, browser renderer, online service, live provider test, or live-service
provider test was executed.

## Follow-Up

Useful non-overlapping YAML follow-ups remain writer-side ordered-pair/comment
emission, richer source-span diagnostics, and metadata consumer handoff.
