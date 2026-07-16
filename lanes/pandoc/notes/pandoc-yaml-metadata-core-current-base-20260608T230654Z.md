# YAML metadata core current-base: explicit typed mapping child scalars

Micro-slice: `pandoc-yaml-metadata-core-current-base-20260608T230654Z`
Base accepted HEAD: `10431f580294803a3ec23f7a211f80a2fb3c9659`

## Behavior

`MarkdownReader` now applies explicit YAML core scalar tags before implicit
typing when a block mapping value is supplied by indented child scalar lines.
This keeps the source contract aligned with the accepted sequence-item behavior:

```yaml
review:
  source-revision: !!str
    007
  priority: !!int
    0x2A
  approved: !!bool
    true
  captured-at: !!timestamp
    2026-06-08 12:34:56Z
  withdrawn: !!null
    reviewer note is intentionally nulled
```

The resulting metadata preserves `source-revision` as the string `007`, coerces
the tagged int/bool/timestamp/null child values, and records typed provenance on
the mapping-value paths such as `/review/priority`.

## Source Truth And Non-Overlap

- Source truth: Pandoc YAML/front-matter metadata behavior for explicit core
  scalar tags, bounded to the native PHP `MarkdownReader` metadata parser and
  existing WordPress YAML handoff.
- No current `port-pandoc-*.needs-lane-rework.md` note existed before editing.
- This follows the accepted explicit typed nested sequence scalar slice without
  repeating it: the new behavior is specifically for block mapping values whose
  scalar source is supplied by indented child lines.
- This does not overlap accepted YAML block-scalar tags, sequence-item tags,
  explicit null keys, alias diagnostic paths, stream override diagnostics, top-
  level flow mapping, quoted ambiguous field names, directive boundary
  diagnostics, or writer-side special-float quoting.
- No Pandoc, Cabal solver/build/test command, Haskell runner, external YAML
  parser, online service, live provider test, or live-service provider test was
  executed.

## Evidence

- Baseline focused: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 4172 assertions, 0 failures`
- Red-first focused after adding the test:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 4174 assertions, 1 failures`
  - Failure: explicit `!!str` mapping child scalar `007` was parsed as integer
    `7`.
- Final focused: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 4202 assertions, 0 failures`
- WordPress smoke: `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`
- PHP lint:
  - `php -l lanes/pandoc/src/MarkdownReader.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`
  - Result: no syntax errors detected.
- JSON validation: `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json`
  decoded successfully with `JSON_THROW_ON_ERROR`.
- Diff check: `git diff --check -- lanes/pandoc`
  - Result: passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json`: `phpPass` `1953 -> 1954`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`: `benchmarkDenominator.mapped`
  `2374 -> 2375`.
- Added inventory keys:
  - `mappedYamlMetadataExplicitTypedMappingChildScalarCases`: `1`
  - `yamlMetadataExplicitTypedMappingChildScalarAssertions`: `30`

## Dependency Closure

No new support component is needed. This reuses the native PHP Markdown/YAML
metadata parser, existing scalar tag/provenance helpers, focused MarkdownReader
tests, and the WordPress YAML metadata handoff example. Full upstream runner
parity remains gated on a hydrated pinned upstream checkout and reviewed
non-mutating Cabal plan.

## Follow-Up

A non-overlapping YAML follow-up could cover explicit tag handling for nested
collection-valued mapping children or additional YAML 1.2 directive/provenance
diagnostics.
