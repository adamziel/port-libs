# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260606T111426Z`
Base accepted HEAD: `f3e6ef9e9a7803edbdb9db6d76cbe13ebbfcd147`

## Behavior Added

- `MarkdownReader` now converts explicit YAML `!!float` special scalars into
  native PHP special floats for metadata handoff.
- Covered forms are `.inf`, `+.Inf`, `-.INF`, and `.NaN` in block mappings,
  flow mappings, and nested reference metadata values.
- Invalid special-looking values such as `.infinite` and `not-a-float` remain
  source strings so WordPress review packets do not hide questionable front
  matter.
- `wordpress-yaml-metadata-handoff.php --self-test` now verifies block and
  flow special float metadata.

## Source Truth Boundary

Pandoc's `yaml_metadata_block` contract treats front matter as YAML metadata
before converting it into document metadata. This bounded PHP slice ports one
explicit YAML float scalar behavior for native review packets; it does not
claim full YAML schema parity, multi-document stream handling, writer-side YAML
directive/comment emission, or upstream runner parity.

No local hydrated Pandoc checkout was present under the worktree or upstream
cache for this slice. No Pandoc binary, Cabal solver/build/test command,
Haskell runner, external YAML parser, online sanitizer, online service, live
provider test, or live-service provider test was executed.

## Evidence

- Rework-note check:
  `ls -1 /home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md 2>/dev/null || true`
  - Result: no current Pandoc rework note was present.
- Red probe before focused test:
  `php -r 'require "tools/bootstrap.php"; $doc=(new \PortLibs\Pandoc\MarkdownReader())->read("---\nreview:\n  pos: !!float .inf\n  neg: !!float -.Inf\n  nan: !!float .NaN\n...\n\n# Body\n"); var_export($doc->attr("meta")); echo "\n";'`
  - Result: `pos`, `neg`, and `nan` were source strings.
- Red-first after adding the focused special-float test:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 3150 assertions, 1 failures`.
  - Failure: `!!float .inf` remained a string, so `is_infinite()` rejected it.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 3165 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+17` assertions from the accepted
    `3148`-assertion baseline before this case.
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

- `lane-status.json` `phpPass`: `1310 -> 1311`.
- `UPSTREAM_TEST_MANIFEST.json` mapped native checks: `1724 -> 1725`.
- Added manifest inventory keys:
  - `mappedYamlMetadataSpecialFloatCases: 1`
  - `yamlMetadataSpecialFloatAssertions: 17`

## Dependency Closure

No new support component is needed. This extends the existing native PHP YAML
metadata subset in `MarkdownReader` and reuses existing AST metadata,
`MarkdownWriter`, `WordPressBlockWriter`, and the WordPress YAML metadata
handoff example. It does not require Pandoc, Cabal, Haskell runners, external
YAML libraries, external template engines, browser renderers, online
sanitizers, online services, live provider tests, or live-service provider
tests.

## Non-Overlap

This patch does not repeat accepted YAML block placement, omitted opening
markers, document marker comments, fenced-code exclusion, JSON-object metadata,
top-level flow mapping documents, flow-map parsing, multiline flow
collections, flow comments, quoted flow scalars, block sequences, compact
sequence maps, anchors, aliases, alias diagnostics, alias path diagnostics,
merge keys, merge-sequence precedence, explicit scalar/core tags, explicit
integer base or sexagesimal parsing, non-specific tags, custom tag provenance
paths, tag directives, `!!set`, `!!omap`/`!!pairs`, timestamp/binary tags,
block-scalar handling, explicit mapping key normalization, explicit sequence
keys, flow explicit null keys, duplicate-key diagnostics, plain multiline
scalars, ambiguous top-level field diagnostics, quoted ambiguous field
preservation, or writer metadata emission. It owns only explicit `!!float`
special scalar coercion and invalid special-float preservation.

It also does not touch accepted Markdown/HTML reader/writer behavior,
CSL/BibTeX, DOCX/ODT, EPUB3, legacy DOC/CFB, ZIP/OPC, doctemplate, table
geometry, Math/TeX, archive compression, XML/HTML5 DOM, charset/Unicode,
syntax-highlighting, or PDF engine handoff support.

## Follow-Up

Keep true multi-document YAML streams, writer-side directive/comment emission,
richer Pandoc `MetaValue` fidelity, source-location diagnostics, non-initial
metadata writer emission, full YAML schema validation, and full upstream runner
dependency planning as separate bounded slices.
