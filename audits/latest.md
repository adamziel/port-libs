# Independent Audit - 2026-05-24T13:43Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all 12
`lanes/*/lane-status.json`, `dependency-backlog.json`, and recent Git history
through `c3f971d3 Record integration hold status`. I did not edit lane
implementation files, launch agents or tmux sessions, push, read secrets,
inspect process environments, credential stores, provider configs, or auth
files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24 13:41-13:43
HEAD moved during audit: 7cc6b5222c4a -> c3f971d30bda
recent history: c3f971d3 Record integration hold status; 7cc6b522 Refresh independent audit status; 90c38ff9 Record integration hold status; 5ffb2912 Refresh independent audit status; 35121fcf Record integration hold status
branch sample: main...origin/main [ahead 894, behind 68]
tracked dirty rows: 329 -> 329
default status rows including untracked: 18420 -> 18478
git diff --shortstat: 329 files changed, 243350 insertions(+), 31174 deletions(-) -> 329 files changed, 243519 insertions(+), 31169 deletions(-)
dashboard snapshot: porting.html and porting-summary.json still publish source 89260857cc71; sampled HEAD is c3f971d30bda
dependency backlog: dependency-backlog.json has 37 rows (1 blocked, 25 candidate, 11 deferred, 0 active)
root run by this audit: not started
json validation: jq empty passed for all 12 lane manifests, all 12 lane-status files, porting-summary.json, and dependency-backlog.json
```

Required pre-root process gate:

```text
13:41Z pgrep -af '^php tools/run-tests\.php( |$)': no rows
13:43Z pgrep -af '^php tools/run-tests\.php( |$)': no rows
```

I did not start `php tools/run-tests.php`. The process gate was clear, but the
checkout failed the stability gate while `HEAD`, untracked-inclusive status
rows, and shortstat changed, and every current lane handoff remains
pending/uncommitted in a broad shared dirty tree.

Current manifest/status drift sample:

```text
lane          current manifest/status                 dashboard
difftastic    manifest 1115/901, status 3323 pass     1077/851, 3245 pass
dolt          status 426 pass                         425 pass
esbuild       manifest/status 440                     429 mapped/pass
gitoxide      status 7273 pass                        7152 pass
libsqlite     manifest/status 355                     349 mapped, 348 pass
LightningCSS  manifest 2801, status 4115 pass         2765 mapped, 4065 pass
markerPDF     manifest 404/355, status 491 pass       396/347, 484 pass
pandoc        manifest 1971, status 1961 checks       1891 mapped, 362 pass
quadrable     status 236 pass                         232 pass
rclone        manifest/status 926                     906 mapped/pass
readability   status 3617 pass                        3545 pass
syncthing     status 8115 pass                        7902 pass
```

## Findings

1. **Critical - the repository is still not an acceptance checkpoint.**
   - Paths: `progress.md:15`, `progress.md:46`, `lanes/*/lane-status.json:13`,
     `audits/integration-status.md:1`, `goal.md:29`, `goal.md:48`.
   - Goal requirement at risk: small reviewable slices must be committed with
     passing tests, and finished agent work must be verified, committed, cleaned
     up, and assigned onward.
   - Evidence: `HEAD` moved from `7cc6b5222c4a` to `c3f971d30bda` during this
     audit, after another integration-hold commit. The untracked-inclusive row
     count moved `18420 -> 18478`, and shortstat moved while the audit was
     sampling. All 12 lane statuses still report `pending`, `uncommitted`, `not
     committed`, or shared dirty-worktree commit ownership rather than accepted
     implementation commits.

2. **Critical - a no-argument root run would still be invalid from this audit.**
   - Paths: `tools/run-tests.php`, `progress.md:48`, `goal.md:49`.
   - Goal requirement at risk: repo-wide tests and static checks must be
     recorded honestly from a stable snapshot and must not duplicate active
     harness work.
   - Evidence: the exact process gate returned no rows at both samples, but the
     checkout moved between those samples and was not a frozen acceptance
     snapshot. Starting root PHP here would blend 329 tracked dirty files and
     18,478 total status rows from multiple lane batches into one ambiguous
     result.

3. **High - `porting.html` and `porting-summary.json` are materially stale.**
   - Paths: `porting.html:34`, `porting.html:35`, `porting.html:38`,
     `porting.html:56`, `porting.html:67`, `porting-summary.json:2`,
     `porting-summary.json:3`, `porting-summary.json:8`, `goal.md:3`,
     `goal.md:45`.
   - Goal requirement at risk: the dashboard must show current denominator,
     mapped tests, PHP pass/fail, phase, audit, current work, blocker, and
     commit.
   - Evidence: both generated artifacts still publish source commit
     `89260857cc71`, while sampled `HEAD` is `c3f971d30bda`. Current
     manifests/statuses exceed or contradict the dashboard for every lane in the
     drift sample above.

4. **High - manifest/status counts and warning text remain non-normalized.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:1115`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:1125`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:3312`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:857`,
     `lanes/markerpdf/lane-status.json:10`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:301`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:303`,
     `lanes/pandoc/lane-status.json:5`, `goal.md:24`, `goal.md:35`.
   - Goal requirement at risk: every lane needs a defensible denominator,
     mapped upstream tests, PHP passing/failing counts, and precise blocker
     recording.
   - Evidence: Difftastic now records `1115/901` but still carries stale warning
     text for both `901/1115` and `894/1108`. LightningCSS records mapped
     `2801` while warning text says `2,800`. markerPDF records manifest
     `404/355`, while lane status still says `403 total / 354 mapped`.
     Pandoc records manifest `mapped: 1971`, warning text saying `1,940`, and
     lane status saying `1,961` focused checks with `368` PHP passes.

5. **High - support-library coverage remains planning-only under the latest
   rich-function directive.**
   - Paths: `dependency-backlog.json:3`, `dependency-backlog.json:4`,
     `dependency-backlog.json:7`, `dependency-backlog.json:25`,
     `dependency-backlog.json:81`, `dependency-backlog.json:129`,
     `dependency-backlog.json:145`, `dependency-backlog.json:163`,
     `dependency-backlog.json:179`, `dependency-backlog.json:214`,
     `dependency-backlog.json:233`, `dependency-backlog.json:256`,
     `dependency-backlog.json:272`, `dependency-backlog.json:322`,
     `dependency-backlog.json:340`, `dependency-backlog.json:365`,
     `dependency-backlog.json:629`, `porting.html:72`, `porting.html:77`,
     `progress.md:32`.
   - Goal requirement at risk: support libraries require lane-grade bounded
     native components, activation gates, dependency-specific upstream/spec
     denominators, mapped fixtures, PHP pass/fail evidence, malformed/corrupt
     cases where relevant, and as much upstream/spec suite evidence as can
     actually run.
   - Evidence: the backlog has 37 rows and `0` active bounded support ports.
     There are no separate support-library `UPSTREAM_TEST_MANIFEST.json` files
     or pass/fail ledgers. Pandoc's required DOC, DOCX/OpenXML, PDF
     handoff/text extraction, EPUB, ODT/OpenDocument, templates, citations,
     math, tables, package containers, XML/HTML, Unicode/charset, JSON/YAML,
     and archive/compression needs are routed as gated candidate/deferred rows,
     but none has dependency-specific manifest evidence yet. The backlog policy
     mentions bounded `sudo -n` installs before final tooling blockers, but the
     rows themselves are still activation plans, not attempted support-suite
     evidence.

6. **High - markerPDF still over-credits external runtime and shell-boundary
   plans beside real native PDF work.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:857`,
     `lanes/markerpdf/lane-status.json:12`, `goal.md:1`, `goal.md:30`.
   - Goal requirement at risk: wrappers around JS/Rust/Go/C binaries,
     shell-outs, bridge calls, whole applications, and external converter
     wrappers must not count as the native deliverable.
   - Evidence: markerPDF has useful native PDF text/filter/object progress.
     The same manifest/status surface still inventories chunk-convert shell
     lifecycle, Streamlit/FastAPI/Uvicorn app/server paths, live
     PIL/pypdfium/PDFium/Poppler/Ghostscript/OCR/Tesseract execution, Texify
     and Torch/model gates, Poetry/publish workflows, and optional
     Pandoc/XeLaTeX helper execution. Those can be blockers or supplied-runner
     contracts, but not native port progress.

7. **Medium - near-complete percentages overstate accepted upstream parity.**
   - Paths: `porting.html:32`, `porting.html:56`, `porting.html:67`,
     `porting-summary.json:8`, `lanes/*/lane-status.json:12`, `goal.md:35`,
     `goal.md:40`.
   - Goal requirement at risk: passing tests are not enough; upstream tests are
     the source of truth where possible, and hard gaps must be recorded as
     blockers or future slices.
   - Evidence: the public dashboard reports `98.3%` average progress and most
     lanes at `98%` or `99%`, while every lane handoff remains uncommitted, one
     serialized root result is still absent for the current tree,
     support-library work has no active bounded ports, and full upstream runners
     remain static, bounded, unexecuted, or intentionally excluded for multiple
     lanes.

8. **Medium - recent history remains status/hold dominated rather than accepted
   implementation integration.**
   - Paths: `audits/latest.md`, `audits/integration-status.md:1`,
     `progress.md:48`, `lanes/*/lane-status.json:13`, `goal.md:20`,
     `goal.md:48`.
   - Goal requirement at risk: the supervisor must integrate useful work,
     enforce standards, keep the roadmap honest, and assign the next
     highest-value slice after verification.
   - Evidence: the latest sampled commits are `c3f971d3 Record integration hold
     status`, `7cc6b522 Refresh independent audit status`, `90c38ff9 Record
     integration hold status`, `5ffb2912 Refresh independent audit status`, and
     `35121fcf Record integration hold status`. That preserves the hold but does
     not convert lane-local evidence into accepted implementation checkpoints.

## Next Intervention

Freeze lane writers, dashboard/status publishers, support-library scouts,
focused runners, root runners, capacity executors, Dolt, and the Dolt runner.
Require two stable polls of `HEAD`, tracked rows, untracked-inclusive rows,
shortstat, exact process gates, dashboard/dependency counts, status timestamps,
and relevant log mtimes. Then accept exactly one coherent lane batch with
manifest/status schema and count normalization, run focused lane verification
plus `git diff --check`, activate only support-library rows whose base-lane
gate is accepted or truly blocked, add dependency-specific support manifests
before counting support progress, regenerate `porting.html` and
`porting-summary.json` from the accepted commit, and run one serialized
no-argument `php tools/run-tests.php` only if the exact process gate is empty on
that frozen snapshot.
