# Pandoc BibTeX/CSL Core Current-Base Slice

Micro-slice: `pandoc-bibtex-csl-core-current-base-20260609T115037Z`
Base accepted HEAD: `329b990b1079e0c81d2c156d545b769dc66d69c3`

## Behavior

Added a bounded native PHP BibTeX/BibLaTeX to CSL handoff component for the current accepted base, which did not have a BibTeX/CSL class. `BibtexCslProcessor` now:

- Parses `@article`, `@book`, and other BibTeX-style entries with braced, quoted, bare, concatenated, and `@string` macro values.
- Normalizes core CSL item fields: `id`, `type`, `title`, `author`, `editor`, `issued.date-parts`, `container-title`, `volume`, `issue`, `page`, `DOI`, `URL`, `publisher`, and `publisher-place`.
- Collects cited keys from existing Pandoc-like `citation` AST nodes in first-citation order, including explicit multi-id citation nodes.
- Produces a `definition_list` bibliography AST with missing-citation diagnostics so WordPress review handoffs can keep unresolved bibliography keys visible.

## Non-Overlap

This slice intentionally avoids the queued specialized BibTeX/CSL field work I found in handoff artifacts: entry subtype/source kind, library call numbers, pagination/bookpagination, article numbers/eid, event-place lists, manual/booklet/letter aliases, escaped-brace handling, printing numbers, periodical aliases, supplement numbers, date markers, text macro wrappers, MR/ZBL identifiers, and broader citeproc style behavior. It adds only the current-base core parser/CSL handoff needed before those finer fields can be meaningful on this branch.

## Verification

- Pre-edit probe: `php -r 'require "tools/bootstrap.php"; var_export(class_exists("PortLibs\\\\Pandoc\\\\BibtexCslProcessor")); echo PHP_EOL;'` returned `false`.
- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php -l lanes/pandoc/examples/wordpress-bibtex-csl-review.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php` passed with `1 test files, 34 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-bibtex-csl-review.php --self-test`
- `git diff --check -- lanes/pandoc`

Root harness: not run - isolated micro-slice.

## Dependency Closure

No external support component was added or activated. The patch stays native PHP under `lanes/pandoc/**` and reuses the existing AST, Markdown citation parsing, Markdown writer, WordPress block writer, and focused test harness. No Pandoc, citeproc, BibTeX, Biber, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, external template engine, TeX/PDF engine, browser renderer, online service, live provider test, or live-service provider test was executed.

## Next

After this core current-base handoff, a follow-up can add non-overlapping BibLaTeX datamodel fields or CSL rendering behavior. Good candidates are language/original-language metadata, ISBN/ISSN/catalog identifiers not already queued, or tighter citation-cluster locator handling if the separate citation-CSL lane has not already accepted it.
