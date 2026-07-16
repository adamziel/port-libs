# YAML metadata core current-base: explicit typed block scalars

Micro-slice: `pandoc-yaml-metadata-core-current-base-20260608T222315Z`
Base accepted HEAD: `638c2a05c9464741270d591f95240e54d5519ba1`

## Behavior

`MarkdownReader` now records `yaml-typed-scalar` provenance when an explicit YAML core scalar tag is applied to a parsed literal, folded, or multiline scalar value. This closes the provenance gap for metadata such as:

```yaml
review:
  approved: !!bool >-
    true
  priority: !!int |-
    0x2A
  captured-at: !!timestamp >-
    2026-06-08 10:15:30Z
  withdrawn: !!null |-
    reviewer note is intentionally nulled
```

The values were already coerced; this slice makes the audit trail match the existing single-line explicit scalar behavior. Invalid explicit numeric block scalars still remain visible as source strings and do not get typed provenance.

## Source truth and non-overlap

- Source truth: Pandoc YAML metadata/front-matter behavior for core scalar tags, bounded to the native PHP `MarkdownReader` metadata parser and existing WordPress YAML handoff.
- No current `port-pandoc-*.needs-lane-rework.md` note existed before editing.
- This does not overlap the accepted YAML explicit null-key, alias diagnostic path, top-level flow mapping, quoted ambiguous field, or indented document-marker scalar slices.
- No Pandoc, Cabal, Haskell runner, external YAML parser, online service, live provider test, or live-service provider test was executed.

## Evidence

- Red-first: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 4124 assertions, 1 failures`
  - Failure: the new explicit typed block scalar test expected 4 typed provenance entries and found 0.
- Final focused: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 4144 assertions, 0 failures`
- WordPress smoke: `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`
- Syntax:
  - `php -l lanes/pandoc/src/MarkdownReader.php` -> no syntax errors
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php` -> no syntax errors
  - `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php` -> no syntax errors
- JSON validation:
  - `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`
- Whitespace:
  - `git diff --check -- lanes/pandoc`
  - Result: no output
- Root harness: not run - isolated micro-slice.

## Status delta

- `lanes/pandoc/lane-status.json`: `phpPass` `1921 -> 1922`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`: `benchmarkDenominator.mapped` `2344 -> 2345`.
- Added inventory keys:
  - `mappedYamlMetadataExplicitTypedBlockScalarCases`: `1`
  - `yamlMetadataExplicitTypedBlockScalarAssertions`: `36`

## Dependency closure

No new support component is needed. This reuses the native PHP Markdown/YAML metadata parser, existing scalar provenance records, and the lane-local WordPress YAML metadata handoff smoke. Full upstream runner parity remains gated on a hydrated pinned upstream checkout and a reviewed non-mutating Cabal plan.

## Follow-up

A non-overlapping YAML follow-up could cover explicit core scalar tags on nested block sequence items or broader YAML 1.2 tag/provenance diagnostics.
