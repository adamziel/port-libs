# OPC XML Relationships Core Current Base

Micro-slice: `pandoc-opc-xml-relationships-core-current-base-20260608T211618Z`

Base accepted HEAD: `860604a0752757d495f65dc774700e48fce8b337`

## Scope

This slice adds bounded native OPC custom XML properties payload preflight:

- `OpcRelationshipGraph::preflightCustomXmlProperties()` reuses the existing WordprocessingML `customXmlProps` role/content-type checks.
- Correctly typed internal properties parts are parsed as `ds:datastoreItem` payloads.
- The preflight reports `ds:itemID` GUID shape, schemaRef URI metadata, wrong-root diagnostics, and malformed XML parse errors.
- The WordPress DOCX OPC preflight smoke now exposes the datastore item id and schemaRef URIs for reviewer/import handoff.

## Evidence

- Rework notes: `find /home/claude/port-libs/.tmux-team/tmp/handoff-candidates -maxdepth 1 -name 'port-pandoc-*.needs-lane-rework.md'` produced no output.
- Baseline focused run: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` passed with `1 test files, 2579 assertions, 0 failures`, but emitted an existing warning from a nested relationship fixture variable typo. This slice fixes that warning.
- New focused run before parse-error catch was corrected: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` failed with `1 test files, 2579 assertions, 1 failures` at malformed custom XML properties parse handling.
- Final focused run: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` passed with `1 test files, 2616 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test` passed with `opc docx preflight self-test ok`.
- PHP lint: `php -l lanes/pandoc/src/OpcRelationshipGraph.php`, `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`, and `php -l lanes/pandoc/examples/wordpress-docx-opc-preflight.php` all reported no syntax errors.
- JSON validation: `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'` reported `pandoc json ok`.
- Whitespace diff check: `git diff --check -- lanes/pandoc` passed with no output.

## Dependency Closure

No new support component is needed. The slice reuses native `ZipPackage`, `OpcContentTypes`, `OpcRelationships`, `OpcRelationshipGraph`, and `XmlHtmlDom` support. It does not run Pandoc, Word, LibreOffice, zip/unzip, XMLDSig validators, external XML tools, online services, live provider tests, or live-service provider tests.

## Follow-Up

Non-overlapping OPC follow-up candidates: encrypted package relationship policy, signature-object digest metadata, or DOCX reader integration that consumes the custom XML properties payload metadata. Do not repeat `customXmlProps` datastoreItem payload preflight.
