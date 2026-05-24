# Independent Audit - 2026-05-24T13:56Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all 12
`lanes/*/lane-status.json`, `dependency-backlog.json`, `audits/latest.md`,
`audits/integration-status.md`, and recent Git history through
`7dafaf8f Record integration hold status`. I did not edit lane implementation
files, launch agents or tmux sessions, push, read secrets, inspect process
environments, credential stores, provider configs, or auth files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24 13:48-13:56
HEAD moved during audit: bebf4cbb60f9 -> 7dafaf8f08b3
recent history: 7dafaf8f Record integration hold status; bebf4cbb Record integration hold status; 231cfa8f Refresh independent audit status; c3f971d3 Record integration hold status; 7cc6b522 Refresh independent audit status
branch sample: main...origin/main [ahead 897, behind 68]
tracked dirty rows: 329 -> 332
default status rows including untracked: 18484 -> 18499
git diff --shortstat: 329 files changed, 244538 insertions(+), 31283 deletions(-) -> 332 files changed, 245193 insertions(+), 31394 deletions(-)
dashboard snapshot: porting.html and porting-summary.json still publish source 89260857cc71; sampled HEAD is 7dafaf8f08b3
dependency backlog: dependency-backlog.json has 37 rows (1 blocked, 25 candidate, 11 deferred, 0 active)
root run by this audit: not started
json validation: jq empty passed for all 12 lane manifests, all 12 lane-status files, porting-summary.json, and dependency-backlog.json
```

Required pre-root process gate:

```text
13:48Z pgrep -af '^php tools/run-tests\.php( |$)': no rows
13:51Z pgrep -af '^php tools/run-tests\.php( |$)': no rows
13:55Z pgrep -af '^php tools/run-tests\.php( |$)': 3703958 php tools/run-tests.php lanes/syncthing/tests
13:55Z owner evidence: PID 3703958, user claude, elapsed 01:03, cmd php tools/run-tests.php lanes/syncthing/tests
```

I did not start `php tools/run-tests.php`. The exact process gate was clear at
the initial samples but later became occupied by a focused Syncthing harness,
and the checkout continued to move. A no-argument root result from this moving
shared tree would not be attributable to a reviewed acceptance snapshot.

Current manifest/status drift sample:

```text
lane          current manifest/status                    dashboard
difftastic    manifest 1123/909, status 3330 pass        1077/851, 3245 pass
dolt          manifest 613/613, status 426 pass/1 fail   613/613, 425 pass/0 fail
esbuild       manifest/status 441                        429 mapped/pass
gitoxide      status 7273 pass                           7152 pass
libsqlite     manifest/status 357                        349 mapped, 348 pass
LightningCSS  manifest 2815, status 4129 pass            2765 mapped, 4065 pass
markerPDF     manifest 405/356, status 492 pass          396/347, 484 pass
pandoc        manifest 1979, status 370 pass             1891 mapped, 362 pass
quadrable     status 237 pass                            232 pass
rclone        manifest/status 929                        906 mapped/pass
readability   status 3630 pass                           3545 pass
syncthing     status 8138 pass                           7902 pass
```

## Findings

1. **Critical - the repository is still not an acceptance checkpoint.**
   - Paths: `progress.md:46`, `lanes/*/lane-status.json:13`,
     `audits/integration-status.md:1`, `goal.md:29`, `goal.md:48`.
   - Goal requirement at risk: small reviewable slices must be committed with
     passing tests, and finished agent work must be verified, committed, cleaned
     up, and assigned onward.
   - Evidence: `HEAD` moved from `bebf4cbb60f9` to `7dafaf8f08b3` during this
     audit after two more integration-hold commits. Tracked dirty rows moved
     `329 -> 332`, untracked-inclusive status rows moved `18484 -> 18499`, and
     shortstat moved while sampling. All 12 lane statuses still report
     `pending`, `uncommitted`, `not committed`, or shared dirty-worktree commit
     ownership instead of accepted implementation commits.

2. **Critical - a no-argument root run would still be invalid from this audit.**
   - Paths: `tools/run-tests.php`, `progress.md:46`, `goal.md:49`.
   - Goal requirement at risk: repo-wide tests and static checks must be run
     and recorded honestly from a stable snapshot, without duplicating an active
     root harness.
   - Evidence: the exact pre-root gate returned no rows at 13:48Z and 13:51Z,
     then matched focused Syncthing PID `3703958` owned by `claude` at 13:55Z.
     The tree also moved to 332 tracked dirty files and 18,499 status rows.
     Starting the root harness here would blend multiple unaccepted lane
     batches and active focused verification into one ambiguous result.

3. **High - Dolt now has a recorded focused PHP failure.**
   - Paths: `lanes/dolt/lane-status.json:5`, `lanes/dolt/lane-status.json:7`,
     `lanes/dolt/lane-status.json:10`, `lanes/dolt/lane-status.json:12`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:17`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:68`, `goal.md:29`,
     `goal.md:35`.
   - Goal requirement at risk: lane slices need passing PHP tests and
     meaningful fixture parity before acceptance.
   - Evidence: Dolt status now records `phpFail: 1`. The failing case is
     `wordpress query-diff json_extract fixture renders option JSON review` in
     `lanes/dolt/tests/QueryDiffCommandTest.php`; expected three aligned option
     rows, actual output returned four positional mismatches. This must be fixed
     or explicitly reverted before Dolt can be accepted or used in a root
     aggregate.

4. **High - `porting.html` and `porting-summary.json` are materially stale.**
   - Paths: `porting.html:32`, `porting.html:34`, `porting.html:35`,
     `porting.html:56`, `porting.html:67`, `porting-summary.json:2`,
     `porting-summary.json:3`, `porting-summary.json:8`, `goal.md:3`,
     `goal.md:45`.
   - Goal requirement at risk: the dashboard must show current denominator,
     mapped tests, PHP pass/fail, WordPress scenarios, phase, audit, current
     work, blocker, and commit.
   - Evidence: both generated artifacts still publish source commit
     `89260857cc71`, while sampled `HEAD` is `7dafaf8f08b3`. Current manifests
     and statuses exceed or contradict the dashboard for every lane in the drift
     table above, and Dolt now has a failure not visible on the dashboard.

5. **High - support-library coverage is still first-class only in planning, not
   in evidence.**
   - Paths: `dependency-backlog.json:3`, `dependency-backlog.json:4`,
     `dependency-backlog.json:7`, `dependency-backlog.json:81`,
     `dependency-backlog.json:129`, `dependency-backlog.json:145`,
     `dependency-backlog.json:163`, `dependency-backlog.json:179`,
     `dependency-backlog.json:214`, `dependency-backlog.json:233`,
     `dependency-backlog.json:256`, `dependency-backlog.json:272`,
     `dependency-backlog.json:322`, `dependency-backlog.json:340`,
     `dependency-backlog.json:365`, `dependency-backlog.json:629`,
     `porting.html:72`, `porting.html:77`.
   - Goal requirement at risk: support libraries require lane-grade bounded
     native components, activation gates, dependency-specific upstream/spec
     denominators, mapped fixtures, PHP pass/fail evidence, malformed/corrupt
     cases where relevant, and as much upstream/spec suite evidence as can
     actually run.
   - Evidence: the backlog still has 37 rows and `0` active bounded support
     ports. `rg --files -g 'UPSTREAM_TEST_MANIFEST.json'` returns only the 12
     lane manifests, so no support-library manifest/pass-fail ledger exists.
     Pandoc's required DOC, DOCX/OpenXML, PDF handoff/text extraction, EPUB,
     ODT/OpenDocument, templates, citations, math, tables, package containers,
     XML/HTML, Unicode/charset, JSON/YAML, and archive/compression needs are
     covered as gated rows, but none has dependency-specific suite evidence,
     malformed/corrupt evidence, or bounded `sudo -n` install attempt/ruled-out
     notes yet.

6. **High - markerPDF still mixes native PDF progress with external runtime and
   shell-boundary plans.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:857`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:862`,
     `lanes/markerpdf/lane-status.json:12`, `goal.md:1`, `goal.md:30`.
   - Goal requirement at risk: wrappers around JS/Rust/Go/C binaries,
     shell-outs, bridge calls, whole applications, and external converter
     wrappers must not count as the native deliverable.
   - Evidence: markerPDF has useful native PDF extraction evidence, now
     `405/356` static units and manifest `phpBehaviorTests: 493`. The same
     manifest/status surface still carries Streamlit/FastAPI/Uvicorn app/server
     plans, chunk-convert shell lifecycle plans, live PIL/pypdfium/PDFium,
     Poppler/Ghostscript/OCR/Tesseract/Texify/Torch/Nougat dependencies, Poetry
     packaging, and Pandoc/XeLaTeX helper execution. These can be blockers or
     supplied-runner contracts, not native progress.

7. **High - manifest/status schemas and counts remain non-normalized.**
   - Paths: `lanes/dolt/lane-status.json:5`, `lanes/dolt/lane-status.json:7`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:28`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:68`,
     `lanes/markerpdf/lane-status.json:6`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:867`,
     `lanes/pandoc/lane-status.json:6`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:306`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:1223`, `goal.md:24`,
     `goal.md:35`.
   - Goal requirement at risk: every lane needs a defensible denominator,
     mapped upstream tests, PHP passing/failing counts, and precise current
     status.
   - Evidence: Dolt manifest still has stale green latest-slice PHP evidence
     while runner metadata records the new failure. markerPDF manifest says
     `phpBehaviorTests: 493`, but lane status still says `phpPass: 492`.
     Pandoc status says `370` PHP passes and current wrapper/CSL work, while
     manifest `nativeImplementation.latestSlice` still describes the older
     raw-block/div slice and has no normalized PHP behavior-test field.

8. **Medium - near-complete percentages still overstate accepted upstream
   parity.**
   - Paths: `porting.html:32`, `porting.html:56`, `porting.html:67`,
     `porting-summary.json:8`, `porting-summary.json:12`,
     `porting-summary.json:199`, `lanes/*/lane-status.json:12`,
     `goal.md:35`, `goal.md:40`.
   - Goal requirement at risk: passing tests are not enough; upstream tests are
     the source of truth where possible, and hard gaps must be recorded as
     blockers or future slices.
   - Evidence: the public dashboard reports `98.3%` average progress and most
     lanes at `98%` or `99%`, but every lane handoff remains uncommitted or
     pending, Dolt currently has a focused PHP failure, one serialized root
     result is absent for the current tree, support-library work has no active
     bounded ports, and full upstream runners remain static, bounded,
     unexecuted, or intentionally excluded for multiple lanes.

9. **Medium - recent history remains status/hold dominated rather than accepted
   integration.**
   - Paths: `audits/latest.md`, `audits/integration-status.md:1`,
     `progress.md:46`, `lanes/*/lane-status.json:13`, `goal.md:20`,
     `goal.md:48`.
   - Goal requirement at risk: the supervisor must integrate useful work,
     enforce standards, keep the roadmap honest, and assign the next
     highest-value slice after verification.
   - Evidence: the latest sampled commits are `7dafaf8f Record integration hold
     status`, `bebf4cbb Record integration hold status`, `231cfa8f Refresh
     independent audit status`, `c3f971d3 Record integration hold status`, and
     `7cc6b522 Refresh independent audit status`. This preserves the hold but
     does not convert lane-local evidence into accepted implementation
     checkpoints.

## Next Intervention

Freeze lane writers, rearm prompts, dashboard/status publishers,
support-library scouts, capacity executor rows, focused runners, root runners,
Dolt, and Dolt-runner. Require two stable polls of `HEAD`, tracked rows,
untracked-inclusive rows, shortstat, exact process gates, dashboard/dependency
counts, status timestamps, and relevant log mtimes. The first accepted
intervention should resolve or revert the Dolt `JSON_EXTRACT` focused PHP
failure, then accept exactly one owner-free lane batch with manifest/status
schema and count normalization, run focused lane verification plus
`git diff --check`, activate only support-library rows whose base-lane gate is
accepted or truly blocked, add dependency-specific support manifests before
counting support progress, regenerate `porting.html` and `porting-summary.json`
from the accepted commit, and run one serialized no-argument
`php tools/run-tests.php` only if the exact process gate is empty on that frozen
snapshot.
