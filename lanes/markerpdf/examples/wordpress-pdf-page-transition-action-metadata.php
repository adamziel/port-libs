<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageOneContent = 'BT /F1 12 Tf 72 720 Td (Slide body stays visible) Tj ET';
$pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Second slide stays clean) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 20 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Dur 8 /Trans 5 0 R /AA << /O 6 0 R /C << /S /GoToR /F (deck-appendix.pdf) /D /Slide#202 /NewWindow true >> >> /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Trans << /S /Split /D .75 /Dm /H /M /O /Di 90 /SS .5 /B true >> /AA << /O << /S /Launch /Win << /F (helper.exe) /O (print) >> >> /C << /S /URI /URI (javascript:alert\\(1\\)) /Next 7 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /S /Dissolve /D 1.5 >>\nendobj\n"
    . "6 0 obj\n<< /S /URI /URI (https://example.com/slide-notes) >>\nendobj\n"
    . "7 0 obj\n<< /S /GoTo /D /Start >>\nendobj\n"
    . "20 0 obj\n<< /Names [(Start) [3 0 R /Fit]] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfOutlineExtractor();
$pages = $extractor->getPageTransitionActionMetadata($pdf);
$paragraphs = explode("\n", (new PdfTextExtractor())->extractPlainText($pdf));

$actions = [];
foreach ($pages as $page) {
    foreach ($page['actions'] as $action) {
        $actions[] = $action + ['source_page' => $page['pnum']];
    }
}

echo '<!-- markerpdf-pdf-page-transition-action-metadata ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-page-transition-action-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions_on_import' => false,
    'page_metadata_count' => count($pages),
    'page_action_count' => count($actions),
    'transition_styles' => array_values(array_filter(array_map(static fn (array $page): ?string => $page['transition']['style'] ?? null, $pages))),
    'action_types' => array_column($actions, 'action_type'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($paragraphs as $paragraph) {
    if ($paragraph === '') {
        continue;
    }

    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n";
}

echo "<!-- wp:list -->\n<ul>\n";
foreach ($pages as $page) {
    $transition = $page['transition'];
    if ($transition !== null) {
        $attrs = [
            'data-marker-page' => (string) $page['pnum'],
            'data-marker-transition-style' => (string) $transition['style'],
        ];
        if ($page['display_duration'] !== null) {
            $attrs['data-marker-display-duration'] = (string) $page['display_duration'];
        }
        if ($transition['duration'] !== null) {
            $attrs['data-marker-transition-duration'] = (string) $transition['duration'];
        }

        $attrText = '';
        foreach ($attrs as $name => $value) {
            $attrText .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }

        echo '<li' . $attrText . '>Page ' . htmlspecialchars((string) ($page['pnum'] + 1), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ' transition review</li>' . "\n";
    }

    foreach ($page['actions'] as $action) {
        $attrs = [
            'data-marker-page' => (string) $page['pnum'],
            'data-marker-page-action-event' => $action['event'],
            'data-marker-page-action-type' => $action['action_type'],
            'data-marker-page-action-safety' => $action['safety'],
            'data-marker-executes-on-import' => $action['executes_on_import'] ? 'true' : 'false',
        ];
        foreach (['uri', 'file', 'destination', 'operation'] as $key) {
            if (is_string($action[$key] ?? null) && $action[$key] !== '') {
                $attrs['data-marker-page-action-' . str_replace('_', '-', $key)] = $action[$key];
            }
        }

        $attrText = '';
        foreach ($attrs as $name => $value) {
            $attrText .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }

        echo '<li' . $attrText . '>Page ' . htmlspecialchars((string) ($page['pnum'] + 1), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ' '
            . htmlspecialchars($action['event_label'] . ' ' . $action['action_type'] . ' ' . $action['safety'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
    }
}
echo "</ul>\n<!-- /wp:list -->\n";
