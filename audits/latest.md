# Independent Audit - 2026-05-24T14:21Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 root `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all
12 `lanes/*/lane-status.json`, `dependency-backlog.json`, and recent Git
history through `bda91e40 Record integration hold status`. I did not edit lane
implementation files, launch agents or tmux sessions, push, read secrets,
inspect process environments, credential stores, provider configs, or auth
files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24 14:16-14:21
HEAD moved during this audit: 8d99c21411bc -> dd2eb9a0a61f -> bda91e40435c
recent history: bda91e40 Record integration hold status; dd2eb9a0 Record integration hold status; 8d99c214 Refresh independent audit status; 2afd0097 Record integration hold status; 4eefd6f5 Record integration hold status
branch sample: main...origin/main [ahead 905, behind 68] before the latest integration-hold commit
tracked dirty rows: 330 -> 329 -> 331
default status rows including untracked: 18523 -> 18522 -> 18528 -> 18535
git diff --shortstat: 330 files changed, 246216 insertions(+), 30424 deletions(-) -> 329 files changed, 246392 insertions(+), 30403 deletions(-) -> 329 files changed, 246422 insertions(+), 30407 deletions(-) -> 331 files changed, 246896 insertions(+), 30517 deletions(-)
dashboard snapshot: porting.html and porting-summary.json still publish source 89260857cc71 generated 2026-05-24 12:29:46 UTC; sampled HEAD is bda91e40435c
dependency backlog: dependency-backlog.json has 37 rows (1 blocked, 25 candidate, 11 deferred, 0 active)
root run by this audit: not started
json validation: jq empty passed for all 12 lane manifests, all 12 lane-status files, porting-summary.json, and dependency-backlog.json
```

Required pre-root process gate:

```text
14:16Z pgrep -af '^php tools/run-tests\.php( |$)': no rows
14:18Z pgrep -af '^php tools/run-tests\.php( |$)': no rows
14:19Z pgrep -af '^php tools/run-tests\.php( |$)': 3987420 php tools/run-tests.php lanes/syncthing/tests
14:19Z owner evidence: PID 3987420 user claude elapsed 00:50 cmd php tools/run-tests.php lanes/syncthing/tests
14:21Z pgrep -af '^php tools/run-tests\.php( |$)': 4025977 php tools/run-tests.php lanes/readability/tests/ArticleExtractorTest.php
14:21Z owner evidence: PID 4025977 exited before owner sampling
14:21Z second pgrep -af '^php tools/run-tests\.php( |$)': no rows
```

I did not start `php tools/run-tests.php`. Even when the exact gate was briefly
clear, the checkout failed the stability gate: `HEAD`, untracked-inclusive
status rows, dirty shortstat, and lane manifest/status counts changed during the
audit. A focused Syncthing harness was active at 14:19Z and a transient focused
Readability harness appeared at 14:21Z before the gate cleared again.

Current dashboard drift sample:

```text
lane          current status/manifest              dashboard
difftastic    status 3342 pass, manifest 925/1139  3245 pass, 851/1077
dolt          status 427 pass/0 fail               425 pass/0 fail
esbuild       status 444 pass, manifest 445 mapped 429 pass/mapped
gitoxide      status 7308 pass                     7152 pass
libsqlite     status/manifest 359 pass/mapped      348 pass, 349 mapped
LightningCSS  status 4137 pass, manifest 2829      4065 pass, 2765 mapped
markerPDF     status 495 pass, manifest 358/407    484 pass, 347/396
pandoc        status 372 pass, manifest 1995/2276  362 pass, 1891/2276
quadrable     status 239 pass                      232 pass
rclone        status/manifest 935 pass/mapped      906 pass/mapped
readability   status 3652 pass                     3545 pass
syncthing     status 8204 pass                     7902 pass
```

## Findings

1. **Critical - the repository is still not an acceptance checkpoint.**
   - Paths: `goal.md:29`, `goal.md:48`, `progress.md:48`,
     `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`,
     `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/libsqlite/lane-status.json:13`,
     `lanes/lightningcss/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/pandoc/lane-status.json:13`,
     `lanes/quadrable/lane-status.json:13`,
     `lanes/rclone/lane-status.json:13`,
     `lanes/readability/lane-status.json:13`,
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: small reviewable slices must be committed with
     passing tests, and finished agent work must be verified, committed,
     cleaned up, and assigned onward.
   - Evidence: `HEAD` moved from `8d99c21411bc` through `dd2eb9a0a61f` to
     `bda91e40435c` during this audit, while dirty counts and shortstat
     changed. Every lane status still
     reports `pending`, `uncommitted`, `not committed`, or a shared dirty
     worktree handoff. This is a broad aggregate of lane output, not a
     supervisor-accepted implementation batch.

2. **Critical - no-argument root verification is not attributable to a frozen snapshot.**
   - Paths: `tools/run-tests.php`, `goal.md:49`, `progress.md:48`.
   - Goal requirement at risk: repo-wide tests and static checks must be run
     periodically and recorded honestly from a stable tree.
   - Evidence: the exact pre-root gate was briefly empty at 14:16Z and 14:18Z,
     but the checkout kept moving; by 14:19Z the gate matched focused
     Syncthing PID `3987420` owned by `claude`, and at 14:21Z a transient
     focused Readability PID `4025977` appeared before owner sampling. Starting an audit-owned
     no-argument root run would create more non-attributable evidence on a
     changing broad dirty aggregate.

3. **High - `porting.html` and `porting-summary.json` are materially stale.**
   - Paths: `porting.html:32`, `porting.html:34`, `porting.html:35`,
     `porting.html:56`, `porting.html:67`, `porting-summary.json`,
     `goal.md:3`, `goal.md:45`.
   - Goal requirement at risk: the dashboard must show current denominator,
     mapped tests, PHP pass/fail, WordPress scenarios, phase, audit, current
     work, blocker, and commit.
   - Evidence: the dashboard still claims source `89260857cc71` generated at
     `2026-05-24 12:29:46 UTC`, while the sampled `HEAD` is
     `bda91e40435c`. The drift table above shows all 12 lanes have newer
     manifest/status counts than the published dashboard.

4. **High - support-library coverage is still backlog-only, not first-class lane-granular work.**
   - Paths: `dependency-backlog.json:7`, `dependency-backlog.json:82`,
     `dependency-backlog.json:129`, `dependency-backlog.json:146`,
     `dependency-backlog.json:164`, `dependency-backlog.json:179`,
     `dependency-backlog.json:214`, `dependency-backlog.json:233`,
     `dependency-backlog.json:256`, `dependency-backlog.json:272`,
     `dependency-backlog.json:322`, `dependency-backlog.json:340`,
     `dependency-backlog.json:365`, `dependency-backlog.json:629`,
     `progress.md:17`, `porting.html:72`.
   - Goal requirement at risk: support libraries require a bounded native PHP
     component, activation gate, dependency-specific upstream/spec denominator,
     mapped fixtures, PHP pass/fail evidence, malformed/corrupt cases where
     relevant, and as much upstream/spec suite evidence as can actually run.
   - Evidence: `dependency-backlog.json` has 37 rows and 0 active rows; only
     the 12 base-lane root manifests exist under `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
     Pandoc's required DOC, DOCX/OpenXML, PDF handoff/text extraction, EPUB,
     ODT/OpenDocument, templates, citations, math, tables, package containers,
     XML/HTML, Unicode/charset, and archive/compression needs are present as
     gated backlog rows, but none has a dependency-specific manifest, PHP
     pass/fail ledger, malformed/corrupt-case evidence, or bounded `sudo -n`
     install-attempt/ruled-out notes.

5. **High - markerPDF still blends native PDF progress with external runtime and shell-boundary plans.**
   - Paths: `goal.md:1`, `goal.md:30`,
     `lanes/markerpdf/lane-status.json:5`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:797`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:805`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:823`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1122`.
   - Goal requirement at risk: wrappers around JS/Rust/Go/C binaries,
     shell-outs, bridge calls, whole applications, and external converter
     wrappers must not count as native deliverables.
   - Evidence: markerPDF has real native PDF extraction progress, now `358/407`
     mapped with `495` local behavior tests. The same denominator/status
     surface still includes Streamlit/FastAPI/Uvicorn plans, chunk-convert
     shell lifecycle planning, PIL/pypdfium/PDFium, Poppler/Ghostscript,
     OCR/Tesseract/Texify/Torch/Nougat dependency boundaries, Poetry/package
     planning, and Pandoc/XeLaTeX helper execution. These must stay blockers,
     supplied-runner contracts, or explicit non-goals, not accepted native
     progress.

6. **Medium - Pandoc rich-function support is routed but not proven.**
   - Paths: `lanes/pandoc/lane-status.json:5`,
     `lanes/pandoc/lane-status.json:12`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:316`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:1249`,
     `dependency-backlog.json:82`, `dependency-backlog.json:129`,
     `dependency-backlog.json:146`, `dependency-backlog.json:164`,
     `dependency-backlog.json:179`, `dependency-backlog.json:214`,
     `dependency-backlog.json:233`, `dependency-backlog.json:256`.
   - Goal requirement at risk: Pandoc must account for essential rich
     conversion libraries with real upstream/spec-suite evidence rather than
     fixture-only credit unless broader suites were attempted and bounded.
   - Evidence: Pandoc's latest slice honestly narrows HTML math output and says
     PlainMath/MathML conversion is not claimed, but the lane still reports
     `99%` while full Haskell runner parity is unexecuted and all supporting
     DOC/DOCX/PDF/EPUB/ODT/template/citation/math/package/XML/Unicode/charset
     rows remain inactive backlog entries with no separate support manifests.

7. **Medium - near-complete percentages overstate accepted parity.**
   - Paths: `porting.html:32`, `porting.html:56`, `porting.html:67`,
     `lanes/gitoxide/lane-status.json:4`,
     `lanes/markerpdf/lane-status.json:4`,
     `lanes/pandoc/lane-status.json:4`, `goal.md:35`, `goal.md:37`.
   - Goal requirement at risk: passing tests are not enough; upstream tests are
     the source of truth where possible, and hard gaps must be blockers or
     future slices.
   - Evidence: the public dashboard still reports `98.3%` average progress and
     most lane statuses report `98` or `99`, while every lane handoff is
     pending/uncommitted, full upstream runners remain static/bounded or
     unexecuted for several lanes, root verification is not attributable to a
     fixed snapshot, and support-library work has no active bounded port.

8. **Medium - recent history remains status/hold dominated.**
   - Paths: `audits/latest.md`, `progress.md:46`, `goal.md:20`,
     `goal.md:48`.
   - Goal requirement at risk: the supervisor must integrate useful work,
     enforce standards, keep the roadmap honest, and assign the next
     highest-value slice after verification.
   - Evidence: the latest commits are `bda91e40 Record integration hold
     status`, `dd2eb9a0 Record integration hold status`, `8d99c214 Refresh
     independent audit status`, `2afd0097 Record integration hold status`, and
     `4eefd6f5 Record integration hold status`, not accepted lane
     implementation commits.

## Next Intervention

Freeze lane writers, focused/root runners, dashboard/status publishers,
support-library scouts, capacity rows, and integration-hold writers. Record the
results for focused Syncthing PID `3987420` and transient focused Readability
PID `4025977` only if attributable to specific snapshots. Require two stable polls of
`HEAD`, tracked/default status counts, shortstat, exact
`pgrep -af '^php tools/run-tests\.php( |$)'`, dashboard/dependency counts,
lane status timestamps, and relevant log mtimes. Accept exactly one owner-free
lane batch at a time, normalizing manifest/status schema and commit fields
before claiming progress. Add support-library manifests only behind an
accepted base-lane gate or true component blocker. Regenerate `progress.md`,
`porting.html`, and `porting-summary.json` from the accepted commit, then run
one serialized no-argument root harness only if the exact process gate remains
empty on that frozen snapshot.
