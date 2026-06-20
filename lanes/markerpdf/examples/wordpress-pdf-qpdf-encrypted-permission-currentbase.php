<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\CorePdfConverter;
use PortLibs\MarkerPDF\PdfSecurityPreflight;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$qpdfNoExtractFixture = static function (): string {
    // Generated once with qpdf 12.3.2:
    // qpdf --encrypt userpass ownerpass 256 --print=full --modify=none --extract=n --annotate=n -- plain.pdf r6-noextract.pdf
    $encoded = <<<'BASE64'
JVBERi0xLjcKJb/3ov4KMSAwIG9iago8PCAvRXh0ZW5zaW9ucyA8PCAvQURCRSA8PCAvQmFzZVZl
cnNpb24gLzEuNyAvRXh0ZW5zaW9uTGV2ZWwgOCA+PiA+PiAvUGFnZXMgMiAwIFIgL1R5cGUgL0Nh
dGFsb2cgPj4KZW5kb2JqCjIgMCBvYmoKPDwgL0NvdW50IDEgL0tpZHMgWyAzIDAgUiBdIC9UeXBl
IC9QYWdlcyA+PgplbmRvYmoKMyAwIG9iago8PCAvQ29udGVudHMgNCAwIFIgL01lZGlhQm94IFsg
MCAwIDYxMiA3OTIgXSAvUGFyZW50IDIgMCBSIC9SZXNvdXJjZXMgPDwgL0ZvbnQgPDwgL0YxIDUg
MCBSID4+ID4+IC9UeXBlIC9QYWdlID4+CmVuZG9iago0IDAgb2JqCjw8IC9MZW5ndGggOTYgL0Zp
bHRlciAvRmxhdGVEZWNvZGUgPj4Kc3RyZWFtCogJKwyETArTz8Wku934Eu/Y9P2saRvMhpMVYp+S
yql0FUt5yhlLXVMy1UaoP60sOAFs9mXPZFNBzb5LQ/tZ7RswbxUKtvCHZhXl7XCdHw9ZSkFxs7lf
fV8Hrzgrt3DMC2VuZHN0cmVhbQplbmRvYmoKNSAwIG9iago8PCAvQmFzZUZvbnQgL0hlbHZldGlj
YSAvU3VidHlwZSAvVHlwZTEgL1R5cGUgL0ZvbnQgPj4KZW5kb2JqCjYgMCBvYmoKPDwgL0NGIDw8
IC9TdGRDRiA8PCAvQXV0aEV2ZW50IC9Eb2NPcGVuIC9DRk0gL0FFU1YzIC9MZW5ndGggMzIgPj4g
Pj4gL0ZpbHRlciAvU3RhbmRhcmQgL0xlbmd0aCAyNTYgL08gPDdlYjFlMzQxNzNlNDUxN2VkZmE1
YmI0ZjI1NzM1NTYxYjY4NzBkNjBlMTZmNTI2MDNkMDc4M2M0MjAzMjllOGYwYmQzYmU1NGQwZDYw
ZGQxMGQ2M2IzNTY2NjEwMThlMz4gL09FIDw5ZWUzNTBkMTAwMDE2ZDMyZDVjMDZjMGE0YzFiMjg2
YzA0ZWI3N2NhYjMxNWJjNjgwOGQ2YjIwOGViNTNhN2M1PiAvUCAtMTM0MCAvUGVybXMgPDc0M2M4
YjMwYzA2YjBjOWMzMzMzZGYzZWI3NmMzMGI3PiAvUiA2IC9TdG1GIC9TdGRDRiAvU3RyRiAvU3Rk
Q0YgL1UgPDM1ZmI0ODc0NDAwMmY2MDUwNDQyNTA3MjE0NzkzNGZkMTY0YzJiZDc0N2UyY2I3Mzhh
NDE4ZjA2MzllYjE5ZmJlNTlmM2VmOTcyN2FjYjQyZWJlYjQ4NTM1NzVjYzJjZT4gL1VFIDxkODE1
NTcxMWM0YTM3ODg0ODQyNDU0MGQ0NjEyMjRhNDhmMTUyZGExOThiYjlmODNhMWI1Njk1ZjEyN2Ji
OWMyPiAvViA1ID4+CmVuZG9iagp4cmVmCjAgNwowMDAwMDAwMDAwIDY1NTM1IGYgCjAwMDAwMDAw
MTUgMDAwMDAgbiAKMDAwMDAwMDEzMCAwMDAwMCBuIAowMDAwMDAwMTg5IDAwMDAwIG4gCjAwMDAw
MDAzMTcgMDAwMDAgbiAKMDAwMDAwMDQ4MyAwMDAwMCBuIAowMDAwMDAwNTUzIDAwMDAwIG4gCnRy
YWlsZXIgPDwgL1Jvb3QgMSAwIFIgL1NpemUgNyAvSUQgWzwwNDY2YzMyNDJlMGRiZTdjYmEwZmIz
NjdlZDgzNzExNj48MDQ2NmMzMjQyZTBkYmU3Y2JhMGZiMzY3ZWQ4MzcxMTY+XSAvRW5jcnlwdCA2
IDAgUiA+PgpzdGFydHhyZWYKMTEwMwolJUVPRgo=
BASE64;

    $compact = preg_replace('/\s+/', '', $encoded);
    $bytes = is_string($compact) ? base64_decode($compact, true) : false;
    if (!is_string($bytes)) {
        throw new RuntimeException('Unable to decode qpdf encrypted fixture.');
    }

    return $bytes;
};

$pdf = $qpdfNoExtractFixture();
$preflight = (new PdfSecurityPreflight())->analyze($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$path = sys_get_temp_dir() . '/markerpdf-wordpress-qpdf-encrypted-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, $pdf);

try {
    $pipelineCalls = 0;
    $result = (new CorePdfConverter())->convertWithSuppliedPages(
        $path,
        [['pnum' => 0, 'blocks' => []]],
        [],
        static function () use (&$pipelineCalls): array {
            $pipelineCalls++;

            return ['text' => 'should not import', 'images' => [], 'metadata' => []];
        }
    );
} finally {
    unlink($path);
}

$passwordReview = $preflight['password_handling_review'] ?? [];
$permission = $preflight['permission_preflight'] ?? [];
$encodedReview = json_encode([$preflight, $result], JSON_UNESCAPED_SLASHES);
$rawFixtureContentExposed = is_string($encodedReview)
    && (
        str_contains($encodedReview, 'QPDF encrypted permission body')
        || str_contains($encodedReview, 'userpass')
        || str_contains($encodedReview, 'ownerpass')
    );

if (
    $plainText !== ''
    || ($result['context']['stage'] ?? null) !== 'encrypted-pdf-preflight'
    || $pipelineCalls !== 0
    || ($preflight['encrypted'] ?? null) !== true
    || ($permission['policy'] ?? null) !== 'copy_extract_denied_by_permissions'
    || ($passwordReview['password_prompt_policy'] ?? null) !== 'password_required_ready_for_external_validation'
    || $rawFixtureContentExposed
) {
    throw new RuntimeException('Expected qpdf encrypted permission fixture to remain review-only and blocked before WordPress import.');
}

echo '<!-- markerpdf-qpdf-encrypted-permission-currentbase-smoke ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdf-qpdf-encrypted-permission-currentbase',
    'qpdf_derived_fixture' => true,
    'algorithm' => $preflight['encryption']['algorithm'] ?? null,
    'revision_label' => $preflight['encryption']['revision_label'] ?? null,
    'permission_signed' => $permission['permission_signed'] ?? null,
    'permission_hex' => $permission['permission_hex'] ?? null,
    'policy' => $permission['policy'] ?? null,
    'password_prompt_policy' => $passwordReview['password_prompt_policy'] ?? null,
    'password_validation_performed' => $passwordReview['password_validation_performed'] ?? null,
    'decryption_performed' => $passwordReview['decryption_performed'] ?? null,
    'stage' => $result['context']['stage'] ?? null,
    'model_pipeline_calls' => $pipelineCalls,
    'should_queue_models' => $result['metadata']['pdf_security']['should_queue_models'] ?? null,
    'raw_fixture_content_exposed' => $rawFixtureContentExposed,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:heading -->\n";
echo "<h2>Encrypted PDF Permission Review</h2>\n";
echo "<!-- /wp:heading -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(
    'The encrypted PDF stays out of WordPress content import. Permission flags and password readiness are review metadata until a caller supplies a password validation and decryption layer.',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:qpdf-encrypted-permission-review ' . htmlspecialchars(json_encode([
    'permission' => [
        'copy_or_extract_allowed' => $permission['copy_or_extract_allowed'] ?? null,
        'accessibility_extract_allowed' => $permission['accessibility_extract_allowed'] ?? null,
        'content_extraction_boundary' => $permission['content_extraction_boundary'] ?? null,
        'authentication_status' => $permission['permission_authentication_status'] ?? null,
    ],
    'password_handling' => [
        'requires_password_for_content_extraction' => $passwordReview['requires_password_for_content_extraction'] ?? null,
        'standard_authentication_material_ready_for_password_attempt' => $passwordReview['standard_authentication_material_ready_for_password_attempt'] ?? null,
        'password_validation_boundary' => $passwordReview['password_validation_boundary'] ?? null,
        'native_text_extraction_allowed_now' => $passwordReview['native_text_extraction_allowed_now'] ?? null,
    ],
    'security' => [
        'executes_decryption' => false,
        'executes_permission_enforcement' => false,
        'executes_python_or_models' => false,
        'executes_external_pdf_tools' => false,
    ],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
