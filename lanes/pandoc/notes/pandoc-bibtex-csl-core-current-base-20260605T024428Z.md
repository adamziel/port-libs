# Pandoc BibTeX CSL Core Current Base

Slice: `pandoc-bibtex-csl-core-current-base-20260605T024428Z`

Accepted base: `4562d47a4f46741b5cc7da80b4fb55c52831e4e3`

## Behavior

- Added bounded BibLaTeX patent/legal entry-family handoff for `@patent`,
  `@legislation`, `@legal`, and `@jurisdiction`.
- `BibtexCslParser` now maps those entries to CSL-compatible
  `patent`, `legislation`, and `legal_case` item types and preserves
  `number`, `genre`, `authority`, `jurisdiction`, `publisher-place`,
  `holder`, `event-date`, and `status` metadata.
- `CitationCslProcessor` now normalizes those fields, exposes them to bounded
  CSL text/date variable rendering, and includes compact patent/legal metadata
  in fallback bibliography output for reviewer queues.
- Updated the WordPress BibTeX handoff example so patent and legislation
  sources keep legal review metadata in rendered WordPress blocks.
- No Pandoc, citeproc, BibTeX, Biber, bibliography manager, external renderer,
  online service, or upstream Haskell runner was invoked.

## Source Truth

- This slice follows the lane's accepted BibTeX/BibLaTeX-to-CSL handoff model:
  parsed `.bib` entries become CSL-like item records before native citation
  and bibliography rendering.
- The behavior is bounded to patent/legal metadata needed for review packets.
  It does not attempt full citeproc legal style parity, jurisdiction-specific
  abbreviation rules, note-style legal citations, or online style catalogs.

## Red/Green Evidence

- Baseline command:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result before edit: `1 test files, 377 assertions, 0 failures`.
- Red-first/fix evidence:
  - The first focused run after adding the new patent/legal case failed with
    `1 test files, 399 assertions, 1 failures`.
  - Failure: `queue-case` rendered `Jurisdiction: 9th Cir..` with doubled
    punctuation. The helper now trims existing trailing periods before adding
    sentence punctuation.
- After implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`:
    `1 test files, 405 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test`:
    `wordpress-bibtex-csl-handoff self-test passed`.
  - `php -l lanes/pandoc/src/BibtexCslParser.php`: no syntax errors.
  - `php -l lanes/pandoc/src/CitationCslProcessor.php`: no syntax errors.
  - `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php`: no syntax
    errors.
  - `php -r '$files=["lanes/pandoc/lane-status.json","lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"]; foreach ($files as $file) { json_decode(file_get_contents($file), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, $file . ": " . json_last_error_msg() . PHP_EOL); exit(1); } echo $file . " valid" . PHP_EOL; }'`:
    both JSON files valid.
  - `git diff --check -- lanes/pandoc`: clean.
  - Root harness: not run - isolated micro-slice.

## Status Delta

- `CitationCslProcessorTest.php` moves from 19 focused cases and 377
  assertions to 20 focused cases and 405 assertions.
- Lane status moves from 549 to 550 PHP PASS cases.
- Manifest mapped checks move from 1027 to 1028.
- BibTeX/CSL mapped core cases move to 7 after carrying forward accepted
  crossref, xdata, TeX-accent, entry-set, related-entry, translation, and
  source-file policy slices plus this patent/legal metadata slice.

## Non-Overlap

This does not repeat accepted CSL JSON item basics, source-access date/name
metadata, initial BibTeX/BibLaTeX parser coverage, BibTeX crossref
inheritance, common TeX accent decoding, xdata inheritance, BibLaTeX entry
sets, related-entry metadata, translation/original-publication metadata,
source-file attachment policy diagnostics, CSL style XML/locales, citation
cluster parsing, missing citation preservation, ZIP/OPC package primitives,
DOCX/ODT/EPUB3 package parsing, table geometry, doctemplate, YAML, archive
compression, math/TeX, legacy DOC/CFB, charset helpers, PDF handoff planning,
XML/HTML5 DOM, or upstream-runner dependency audit work.

## Dependency Closure

No new support component is needed. This reuses the existing native
`BibtexCslParser`, `CitationCslProcessor`, Markdown reader/writer, and
WordPress block writer. Remaining bounded citation follow-up work includes
additional BibLaTeX entry families, richer legal/date/status localization,
full CSL choose/label/number rendering, disambiguation, citation-position
logic, note-style output, and full upstream runner hydration.
