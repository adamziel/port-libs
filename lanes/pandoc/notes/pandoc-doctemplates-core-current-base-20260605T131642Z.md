# Pandoc doctemplates core current-base 2026-06-05T13:16:42Z

## Slice

- Added bounded wrapped rendering to `PortLibs\Pandoc\DocTemplate`.
- `renderWrapped()` and `renderResourceWrapped()` now preserve `$~$`
  breakable-space regions internally and reflow them at a caller-supplied line
  length.
- Normal `render()` / `renderResource()` output is unchanged: `$~$` regions
  still collapse literal whitespace to spaces without line wrapping.
- Breakable-space markers flow through partials and applied partials, and the
  existing `nowrap` pipe now suppresses wrapping by converting those markers
  back to ordinary spaces.
- The wrapper uses the existing `UnicodeText::displayWidth()` helper for line
  width accounting and leaves long unbreakable chunks intact.
- Updated the WordPress doctemplate review-packet smoke with a wrapped
  plain-text reviewer summary embedded in the native HTML packet.

## Source Truth

- Stackage/Hackage `doctemplates-0.11.0.1` documents templates as rendering to
  doclayout `Doc` values and says those values can wrap flexibly on breaking
  spaces.
  <https://www.stackage.org/package/doctemplates>
- The same doctemplates documentation defines `$~$...$~$` breakable-space
  regions and says the marker matters when rendering to `Doc` but not plain
  `Text` or `String`.
  <https://www.stackage.org/package/doctemplates>
- The documented `nowrap` pipe disables line wrapping on breakable spaces.
  <https://www.stackage.org/package/doctemplates>
- `Text.DocLayout.renderPlain (Just n)` reflows text at breakable spaces,
  while `renderPlain Nothing` does not reflow; `space` is documented as a
  breaking reflowable space and width helpers account for combining and
  double-wide characters.
  <https://hackage-content.haskell.org/package/doclayout-0.5.0.1/docs/Text-DocLayout.html>
- No Pandoc binary, Cabal build, Haskell runner, external template engine,
  browser renderer, JavaScript, online sanitizer, or online service was
  executed.

## Evidence

- Rework note check found no current
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  paths.
- Baseline focused command before edits:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result before edits: `1 test files, 62 assertions, 0 failures`.
- Red-first focused run after adding the new expectation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 62 assertions, 1 failures`.
  - Failure: `Call to undefined method PortLibs\Pandoc\DocTemplate::renderWrapped()`.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 63 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  - Result: `OK wordpress doctemplate review packet`.
- PHP lint:
  - `php -l lanes/pandoc/src/DocTemplate.php`
  - `php -l lanes/pandoc/tests/DocTemplateTest.php`
  - `php -l lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`
  - Result: no syntax errors.
- Lane JSON validation:
  `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $f) { json_decode(file_get_contents($f), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, "$f: " . json_last_error_msg() . PHP_EOL); exit(1); } } echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`.
- `git diff --check -- lanes/pandoc`
  - Result: no whitespace errors.

## Non-Overlap

This slice does not repeat accepted doctemplate comments, delimiters,
conditionals, loop scoping, separators, `$it$`, `$^$`, automatic multiline
nesting, parameter-free pipe presence, deterministic map-pairs ordering,
parameterized pipes, Unicode display-width padding, missing/null pipe handling,
resource-map partial discovery, path-style partial lookup, applied partials,
partial final-newline handling, partial recursion guards, braced directive
tokenizer behavior, alpha overflow labels, boolean false output, Unicode
identifier parsing, multiline control boundary newline swallowing, empty
standalone partial line swallowing, `chomp` traversal, or dedented nesting
termination. It only adds bounded wrapped rendering for already-parsed `$~$`
breakable-space regions and wires `nowrap` to suppress that wrapping.

It does not touch ZIP/OPC package primitives, YAML metadata, Citation/CSL,
BibTeX/CSL, Markdown/HTML readers, Markdown/WordPress writers, DOCX/ODT/EPUB
or legacy-DOC parsing, table geometry, math conversion, PDF handoff planning,
archive compression, syntax highlighting, XML/HTML5 DOM, charset/Unicode, or
upstream-runner dependency audit behavior.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`DocTemplate` renderer and the accepted `UnicodeText` display-width helper.
Full doclayout `Doc` value modeling, source-position diagnostics,
filesystem-backed template discovery beyond the existing resource map,
writer-extension template selection, default-template parity, and full
upstream Pandoc runner parity remain separate activation slices.

Root harness: not run - isolated micro-slice.
