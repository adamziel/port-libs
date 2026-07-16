<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$templateXml = <<<'XML'
<template xmlns="http://www.xfa.org/schema/xfa-template/3.3/">
  <subform name="registration">
    <field name="registration.email"><caption><value><text>Email</text></value></caption></field>
    <field name="registration.secret"><caption><value><text>Secret</text></value></caption></field>
  </subform>
</template>
XML;
$datasetsXml = <<<'XML'
<xfa:datasets xmlns:xfa="http://www.xfa.org/schema/xfa-data/1.0/">
  <xfa:data>
    <registration><email>editor@example.com</email><secret>do not render</secret></registration>
  </xfa:data>
</xfa:datasets>
XML;
$configXml = '<config><present>pdf</present></config>';
$templateStream = gzcompress($templateXml);
assert(is_string($templateStream));
$configHex = strtoupper(bin2hex($configXml));

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R] /XFA [(template) 30 0 R (datasets) 31 0 R (config) <{$configHex}>] >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (fallback.email) /V (fallback@example.com) >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($templateStream) . " /Filter /FlateDecode >>\nstream\n"
    . $templateStream
    . "\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($datasetsXml) . " >>\nstream\n"
    . $datasetsXml
    . "\nendstream\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$packets = $form['xfa_packets'];
$fieldNames = [];
$dataNodeNames = [];
foreach ($packets as $packet) {
    foreach ($packet['field_names'] as $fieldName) {
        $fieldNames[$fieldName] = true;
    }
    foreach ($packet['data_node_names'] as $nodeName) {
        $dataNodeNames[$nodeName] = true;
    }
}

echo '<!-- markerpdf:pdf-xfa-form-packet-review ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-catalog-acroform-xfa',
    'native_boundary' => 'catalog /AcroForm /XFA packet array extraction before WordPress form-review rendering',
    'xfa_overrides_page_content' => $form['xfa_overrides_page_content'],
    'packet_count' => count($packets),
    'fallback_acroform_field_count' => count($form['fields']),
    'packet_names' => array_column($packets, 'name'),
    'field_names' => array_keys($fieldNames),
    'data_node_names' => array_keys($dataNodeNames),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($packets as $packet) {
    $reviewParts = [];
    if ($packet['field_names'] !== []) {
        $reviewParts[] = 'fields: ' . implode(', ', $packet['field_names']);
    }
    if ($packet['data_node_names'] !== []) {
        $reviewParts[] = 'data: ' . implode(', ', $packet['data_node_names']);
    }
    if ($reviewParts === []) {
        $reviewParts[] = 'root: ' . ($packet['xml_root'] ?? 'unknown');
    }

    echo '<li>'
        . htmlspecialchars($packet['name'] . ' packet (' . implode('; ', $reviewParts) . ')', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
