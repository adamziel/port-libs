# YAML Metadata Writer Special Float Scalars

Slice: `pandoc-yaml-metadata-core-current-base-20260608T214832Z`
Base accepted HEAD: `de56150306796ff6c39d1f6214abe62da3666962`
Date: 2026-06-08 UTC

## Behavior

Native `MarkdownWriter` now treats YAML special float spellings as ambiguous
plain scalars when the metadata value is a string. String metadata values such
as `.inf`, `+.INF`, `-.Inf`, `.NaN`, `-.inf`, and `+.nan` are emitted as
quoted YAML strings so that the existing `MarkdownReader` does not parse them
back as PHP `INF` or `NAN` values on round-trip.

The guard is limited to YAML metadata writer emission. Ordinary strings such
as `safe.inf` remain plain scalars.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  passed with `1 test files, 4061 assertions, 0 failures`.
- Final: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  passed with `1 test files, 4079 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  passed.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This slice does not repeat accepted YAML front-matter parsing, anchors, aliases,
merge keys, custom tag expansion, directive provenance, sexagesimal-looking
string quoting, or document-marker scalar handling. It adds writer-side
round-trip preservation for special-float-looking string metadata only.

## Dependency Closure

No new native PHP support component is needed. The implementation reuses the
existing MarkdownWriter YAML metadata serializer, MarkdownReader YAML scalar
parser, and WordPress YAML metadata handoff example.

No Pandoc, Cabal solver/build/test command, Haskell runner, external YAML
parser, zip/unzip, office suite, online service, live provider test, or
live-service provider test was executed.

## Next Task

A non-overlapping YAML follow-up can target bounded schema compatibility for
additional ambiguous writer scalars, nested collection provenance, or YAML
metadata handoff into citation/bibliography consumers.
