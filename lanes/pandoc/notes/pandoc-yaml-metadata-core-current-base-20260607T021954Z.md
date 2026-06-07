# pandoc-yaml-metadata-core-current-base-20260607T021954Z

## Behavior Added

The native PHP Markdown reader now resolves YAML anchors and aliases whose
names include URI-like punctuation such as `:` and `/`.

Covered bounded forms:

- block mapping defaults such as `&source:review/defaults`;
- merge-key aliases such as `<<: *source:review/defaults`;
- sequence item anchors such as `&source/ref-primary`;
- flow aliases such as `{defaults: *source:review/defaults}`.

This prevents source-export review packets from leaving punctuation-bearing
aliases as literal `*source:...` strings or silently skipping merge defaults.

## Source Truth Boundary

Pandoc's YAML metadata path delegates front matter to a YAML parser before
metadata conversion. YAML anchor names are not limited to only alphanumeric,
dot, underscore, and hyphen characters; the bounded local contract is to allow
non-space anchor characters except flow collection separators while keeping
existing merge, tag, and diagnostic behavior unchanged.

No Pandoc binary, Cabal solver/build/test command, Haskell runner, external
YAML parser, online service, live provider test, or live-service provider test
was executed.

## Evidence

- Rework notes: no `port-pandoc-*.needs-lane-rework.md` note was present for
  this lane before editing.
- Baseline focused command:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 3267 assertions, 0 failures`.
- Red-first direct probe before implementation:
  - Result: `*source:review/defaults` remained literal in flow metadata and
    `<<: *source:review/defaults` did not apply inherited defaults.
- Final focused command:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 3286 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+19` focused assertions.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test`
  - Result: `yaml metadata handoff self-test ok`.
- PHP lint:
  `php -l lanes/pandoc/src/MarkdownReader.php`
  `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
  `php -l lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`
  - Result: no syntax errors detected.
- Lane JSON metadata validation:
  `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json metadata valid\n";'`
  - Result: `pandoc json metadata valid`.
- Whitespace check:
  `git diff --check -- lanes/pandoc`
  - Result: no output.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `1433 -> 1434`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1850 -> 1851`.
- New manifest inventory keys:
  - `mappedYamlMetadataAnchorAliasPunctuationCases`: `1`
  - `yamlMetadataAnchorAliasPunctuationAssertions`: `19`

## Dependency Closure

No new support component is needed. The slice reuses native PHP
`MarkdownReader`, `AstNode`, `MarkdownWriter`, `WordPressBlockWriter`, and the
existing WordPress YAML metadata handoff example.

Full Pandoc runner parity remains blocked by hydrating the pinned upstream
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` and explicitly
authorizing Haskell/Cabal solver, build, and runner work. This patch does not
add that dependency and does not require an external YAML library.

## Non-Overlap

This does not repeat accepted YAML block placement, omitted opening marker
handling, document-marker comments, fenced-code exclusion, JSON metadata,
top-level flow mapping documents, multiline flow metadata, ordinary anchors
and aliases, alias path diagnostics, duplicate-key diagnostics, merge-sequence
precedence, explicit merge tags, explicit scalar/core tags, custom tag
provenance paths, tag directives, sets, ordered maps/pairs, block scalars,
explicit sequence/map keys, flow explicit null keys, plain multiline scalars,
or quoted ambiguous field-name diagnostics.

This slice owns only punctuation-bearing YAML anchor and alias names in the
metadata subset.

## Follow-Up

Good YAML follow-up candidates are true multi-document stream policy,
writer-side directive/comment emission, richer source-location diagnostics, and
full YAML schema validation. Those should remain native and bounded unless the
supervisor explicitly authorizes Pandoc/Haskell runner work.
