# Pandoc doctemplates core current-base 2026-06-05T01:08:30Z

## Slice

- Aligned `PortLibs\Pandoc\DocTemplate` interpolation with upstream
  doctemplates by removing exactly one terminal LF, CRLF, or CR from rendered
  string variables.
- Kept `chomp` distinct: it remains the pipe that removes all trailing
  newlines.
- Extended focused coverage for LF, CRLF, list-item interpolation, `chomp`
  distinction, and WordPress review-body wrapper output.
- Updated the WordPress doctemplate review-packet self-test so a body value
  ending in a newline does not create a blank line before the closing section.

## Source Truth

- Hackage/Stackage `doctemplates-0.11.0.1` documentation and changelog:
  boolean false renders as `false`, and doctemplates 0.4 removed a single
  final newline from interpolated variables.
- Pandoc User's Guide `Template syntax`: interpolated variables render simple
  values verbatim without automatic escaping, lists concatenate values, maps
  render as `true`, and the `chomp` pipe removes trailing newlines.
- No Pandoc binary, Haskell test binary, Cabal build, Word, LibreOffice,
  `zip`/`unzip`, external template engine, TeX/PDF engine, browser renderer,
  roff, Typst, MathJax, KaTeX, or online service was executed.

## Evidence

- Rework note check found no current
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  paths.
- Red-first probe before implementation:
  `php -r 'require "tools/bootstrap.php"; $r = new \PortLibs\Pandoc\DocTemplate(); $template = "<section>\n  \$^\$\$body\$\n</section>"; $body = "<!-- wp:paragraph --><p>One</p><!-- /wp:paragraph -->\n<!-- wp:paragraph --><p>Two</p><!-- /wp:paragraph -->\n"; $out = $r->render($template, ["body" => $body]); if (str_contains($out, "\n\n</section>")) { fwrite(STDERR, "unexpected blank before closing section\n" . $out); exit(1); } fwrite(STDOUT, $out);'`
  failed with `unexpected blank before closing section`.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with
  1 test file, 36 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  passed with `OK wordpress doctemplate review packet`.
- PHP lint, JSON validation, and `git diff --check -- lanes/pandoc` passed in
  final worker verification.

## Non-Overlap

This slice does not repeat accepted doctemplate comments, delimiters,
interpolated variable lookup, boolean false rendering, conditionals, loops,
separators, `$it$`, `$^$`, automatic multiline nesting, `$~$` markers,
parameter-free or parameterized pipes, inline partial arrays, resource-map
partial discovery, applied partial rendering, or partial recursion guards. It
only changes the default rendering of interpolated string values with a single
terminal newline, leaving `chomp` to remove all trailing newlines.

It does not touch ZIP/OPC package primitives, YAML metadata, Citation/CSL,
BibTeX/CSL, Markdown/HTML readers, Markdown/WordPress writers, DOCX/ODT/EPUB
or legacy-DOC parsing, table geometry, math conversion, PDF handoff planning,
archive compression, syntax highlighting, or upstream-runner dependency audit
behavior.

## Dependency Closure

No new external support component is needed. This reuses the existing native
PHP `pandoc-doctemplates-core` renderer and tightens one interpolation
normalization rule. Full doclayout wrapping, filesystem-backed template
discovery, writer-extension template selection, default-template parity, and
full upstream Pandoc runner parity remain separate activation slices.

Root harness: not run - isolated micro-slice.
