# Independent Audit - 2026-05-24T18:23Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, `dependency-backlog.json`, every
`lanes/*/UPSTREAM_TEST_MANIFEST.json`, sampled `lanes/*/lane-status.json`,
`audits/integration-status.md`, and recent Git history through
`314f3574 Record integration hold status`. I did not edit lane implementation
files, launch agents or tmux sessions, push, read secrets, inspect process
environments, credential stores, provider configs, or auth files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless they are explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24 18:08-18:23
current HEAD at audit write: 314f357474f7
observed HEAD movement during audit: 5b28ae3aaccc -> aba25b9cd969 -> 314f357474f7
recent history: 314f3574 Record integration hold status; aba25b9c Record integration hold status; 5b28ae3a Refresh independent audit status
branch sample: main...origin/main [ahead 970, behind 68]
tracked dirty rows: 332
default status rows including untracked: 20640
git diff --shortstat HEAD: 332 files changed, 269512 insertions(+), 32271 deletions(-)
dashboard snapshot: porting.html and porting-summary.json still publish source 89260857cc71 generated 2026-05-24 12:29:46 UTC
dependency backlog: 37 rows (0 active, 25 candidate, 1 blocked, 11 deferred), updated 2026-05-24 12:29:10 UTC
json validation by this audit: jq empty passed for all 12 lane manifests, all 12 lane-status files, dependency-backlog.json, and porting-summary.json; one earlier batch read hit a transient readability manifest parse error while files were being rewritten, then per-file validation passed
root run by this audit: not started
```

Required exact pre-root process gate:

```text
initial audit sample: pgrep -af '^php tools/run-tests\.php$' matched `663756 php tools/run-tests.php`
later audit sample: pgrep matched `668308 php tools/run-tests.php`
owner evidence: `668308 claude 668253 R 01:14 php tools/run-tests.php`
post-edit validation sample: pgrep matched `723156 php tools/run-tests.php`
owner evidence: `723156 claude 723058 R 00:38 php tools/run-tests.php`
final validation sample: pgrep matched `761407 php tools/run-tests.php`
owner evidence: `761407 claude 761238 R 00:08 php tools/run-tests.php`
post-commit spot check: pgrep matched `774384 php tools/run-tests.php`
owner evidence: `774384 claude 774307 R 00:20 php tools/run-tests.php`
```

I did not start `php tools/run-tests.php`. The exact no-argument root harness
gate was occupied during validation, and the checkout was still a moving broad
dirty aggregate.

Latest sampled manifest/status counts. These are samples from files that were
rewritten during the audit, not an acceptance ledger:

```text
lane          latest sampled manifest/status
difftastic    manifest 1052/1213; status 3624 assertions
dolt          manifest 613/613; status 446 PASS cases; manifest native PHP ledger still says 442
esbuild       manifest 465/2567; status 465 tests; manifest native PHP ledger still says 464
gitoxide      manifest 2877/2877; status 7558 assertions
libsqlite     manifest 374/1589; status 374 cases / 5992 assertions
LightningCSS  manifest 2952/3548; status 4263 assertions
markerPDF     manifest 376/425; status 514 behavior tests
pandoc        manifest 2251/2276; status 391 behavior tests
quadrable     manifest 55/55; status 251 behavior tests
rclone        manifest 971/1601; status 971 behavior tests
readability   manifest 1984/1984; status 3770 assertions; manifest native PHP ledger still says 285 behavior tests
syncthing     manifest 658/658; status 8783 assertions
```

## Findings

1. **Critical - the repository is still a live dirty aggregate, not an acceptance baseline.**
   - Paths: `progress.md:15`, `progress.md:51`,
     `audits/integration-status.md:15-39`,
     `audits/integration-status.md:43-51`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and
     `goal.md:52` require small reviewable committed slices, verified
     handoffs, and visible stable progress for every lane.
   - Evidence: `HEAD` moved during this audit from `5b28ae3aaccc` through
     `aba25b9cd969` to `314f357474f7`; the current worktree has `332`
     tracked dirty rows, `20640` untracked-inclusive status rows, and
     `332 files changed, 269512 insertions(+), 32271 deletions(-)`.
     The latest integration status records no lane/status claim accepted, no
     dashboard regeneration, no support-library activation, active Codex child
     processes for every primary lane, and no coherent owner-free lane batch.

2. **Critical - no trustworthy no-argument root acceptance result exists for the current tree.**
   - Paths: `tools/run-tests.php`, `audits/integration-status.md:35-39`.
   - Goal requirement at risk: `goal.md:49` requires periodic repo-wide tests
     and honest failure recording; the current audit instruction requires the
     exact duplicate-root gate before any no-argument root run.
   - Evidence: the required `pgrep -af '^php tools/run-tests\.php$'` gate
     matched `663756 php tools/run-tests.php`, later matched
     `668308 php tools/run-tests.php`, post-edit validation matched
     `723156 php tools/run-tests.php`, and final validation matched
     `761407 php tools/run-tests.php`. A post-commit spot check matched
     `774384 php tools/run-tests.php`. Owner evidence for active samples was
     `668308 claude 668253 R 01:14 php tools/run-tests.php` and
     `723156 claude 723058 R 00:38 php tools/run-tests.php`,
     `761407 claude 761238 R 00:08 php tools/run-tests.php`, and
     `774384 claude 774307 R 00:20 php tools/run-tests.php`. I did not start a
     duplicate. Any result from these moving dirty-root runs cannot be treated
     as acceptance for an accepted lane batch.

3. **Critical - `porting.html` and `porting-summary.json` are stale against current lane metadata.**
   - Paths: `porting.html:32-38`, `porting.html:56-67`,
     `porting-summary.json:2-8`, `porting-summary.json:10-205`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require the
     dashboard to track denominator, mapped tests, PHP pass/fail, phase,
     audit, current work, blocker, and commit.
   - Evidence: the dashboard still publishes source `89260857cc71`, generated
     `2026-05-24 12:29:46 UTC`, while current `HEAD` is `314f357474f7`.
     Current sampled lane files now report Difftastic `1052/1213` and `3624`
     assertions while the dashboard says `851/1077` and `3245`; markerPDF
     `376/425` and `514` behavior tests while the dashboard says `347/396`
     and `484`; Pandoc manifest `2251/2276` while the dashboard says
     `1891/2276`; rclone `971/1601` while the dashboard says `906/1601`;
     Readability `3770` assertions while the dashboard says `3545`; and
     Syncthing `8783` assertions while the dashboard says `7902`.

4. **High - manifest/status ledgers are still non-atomic and internally inconsistent.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/difftastic/lane-status.json:5-6`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2596`,
     `lanes/dolt/lane-status.json:6`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:16`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:437`,
     `lanes/esbuild/lane-status.json:6`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:1084`,
     `lanes/readability/lane-status.json:6`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and
     `goal.md:44-45` require durable coordination by denominator, mapped
     tests, PHP pass/fail, current work, blocker, and commit.
   - Evidence: Dolt status says `446` PASS cases while manifest
     `phpBehaviorTests` remains `442`. esbuild status says `465` while
     manifest `phpBehaviorTests` remains `464`. Readability status says
     `3770` assertions while manifest `phpBehaviorTests` still says `285`.
     During one all-manifest batch read, `jq` also hit a transient
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json` parse error before a
     per-file rerun passed, showing readers can observe files mid-write.

5. **High - support-library coverage is still backlog-only, not first-class lane-granular work.**
   - Paths: `dependency-backlog.json:3-4`,
     `dependency-backlog.json:7-22`, `dependency-backlog.json:81-94`,
     `dependency-backlog.json:129-176`,
     `dependency-backlog.json:214-287`,
     `dependency-backlog.json:340-425`,
     `dependency-backlog.json:629-645`, `porting.html:72-78`.
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
     lists only the 12 base lane manifests, not support-library manifests.
     There are no dependency-specific PHP ledgers, malformed/corrupt evidence
     records, accepted activation records, or bounded install-attempt notes.

6. **High - Pandoc remains far short of the original rich conversion-kernel goal despite 99 percent status language.**
   - Paths: `goal.md:12`, `lanes/pandoc/lane-status.json:5`,
     `lanes/pandoc/lane-status.json:11-14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:382`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:384-386`,
     `dependency-backlog.json:7-22`, `dependency-backlog.json:81-94`,
     `dependency-backlog.json:129-176`,
     `dependency-backlog.json:214-287`,
     `dependency-backlog.json:340-425`.
   - Goal requirement at risk: `goal.md:12` requires a document conversion
     kernel with a shared AST plus readers/writers for Markdown, HTML, WXR,
     EPUB/PDF-oriented intermediate forms, and WordPress block output.
   - Evidence: Pandoc reports `99%`, but full upstream Haskell runner parity
     remains unexecuted. The current work explicitly does not invoke upstream
     Pandoc, browser tooling, converter shell-outs, PDF processing,
     ZIP/package parsers, citation/CSL engines, PlainMath/MathML conversion,
     TeX math/ref conversion, XML/HTML support-library expansion, or broader
     syntax highlighting. DOC/DOCX/OpenXML, PDF input/output handoff, EPUB,
     ODT/OpenDocument, citations, math, templates, tables, JSON/YAML metadata,
     archive/compression, XML/HTML, Unicode, and charset remain inactive
     support rows. `rg -n 'WXR' lanes/pandoc` only finds incidental Markdown
     text/example references, not an accepted WXR reader/writer denominator.

7. **High - markerPDF still mixes native PDF progress with plan-only runtime/application evidence.**
   - Paths: `goal.md:9`, `lanes/markerpdf/lane-status.json:5`,
     `lanes/markerpdf/lane-status.json:12-14`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:10-19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:595`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1177-1206`.
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
     manifest/status ledgers disagree, several upstream runners remain
     static-only or bounded, and no support-library row is active.

## Next Intervention

Freeze or wait out active lane workers, root/focused runners, status
publishers, dashboard publishers, evaluator/auditor loops, capacity jobs, and
integrator loops. Then accept or reject one owner-free lane batch from a stable
two-poll snapshot. The current best intervention remains an integration freeze
plus one small intake: normalize the Dolt/esbuild/Readability manifest-status
count drift and stop non-atomic manifest/status writes first, then pick a
single owner-free lane batch with stopped logs, run focused verification, run
exactly one serialized no-argument `php tools/run-tests.php` only after
`pgrep -af '^php tools/run-tests\.php$'` is clear and the checkout is frozen,
run `git diff --check`, regenerate `porting.html` and `porting-summary.json`
from the accepted commit, and keep support-library rows inactive until a
base-lane slice is accepted or accepted-blocked on one bounded component.
