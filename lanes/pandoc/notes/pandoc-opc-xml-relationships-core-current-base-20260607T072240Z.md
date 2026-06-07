# OPC XML Relationships Core Current Base - Thumbnail Preflight

Micro-slice: `pandoc-opc-xml-relationships-core-current-base-20260607T072240Z`
Accepted base: `f59b519bb251aefa4fdb1c3cda61b4eaa10eaee0`

## Behavior

Added bounded native OPC thumbnail relationship preflight in `OpcRelationshipGraph::preflightThumbnails()`.

Source truth used for this bounded cluster: the ECMA-376 thumbnail-part reference at `https://c-rex.net/samples/ooxml/e1/Part1/OOXML_P1_Fundamentals_Thumbnail_topic_ID0EWLEO.html` identifies thumbnail parts by the `http://schemas.openxmlformats.org/package/2006/relationships/metadata/thumbnail` relationship type, allows any supported image content type, requires internal targets, limits package and part sources to one thumbnail relationship each, and says thumbnail parts shall not have relationships to other standard parts.

The new graph helper reuses existing OPC relationship target preflight rows and adds thumbnail-specific package import checks:

- preserves target existence, content type, relationship-type URI, external-target, relationship-part-target, and rewrite diagnostics from `preflightTargetsForSource()`;
- reports `multiple-thumbnail-relationships-for-source` when a package or part source declares more than one thumbnail relationship;
- reports `external-thumbnail-target` for external thumbnail targets;
- reports `invalid-thumbnail-content-type` when an internal thumbnail target has a declared content type whose media type is not `image/*`;
- reports `thumbnail-target-has-relationships` when the target part also has a loaded relationship part.

The WordPress DOCX OPC preflight example now exposes top-level `thumbnailPreflight` rows and `wordpressImport.thumbnailParts` for package thumbnail review.

## Focused Evidence

- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `php -l lanes/pandoc/examples/wordpress-docx-opc-preflight.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 1574 assertions, 0 failures`
  - Delta: `+1` PHP PASS case and `+41` focused assertions for thumbnail relationship preflight.
- `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $f) { json_decode(file_get_contents($f), true, 512, JSON_THROW_ON_ERROR); echo $f, " ok\n"; }'`
  - Result: both updated JSON files parsed.
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `ZipPackage`, `OpcContentTypes`, `OpcRelationships`, `OpcRelationshipGraph` target preflight, and the existing PHP test/example harness.

No Pandoc, Cabal, Haskell runner, Word, LibreOffice, zip/unzip, external XML tool, XMLDSig validator, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This avoids the accepted OPC Pack URI part-name validation, content-type inventory, core-properties preflight, officeDocument-root preflight, digital-signature relationship-transform, embedded package/object, and relationship closure slices. It owns only package metadata thumbnail relationship policy and WordPress OPC review surfacing.

## Follow-Up

Potential follow-up remains bounded OPC relationship semantics: additional package metadata roles, relationship transform selector integration, or DOCX reader use of existing OPC preflight diagnostics. Do not widen this into full Office validation or external thumbnail decoding/fetching.
