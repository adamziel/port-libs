# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260606T031251Z`
Base accepted HEAD: `ec3e9194c10aec1d28ce93f5e409f6a334a84508`

## Behavior Added

- `MarkdownReader` now converts explicit YAML `!!int` sexagesimal scalars into
  integer seconds for bounded metadata handoff.
- Covered positions are block mappings, flow mappings, and nested reference
  metadata values.
- Invalid base-60 components such as `1:60` and malformed tokens remain source
  strings so WordPress review packets do not hide questionable front matter.
- `wordpress-yaml-metadata-handoff.php --self-test` now verifies block and flow
  sexagesimal `!!int` durations.

## Source Truth Boundary

Pandoc's `yaml_metadata_block` contract treats front matter as YAML metadata
before converting it into document metadata. This bounded PHP slice ports one
explicit YAML integer scalar behavior for native review packets; it does not
claim full YAML schema parity, special float scalar handling, multi-document
stream handling, or upstream runner parity.

No local hydrated Pandoc checkout was present under the worktree or upstream
cache for this slice. No Pandoc binary, Cabal solver/build/test command,
Haskell runner, external YAML parser, online sanitizer, online service, or live
provider test was executed.

## Evidence

- Rework-note check:
  `find /home/claude/port-libs/.tmux-team/tmp/handoff-candidates -maxdepth 1 -name 'port-pandoc-*.needs-lane-rework.md' -print | sort`
  - Result: no current Pandoc rework note was present.
- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 3084 assertions, 0 failures`.
- Red-first after adding the focused sexagesimal integer test:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 3086 assertions, 1 failures`.
  - Failure: `!!int 1:20:30` remained the string `1:20:30` instead of integer
    `4830`.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 3098 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+14` assertions.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`.
- PHP lint:
  `php -l lanes/pandoc/src/MarkdownReader.php`
  `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`
  - Result: no syntax errors detected.
- JSON metadata validation:
  `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " valid\n"; }'`
  - Result: both JSON files valid.
- `git diff --check -- lanes/pandoc`
  - Result: no output.

Root harness not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1170 -> 1171`.
- `UPSTREAM_TEST_MANIFEST.json` mapped native checks: `1620 -> 1621`.
- Added manifest inventory keys:
  - `mappedYamlMetadataSexagesimalIntegerCases: 1`
  - `yamlMetadataSexagesimalIntegerAssertions: 14`

## Dependency Closure

No new support component is needed. This extends the existing native PHP YAML
metadata subset in `MarkdownReader` and reuses existing AST metadata,
`MarkdownWriter`, `WordPressBlockWriter`, and the WordPress YAML metadata
handoff example. It does not require Pandoc, Cabal, Haskell runners, external
YAML libraries, external template engines, browser renderers, online
sanitizers, online services, or live provider tests.

## Non-Overlap

This patch does not repeat accepted YAML block placement, omitted opening
markers, fenced-code exclusion, JSON-object metadata, top-level flow mapping
documents, flow-map parsing, multiline flow collections, flow comments, quoted
flow scalars, block sequences, compact sequence maps, anchors, aliases, alias
diagnostics, merge keys, merge-sequence precedence, explicit scalar/core tags,
explicit integer hexadecimal/binary/octal/underscore base parsing,
non-specific tags, custom tag provenance paths, tag directives, `!!set`,
`!!omap`/`!!pairs`, timestamp/binary tags, block-scalar handling, explicit
mapping key normalization, flow explicit null keys, plain multiline scalars,
ambiguous top-level field diagnostics, quoted ambiguous field preservation, or
writer metadata emission. It owns only explicit `!!int` sexagesimal scalar
coercion and invalid sexagesimal preservation.

It also does not touch accepted Markdown/HTML reader/writer behavior,
CSL/BibTeX, DOCX/ODT, EPUB3, legacy DOC/CFB, ZIP/OPC, doctemplate, table
geometry, Math/TeX, archive compression, XML/HTML5 DOM, charset/Unicode,
syntax-highlighting, or PDF engine handoff support.

## Follow-Up

Keep multi-document YAML streams, special floating-point scalar handling,
richer Pandoc `MetaValue` fidelity, non-initial metadata writer emission, full
YAML schema validation, and full upstream runner dependency planning as
separate bounded slices.
