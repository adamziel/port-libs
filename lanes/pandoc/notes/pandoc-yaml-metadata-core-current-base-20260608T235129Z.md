# YAML metadata core current-base: flow implicit tagged keys

Micro-slice: `pandoc-yaml-metadata-core-current-base-20260608T235129Z`
Base accepted HEAD: `d882dae9d858147bc44d510727ef5cac23951c50`

## Behavior

`MarkdownReader` now normalizes implicit custom tags on YAML flow mapping keys before inserting metadata. A key such as:

```yaml
flow-review: {!reviewer owner: Import Desk, !source%2Fkey "source:key": metadata value}
```

now stores `owner` and `source:key` instead of leaking raw `!reviewer owner`-style keys, while `yamlMetadataTagProvenance` is retargeted to the normalized metadata paths.

## Source truth and non-overlap

- Source truth: Pandoc YAML metadata/front-matter behavior for tag directives and mapping keys, bounded to the native PHP `MarkdownReader` metadata parser and existing WordPress YAML handoff.
- No current `port-pandoc-*.needs-lane-rework.md` note existed before editing.
- This does not overlap the accepted YAML explicit null-key, explicit typed block-scalar, alias diagnostic path, top-level flow mapping, quoted ambiguous field, or indented document-marker scalar slices.
- No Pandoc, Cabal, Haskell runner, external YAML parser, online service, live provider test, or live-service provider test was executed.

## Evidence

- Red-first probe before implementation:
  - Result: `{!reviewer owner: Import Desk}` leaked a raw `!reviewer owner` metadata key.
- Baseline focused: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 4218 assertions, 0 failures`
- Final focused: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 4235 assertions, 0 failures`
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

- `lanes/pandoc/lane-status.json`: `phpPass` `1988 -> 1989`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`: `benchmarkDenominator.mapped` `2406 -> 2407`.
- Added inventory keys:
  - `mappedYamlMetadataFlowImplicitTaggedKeyCases`: `1`
  - `yamlMetadataFlowImplicitTaggedKeyAssertions`: `17`

## Dependency closure

No new support component is needed. This reuses the native PHP Markdown/YAML metadata parser, existing `%TAG` directive expansion, key-directive normalization, and the lane-local WordPress YAML metadata handoff smoke. Full upstream runner parity remains gated on a hydrated pinned upstream checkout and a reviewed non-mutating Cabal plan.

## Follow-up

A non-overlapping YAML follow-up could cover quoted explicit tagged flow keys, tagged alias paths inside flow sequences, or schema-version-specific YAML tag resolution.
