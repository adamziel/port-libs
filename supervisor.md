# Supervisor Goal: Port-Libs Throughput

## Outcome
- Keep the tmux worker team moving on native PHP ports while integrating only clean, verified slices.
- Keep `progress.md`, audits, and the public GitHub Pages dashboard current from committed evidence.

## Intensity
- Level: max.
- Starting workers: 10-11 active lane sessions, resource-gated by CPU, RAM, disk, and independent work availability.
- Scaling rule: add workers only for bounded slices with clear artifacts; remove or redirect workers that produce broad dirty state without integration-ready outputs.
- Durable launch constraint: every newly started subagent/worker must run
  `gpt-5.5` with `model_reasoning_effort="xhigh"` on the fast/priority service
  tier unless the user explicitly replaces this rule.

## Non-Goals
- No lane implementation in the supervisor session.
- No live-service provider tests unless bounded, opt-in, and documented.
- No markerPDF GPU/model execution: no live OCR, Surya/Texify/Torch,
  Streamlit/FastAPI model workers, or upstream model benchmark parity unless
  the user explicitly authorizes those runs.
- No secret inspection, printing, or copying.
- No wrappers as final port deliverables.

## Worker-Creation Stop + Recovery 2026-06-02T23:35Z
- User stopped worker creation after markerPDF/Pandoc refiller churn. Do not
  spawn or refill markerPDF/Pandoc workers until the user explicitly
  re-enables worker creation.
- Live recovery state: `main` should have only the primary shell and
  supervisor panes; markerPDF/Pandoc refiller scripts are intentionally
  non-executable so `run-isolated-lane-worker.sh` exit traps cannot refill
  those pools.
- Regroup path: one supervisor/integrator lane, using the clean integration
  worktree. Score existing ready handoffs serially, accept only clean no-GPU
  native source/test slices, and leave conflicting handoffs marked
  rebase-needed instead of starting replacement workers.
- Pandoc dependency workers did not produce ready handoffs before the stop.
  Treat Pandoc dependency work as pending until worker creation is explicitly
  re-enabled.

## MarkerPDF Native Scope + Pandoc Dependency Regroup 2026-06-02T23:17Z
- User direction: no GPU models will run. markerPDF is now supervised as a
  native PHP searchable-PDF/parser/converter port plus supplied-boundary review
  pipeline, not as full upstream Marker model-stack parity.
- markerPDF work that remains in scope: PDF objects, fonts, CMaps, stream
  filters, xref repair, metadata, outlines, annotations, AcroForms, security
  preflight, page geometry, image/filter metadata, native text extraction, and
  supplied-boundary table/equation/image handoffs.
- markerPDF capabilities that must be reported as unavailable under this
  scope: scanned-PDF OCR, Surya layout/reading-order/OCR/table-cell models,
  Texify equation recognition, Torch/model batching, page-pixel visual table
  recognition, and exact upstream model benchmark parity.
- Previous steady-state worker split of about 8 markerPDF native workers plus
  3 Pandoc dependency workers is superseded by the worker-creation stop above.
- Pandoc dependency workers should focus first on bounded rows already in
  `dependency-backlog.json`: `shared-zip-package-core`,
  `xml-html5-dom-core`/OPC relationships needed by DOCX/EPUB/ODT,
  `pandoc-doctemplates-core`, YAML/metadata, citation/CSL, math/TeX, charset,
  and the upstream Cabal runner dependency audit. They must produce focused
  lane patches or explicit bounded audit notes; no external converter or
  shell-out progress is accepted.

## Split Priority Override 2026-05-31T08:10Z
- New user direction: run the visible worker pool roughly half on continued
  libsqlite closure and half on Gitoxide porting. Do not keep all capacity on
  libsqlite while Gitoxide has no active workers.
- New subagent launch rule: all newly started subagents must use
  `gpt-5.5` with `model_reasoning_effort="xhigh"` on the fast/priority service
  tier unless the user explicitly changes this later.
- Current target: 6 active isolated libsqlite workers and 5 active isolated
  Gitoxide workers in tmux session `main`, with no long sleepers. Preserve
  dirty worktrees and handoffs while stopping excess libsqlite panes only as
  needed to make room.
- Gitoxide workers should own bounded upstream-backed slices in protocol/
  transport, reference transactions, pack/index/object database behavior,
  commit/tree writing/parsing, SSH/auth boundaries, and fixture parity.
- Continue libsqlite integration from accepted evidence; do not publish a
  libsqlite batch that introduces an unaccounted default-memory regression.

## Worker Topology
- supervisor: manage queue, liveness, resource use, integration pressure, and anti-drift.
- lane workers: implement lane-local native PHP slices and leave focused evidence. Normal watchdog restarts run as isolated worktree workers by default; use `WATCHDOG_USE_ISOLATED_LANE_WORKERS=0` only for an explicit shared-checkout compatibility fallback.
- preflight/isolation workers: extract broad dirty handoffs into clean worktree patches and audits.
- lane-group integrators: pre-review independent ready markers by disjoint lane group, run only focused checks, and queue acceptable candidates for the serialized root/commit gate.
- integrator: accept or reject one isolated slice at a time after stability checks and verification; this remains the fallback and the only root/commit authority.
- evaluator: every 20 minutes, give candid feedback and status-page guidance.
- dashboard updater: publish verified committed status snapshots to GitHub Pages.

## Workflow
1. Lane workers produce bounded feature slices.
2. Preflight workers reject broad handoffs or nominate exact sub-slices.
3. Isolation workers rebuild nominated sub-slices in clean detached worktrees.
4. Lane-group integrators may pre-review owned ready markers in parallel and queue small candidates; they do not run duplicate root harnesses or commit accepted progress.
5. Integrator accepts only small clean patches with focused evidence, then root verification when safe.
6. Dashboard updater publishes verified committed status.

## Quality Gates
- Focused lane tests must run clean for any accepted slice.
- `git diff --check` must pass on touched files.
- Root `php tools/run-tests.php` is serialized and run only from a stable snapshot.
- Support-library progress must have explicit denominators, evidence, and non-goals.

## Rejected Distractions
- Broad dirty lane dumps presented as integration-ready.
- Missing dependency/tooling claims without a bounded install attempt when install is reasonable.
- Provider/live-service tests that require credentials or unconstrained network state.
- Status pages that hide dependency gates or imply richer coverage than committed evidence supports.

## Libsqlite Generic API Rule 2026-05-30T13:20Z
- User priority update: the libsqlite port must have zero WordPress-specific
  classes, interfaces, traits, functions, or methods. Do not accept libsqlite
  patches that add declarations whose names contain `WordPress`, `wordpress`,
  `WP`, `Wp`, `wp_`, `OptionRow`, `optionRowName`, `optionRowValue`,
  `wpError`, `OptionsTable`, `optionsTable`, `OptionName`, `optionName`,
  `OptionValue`, `optionValue`, `OptionId`, `optionId`, `Multisite`, `Network`,
  `Autoload`, `autoload`, `BlogId`, or `blogId`. SQLite compile-option helpers
  are allowed only when they are clearly about SQLite compile options.
- New libsqlite handoffs should use generic application scenario names, not
  WordPress-specific smokes/examples. Existing fixture table strings are test
  data only and are not permission to expose WordPress-named APIs.
- Required gate for source-moving libsqlite batches: run the committed
  `SQLiteNoWordPressSpecificApiTest.php` guard or the equivalent declaration
  `rg` audit, plus focused slice tests and `git diff --check`.

## Final Acceptance Criteria
- Each lane has native PHP behavior mapped to upstream evidence, generic application scenarios, and status metadata.
- Required support libraries are tracked at bounded native component granularity.
- Public dashboard is current and evidence-based.
- No unreviewed broad dirty handoffs are treated as accepted progress.

## Libsqlite Numbered Helper Consolidation Rule 2026-05-29T10:08Z
- User priority update: remove numbered suffixes such as
  `CurrentSourceNext150Plan.php` from consolidated libsqlite source helpers
  and merge any remaining duplicate numbered helper families into stable
  unnumbered classes.
- Refills must not assign work that creates or extends a new numbered
  duplicate source helper. Workers may keep numbered test/example range names,
  but shared source helpers should have stable names.
- Initial grouped inventory from `1a570158f`: VFS has 55 numbered helpers,
  PRAGMA/FK 33, B-tree vacuum/freeblock 32, pager reader-cache 29, rowvalue
  savepoint 11, WAL hot-journal/savepoint/checkpoint 9, planner STAT4 6, and
  smaller pairs/triples including B-tree `CurrentSourceNext93/150Plan`.
- One visible lane is active on the B-tree `CurrentSourceNext93/150Plan`
  duplicate group. As range workers finish, refill windows with non-overlapping
  consolidation lanes until these suffix groups are gone.
