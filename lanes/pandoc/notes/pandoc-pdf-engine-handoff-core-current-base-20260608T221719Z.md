# Pandoc PDF Engine Handoff Current-Base DecodeParms

## Scope

This slice adds bounded native produced-PDF stream decode-parameter handoff to `PdfEngineHandoff`. It inspects `/DecodeParms` and abbreviated `/DP` dictionaries already attached to streams discovered by the fake-runner stream-filter policy, and reports filter-specific metadata for Flate predictors, CCITT fax parameters, and Crypt filter decode parameters.

The patch stays inside the PDF fake-runner boundary. It does not decode stream bytes and does not run Pandoc, Cabal/Haskell runners, TeX/PDF engines, Typst, browser renderers, roff, external PDF validators, online services, live provider tests, or live-service provider tests.

## Status Delta

- Added 1 mapped PDF engine handoff case.
- Added 11 focused assertions in `lanes/pandoc/tests/PdfEngineHandoffTest.php`.
- `lane-status.json` `phpPass` moves from 1912 to 1913.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped` moves from 2335 to 2336.
- PDF engine handoff inventory moves from 12 to 13 cases and from 108 to 119 assertions.

## Evidence

- Rework note check: `find /home/claude/port-libs/.tmux-team/tmp/handoff-candidates -maxdepth 1 -type f -name 'port-pandoc-*.needs-lane-rework.md'` returned no top-level rework notes for this lane.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` passed with `1 test files, 987 assertions, 0 failures`.
- Syntax: `php -l lanes/pandoc/src/PdfEngineHandoff.php`, `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`, and `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php` all reported no syntax errors.
- JSON: `jq empty lanes/pandoc/lane-status.json` and `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` passed.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` passed with `1 test files, 998 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test` passed with `pdf engine handoff self-test ok`.
- Diff check: `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This slice avoids the accepted PDF engine clusters for stream filter policy, XMP/PDF-A, output intents, tagged structure, catalog URI base, page display metadata, page timing, viewports, actions, page resources, signatures/DSS/ByteRange, AcroForm, optional content, collections, embedded-file filter policy, and rich media. It only adds decode-parameter metadata for streams already surfaced by the fake-runner inspection.

## Dependency Closure

No new support component is needed. The implementation reuses native PHP PDF object parsing, value parsing, reference resolution, and stream-filter policy summaries in `PdfEngineHandoff`. The next related support edge can stay local by adding object-stream member provenance, Crypt-filter permission detail, or incremental revision/signature provenance without invoking external engines.
