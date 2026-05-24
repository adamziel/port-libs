# Independent Audit - 2026-05-24T18:27Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, `dependency-backlog.json`, every
`lanes/*/UPSTREAM_TEST_MANIFEST.json`, sampled `lanes/*/lane-status.json`,
and recent Git history through `12a79d56 Record next integration queue`. I did
not edit lane implementation files, launch agents or tmux sessions, push, read
secrets, inspect process environments, credential stores, provider configs, or
auth files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless they are explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24 18:24-18:27
observed HEAD movement during audit: cac668c6ddd0 -> 83f0c309e693 -> 12a79d56908e
current HEAD at audit write: 12a79d56908e
recent history: 12a79d56 Record next integration queue; 83f0c309 Record LightningCSS handoff rejection; cac668c6 Record integration handoff rejections
branch sample: main...origin/main [ahead 975, behind 68]
tracked dirty rows: 330
default status rows including untracked: 20827
git diff --shortstat HEAD: 330 files changed, 269647 insertions(+), 32119 deletions(-)
dashboard snapshot: porting.html and porting-summary.json still publish source 89260857cc71 generated 2026-05-24 12:29:46 UTC
dependency backlog: 37 rows (0 active, 25 candidate, 1 blocked, 11 deferred), updated 2026-05-24 12:29:10 UTC
json validation by this audit: jq empty passed for all 12 lane manifests, all 12 lane-status files, dependency-backlog.json, and porting-summary.json
root run by this audit: not started
```

Required exact pre-root process gate:

```text
initial audit sample: pgrep -af '^php tools/run-tests\.php$' matched `774384 php tools/run-tests.php`; it exited before owner sampling
later audit sample: pgrep matched `818257 php tools/run-tests.php`
owner evidence: `818257 claude 818206 R+ 01:02 php tools/run-tests.php`
pre-commit sample: pgrep matched `834683 php tools/run-tests.php`
owner evidence: `834683 claude 834607 R+ 00:15 php tools/run-tests.php`
```

I did not start `php tools/run-tests.php`. The exact no-argument root harness
gate was occupied, and the checkout was still a moving broad dirty aggregate.

Latest sampled manifest/status counts. These are samples from a moving
worktree, not an acceptance ledger:

```text
lane          latest sampled manifest/status
difftastic    manifest 1052/1213; status 3624 assertions
dolt          manifest 613/613; status 448 PASS cases; manifest native PHP ledger still says 442
esbuild       manifest 467/2567; status 467 tests
gitoxide      manifest 2877/2877; status 7578 assertions
libsqlite     manifest 374/1589; status 374 cases / 5992 assertions
LightningCSS  manifest 2952/3548; status 4276 assertions
markerPDF     manifest 376/425; status 514 behavior tests
pandoc        manifest 2251/2276; status 391 behavior tests
quadrable     manifest 55/55; status 252 behavior tests
rclone        manifest 971/1601; status 971 behavior tests
readability   manifest 1984/1984; status 3834 assertions / 286 behavior tests
syncthing     manifest 658/658; status 8884 assertions
```

## Findings

1. **Critical - the repository is still a live dirty aggregate, not an acceptance baseline.**
   - Paths: `progress.md:15`, `progress.md:49-51`,
     `lanes/dolt/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/readability/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and
     `goal.md:52` require small reviewable committed slices, verified
     handoffs, and visible stable progress for every lane.
   - Evidence: `HEAD` moved during this audit from `cac668c6ddd0` to
     `83f0c309e693` to `12a79d56908e`. The checkout still has `330`
     tracked dirty rows, `20827` untracked-inclusive status rows, and
     `330 files changed, 269647 insertions(+), 32119 deletions(-)`.
     Current lane statuses continue to describe uncommitted or pending
     handoffs owned by the supervisor/integrator rather than accepted lane
     commits.

2. **Critical - no trustworthy no-argument root acceptance result exists for the current tree.**
   - Paths: `tools/run-tests.php`, `progress.md:51`.
   - Goal requirement at risk: `goal.md:49` requires periodic repo-wide tests
     and honest failure recording; the current audit instruction requires the
     exact duplicate-root gate before any no-argument root run.
   - Evidence: the required `pgrep -af '^php tools/run-tests\.php$'` gate
     matched `774384 php tools/run-tests.php` early in this audit, then later
     matched `818257 php tools/run-tests.php`; the pre-commit gate then
     matched `834683 php tools/run-tests.php`. Owner evidence included
     `818257 claude 818206 R+ 01:02 php tools/run-tests.php` and
     `834683 claude 834607 R+ 00:15 php tools/run-tests.php`.
     I did not start a duplicate root run. Even if an external dirty-root run
     finishes green, it cannot be acceptance evidence for an owner-free frozen
     snapshot while `HEAD`, manifests, and status files continue to move.

3. **Critical - `porting.html` and `porting-summary.json` are stale against current lane metadata.**
   - Paths: `porting.html:32-38`, `porting.html:56-67`,
     `porting-summary.json:2-8`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require the
     dashboard to track denominator, mapped tests, PHP pass/fail, phase,
     audit, current work, blocker, and commit.
   - Evidence: the dashboard still publishes source `89260857cc71`, generated
     `2026-05-24 12:29:46 UTC`, while sampled `HEAD` is `12a79d56908e`.
     Current sampled lane files now report Difftastic `1052/1213` and `3624`
     assertions while the dashboard says `851/1077` and `3245`; Dolt `448`
     PASS cases while the dashboard says `425`; esbuild `467` while the
     dashboard says `429`; Gitoxide `7578` assertions while the dashboard says
     `7152`; markerPDF `376/425` and `514` behavior tests while the dashboard
     says `347/396` and `484`; Pandoc `2251/2276` while the dashboard says
     `1891/2276`; Readability `3834` assertions while the dashboard says
     `3545`; and Syncthing `8884` assertions while the dashboard says `7902`.

4. **High - manifest/status ledgers are still non-atomic and internally inconsistent.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2592`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2599`,
     `lanes/dolt/lane-status.json:5-13`,
     `lanes/lightningcss/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and
     `goal.md:44-45` require durable coordination by denominator, mapped
     tests, PHP pass/fail, current work, blocker, and commit.
   - Evidence: Dolt status reports `448` PASS cases and a JSON_VALUE slice,
     but the manifest native PHP ledger still says `phpBehaviorTests: 442`
     and its warning prose still references `430 PASS cases`. During this
     audit, Dolt status sampling moved from the prior JSON_PRETTY/446-style
     state to the JSON_VALUE/448 state. LightningCSS status still says
     `HEAD 314f357474f7 at status update` while sampled `HEAD` is
     `12a79d56908e`. Readers are observing live status churn rather than
     atomic snapshot files.

5. **High - support-library coverage is still backlog-only, not first-class lane-granular work.**
   - Paths: `dependency-backlog.json:3-22`,
     `dependency-backlog.json:82-94`,
     `dependency-backlog.json:131-176`,
     `dependency-backlog.json:257-268`,
     `dependency-backlog.json:406-426`,
     `dependency-backlog.json:629-646`, `porting.html:72-78`.
   - Goal requirement at risk: `goal.md:35-40` require meaningful fixture
     parity, edge-case coverage, upstream tests as source of truth, and
     explicit blockers; the latest support-library directive requires bounded
     native support components with activation gates, dependency-specific
     upstream/spec denominators, mapped fixtures, PHP pass/fail evidence,
     malformed/corrupt cases where relevant, and bounded install-attempt notes
     where tooling is missing.
   - Evidence: the backlog has visible rows for Pandoc DOC, DOCX/OpenXML,
     PDF input/output handoff, EPUB, ODT/OpenDocument, templates, citations,
     math, tables, package containers, XML/HTML, Unicode/charset,
     JSON/YAML metadata, syntax highlighting, and archive/compression, but it
     still has `0` active support rows. `rg --files -g '*UPSTREAM_TEST_MANIFEST.json'`
     returns only the 12 base lane manifests, not dependency-specific support
     manifests. There are no accepted support PHP ledgers, malformed/corrupt
     evidence records, accepted activation records, or bounded install-attempt
     notes for these rows.

6. **High - Pandoc remains far short of the original rich conversion-kernel goal despite 99 percent status language.**
   - Paths: `goal.md:12`, `lanes/pandoc/lane-status.json:4-5`,
     `lanes/pandoc/lane-status.json:11-14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:389-391`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:1390`,
     `lanes/pandoc/tests/MarkdownReaderTest.php:2157`.
   - Goal requirement at risk: `goal.md:12` requires a document conversion
     kernel with a shared AST plus readers/writers for Markdown, HTML, WXR,
     EPUB/PDF-oriented intermediate forms, and WordPress block output.
   - Evidence: Pandoc reports `99%`, but full upstream Haskell runner parity
     remains unexecuted. The current slice explicitly does not invoke upstream
     Pandoc, browser tooling, converter shell-outs, PDF processing,
     ZIP/package parsers, citation/CSL engines, PlainMath/MathML conversion,
     TeX math/ref conversion, XML/HTML support-library expansion, or broader
     syntax highlighting. DOC/DOCX/OpenXML, PDF input/output handoff, EPUB,
     ODT/OpenDocument, citations, math, templates, tables, JSON/YAML metadata,
     archive/compression, XML/HTML, Unicode, and charset remain inactive
     support rows. `rg -n 'WXR' lanes/pandoc` only finds the literal text
     "Export WXR" inside a Markdown reader test, not an accepted WXR reader or
     writer denominator.

7. **High - markerPDF still mixes native PDF progress with plan-only runtime/application evidence.**
   - Paths: `goal.md:9`, `lanes/markerpdf/lane-status.json:4-5`,
     `lanes/markerpdf/lane-status.json:12-14`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:10-19`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`, and
     `goal.md:35-40` say wrappers, shell-outs, and plan-only application
     behavior must not count as native implementation progress.
   - Evidence: the native PDF BT/ET text-object gate is useful and explicitly
     avoids Python/pdftext/pypdfium/Poppler/Ghostscript, but the denominator
     still includes benchmark archive planning, Streamlit app flow,
     FastAPI/Uvicorn server shape, Poetry/package metadata, OCR install
     planning, multiprocessing/chunk shell lifecycle, model-runtime dependency
     graphs, and other plan-only runtime evidence. Richer searchable PDF,
     OCR/layout, and table work should be credited only through accepted
     bounded rows such as `pdf-text-dictionary-core`,
     `layout-ocr-result-core`, and `table-geometry-core`.

8. **Medium - the 98-99 percent progress claims remain misleading.**
   - Paths: `porting.html:32`, `porting.html:56-67`,
     `porting-summary.json:8`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:35-40` require meaningful fixture
     parity, edge-case coverage, upstream tests as source of truth, and
     explicit blockers for hard features.
   - Evidence: the published average is `98.3%` and most lanes report
     `98-99%`, but the dashboard is stale, current lane changes are
     unaccepted, the dirty tree has no serialized root acceptance result,
     manifest/status ledgers still disagree, several upstream runners remain
     static-only or bounded, and no support-library row is active.

## Next Intervention

Freeze or wait out active lane workers, root/focused runners, status
publishers, dashboard publishers, evaluator/auditor loops, capacity jobs, and
integrator loops. Then accept or reject one owner-free lane batch from a stable
two-poll snapshot. The current best intervention remains an integration freeze
plus one small intake: normalize Dolt manifest-status count drift and enforce
atomic manifest/status writes first, then pick a single owner-free lane batch
with stopped logs, run focused verification, run exactly one serialized
no-argument `php tools/run-tests.php` only after
`pgrep -af '^php tools/run-tests\.php$'` is clear and the checkout is frozen,
run `git diff --check`, regenerate `porting.html` and `porting-summary.json`
from the accepted commit, and keep support-library rows inactive until a
base-lane slice is accepted or accepted-blocked on one bounded component.
