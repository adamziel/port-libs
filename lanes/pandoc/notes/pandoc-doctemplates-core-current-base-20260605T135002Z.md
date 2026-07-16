# Pandoc doctemplates core current-base 2026-06-05T13:50:02Z

## Slice

- Aligned included partial final-newline handling in
  `PortLibs\Pandoc\DocTemplate` with upstream doctemplates.
- Included partial output now removes exactly one final line ending whether it
  is LF, CRLF, or CR.
- Applied partial rows use the same rule, so CRLF-terminated row partials do
  not leave bare CR bytes between rendered rows.
- Deliberate extra blank lines remain intact: a partial ending in two LF bytes
  still contributes one final LF after inclusion.
- Updated the WordPress doctemplate review-packet smoke with a CRLF-terminated
  component partial and a guard that rejects leaked CR bytes in reviewer HTML.

## Source Truth

- Pandoc User's Guide `Template syntax`, `Partials` documents that final
  newlines are omitted from included partials:
  https://pandoc.org/demo/example33/6.1-template-syntax.html
- Hackage/Stackage `doctemplates-0.11.0.1` documents doctemplates as Pandoc's
  template renderer and shares the same partial-inclusion contract:
  https://hackage.haskell.org/package/doctemplates
- This slice uses the existing native PHP renderer only. No Pandoc binary,
  Cabal build, Haskell runner, external template engine, browser renderer,
  JavaScript, online sanitizer, or online service was executed.

## Evidence

- Rework note check found no current
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  paths.
- Baseline focused command before edits:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result before edits: `1 test files, 63 assertions, 0 failures`.
- Red-first focused run after adding the CR/CRLF expectation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 63 assertions, 1 failures`.
  - Failure: CRLF and CR partials rendered as `alpha\r` instead of `alpha`.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 64 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  - Result: `OK wordpress doctemplate review packet`.
- PHP lint:
  - `php -l lanes/pandoc/src/DocTemplate.php`
  - `php -l lanes/pandoc/tests/DocTemplateTest.php`
  - `php -l lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`
  - Result: no syntax errors.
- Lane JSON validation:
  `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " json ok\n"; }'`
  - Result: both files valid.
- `git diff --check -- lanes/pandoc`
  - Result: no whitespace errors.

## Non-Overlap

This slice does not repeat accepted doctemplate comments, delimiters,
conditionals, loop scoping, separators, `$it$`, `$^$`, automatic multiline
nesting, parameter-free pipes, deterministic map-pairs ordering,
parameterized pipes, Unicode display-width padding, missing/null pipe handling,
resource-map partial discovery, path-style partial lookup, applied partial
variable rebinding, partial recursion guards, braced directive tokenizer
behavior, alpha overflow labels, boolean false output, Unicode identifier
parsing, multiline control boundary newline swallowing, empty standalone
partial line swallowing, `chomp` traversal, breakable-space wrapping, or
dedented nesting termination. It only changes the final line-ending omission
rule for already-rendered included partials.

It does not touch ZIP/OPC package primitives, YAML metadata, Citation/CSL,
BibTeX/CSL, Markdown/HTML readers, Markdown/WordPress writers, DOCX/ODT/EPUB
or legacy-DOC parsing, table geometry, math conversion, PDF handoff planning,
archive compression, syntax highlighting, XML/HTML5 DOM, charset/Unicode, or
upstream-runner dependency audit behavior.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`DocTemplate` renderer and the accepted WordPress doctemplate review-packet
example. Full doclayout `Doc` value modeling, richer source-location
diagnostics, filesystem-backed template discovery beyond the existing resource
map, writer-extension template selection, default-template parity, and full
upstream Pandoc runner parity remain separate activation slices.

Root harness: not run - isolated micro-slice.
