# Pandoc YAML Metadata Core Current Base

Slice: `pandoc-yaml-metadata-core-current-base-20260605T031044Z`

Base accepted HEAD: `0fb4d03b1f14407c8c9bf0982ce92fa4ce11995a`

## Behavior Added

- Extended the native `MarkdownReader` YAML metadata subset to resolve bounded
  explicit YAML core scalar tags instead of leaving every quoted tagged scalar
  as text.
- Coerces `!!int`, `!!float`, `!!bool`, and `!!null` values for root/nested
  metadata values, flow-map values, and nested date-part sequence entries.
- Preserves accepted explicit string behavior for `!!str`, `!str`, and
  `tag:yaml.org,2002:str`, so source revisions like `007` stay text when the
  source says they are strings.
- Accepts full standard tag URI syntax such as
  `!<tag:yaml.org,2002:float>` for scalar metadata.
- Updated the WordPress YAML metadata handoff smoke so typed reviewer metadata
  is visible without changing the accepted later-block review override
  behavior.

## Source Truth

Pandoc's `yaml_metadata_block` contract treats metadata blocks as YAML before
Pandoc converts metadata values into document meta. This slice ports only the
bounded standard scalar-tag behavior needed by native front-matter metadata
handoff. Source-truth reference remains the Pandoc User's Guide
`yaml_metadata_block` contract recorded by earlier YAML notes:
`https://pandoc.org/demo/example2.html#extension-yaml_metadata_block`.

## Verification

- No current-base rework note was present:
  `find /home/claude/port-libs/.tmux-team/tmp/handoff-candidates -maxdepth 1 -type f -name 'port-pandoc-*.needs-lane-rework.md' -print`
  - Result: no output.
- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2531 assertions, 0 failures`.
- Direct pre-edit behavior check:
  `php -r 'require "tools/bootstrap.php"; $doc=(new PortLibs\Pandoc\MarkdownReader())->read("---\nreview:\n  revision: !!int \"007\"\n  confidence: !!float \"0.75\"\n  approved: !!bool \"true\"\n  withdrawn: !!null \"ignored\"\n...\n\nBody.\n"); var_export($doc->attr("meta")); echo "\n";'`
  - Result before edit: `revision`, `confidence`, `approved`, and
    `withdrawn` were exposed as strings.
- `php -l lanes/pandoc/src/MarkdownReader.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 2551 assertions, 0 failures`.
  - Delta: `+20` focused assertions and `+1` focused PASS case.
- `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 6245 assertions, 0 failures`.
  - PASS lines from `rg -c '^PASS'`: `567`.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`
  - Result: `json ok`.
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Status Delta

- Manifest mapped native checks: `1045 -> 1046`.
- Current worktree `phpPass`: verified as `567` PASS lines with 0 failures.
- `mappedYamlMetadataExplicitCoreTagCases`: `0 -> 1`.
- `yamlMetadataExplicitCoreTagAssertions`: `0 -> 20`.

## Dependency Closure

No new support component is needed. This extends the existing native PHP YAML
metadata subset inside `MarkdownReader` and reuses the existing AST and
WordPress block writer. It does not invoke Pandoc, Cabal, Haskell test
binaries, external YAML libraries, Word, LibreOffice, `zip`, `unzip`, external
template engines, TeX/PDF engines, browser renderers, roff, Typst, MathJax,
KaTeX, or online services.

## Non-Overlap

This patch does not repeat accepted YAML placement, fenced-code exclusion,
JSON-object metadata, flow-map metadata, block-style nested sequence metadata,
anchors, aliases, merge keys, comments, block-scalar mapping values,
double-quoted escapes, multiline quoted scalar folding, merge-sequence
precedence, empty scalar null semantics, or sequence block-scalar metadata. It
owns only bounded explicit core scalar tag coercion for metadata values.
It also does not touch accepted Markdown/HTML reader/writer behavior, CSL/
BibTeX, DOCX/ODT, EPUB3, legacy DOC/CFB, ZIP/OPC, doctemplate, table geometry,
Math/TeX, archive compression, XML/HTML5 DOM, charset/Unicode, or PDF engine
handoff support.

## Follow-Up

Keep writer-side YAML emission, timestamp/binary/set tag families, complex
flow comments, multi-document YAML streams, full schema validation, and full
upstream runner dependency planning as separate bounded slices.
