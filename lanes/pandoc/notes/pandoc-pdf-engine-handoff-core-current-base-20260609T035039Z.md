# Pandoc PDF Engine Handoff - Parent Tree Policy

Micro-slice: `pandoc-pdf-engine-handoff-core-current-base-20260609T035039Z`
Base accepted HEAD: `64291fcd23e3d1b723e600a8842760d1fbcdb417`
Lane: `pandoc`

## Scope

This slice adds native fake-runner diagnostics for tagged-PDF structure parent-tree integrity without invoking Pandoc, TeX/PDF engines, Typst, browser renderers, Haskell runners, office tools, zip/unzip, online services, or external validators.

The new `pdfStructureParentTreePolicy` summary reviews already parsed PDF bytes for:

- duplicate parent-tree MCIDs;
- parent-tree MCIDs outside declared number-tree `/Limits`;
- missing structure references;
- references that resolve to non-`StructElem` objects;
- non-artifact marked-content MCIDs that are absent from the structure parent tree.

PDF artifacts are excluded from the missing-parent check because artifact marked content is not supposed to be represented in the structure tree.

## Evidence

Rework notes checked first:

- No current `port-pandoc-*.needs-lane-rework.md` note was present for this lane in `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.

Baseline focused test before the patch:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
- Result: `1 test files, 1191 assertions, 0 failures`

Final focused tests after the patch:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- Result: no syntax errors detected
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- Result: no syntax errors detected
- `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php`
- Result: no syntax errors detected
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
- Result: `1 test files, 1209 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
- Result: `pdf engine handoff self-test ok`

JSON validation:

- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
- Result: `lane-status json ok`
- `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "manifest json ok\n";'`
- Result: `manifest json ok`

Final diff hygiene:

- `git diff --check -- lanes/pandoc`
- Result: clean, no output

Root harness:

- not run - isolated micro-slice

## Status Delta

- `lane-status.json` `phpPass`: `2254 -> 2255`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2660 -> 2661`
- `pdfEngineHandoffCoreCases`: `12 -> 13`
- `mappedPdfEngineHandoffCoreCases`: `12 -> 13`
- `pdfEngineHandoffCoreAssertions`: `108 -> 126`
- Focused PDF handoff assertions: `1191 -> 1209`

## Non-Overlap

This slice does not repeat prior PDF engine work for argv planning, templates, source/resource validation, engine log classification, sidecars, output metrics, xref/object streams, page trees, outlines, page boxes, page labels, document info, XMP, output intents, catalog/viewer policy, name trees, destinations, basic tagging metadata, structure parent-tree extraction, structure element extraction, structure attributes, ID trees, annotations, stream filters, rich media, embedded files, associated files, marked-content properties/artifacts, page content stream summaries, optional content, encryption preflight, signature policies, active actions, web capture, legal attestation, DSS, or PDF/A/PDF/UA conformance review.

The new behavior is limited to integrity policy summarization for the structure parent tree using existing native PDF byte inspection helpers.

## Dependency Closure

No new support component is needed. The implementation reuses the native `PdfEngineHandoff` PDF object parser plus existing structure parent-tree extraction, structure element extraction, page content MCID summaries, marked-content property summaries, marked-content artifact summaries, focused `PdfEngineHandoffTest.php` coverage, and the WordPress PDF handoff example.

Full upstream Pandoc runner parity remains a separate upstream-runner dependency task requiring a hydrated Pandoc checkout and Haskell test executables.

## Follow-Up

Good non-overlapping PDF engine follow-ups are tagged-PDF ID-tree policy review, signature appearance byte-range metadata, or another fake-produced output diagnostic that stays native PHP and does not invoke external renderers or validators.
