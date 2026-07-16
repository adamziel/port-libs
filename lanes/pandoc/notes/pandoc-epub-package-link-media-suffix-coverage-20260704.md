# EPUB package link media and suffix coverage

Date: 2026-07-04

Scope:
- Added checked-in native/package harness coverage for OPF metadata `link`
  `media-type` base values and parameter names.
- Added checked-in coverage for package-level link href query and fragment
  suffixes across OPF metadata links, OCF metadata links, and collection links.
- Updated `metadata-search-link-semantics.epub` to carry an OPF search link
  with both a media-type parameter and href query/fragment suffixes while
  preserving the Pandoc native AST golden.

Fixture and signatures:
- `metadata-search-link-semantics.epub`: `1892` bytes,
  sha256 `02d2f49316abf1e2f2abc8f6959090dc891e24857b849297201782918cca3a3f`.
- Package feature signature at this link/suffix handoff:
  `96ed1b67092db90f74aca44dd20ad923785473466a7c0a0e4d2c4017a7d740da`.
- Current package feature signature after the same-day media-overlay timeline
  coverage update:
  `72ef3e85bd273d75877a80b67fdef29e8dc52adece55ad377e4e82d752db9d17`.
- Current normalized native AST signature:
  `ffd91cfc066cf7daccc223a09e44623efafcfed57a1697b2accb3eb34f2a3acf`.

Coverage deltas:
- Fixture count remains 53 EPUB inputs and 53 same-basename native goldens.
- Package link media-type coverage now reports 11 package link media items.
- Package link media-type parameter coverage now reports 1 parameter,
  `profile`, in 1 fixture.
- Link href suffix coverage now reports 3 suffix-bearing links across 2
  fixtures, with 1 query and 3 fragments.

Focused verification:
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`:
  1 file, 4178 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/EpubNativeAstPackageComparisonHarnessTest.php`:
  1 file, 726 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/EpubExecutableNativeAstComparisonHarnessTest.php lanes/pandoc/tests/EpubMediaBagComparisonHarnessTest.php lanes/pandoc/tests/EpubUpstreamReaderEvidenceTest.php`:
  3 files, 603 assertions, 0 failures.
- `php tools/pandoc-epub-native-ast-package.php --checked-in-fixtures summary --require-fixture-identity --require-current-package-feature-coverage --require-current-package-feature-signature --require-current-native-ast-signature --require-runner-plan --require-package-parity=53 --require-native-readiness=53 --require-mapped-parity=53`:
  passed with 53/53 package acceptance and 53/53 normalized native AST matches.
- `php tools/pandoc-epub-executable-native-ast.php --checked-in-fixtures --pandoc-bin=/opt/homebrew/bin/pandoc --require-pandoc-version="pandoc 3.10" --require-executable-parity=53 summary`:
  passed with 53/53 local/executable normalized AST matches and 53/53
  executable/checked-in native fixture matches.
- `php tools/pandoc-epub-media-bag.php --checked-in-fixtures --require-media-bag-parity=6 --require-media-bag-item-count=10 --require-current-media-bag-signatures --require-runner-plan summary`:
  passed with 6/6 media-bag matches and 10/10 media items.
- `php tools/pandoc-epub-reader-evidence.php --checked-in-fixtures --pandoc-bin=/opt/homebrew/bin/pandoc --require-pandoc-version="pandoc 3.10" --require-test-count=6 --require-fixture-reference-count=6 --require-expected-media-item-count=10 --require-referenced-fixture-identity --require-static-current-signature --require-native-ast-package-parity --require-runner-plan --require-executable-native-ast-parity --require-no-validation-issues`:
  passed with native/package parity 53/53 and executable/native parity 53/53.

Pandoc executable truth:
- `/opt/homebrew/bin/pandoc --version | head -n 1`: `pandoc 3.10`.
- `diff -u lanes/pandoc/fixtures/upstream-current-epub-reader/epub/metadata-search-link-semantics.native <(/opt/homebrew/bin/pandoc -f epub -t native lanes/pandoc/fixtures/upstream-current-epub-reader/epub/metadata-search-link-semantics.epub)`:
  exit 0, no output.

No upstream Haskell/Cabal runner result, EPUB writer parity, external
validator, or full EPUB feature parity is asserted.
