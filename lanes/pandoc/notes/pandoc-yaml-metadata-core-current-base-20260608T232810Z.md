# YAML metadata core current-base: plain tagged mapping keys

Micro-slice: `pandoc-yaml-metadata-core-current-base-20260608T232810Z`
Base accepted HEAD: `72ddd104de73563cbfd9ef3ec17976bf6afc1676`

## Behavior

`MarkdownReader` now normalizes leading YAML node directives on plain block
mapping keys before storing metadata. Source such as:

```yaml
!wp-field source:key: metadata value
review:
  !wp-field owner: Import Desk
  !wp-field "source:label": Metadata Label
  !!str 15: string numeric key
```

now produces metadata keys `source:key`, `owner`, `source:label`, and `15`
instead of literal `!wp-field ...` key names. Custom tag provenance is retargeted
to the normalized metadata path, and core key tags such as `!!str` stay internal
instead of leaking into review provenance.

## Source Truth And Non-Overlap

- Source truth: Pandoc YAML/front-matter metadata behavior for YAML key-node
  tags, bounded to the native PHP `MarkdownReader` metadata parser and existing
  WordPress YAML handoff.
- No current `port-pandoc-*.needs-lane-rework.md` note existed before editing.
- This extends accepted custom tag provenance for explicit mapping keys and flow
  keys to plain block mapping keys. It does not repeat explicit-key tags, flow
  key tags, tag directive provenance, explicit typed scalar children, explicit
  null keys, alias diagnostic paths, top-level flow documents, or writer-side
  YAML quoting cases.
- No Pandoc, Cabal solver/build/test command, Haskell runner, external YAML
  parser, online service, live provider test, or live-service provider test was
  executed.

## Evidence

- Baseline focused:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 4202 assertions, 0 failures`
- Red-first probe:
  - Plain tagged mapping keys were retained as literal `!wpd!field ...` key
    names and produced no `yamlMetadataTagProvenance` entries.
- Final focused:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 4218 assertions, 0 failures`
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`
- PHP lint:
  - `php -l lanes/pandoc/src/MarkdownReader.php`
  - `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`
  - Result: no syntax errors detected.
- JSON validation: `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json` decoded
  successfully with `JSON_THROW_ON_ERROR`.
- Diff check: `git diff --check -- lanes/pandoc`
  - Result: passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json`: `phpPass` `1968 -> 1969`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`: `benchmarkDenominator.mapped`
  `2389 -> 2390`.
- Added inventory keys:
  - `mappedYamlMetadataPlainTaggedKeyCases`: `1`
  - `yamlMetadataPlainTaggedKeyAssertions`: `16`

## Dependency Closure

No new support component is needed. This reuses the native PHP Markdown/YAML
metadata parser, existing tag/anchor provenance helpers, focused
`MarkdownReaderTest.php` coverage, and the WordPress YAML metadata handoff
example. Full upstream runner parity remains gated on a hydrated pinned upstream
checkout and a reviewed non-mutating Cabal plan.

## Follow-Up

A non-overlapping YAML follow-up could cover remaining YAML 1.2 key-node
directive edge cases, nested collection source-span provenance, or additional
alias/tag diagnostics.
