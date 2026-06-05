# Pandoc BibTeX CSL Core Current Base

Slice: `pandoc-bibtex-csl-core-current-base-20260605T021408Z`

Accepted base: `0df7f83fa6571259635166e594b06a5096c92f71`

## Behavior

- Added bounded BibLaTeX source-file attachment policy diagnostics.
- `BibtexCslParser` now keeps safe relative `file` field attachments as
  importable `sourceFiles`, normalizes percent-encoded path segments, and
  exposes remote URI, absolute path, traversal, Windows drive, backslash,
  malformed percent escape, percent-encoded separator, and missing-path entries
  as `sourceFileDiagnostics`.
- `CitationCslProcessor` applies the same policy to direct CSL item
  `sourceFiles` so unsafe paths remain explicit review diagnostics instead of
  silently becoming importable attachments.
- Updated the WordPress BibTeX handoff example so xdata-inherited attachment
  metadata includes one decoded safe reviewer-notes path plus unsafe-path
  diagnostics for remote, traversal, and Windows source-file references.
- No Pandoc, citeproc, BibTeX, Biber, bibliography manager, external renderer,
  online service, or upstream Haskell runner was invoked.

## Source Truth

- This slice follows the accepted lane's BibLaTeX/CSL handoff model: BibLaTeX
  fields are normalized into CSL-compatible item metadata before local
  citation processing.
- The policy is bounded to source-file review safety for WordPress import
  queues. It does not read attachment bytes, fetch remote files, sniff MIME
  types, or bind paths to a repository root.

## Red/Green Evidence

- Baseline command:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result before test edit: `1 test files, 341 assertions, 0 failures`.
- Red-first command:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result before implementation: `1 test files, 343 assertions, 1 failures`.
  - Failure: the new source-file policy case expected only safe relative paths
    in `sourceFiles`, but remote, absolute, traversal, Windows, malformed, and
    encoded unsafe paths were still accepted as importable source files.
- After implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`:
    `1 test files, 359 assertions, 0 failures`.
  - `php tools/run-tests.php lanes/pandoc/tests`:
    `19 test files, 5649 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test`:
    `wordpress-bibtex-csl-handoff self-test passed`.
  - `php -l lanes/pandoc/src/BibtexCslParser.php`: no syntax errors.
  - `php -l lanes/pandoc/src/CitationCslProcessor.php`: no syntax errors.
  - `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php`: no syntax
    errors.
  - `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, $file . ": " . json_last_error_msg() . "\n"); exit(1); } echo $file . " valid\n"; }'`:
    both JSON files valid.
  - `git diff --check -- lanes/pandoc`: clean.
  - Root harness: not run - isolated micro-slice.

## Status Delta

- `CitationCslProcessorTest.php` moves from 17 focused cases and 341
  assertions to 18 focused cases and 359 assertions.
- Local Pandoc lane PASS cases are now 530.
- Manifest mapped checks move from 1006 to 1007.
- BibTeX/CSL mapped core cases move to 6 after carrying forward accepted
  crossref, xdata, TeX-accent, entry-set, related-entry, and translation
  metadata slices plus this source-file policy slice.

## Non-Overlap

This does not repeat accepted CSL JSON item basics, source-access date/name
metadata, initial BibTeX/BibLaTeX parser coverage, BibTeX crossref
inheritance, common TeX accent decoding, xdata inheritance, BibLaTeX entry
sets, related-entry metadata, translation/original-publication metadata, CSL
style XML/locales, citation cluster parsing, missing citation preservation,
ZIP/OPC package primitives, DOCX/ODT/EPUB3 package parsing, table geometry,
doctemplate, YAML, archive compression, math/TeX, legacy DOC/CFB, charset
helpers, PDF handoff planning, XML/HTML5 DOM, or upstream-runner dependency
audit work.

## Dependency Closure

No new support component is needed. This reuses the existing native
`BibtexCslParser`, `CitationCslProcessor`, Markdown reader/writer, and
WordPress block writer. Remaining bounded citation follow-up work includes
attachment byte extraction, repository-root binding, duplicate attachment name
policy, MIME sniffing, richer BibLaTeX entry families, full CSL macro/text/date
rendering, bibliography disambiguation, citation-position logic, note-style
output, and full upstream runner hydration.
