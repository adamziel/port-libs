<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 3 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Migration Appendix) /Parent 5 0 R /A << /S /GoToR /F (appendix.pdf) /D [2 /FitH 720] /NewWindow true >> /Next 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Title (Reviewer Guide) /Parent 5 0 R /A 8 0 R >>\nendobj\n"
    . "8 0 obj\n<< /S /GoToR /F << /F (legacy.pdf) /UF <FEFF007200650076006900650077002D00670075006900640065002E007000640066> >> /D /Chapter#202 /NewWindow false >>\nendobj\n"
    . "%%EOF";

$actions = (new PdfOutlineExtractor())->getRemoteGoToActions($pdf);

echo '<!-- markerpdf-pdf-remote-goto ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-outline-remote-goto-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'remote_action_count' => count($actions),
    'remote_files' => array_column($actions, 'file'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($actions as $action) {
    $attrs = [
        'data-marker-outline-level' => (string) $action['level'],
        'data-marker-remote-file' => $action['file'],
    ];
    if ($action['destination'] !== null) {
        $attrs['data-marker-remote-destination'] = $action['destination'];
    }
    if ($action['page'] !== null) {
        $attrs['data-marker-remote-page'] = (string) $action['page'];
    }
    if ($action['new_window'] !== null) {
        $attrs['data-marker-new-window'] = $action['new_window'] ? 'true' : 'false';
    }

    $attrText = '';
    foreach ($attrs as $name => $value) {
        $attrText .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
    }

    echo '<li' . $attrText . '>' . htmlspecialchars($action['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
