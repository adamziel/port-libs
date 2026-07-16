# Pandoc YAML Metadata Collection Provenance

## Behavior

This slice maps one bounded YAML/front-matter metadata behavior: collection-level source provenance for block and flow mappings/sequences.

`MarkdownReader` now records document metadata under `yamlMetadataCollectionProvenance` with JSON-pointer-style `path`, `kind`, `style`, `memberCount`, `sourceLine`, `contentStartLine`, and `contentEndLine` fields. The internal `__yamlMetadataCollectionProvenance` handoff key is stripped from rendered metadata, matching the existing scalar-provenance pattern.

## Source Truth And Non-Overlap

The lane has no hydrated upstream Pandoc checkout in `/home/claude/port-libs/.upstream-cache/pandoc`, so this uses the accepted lane manifest/status and existing MarkdownReader YAML metadata behavior as source truth. It does not repeat accepted scalar provenance, quoted/plain scalar metadata, block-scalar document-marker preservation, alias diagnostic paths, explicit/null keys, anchors, tags, comments, streams, or top-level flow mapping document parsing.

No Pandoc, Cabal solver/build/test command, Haskell runner, external YAML parser, online service, live provider test, or live-service provider test was executed.

## Evidence

- No `port-pandoc-*.needs-lane-rework.md` note existed for this lane before editing.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed with `1 test files, 3740 assertions, 0 failures`.
- Red-first: after adding the focused test, `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` failed with `1 test files, 3749 assertions, 1 failures` because collection provenance records were absent.
- Final focused reader test: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed with `1 test files, 3791 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test` passed.
- PHP lint passed for `lanes/pandoc/src/MarkdownReader.php`, `lanes/pandoc/tests/MarkdownReaderTest.php`, and `lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`.
- Lane JSON validation passed for `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` and `lanes/pandoc/lane-status.json`.
- `git diff --check -- lanes/pandoc` passed.

## Status Delta

- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2080 -> 2081`.
- Added `mappedYamlMetadataCollectionProvenanceCases: 1`.
- Added `yamlMetadataCollectionProvenanceAssertions: 51`.
- `lanes/pandoc/lane-status.json` phpPass: `1660 -> 1661`.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP MarkdownReader YAML/front-matter parser, focused MarkdownReader tests, and the existing WordPress YAML metadata handoff example. Full upstream Pandoc runner parity remains a separate upstream-runner dependency audit path.

## Follow-Up

Choose a distinct YAML metadata parser gap next, such as multi-document policy, directive preservation, or source span handoff for anchored collection merges. Keep it native PHP and avoid Pandoc/Cabal/Haskell/external YAML execution from this lane.
