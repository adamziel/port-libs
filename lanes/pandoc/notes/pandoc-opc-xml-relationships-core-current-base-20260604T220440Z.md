# pandoc-opc-xml-relationships-core-current-base-20260604T220440Z

Base: `b0c5497210a93bba00e8d20d212e5fa445b9011f`

Source truth:

- Existing lane package contract: use native PHP ZIP/OPC primitives and do not
  shell out to Pandoc, zip/unzip, Office tools, Haskell test binaries, or
  online services.
- OPC relationship parts are package parts with the fixed relationship MIME
  type. A `.rels` entry under `_rels/` belongs to either the package root or a
  concrete source part; if that source part is absent, DOCX/EPUB import should
  surface the orphan instead of silently treating nested relationship metadata
  as reachable content.
- This is bounded native OPC package semantics, not upstream Haskell runner
  parity. The pinned Pandoc checkout is still not hydrated in this isolated
  worktree.

Implementation:

- Added `OpcRelationshipGraph::preflightPackageParts()`.
- The preflight skips `[Content_Types].xml` and ZIP directory entries, then
  reports each package part's resolved content type, whether it is a
  relationship part, its source part, source existence, validity, and issues.
- New issue codes:
  - `missing-content-type`
  - `invalid-relationship-content-type`
  - `orphan-relationship-part`
- Updated `wordpress-docx-opc-preflight.php` to include package-part integrity
  alongside relationship target and reachable-closure diagnostics.

Focused evidence:

- Red check before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: failed on missing `OpcRelationshipGraph::preflightPackageParts()`.
- Focused green check:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 214 assertions, 0 failures`
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`
- Full focused lane check:
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `13 test files, 3651 assertions, 0 failures`
- Syntax:
  - `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
  - `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - `php -l lanes/pandoc/examples/wordpress-docx-opc-preflight.php`
  - Result: no syntax errors
- Metadata and diff checks:
  - `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`
  - `git diff --check -- lanes/pandoc`
  - Result: passed with no output

Status delta:

- `phpPass`: `382 -> 383`
- mapped native checks: `839 -> 840`
- OPC package-part preflight cases: `0 -> 1`
- focused OPC assertions: `194 -> 214`

Dependency closure:

- No new support component is needed. This reuses the accepted native PHP
  `ZipPackage`, `OpcContentTypes`, `OpcRelationships`, and
  `OpcRelationshipGraph` paths.

Exclusions:

- Did not execute Pandoc, Cabal solver/build/test commands, Haskell test
  binaries, citeproc, BibTeX/Biber, bibliography managers, Word, LibreOffice,
  tar, zip/unzip, lz4, external template engines, TeX/PDF engines, MathJax,
  KaTeX, Typst, browser renderers, roff, or online services.
- Full upstream-runner parity remains gated on hydrating the Pandoc checkout
  and Cabal package/project files already described in lane status.

Next:

- Keep digital signature origin/relationships, embedded package policy,
  external target allow/deny policy, and stricter MIME grammar validation as
  separate bounded OPC slices.
