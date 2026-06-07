# Pandoc YAML Metadata Current-Base Tag URI Suffixes

Slice: `pandoc-yaml-metadata-core-current-base-20260607T062005Z`
Base accepted HEAD: `a628bc0ed53d2164babe3e634620f5035ffb50f0`

## Behavior Added

Native `MarkdownReader` YAML metadata now recognizes URI-like tag suffix
characters on `%TAG`-expanded tags and primary-handle tags.

Covered bounded forms include:

- percent-escaped suffixes such as `!wp!source%2Fowner`;
- query suffixes such as `!wp!source?kind=uri`;
- fragment suffixes such as `!wp!source#fragment`;
- semicolon/ampersand/equal scoped suffixes such as
  `!wp!source;kind=review&draft=false`;
- primary-handle suffixes such as `!source%2Fprimary`;
- tagged explicit flow keys such as
  `? !wp!key%2Fsource "source:key": !wp!value?kind=flow metadata value`.

The tags are consumed before scalar parsing, metadata values no longer leak raw
tag handles, and `yamlMetadataTagProvenance` preserves expanded tag URIs with
JSON-pointer-style metadata paths for WordPress review packets.

## Source Truth Boundary

Pandoc delegates `yaml_metadata_block` contents to YAML parsing before metadata
conversion. YAML tags are URI-shaped identifiers, and `%TAG` directives expand
handles into full tag URI prefixes. This slice ports only the bounded native
PHP support-library behavior needed for URI-like custom tag suffixes in Pandoc
front matter.

No Pandoc binary, Cabal solver/build/test command, Haskell runner, external
YAML parser, online service, live provider test, or live-service provider test
was executed.

## Evidence

- Rework notes: no
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  file was present before editing.
- Red-first probe before implementation:
  `php -r 'require "tools/bootstrap.php"; use PortLibs\Pandoc\MarkdownReader; $doc=(new MarkdownReader())->read("---\n%TAG !wp! tag:example.test,2026:review/\n---\nreview: {owner: !wp!source%2Furi Import Desk, link: !wp!source?kind=uri https://example.test/export#one}\n...\n\n# Body\n"); var_export($doc->attr("meta", [])); echo "\n"; var_export($doc->attr("yamlMetadataTagProvenance", [])); echo "\n";'`
  - Result: raw `!wp!source%2Furi` and `!wp!source?kind=uri` text remained in
    metadata values and tag provenance was empty.
- Focused verification:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 3369 assertions, 0 failures`.
  - Delta: `+1` focused PHP PASS case and `+27` focused assertions.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`.
- PHP lint:
  `php -l lanes/pandoc/src/MarkdownReader.php`
  `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`
  - Result: no syntax errors detected.

Additional final checks are recorded in the worker handoff: JSON metadata
validation and `git diff --check -- lanes/pandoc`.

Root harness not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `1459 -> 1460`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`:
  `1877 -> 1878`.
- New manifest inventory keys:
  - `mappedYamlMetadataTagUriSuffixCases: 1`
  - `yamlMetadataTagUriSuffixAssertions: 27`

## Dependency Closure

No new support component is needed. The slice reuses the native PHP
`MarkdownReader` YAML metadata parser, AST metadata attrs,
`WordPressBlockWriter`, `MarkdownWriter`, the existing WordPress YAML metadata
handoff example, and the lane PHP harness.

Full upstream YAML runner parity remains out of scope for this worker because
Pandoc/Cabal/Haskell runner execution and external YAML parser parity were not
invoked.

## Non-Overlap

This slice does not repeat accepted YAML block placement, omitted opening
marker handling, directive document starts, `%YAML` version directive
provenance, document-marker comments, fenced-code exclusion, JSON/top-level
flow documents, multiline flow collections, comments, quoted scalars,
anchors/aliases including punctuation anchors, alias diagnostics/path,
duplicate-key diagnostics, merge sequences/merge tags, explicit or implicit
null keys, explicit sequence/map keys, block/flow sets, ordered maps/pairs,
block scalar chomping/document-marker scalar handling, ambiguous fields, or
writer emission work.

It owns only `%TAG`-expanded and primary-handle custom tag suffixes with
URI-like characters, plus their metadata path provenance.

## Follow-Up

Next YAML/front-matter work should stay bounded to non-overlapping parser or
writer gaps such as directive diagnostics for invalid `%TAG` lines,
source-location diagnostics, writer-side YAML emission parity, or additional
schema edge cases without invoking external YAML tooling or upstream Pandoc
runners.
