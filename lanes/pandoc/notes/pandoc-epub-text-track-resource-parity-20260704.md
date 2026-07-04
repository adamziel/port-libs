# Pandoc EPUB Text Track Resource Parity 20260704

Lane: EPUB reader/native/package parity.

Baseline: `origin/main` at `deafe7945cbf3081e71ab5bacb041d9678d64571`.

## Increment

- Classified OPF `text/vtt` manifest items as package `text-track` resources, including `.vtt` path inference and encrypted-resource role bucketing.
- Added checked-in fixture pair:
  - `lanes/pandoc/fixtures/upstream-current-epub-reader/epub/text-track-captions.epub`
  - `lanes/pandoc/fixtures/upstream-current-epub-reader/epub/text-track-captions.native`
- Generated the native golden with `/opt/homebrew/bin/pandoc -f epub -t native`.
- Normalized EPUB reader raw HTML media blocks so local AST output matches Pandoc native output for preserved `<video>` / `<track>` markup.

## Count Deltas

- Checked-in EPUB package inputs: `53 -> 54`.
- Checked-in same-basename `.native` goldens: `53 -> 54`.
- Checked-in fixture identity files: `106 -> 108`.
- Package feature coverage: `fixtureCount=54`, `manifestItems=201`, `metadataCreators=51`.
- Resource kind coverage includes `text-track:1`; related totals now include `navigation:55`, `video:3`, `xhtml:78`.

## Hashes

```text
2559039311ac1b9a25be74e4b4a7587cadc5579563a8d0ff1fb3b80503c30da5  text-track-captions.epub
e1f54a06e556fcd9a130357978b110a6931ed79f27af8424df8a861155b71eed  text-track-captions.native
1af32d34df903f28afae703774ba4c12863921d3506f50703a12d7813bf0f245  checked-in current package-feature signature
e0225ddb5570939c39757786bb746559f0e5bff25b4a6cac03fd4fb2552f2faf  checked-in current normalized native AST signature
```

## Evidence

Red-first package coverage run before `text/vtt` classification:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php
1 test files, 4137 assertions, 1 failures
Expected: 11
Actual: 10
```

Focused gates after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/Epub*.php
11 test files, 7950 assertions, 0 failures
```

```text
php tools/pandoc-epub-native-ast-package.php --checked-in-fixtures summary --require-package-parity=54 --require-native-readiness=54 --require-mapped-parity=54 --require-fixture-identity --require-current-package-feature-coverage --require-current-package-feature-signature --require-current-native-ast-signature --require-runner-plan
packages: total=54 compared=54 packageParsed=54 readerParsed=54 packageFailures=0 readerFailures=0
normalizedAst: matches=54 (100.00%) mismatches=0
fixtureIdentity: status=valid-checked-in-current-epub-fixture-identity expected=108 observed=108
```

```text
php tools/pandoc-epub-reader-evidence.php --checked-in-fixtures --json --require-native-ast-package-parity
nativeAstPackageParity: totalEpubCount=54 normalizedAstMatchCount=54
```

Parent integration note: shared manifest/workflow counters were intentionally left for parent integration; the local EPUB status test accepts both the current local fixture state and the shared manifest state while that update is pending.
