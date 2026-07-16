# PDF/Typst Import Path Boundary Conflicts

Slice: `pandoc-typst-import-path-boundary-conflicts`

Current base: `0490e796fe1c`

This slice keeps Typst import/include review metadata-only while tightening the source scanner and fake-run boundary review:

- masks Typst comments and string literals before searching for source `#import`/`#include` directives, preserving byte offsets for diagnostics
- records dynamic/non-literal import expression snippets, grouped unsupported-expression summaries, and duplicate unsupported-expression counts
- compares literal source package imports with Typst dependency sidecar package inputs in fakeRun, artifact review, and sequence output
- preserves the sidecar-driven `typstPackageDependencyPolicy` behavior

Counters:

- `phpPass`: 3444 -> 3445
- mapped denominator: 3393 -> 3394
- `mappedTypstImportPathPolicyCases`: 1 -> 2
- `typstImportPathPolicyAssertions`: 47 -> 96

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` passed 1 file, 2357 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` passed 46 files, 80275 assertions, 0 failures
