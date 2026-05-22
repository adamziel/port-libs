<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$laneDirs = glob($root . '/lanes/*', GLOB_ONLYDIR) ?: [];
sort($laneDirs);

$rows = [];
$total = 0.0;

foreach ($laneDirs as $dir) {
    $manifestPath = $dir . '/UPSTREAM_TEST_MANIFEST.json';
    $statusPath = $dir . '/lane-status.json';
    $manifest = is_file($manifestPath) ? json_decode((string) file_get_contents($manifestPath), true) : [];
    $status = is_file($statusPath) ? json_decode((string) file_get_contents($statusPath), true) : [];
    if (!is_array($manifest) || !is_array($status)) {
        continue;
    }

    $progress = (float) ($status['estimatedProgress'] ?? 0);
    $total += $progress;
    $rows[] = [
        'library' => $status['library'] ?? basename($dir),
        'suite' => $status['suiteProgress'] ?? 'unmapped',
        'source' => $manifest['upstream']['url'] ?? 'pending',
        'denominator' => (string) ($manifest['benchmarkDenominator']['total'] ?? 'pending'),
        'mapped' => (string) ($manifest['benchmarkDenominator']['mapped'] ?? 'pending'),
        'php' => ($status['phpPass'] ?? 0) . ' / ' . ($status['phpFail'] ?? 0),
        'wp' => (string) ($status['wordpressScenarios'] ?? 'pending'),
        'phase' => (string) ($status['phase'] ?? 'planning'),
        'audit' => (string) ($status['audit'] ?? 'not started'),
        'work' => (string) ($status['currentWork'] ?? ''),
        'blocker' => (string) ($status['blocker'] ?? ''),
        'commit' => (string) ($status['latestCommit'] ?? 'none'),
        'progress' => $progress,
    ];
}

$average = $rows === [] ? 0.0 : $total / count($rows);
$generated = gmdate('Y-m-d H:i:s') . ' UTC';

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$htmlRows = '';
foreach ($rows as $row) {
    $htmlRows .= '<tr>'
        . '<td>' . $escape($row['library']) . '</td>'
        . '<td><meter min="0" max="100" value="' . $escape((string) $row['progress']) . '"></meter> ' . $escape((string) $row['progress']) . '%</td>'
        . '<td>' . $escape($row['suite']) . '</td>'
        . '<td>' . $escape($row['source']) . '</td>'
        . '<td>' . $escape($row['denominator']) . '</td>'
        . '<td>' . $escape($row['mapped']) . '</td>'
        . '<td>' . $escape($row['php']) . '</td>'
        . '<td>' . $escape($row['wp']) . '</td>'
        . '<td>' . $escape($row['phase']) . '</td>'
        . '<td>' . $escape($row['audit']) . '</td>'
        . '<td>' . $escape($row['work']) . '</td>'
        . '<td>' . $escape($row['blocker']) . '</td>'
        . '<td>' . $escape($row['commit']) . '</td>'
        . '</tr>' . "\n";
}

$html = <<<HTML
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Native PHP Porting Dashboard</title>
  <style>
    :root { color-scheme: light dark; font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
    body { margin: 0; padding: 24px; background: Canvas; color: CanvasText; }
    header { display: flex; justify-content: space-between; gap: 24px; align-items: baseline; margin-bottom: 20px; }
    h1 { margin: 0; font-size: 24px; }
    .summary { display: flex; gap: 16px; flex-wrap: wrap; color: color-mix(in srgb, CanvasText 72%, Canvas); }
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    th, td { border: 1px solid color-mix(in srgb, CanvasText 16%, Canvas); padding: 8px; vertical-align: top; text-align: left; }
    th { background: color-mix(in srgb, CanvasText 8%, Canvas); position: sticky; top: 0; }
    meter { width: 84px; vertical-align: middle; }
    td:nth-child(4), td:nth-child(11), td:nth-child(12) { max-width: 280px; overflow-wrap: anywhere; }
  </style>
</head>
<body>
  <header>
    <h1>Native PHP Porting Dashboard</h1>
    <div class="summary">
      <span>Average progress: <strong>{$escape(number_format($average, 1))}%</strong></span>
      <span>Lanes: <strong>{$escape((string) count($rows))}</strong></span>
      <span>Generated: <strong>{$escape($generated)}</strong></span>
    </div>
  </header>
  <table>
    <thead>
      <tr>
        <th>Library</th>
        <th>Progress</th>
        <th>Suite Progress</th>
        <th>Benchmark Source</th>
        <th>Upstream Denominator</th>
        <th>Mapped Tests</th>
        <th>Local PHP Pass / Fail</th>
        <th>WordPress Scenarios</th>
        <th>Phase</th>
        <th>Audit</th>
        <th>Current Work</th>
        <th>Blocker</th>
        <th>Commit</th>
      </tr>
    </thead>
    <tbody>
{$htmlRows}    </tbody>
  </table>
</body>
</html>
HTML;

file_put_contents($root . '/porting.html', $html);
fwrite(STDOUT, "Generated porting.html with " . count($rows) . " lanes\n");
