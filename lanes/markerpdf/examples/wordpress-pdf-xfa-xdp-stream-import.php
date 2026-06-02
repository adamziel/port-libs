<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAcroFormExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$xdpXml = <<<'XML'
<?xml version="1.0" encoding="UTF-16"?>
<xdp:xdp xmlns:xdp="http://ns.adobe.com/xdp/" xmlns:xfa="http://www.xfa.org/schema/xfa-data/1.0/">
  <xfa:template xmlns:xfa="http://www.xfa.org/schema/xfa-template/3.3/">
    <xfa:subform name="article">
      <xfa:field name="article.title"><xfa:caption><xfa:value><xfa:text>Title</xfa:text></xfa:value></xfa:caption></xfa:field>
    </xfa:subform>
  </xfa:template>
  <xfa:datasets>
    <xfa:data>
      <article><title>Fresh dynamic value</title></article>
    </xfa:data>
  </xfa:datasets>
  <config><present>pdf</present></config>
</xdp:xdp>
XML;

$encoded = iconv('UTF-8', 'UTF-16BE', $xdpXml);
assert(is_string($encoded));
$utf16 = "\xFE\xFF" . $encoded;
$compressed = gzcompress($utf16);
assert(is_string($compressed));

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Fields [6 0 R] /XFA 30 0 R >>\nendobj\n"
    . "6 0 obj\n<< /FT /Tx /T (fallback.title) /V (fallback title) >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($compressed) . " /Filter /FlateDecode >>\nstream\n"
    . $compressed
    . "\nendstream\nendobj\n"
    . "%%EOF";

$form = (new PdfAcroFormExtractor())->extractForm($pdf);
$packets = $form['xfa_packets'];
$packet = $packets[0] ?? [];

echo '<!-- markerpdf:pdf-xfa-xdp-stream-review ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-catalog-acroform-xfa-xdp-stream',
    'native_boundary' => 'catalog /AcroForm /XFA stream decoding before WordPress form-review rendering',
    'xfa_overrides_page_content' => $form['xfa_overrides_page_content'],
    'fallback_acroform_field_count' => count($form['fields']),
    'packet_count' => count($packets),
    'xml_root' => $packet['xml_root'] ?? null,
    'xml_encoding' => $packet['xml_encoding'] ?? null,
    'decoded_to_utf8' => $packet['decoded_to_utf8'] ?? false,
    'xdp_packet_names' => $packet['xdp_packet_names'] ?? [],
    'field_names' => $packet['field_names'] ?? [],
    'data_node_names' => $packet['data_node_names'] ?? [],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_xfa_javascript' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($packets as $packet) {
    $parts = [];
    if (($packet['xdp_packet_names'] ?? []) !== []) {
        $parts[] = 'xdp packets: ' . implode(', ', $packet['xdp_packet_names']);
    }
    if ($packet['field_names'] !== []) {
        $parts[] = 'fields: ' . implode(', ', $packet['field_names']);
    }
    if ($packet['data_node_names'] !== []) {
        $parts[] = 'data nodes: ' . implode(', ', $packet['data_node_names']);
    }
    $parts[] = 'encoding: ' . ($packet['xml_encoding'] ?? 'unknown');

    echo '<li>'
        . htmlspecialchars($packet['name'] . ' packet (' . implode('; ', $parts) . ')', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
