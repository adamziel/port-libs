# YAML Metadata Core Current Base: Tagged Quoted Explicit Keys

Micro-slice: `pandoc-yaml-metadata-core-current-base-20260609T001000Z`

Base accepted HEAD: `256cc5a788644a524334e4ade8b4640cb13ce3e0`

## Behavior

This slice keeps source-quoted top-level YAML field names visible when the key is also prefixed by a YAML tag or anchor directive. The native `MarkdownReader` now strips bounded YAML value directives before deciding whether an explicit mapping key started with a quote.

Covered forms:

- Block explicit key: `? !wpd!key "On"` followed by `: value`
- Flow explicit key: `? !wpd!key "No": value`
- Core-tagged quoted keys such as `? !!str "0x2A": value`

The visible metadata keys survive ambiguous-field filtering, and custom tag provenance remains attached to normalized JSON-pointer-style paths such as `/On`, `/0b101`, `/No`, and `/0b110`.

## Source Truth

The behavior follows the lane's bounded Pandoc YAML/front-matter metadata contract: source quotes make top-level field names string metadata, while custom YAML tags remain metadata provenance. No Pandoc executable, Cabal build/test command, Haskell runner, external YAML parser, online service, live provider test, or live-service provider test was run.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note existed for this lane before editing.
- Baseline before the new test: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed with `1 test files, 4235 assertions, 0 failures`.
- Red-first after adding the focused case: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` failed with `1 test files, 4237 assertions, 1 failures`; the tagged quoted key was dropped as an ambiguous top-level field.
- Final focused run: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed with `1 test files, 4257 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test` passed with `yaml metadata handoff self-test ok`.
- PHP lint passed for `lanes/pandoc/src/MarkdownReader.php`, `lanes/pandoc/tests/MarkdownReaderTest.php`, and `lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`.
- JSON validation passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `1999` -> `2000`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2417` -> `2418`.
- Added inventory counters: `mappedYamlMetadataTaggedQuotedExplicitKeyCases: 1` and `yamlMetadataTaggedQuotedExplicitKeyAssertions: 22`.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP `MarkdownReader` YAML front-matter parser, tag/anchor directive normalization, quote-provenance tracking, and WordPress YAML metadata handoff. Full upstream runner parity remains gated on a hydrated pinned checkout and reviewed non-mutating Cabal plan.

## Follow-Up

A non-overlapping follow-up could cover tagged alias paths inside flow sequences or schema-version-specific YAML tag resolution that is not already covered by explicit key quote provenance.
