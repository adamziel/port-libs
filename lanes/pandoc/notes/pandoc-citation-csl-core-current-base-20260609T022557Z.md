# pandoc-citation-csl-core-current-base-20260609T022557Z

Base accepted HEAD: `7afabee2a7f5893f638a62223e4bc28f1e89fcb0`

Scope:
- Implemented a bounded CSL authority creator handoff for legal citation packets.
- Scalar `authority` metadata remains available to `<text variable="authority">`.
- Scalar and list-form `authority` metadata now also feed `is-creator="authority"` and `<names variable="authority">`.
- Added a WordPress handoff smoke for legal import packets that need issuing authorities routed as creator names.

Non-overlap:
- Did not repeat accepted CSL date sorting, locator labels, number rendering, disambiguation, name-list delimiter, subsequent-author substitute, source variables, or extended non-author creator roles.
- Did not invoke Pandoc, citeproc, BibTeX, Biber, Haskell test binaries, office tools, zip/unzip, TeX/PDF engines, browser renderers, online services, or live provider tests.

Dependency closure:
- No new support component is required.
- Reused existing native PHP `CslStyle`, `CitationCslProcessor`, `MarkdownReader`, and `WordPressBlockWriter` paths.
- Full upstream Pandoc runner remains outside this slice because the local upstream checkout is not hydrated with Cabal project files in this isolated worktree and building `test-pandoc` / `test-pandoc-lua-engine` would require the broad Haskell dependency plan recorded in lane status.

Focused verification:
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 3366 assertions, 0 failures`
- `php -l lanes/pandoc/src/CitationCslProcessor.php`
  - Result: no syntax errors
- `php -l lanes/pandoc/src/CslStyle.php`
  - Result: no syntax errors
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: no syntax errors
- `php -l lanes/pandoc/examples/wordpress-citation-csl-authority-creator-handoff.php`
  - Result: no syntax errors
- `php lanes/pandoc/examples/wordpress-citation-csl-authority-creator-handoff.php --self-test`
  - Result: `wordpress-citation-csl-authority-creator-handoff self-test passed`
- JSON validation for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
  - Result: both files valid
- `git diff --check -- lanes/pandoc`
  - Result: passed

Next:
- Keep subsequent CSL slices non-overlapping: style/locale rendering gaps, BibTeX/BibLaTeX metadata mapping, or citation-position behavior not already covered by authority creator, date-sort, locator, label, disambiguation, or name-list work.
