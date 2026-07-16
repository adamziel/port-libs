# Supervisor Bounded Swarm Restart 2026-06-02T23:45Z

- User instruction: this session is the supervisor, and the project must use a
  swarm of workers. The earlier worker-creation stop is superseded by this
  bounded supervised restart.
- Accepted base before restart: `7daebccdb1e231332676891328ab6455e928870a`.
  Recent accepted markerPDF source commit: `fbbbb072cde28e735a75978a5000fa4f37b58c60`.
- Diagnosis: the first bounded launch dropped most workers because Codex CLI
  sent an enabled `image_generation` tool with unavailable model
  `gpt-image-2`. This was not a memory, disk, or prompt/resource failure.
- Launcher fix: `scripts/run-isolated-lane-worker.sh` now passes
  `--disable image_generation` while preserving the required
  `gpt-5.5` / `xhigh` / `priority` profile. A smoke exec with that flag
  returned `OK`.
- Refiller scripts remain intentionally non-executable. Keep using manual
  bounded invocations with `bash scripts/refill-markerpdf-workers.sh` and
  `bash scripts/refill-pandoc-workers.sh`; do not re-enable recursive
  auto-refill unless explicitly decided by the supervisor.
- Current active shape after refill: 11 dev workers total, 8 markerPDF windows
  and 3 Pandoc windows. Ten patched workers show `--disable image_generation`
  in the process list. One older markerPDF pdftext worker remains from the
  pre-fix launch and should be observed rather than killed unless it exits or
  blocks.
- MarkerPDF workers started after the fix:
  `table-geometry`, `runtime-preflight`, `inline-image`,
  `pdf-dictionary-layout`, `stream-filter-stack`, `font-width-advance`, and
  `type3-charprocs`.
- Pandoc workers started after the fix: `zip-package`,
  `opc-relationships`, and `doctemplates`.
- No new `20260602T234*` ready handoff existed at restart time. Next
  supervisor pass should check for fresh ready files, score worker output, and
  only refill again after confirming clean exits or useful handoffs.
- Resource snapshot after refill: `/` had about 237G free, `/tmp` about 3.8G
  free, and memory about 13Gi available. No swap is configured.
