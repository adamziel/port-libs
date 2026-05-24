# Independent Audit - 2026-05-24T14:12Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all 12
`lanes/*/lane-status.json`, `dependency-backlog.json`, `audits/latest.md`, and
recent Git history through `2afd0097 Record integration hold status`. I did not
edit lane implementation files, launch agents or tmux sessions, push, read
secrets, inspect process environments, credential stores, provider configs, or
auth files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24 14:07-14:12
HEAD since previous audit: 5ad792ff672a -> a3e61039 -> 4eefd6f5d574 -> 2afd0097b1b4
recent history: 2afd0097 Record integration hold status; 4eefd6f Record integration hold status; a3e61039 Refresh independent audit status; 5ad792ff Record integration hold status; eb893809 Record integration hold status
branch sample: main...origin/main [ahead 903, behind 68]
tracked dirty rows: 329 -> 329 -> 331
default status rows including untracked: 18511 -> 18511 -> 18517
git diff --shortstat: 329 files changed, 245184 insertions(+), 30384 deletions(-) -> 329 files changed, 245409 insertions(+), 30450 deletions(-) -> 331 files changed, 245887 insertions(+), 30560 deletions(-)
dashboard snapshot: porting.html and porting-summary.json still publish source 89260857cc71; sampled HEAD is 2afd0097b1b4
dependency backlog: dependency-backlog.json has 37 rows (1 blocked, 25 candidate, 11 deferred, 0 active)
root run by this audit: not started
json validation: jq empty passed for all 12 lane manifests, all 12 lane-status files, porting-summary.json, and dependency-backlog.json
```

Required pre-root process gate:

```text
14:07Z pgrep -af '^php tools/run-tests\.php( |$)': 3851347 php tools/run-tests.php lanes/syncthing/tests; 3861092 php tools/run-tests.php lanes/readability/tests/ArticleExtractorTest.php
14:07Z owner evidence: PID 3851347 user claude elapsed 00:50 cmd php tools/run-tests.php lanes/syncthing/tests; PID 3861092 user claude elapsed 00:10 cmd php tools/run-tests.php lanes/readability/tests/ArticleExtractorTest.php
14:09Z pgrep -af '^php tools/run-tests\.php( |$)': 3878190 php tools/run-tests.php
14:09Z owner evidence: PID 3878190 user claude elapsed 00:26 cmd php tools/run-tests.php
14:12Z pgrep -af '^php tools/run-tests\.php( |$)': 3933498 php tools/run-tests.php lanes/readability/tests/ArticleExtractorTest.php
14:12Z owner evidence: PID 3933498 user claude elapsed 00:09 cmd php tools/run-tests.php lanes/readability/tests/ArticleExtractorTest.php
```

I did not start `php tools/run-tests.php`. The exact gate was occupied first by
focused lane harnesses and then by an active no-argument root harness owned by
`claude`; the final sample again had a focused lane harness active. Starting an
audit-owned root run would duplicate or overlap the harness gate and further
blur attribution for this moving dirty tree.

Current dashboard drift sample:

```text
lane          current status/manifest              dashboard
difftastic    status 3342 pass, manifest 917/1131  3245 pass, 851/1077
dolt          status 427 pass/0 fail               425 pass/0 fail
esbuild       status 443 pass, manifest 444 mapped 429 pass/mapped
gitoxide      status 7302 pass                     7152 pass
libsqlite     status/manifest 358 pass/mapped      348 pass, 349 mapped
LightningCSS  status 4137 pass, manifest 2820      4065 pass, 2765 mapped
markerPDF     status 494 pass, manifest 357/406    484 pass, 347/396
pandoc        status 372 pass, manifest 1995/2276  362 pass, 1891/2276
quadrable     status 238 pass                      232 pass
rclone        status/manifest 933 pass/mapped      906 pass/mapped
readability   status 3640 pass                     3545 pass
syncthing     status 8176 pass                     7902 pass
```

## Findings

1. **Critical - the repository is still not an acceptance checkpoint.**
   - Paths: `goal.md:29`, `goal.md:48`, `progress.md:48`,
     `lanes/dolt/lane-status.json:13`,
     `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/rclone/lane-status.json:13`.
   - Goal requirement at risk: small reviewable slices must be committed with
     passing tests, and finished agent work must be verified, committed,
     cleaned up, and assigned onward.
   - Evidence: since the previous audit `HEAD` advanced through another audit
     commit and two integration-hold commits, while tracked dirty row counts and
     shortstat changed during this audit. Every sampled lane still
     reports `pending`, `uncommitted`, `not committed`, or shared-dirty
     ownership instead of an accepted implementation commit.

2. **Critical - root verification is already active and must not be duplicated.**
   - Paths: `tools/run-tests.php`, `goal.md:49`, `progress.md:48`.
   - Goal requirement at risk: repo-wide tests and static checks must be run and
     recorded honestly from a stable snapshot, without duplicate root harnesses.
   - Evidence: the required exact gate first matched focused Syncthing and
     Readability harnesses, then matched active no-argument root PID `3878190`
     owned by `claude`, then matched focused Readability PID `3933498`. This
     audit correctly did not start a second `php tools/run-tests.php` run.

3. **High - `porting.html` and `porting-summary.json` are materially stale.**
   - Paths: `porting.html:32`, `porting.html:34`, `porting.html:35`,
     `porting.html:56`, `porting.html:67`, `porting-summary.json:2`,
     `porting-summary.json:3`, `goal.md:3`, `goal.md:45`.
   - Goal requirement at risk: the dashboard must show current denominator,
     mapped tests, PHP pass/fail, WordPress scenarios, phase, audit, current
     work, blocker, and commit.
   - Evidence: the dashboard still claims generated time
     `2026-05-24 12:29:46 UTC` and source `89260857cc71`, while current sampled
     `HEAD` is `2afd0097b1b4`. The drift table above shows every lane has newer
     pass counts, mapped counts, denominator counts, or status text than the
     published dashboard.

4. **High - support-library coverage is still backlog-only, not lane-granular evidence.**
   - Paths: `dependency-backlog.json:3`, `dependency-backlog.json:4`,
     `dependency-backlog.json:7`, `dependency-backlog.json:81`,
     `dependency-backlog.json:129`, `dependency-backlog.json:145`,
     `dependency-backlog.json:163`, `dependency-backlog.json:179`,
     `dependency-backlog.json:214`, `dependency-backlog.json:233`,
     `dependency-backlog.json:256`, `dependency-backlog.json:272`,
     `dependency-backlog.json:322`, `dependency-backlog.json:340`,
     `dependency-backlog.json:365`, `dependency-backlog.json:629`,
     `progress.md:17`, `porting.html:72`.
   - Goal requirement at risk: support libraries require a bounded native PHP
     component, activation gate, dependency-specific upstream/spec denominator,
     mapped fixtures, PHP pass/fail evidence, malformed/corrupt cases where
     relevant, and as much upstream/spec suite evidence as can actually run.
   - Evidence: `dependency-backlog.json` has 37 rows and `0` active rows. The
     only `UPSTREAM_TEST_MANIFEST.json` files outside `.upstream-cache` are the
     12 base-lane manifests. Pandoc's required DOC, DOCX/OpenXML, PDF
     handoff/text extraction, EPUB, ODT/OpenDocument, templates, citations,
     math, tables, package containers, XML/HTML, Unicode/charset, and
     archive/compression needs are represented as gated backlog rows, but none
     has a dependency-specific manifest, PHP pass/fail ledger, malformed or
     corrupt-case evidence, or bounded `sudo -n` install attempt/ruled-out note.

5. **High - markerPDF still blends native PDF evidence with external runtime and shell-boundary plans.**
   - Paths: `goal.md:1`, `goal.md:30`,
     `lanes/markerpdf/lane-status.json:5`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:861`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:863`.
   - Goal requirement at risk: wrappers around JS/Rust/Go/C binaries,
     shell-outs, bridge calls, whole applications, and external converter
     wrappers must not count as native deliverables.
   - Evidence: markerPDF has real native PDF extraction progress, now `357/406`
     mapped with `494` local behavior tests. The same denominator/status surface
     still includes Streamlit/FastAPI/Uvicorn app/server plans, chunk-convert
     shell lifecycle planning, PIL/pypdfium/PDFium, Poppler/Ghostscript,
     OCR/Tesseract/Texify/Torch/Nougat dependency boundaries, Poetry/package
     planning, and Pandoc/XeLaTeX helper execution. Those belong as blockers,
     supplied-runner contracts, or explicit non-goals, not accepted native
     progress.

6. **Medium - Pandoc's manifest still has stale machine-readable latest-slice metadata.**
   - Paths: `lanes/pandoc/lane-status.json:5`,
     `lanes/pandoc/lane-status.json:10`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:313`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:317`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:1247`,
     `goal.md:3`, `goal.md:35`.
   - Goal requirement at risk: lane status must precisely track current work,
     blockers, mapped upstream tests, and PHP pass/fail evidence.
   - Evidence: Pandoc status and denominator notes describe the
     `2026-05-24 14:02 UTC` HTML writer MathJax/KaTeX slice with `1,995`
     mapped checks and `371` PHP passes. The manifest's
     `nativeImplementation.latestSlice` still describes the older HTML
     raw-block/div slice, so machine-readable lane evidence disagrees with the
     lane's current status.

7. **Medium - near-complete percentages overstate accepted upstream parity.**
   - Paths: `porting.html:32`, `porting.html:56`, `porting.html:67`,
     `lanes/gitoxide/lane-status.json:4`,
     `lanes/markerpdf/lane-status.json:4`,
     `lanes/pandoc/lane-status.json:4`, `goal.md:35`, `goal.md:37`.
   - Goal requirement at risk: passing tests are not enough; upstream tests are
     the source of truth where possible, and hard gaps must be blockers or
     future slices.
   - Evidence: the public dashboard reports `98.3%` average progress, and most
     lane statuses report `98` or `99`, while all current lane handoffs are
     pending/uncommitted, a no-argument root harness was observed from a broad
     dirty aggregate and then a focused lane harness resumed, full upstream
     runners remain static/bounded/unexecuted for several lanes, and
     support-library work has no active bounded port.

8. **Medium - recent history remains status/hold dominated rather than accepted integration.**
   - Paths: `audits/latest.md`, `progress.md:46`, `goal.md:20`,
     `goal.md:48`.
   - Goal requirement at risk: the supervisor must integrate useful work,
     enforce standards, keep the roadmap honest, and assign the next
     highest-value slice after verification.
   - Evidence: the latest sampled commits are `2afd0097 Record integration hold
     status`, `4eefd6f Record integration hold status`, `a3e61039 Refresh
     independent audit status`, `5ad792ff Record integration hold status`, and
     `eb893809 Record integration hold status`, not accepted lane
     implementation commits.

## Next Intervention

Do not add more lane work on top of this aggregate. Freeze lane writers,
focused/root runners, dashboard/status publishers, support-library scouts,
capacity rows, and integration-hold writers. Recover and record the result for
the just-observed no-argument root PID `3878190` if its log is attributable to a
specific snapshot; otherwise treat it as non-acceptance evidence. Require two
stable polls of `HEAD`, tracked/default status counts, shortstat, exact
`pgrep -af '^php tools/run-tests\.php( |$)'`, dashboard/dependency counts, lane
status timestamps, and relevant log mtimes. Accept exactly one owner-free lane
batch at a time, normalizing manifest/status schema and commit fields before
claiming progress. Add support-library manifests only behind an accepted
base-lane gate or true component blocker. Regenerate `progress.md`,
`porting.html`, and `porting-summary.json` from the accepted commit before
restarting broad implementation.
