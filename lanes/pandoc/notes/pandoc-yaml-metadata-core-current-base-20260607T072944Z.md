# Pandoc YAML Metadata Current-Base Invalid TAG Directives

Slice: `pandoc-yaml-metadata-core-current-base-20260607T072944Z`
Base accepted HEAD: `2ad8162f5a3428f4ace3f2bf0a83927be18816b0`

## Behavior Added

Native `MarkdownReader` YAML metadata now consumes malformed `%TAG` directive
lines as YAML directives and records bounded reviewer diagnostics instead of
treating the malformed directive as metadata content.

Covered behavior:

- malformed handles such as `%TAG !bad tag:invalid.example,2026:` produce a
  `yamlMetadataDiagnostics` entry with `reason: invalid-tag-directive`;
- the following `---` document-start marker still opens the real YAML metadata
  document after the directive preamble;
- valid `%TAG` directives in the same preamble still bind custom tag handles;
- valid custom tag provenance keeps JSON-pointer-style metadata paths.

## Source Truth Boundary

Pandoc's `yaml_metadata_block` delegates front matter to YAML parsing before
metadata conversion. YAML directive lines are part of the document preamble, so
bounded native PHP handoff should preserve malformed directive provenance for
review while continuing to parse the following metadata document when possible.

No Pandoc binary, Cabal solver/build/test command, Haskell runner, external
YAML parser, online service, live provider test, or live-service provider test
was executed.

## Evidence

- Rework notes: no
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  file was present before editing.
- Red-first probe before implementation:
  `php -r 'require "tools/bootstrap.php"; use PortLibs\Pandoc\MarkdownReader; $doc=(new MarkdownReader())->read("---\n%TAG !bad tag:example.test,2026:\n%TAG !wp! tag:example.test,2026:\n---\ntitle: Invalid TAG **Packet**\nreview: {owner: !wp!reviewer Import Desk}\n...\n\n# Body\n"); var_export($doc->attr("meta", null)); echo "\n"; var_export($doc->attr("yamlMetadataDiagnostics", [])); echo "\n";'`
  - Result: the malformed `%TAG` line became a bogus metadata key and no
    diagnostic was emitted.
- Focused verification:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 3384 assertions, 0 failures`.
  - Delta: `+1` focused PHP PASS case and `+15` focused assertions.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`.
- PHP lint:
  `php -l lanes/pandoc/src/MarkdownReader.php`
  `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`
  - Result: no syntax errors detected.
- JSON validation:
  `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json metadata valid\n";'`
  - Result: `pandoc json metadata valid`.
- Whitespace check:
  `git diff --check -- lanes/pandoc`
  - Result: no output.

Root harness not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `1465 -> 1466`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`:
  `1883 -> 1884`.
- New manifest inventory keys:
  - `mappedYamlMetadataInvalidTagDirectiveCases: 1`
  - `yamlMetadataInvalidTagDirectiveAssertions: 15`

## Dependency Closure

No new support component is needed. The slice reuses native PHP
`MarkdownReader` YAML metadata parsing, AST metadata attrs, `MarkdownWriter`,
`WordPressBlockWriter`, the existing WordPress YAML metadata handoff example,
and the lane PHP harness.

Full upstream YAML runner parity, Pandoc/Cabal/Haskell runner parity, external
YAML parser comparison, writer-side YAML emission parity, online services, live
provider tests, and live-service provider tests remain out of scope.

## Non-Overlap

This slice does not repeat accepted YAML block placement, omitted opening
marker handling, valid directive document starts, `%YAML` version directive
provenance, document-marker comments, fenced-code exclusion, JSON/top-level flow
documents, multiline flow collections, comments, quoted scalars, anchors and
aliases, alias diagnostics/path, duplicate-key diagnostics, merge
sequences/merge tags, explicit or implicit null keys, explicit sequence/map
keys, block/flow sets, ordered maps/pairs, block scalar chomping/document-marker
scalar handling, ambiguous fields, custom tag URI suffixes, or writer emission
work.

It owns only invalid `%TAG` directive diagnostics and preservation of the
following metadata document after a malformed tag directive preamble entry.

## Follow-Up

Next YAML/front-matter work should stay bounded to non-overlapping
source-location diagnostics, writer-side YAML emission parity, or additional
schema edge cases without invoking external YAML tooling or upstream Pandoc
runners.
