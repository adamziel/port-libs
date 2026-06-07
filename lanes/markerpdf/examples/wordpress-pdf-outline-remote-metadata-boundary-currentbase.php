<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$visibleContent = 'BT /F1 12 Tf 72 720 Td (WordPress remote outline metadata boundary body) Tj ET';
$metadataPayload = '<?xpacket begin=""?><x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description xmlns:dc="http://purl.org/dc/elements/1.1/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Hidden WordPress Remote Outline Metadata</rdf:li></rdf:Alt></dc:title>'
    . '</rdf:Description></rdf:RDF></x:xmpmeta><?xpacket end="w"?>';
$metadataStream = gzcompress($metadataPayload);
if (!is_string($metadataStream)) {
    throw new RuntimeException('Unable to compress WordPress remote outline metadata stream payload.');
}

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 >>\nendobj\n"
    . "6 0 obj\n<< /Title (WordPress Remote Metadata GoToR) /Parent 5 0 R /A 9 0 R /Metadata 8 0 R /C [0 .2 .6] /F 2 >>\nendobj\n"
    . "8 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($metadataStream) . " >>\nstream\n{$metadataStream}\nendstream\nendobj\n"
    . "9 0 obj\n<< /S /GoToR /F (wordpress-remote-outline-review.pdf) /D (RemoteAppendix) /NewWindow true >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$outlineExtractor = new PdfOutlineExtractor();
$remoteActions = $outlineExtractor->getRemoteGoToActions($pdf);
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);

$outline = $metadata['document_outline'] ?? [];
$remoteReview = $remoteActions[0]['metadata_stream_review'] ?? [];
$actionReview = $navigation['outline_action_review_actions'][0]['outline_metadata_stream_review'] ?? [];
$metadataEncoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$remoteEncoded = json_encode($remoteActions, JSON_UNESCAPED_SLASHES);
$navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);

if (count($remoteActions) !== 1 || ($remoteActions[0]['file'] ?? null) !== 'wordpress-remote-outline-review.pdf') {
    throw new RuntimeException('Expected one remote outline GoToR review row.');
}
if (($remoteActions[0]['outline_object'] ?? null) !== 6 || ($remoteReview['object_number'] ?? null) !== 8) {
    throw new RuntimeException('Expected remote outline row to carry outline-local Metadata stream review.');
}
if (($actionReview['object_number'] ?? null) !== 8) {
    throw new RuntimeException('Expected composite navigation action review to retain outline Metadata review.');
}
if (array_key_exists('title', $metadata)) {
    throw new RuntimeException('Expected outline-local Metadata XMP title to stay out of document metadata roots.');
}
if (!is_string($metadataEncoded)
    || !is_string($remoteEncoded)
    || !is_string($navigationEncoded)
    || str_contains($metadataEncoded, $metadataPayload)
    || str_contains($remoteEncoded, $metadataPayload)
    || str_contains($navigationEncoded, $metadataPayload)
) {
    throw new RuntimeException('Expected outline-local Metadata payload to remain excluded from review JSON.');
}
if (str_contains($plainText, 'WordPress Remote Metadata GoToR')
    || str_contains($plainText, 'wordpress-remote-outline-review.pdf')
    || str_contains($plainText, 'Hidden WordPress Remote Outline Metadata')
) {
    throw new RuntimeException('Expected outline metadata and remote action operands to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-remote-metadata-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-remote-metadata-boundary-currentbase',
    'support_component' => 'native-pdf-outline-remote-action-metadata-review',
    'native_boundary' => 'remote GoToR outline rows carry outline-local Metadata stream hashes as review-only metadata without promoting XMP or remote operands into visible WordPress text',
    'outline_titles' => $outline['titles'] ?? [],
    'remote_action_titles' => array_column($remoteActions, 'title'),
    'remote_action_files' => array_column($remoteActions, 'file'),
    'remote_outline_objects' => array_column($remoteActions, 'outline_object'),
    'metadata_review_object' => $remoteReview['object_number'] ?? null,
    'metadata_review_status' => $remoteReview['status'] ?? null,
    'metadata_review_sha256' => $remoteReview['sha256'] ?? null,
    'navigation_action_metadata_review_object' => $actionReview['object_number'] ?? null,
    'outline_metadata_accepted_as_document_xmp' => $remoteReview['accepted_as_document_xmp'] ?? null,
    'remote_payload_included' => $remoteReview['payload_included'] ?? null,
    'visible_text_excludes_outline_metadata' => !str_contains($plainText, 'WordPress Remote Metadata GoToR')
        && !str_contains($plainText, 'Hidden WordPress Remote Outline Metadata'),
    'visible_text_excludes_remote_action_operands' => !str_contains($plainText, 'wordpress-remote-outline-review.pdf')
        && !str_contains($plainText, 'RemoteAppendix'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>"
    . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</p>\n<!-- /wp:paragraph -->\n\n";
echo "<!-- wp:list -->\n<ul>\n";
foreach ($remoteActions as $row) {
    echo '<li data-marker-remote-destination-file="'
        . htmlspecialchars($row['file'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-object="'
        . htmlspecialchars((string) ($row['outline_object'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-metadata-sha256="'
        . htmlspecialchars((string) (($row['metadata_stream_review']['sha256'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">Remote destination action: '
        . htmlspecialchars($row['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
