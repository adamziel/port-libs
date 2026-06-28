# Semantics Shard 1 Parent Validation - 2026-06-28

Issue: `plib-pc3p2`

Parent branch: `origin/integration/pandoc-semantics`

Leaf branches checked:

- `origin/integration/pandoc-semantics-json`
- `origin/integration/pandoc-semantics-csl`
- `origin/integration/pandoc-semantics-xml`

Result: no leaf commits needed to be folded. Each leaf branch is already an
ancestor of `origin/integration/pandoc-semantics`, and each
`git log origin/integration/pandoc-semantics..origin/integration/pandoc-semantics-*`
check returned no commits.

Validation passed:

- `git merge-base --is-ancestor` for JSON, CSL, and XML leaf heads against the parent.
- `php -r` JSON parsing for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc`.
- `php -l` for `BibtexCslProcessor.php`, `XmlHtmlDom.php`, and the focused test files.
- `php tools/run-tests.php` for `BibtexCslProcessorTest.php`, the 15 XML/HTML DOM review tests from the latest semantics parent fold, and `XmlHtmlDomTest.php`: 17 test files, 7,711 assertions, 0 failures.

No `phpPass` or mapped-denominator movement is claimed because this was a
parent validation shard, not a new mapped behavior fold. `main` was untouched.
