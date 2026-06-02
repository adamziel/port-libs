<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class PdfSecurityPreflight
{
    /**
     * Native WordPress/import preflight for PDF security boundaries. It
     * summarizes encryption permissions and signature byte ranges without
     * decrypting content, validating signatures, signing, or executing actions.
     *
     * @return array<string, mixed>
     */
    public function analyze(string $pdfBytes): array
    {
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdfBytes);
        $form = (new PdfAcroFormExtractor())->extractForm($pdfBytes);
        $encryption = is_array($metadata['encryption'] ?? null) ? $metadata['encryption'] : null;
        $signatures = $this->signatureReviews($form['fields'] ?? [], $pdfBytes);
        $encrypted = $encryption !== null;
        $signedSignatureCount = count(array_filter(
            $signatures,
            static fn (array $signature): bool => ($signature['signed'] ?? false) === true
        ));
        $invalidByteRangeCount = count(array_filter(
            $signatures,
            static fn (array $signature): bool => ($signature['byte_range']['present'] ?? false) === true
                && ($signature['byte_range']['valid'] ?? false) !== true
        ));
        $lockedFieldNames = $this->lockedFieldNames($form['fields'] ?? []);
        $permissionPreflight = $this->permissionPreflight($encrypted, $encryption);
        $reviewReasons = $this->reviewReasons(
            $encrypted,
            $signedSignatureCount,
            $invalidByteRangeCount,
            $lockedFieldNames,
            $permissionPreflight
        );

        return [
            'source' => 'pdf_security_preflight',
            'pdf_bytes' => strlen($pdfBytes),
            'encrypted' => $encrypted,
            'content_extraction_allowed' => !$encrypted,
            'text_extraction_policy' => $encrypted ? 'blocked_without_decryption' : 'native_text_allowed',
            'form_value_import_policy' => $encrypted ? 'review_only_encrypted' : 'native_review_metadata',
            'import_decision' => $this->importDecision($encrypted, $invalidByteRangeCount, $signedSignatureCount),
            'review_reasons' => $reviewReasons,
            'blocked_operations' => $this->blockedOperations($encrypted, $signatures),
            'encryption' => $this->encryptionReview($encryption),
            'permission_preflight' => $permissionPreflight,
            'signature_flags' => $form['signature_flags'] ?? [],
            'signature_field_count' => count($signatures),
            'signed_signature_count' => $signedSignatureCount,
            'invalid_signature_byte_range_count' => $invalidByteRangeCount,
            'locked_field_names' => $lockedFieldNames,
            'signatures' => $signatures,
            'raw_owner_user_keys_exposed' => false,
            'executes_decryption' => false,
            'executes_signature_validation' => false,
            'executes_signing' => false,
            'executes_javascript' => false,
            'executes_python_or_models' => false,
            'executes_external_pdf_tools' => false,
        ];
    }

    /**
     * @param array<string, mixed>|null $encryption
     * @return array<string, mixed>
     */
    private function permissionPreflight(bool $encrypted, ?array $encryption): array
    {
        if (!$encrypted || $encryption === null) {
            return [
                'source' => 'unencrypted_document',
                'encrypted' => false,
                'permissions_declared' => false,
                'requires_password_for_content_extraction' => false,
                'decryption_performed' => false,
                'native_text_extraction_allowed_now' => true,
                'policy' => 'native_text_allowed',
                'review_only' => true,
                'raw_key_material_exposed' => false,
            ];
        }

        $permissions = is_array($encryption['standard_permissions'] ?? null) ? $encryption['standard_permissions'] : [];
        $allowed = array_values(array_filter(
            $permissions['allowed'] ?? [],
            static fn (mixed $value): bool => is_string($value)
        ));
        $denied = array_values(array_filter(
            $permissions['denied'] ?? [],
            static fn (mixed $value): bool => is_string($value)
        ));
        $declared = $permissions !== [];
        $copyAllowed = $declared ? in_array('copy_or_extract', $allowed, true) : null;
        $accessibilityAllowed = $declared ? in_array('extract_for_accessibility', $allowed, true) : null;

        if (!$declared) {
            $policy = 'permissions_unknown_blocked_without_decryption';
            $boundary = 'blocked_encrypted_permissions_unknown';
            $source = 'encryption_dictionary_without_standard_permissions';
        } elseif ($copyAllowed) {
            $policy = 'copy_extract_allowed_after_decryption';
            $boundary = 'blocked_until_decryption_password_available';
            $source = 'standard_security_handler_permissions';
        } else {
            $policy = 'copy_extract_denied_by_permissions';
            $boundary = 'blocked_by_encryption_and_copy_permission';
            $source = 'standard_security_handler_permissions';
        }

        return [
            'source' => $source,
            'encrypted' => true,
            'handler' => $encryption['filter'] ?? null,
            'revision_label' => $encryption['revision_label'] ?? null,
            'permissions_declared' => $declared,
            'permission_hex' => $permissions['hex'] ?? null,
            'allowed' => $allowed,
            'denied' => $denied,
            'copy_or_extract_allowed' => $copyAllowed,
            'accessibility_extract_allowed' => $accessibilityAllowed,
            'print_quality' => $permissions['print_quality'] ?? null,
            'requires_password_for_content_extraction' => (bool) ($encryption['requires_password_for_content_extraction'] ?? true),
            'decryption_performed' => false,
            'native_text_extraction_allowed_now' => false,
            'policy' => $policy,
            'content_extraction_boundary' => $boundary,
            'review_only' => true,
            'raw_key_material_exposed' => false,
        ];
    }

    /**
     * @param list<array<string, mixed>> $fields
     * @return list<array<string, mixed>>
     */
    private function signatureReviews(array $fields, string $pdfBytes): array
    {
        $reviews = [];
        foreach ($fields as $field) {
            if (($field['field_type'] ?? null) !== 'Sig') {
                continue;
            }

            $signature = is_array($field['signature'] ?? null) ? $field['signature'] : [];
            $state = is_array($field['signature_state'] ?? null) ? $field['signature_state'] : [];
            $seed = is_array($field['signature_seed_value'] ?? null) ? $field['signature_seed_value'] : [];
            $lock = is_array($field['signature_lock'] ?? null) ? $field['signature_lock'] : [];
            $byteRange = $state['byte_range'] ?? ($signature['byte_range'] ?? null);

            $reviews[] = [
                'field_name' => $field['name'] ?? null,
                'field_object' => $field['object'] ?? null,
                'signature_object' => $state['signature_object'] ?? ($signature['object'] ?? null),
                'filter' => $signature['filter'] ?? null,
                'subfilter' => $signature['subfilter'] ?? null,
                'signer_name' => $signature['name'] ?? null,
                'signed_at' => $state['signed_at'] ?? ($signature['signed_at'] ?? null),
                'signed' => (bool) ($state['signed'] ?? false),
                'certifying_signature' => (bool) ($state['certifying_signature'] ?? ($field['certifying_signature'] ?? false)),
                'contents_present' => (bool) ($state['contents_present'] ?? ($signature['contents_present'] ?? false)),
                'contents_length_bytes' => $state['contents_length_bytes'] ?? ($signature['contents_length_bytes'] ?? null),
                'byte_range' => $this->byteRangeBoundary($byteRange, $pdfBytes),
                'seed_required_constraints' => $seed['required_constraints'] ?? [],
                'lock_action' => $lock['action'] ?? null,
                'lock_field_names' => $lock['field_names'] ?? [],
                'lock_permission_label' => $lock['permission_label'] ?? null,
                'cryptographic_signature_validated' => false,
                'executes_signature_validation' => false,
                'executes_signing' => false,
                'executes_action' => false,
            ];
        }

        return $reviews;
    }

    /**
     * @return array<string, mixed>
     */
    private function byteRangeBoundary(mixed $byteRange, string $pdfBytes): array
    {
        $fileBytes = strlen($pdfBytes);
        $base = [
            'present' => is_array($byteRange),
            'file_bytes' => $fileBytes,
            'values' => is_array($byteRange) ? array_values($byteRange) : [],
            'segment_count' => 0,
            'segments' => [],
            'shape_valid' => false,
            'non_negative' => false,
            'within_file' => false,
            'sorted_non_overlapping' => false,
            'starts_at_zero' => false,
            'ends_at_file_end' => false,
            'gap_count' => 0,
            'gaps' => [],
            'has_signature_contents_gap' => false,
            'valid' => false,
            'status' => is_array($byteRange) ? 'invalid_shape' : 'missing',
            'cryptographic_signature_validated' => false,
        ];
        if (!is_array($byteRange)) {
            return $base;
        }

        $values = array_values($byteRange);
        $allIntegers = true;
        foreach ($values as $value) {
            if (!is_int($value)) {
                $allIntegers = false;
                break;
            }
        }

        $shapeValid = $allIntegers && count($values) >= 4 && count($values) % 2 === 0;
        $base['shape_valid'] = $shapeValid;
        $base['segment_count'] = $shapeValid ? intdiv(count($values), 2) : 0;
        if (!$shapeValid) {
            return $base;
        }

        $segments = [];
        $nonNegative = true;
        $withinFile = true;
        $sorted = true;
        $previousEnd = null;
        $gaps = [];
        for ($offset = 0, $count = count($values); $offset < $count; $offset += 2) {
            $start = $values[$offset];
            $length = $values[$offset + 1];
            $end = $start + $length;
            $segments[] = [
                'offset' => $start,
                'length' => $length,
                'end' => $end,
            ];

            if ($start < 0 || $length < 0) {
                $nonNegative = false;
            }
            if ($end > $fileBytes) {
                $withinFile = false;
            }
            if ($previousEnd !== null) {
                if ($start < $previousEnd) {
                    $sorted = false;
                } elseif ($start > $previousEnd) {
                    $gaps[] = [
                        'offset' => $previousEnd,
                        'length' => $start - $previousEnd,
                        'end' => $start,
                    ];
                }
            }

            $previousEnd = $end;
        }

        $startsAtZero = ($segments[0]['offset'] ?? null) === 0;
        $last = $segments[count($segments) - 1] ?? null;
        $endsAtFileEnd = is_array($last) && ($last['end'] ?? null) === $fileBytes;
        $hasSignatureContentsGap = $this->hasSignatureContentsGap($pdfBytes, $gaps);
        $valid = $nonNegative && $withinFile && $sorted && $startsAtZero && $endsAtFileEnd && count($gaps) === 1 && $hasSignatureContentsGap;

        $base['segments'] = $segments;
        $base['non_negative'] = $nonNegative;
        $base['within_file'] = $withinFile;
        $base['sorted_non_overlapping'] = $sorted;
        $base['starts_at_zero'] = $startsAtZero;
        $base['ends_at_file_end'] = $endsAtFileEnd;
        $base['gap_count'] = count($gaps);
        $base['gaps'] = $gaps;
        $base['has_signature_contents_gap'] = $hasSignatureContentsGap;
        $base['valid'] = $valid;
        $base['status'] = $this->byteRangeStatus($valid, $nonNegative, $withinFile, $sorted, $startsAtZero, $endsAtFileEnd, $gaps, $hasSignatureContentsGap);

        return $base;
    }

    /**
     * @param list<array{offset: int, length: int, end: int}> $gaps
     */
    private function hasSignatureContentsGap(string $pdfBytes, array $gaps): bool
    {
        if (count($gaps) !== 1) {
            return false;
        }

        $gap = $gaps[0];
        if ($gap['length'] <= 1 || $gap['offset'] < 0 || $gap['end'] > strlen($pdfBytes)) {
            return false;
        }

        $gapBytes = substr($pdfBytes, $gap['offset'], $gap['length']);
        $first = $gapBytes[0] ?? '';
        $last = $gapBytes[strlen($gapBytes) - 1] ?? '';
        $isPdfString = ($first === '<' && $last === '>') || ($first === '(' && $last === ')');
        if (!$isPdfString) {
            return str_contains($gapBytes, '/Contents');
        }

        $prefix = substr($pdfBytes, max(0, $gap['offset'] - 48), min(48, $gap['offset']));

        return str_contains($prefix, '/Contents');
    }

    /**
     * @param list<array{offset: int, length: int, end: int}> $gaps
     */
    private function byteRangeStatus(
        bool $valid,
        bool $nonNegative,
        bool $withinFile,
        bool $sorted,
        bool $startsAtZero,
        bool $endsAtFileEnd,
        array $gaps,
        bool $hasSignatureContentsGap
    ): string {
        if ($valid) {
            return 'covers_file_except_signature_contents';
        }
        if (!$nonNegative) {
            return 'invalid_negative';
        }
        if (!$withinFile) {
            return 'invalid_out_of_bounds';
        }
        if (!$sorted) {
            return 'invalid_overlap';
        }
        if (!$startsAtZero || !$endsAtFileEnd) {
            return 'incomplete_file_coverage';
        }
        if (count($gaps) !== 1 || !$hasSignatureContentsGap) {
            return 'review_required_non_signature_gap';
        }

        return 'invalid_shape';
    }

    /**
     * @param array<string, mixed>|null $encryption
     * @return array<string, mixed>
     */
    private function encryptionReview(?array $encryption): array
    {
        if ($encryption === null) {
            return [
                'is_encrypted' => false,
                'copy_or_extract_allowed' => true,
                'requires_password_for_content_extraction' => false,
                'raw_key_material_exposed' => false,
            ];
        }

        $permissions = is_array($encryption['standard_permissions'] ?? null) ? $encryption['standard_permissions'] : [];
        $allowed = array_values(array_filter(
            $permissions['allowed'] ?? [],
            static fn (mixed $value): bool => is_string($value)
        ));
        $denied = array_values(array_filter(
            $permissions['denied'] ?? [],
            static fn (mixed $value): bool => is_string($value)
        ));

        return [
            'is_encrypted' => true,
            'source' => $encryption['source'] ?? null,
            'object_number' => $encryption['object_number'] ?? null,
            'filter' => $encryption['filter'] ?? null,
            'subfilter' => $encryption['subfilter'] ?? null,
            'algorithm' => $encryption['algorithm'] ?? null,
            'revision_label' => $encryption['revision_label'] ?? null,
            'key_length_bits' => $encryption['key_length_bits'] ?? null,
            'encrypt_metadata' => $encryption['encrypt_metadata'] ?? null,
            'stream_filter' => $encryption['stream_filter'] ?? null,
            'string_filter' => $encryption['string_filter'] ?? null,
            'embedded_file_filter' => $encryption['embedded_file_filter'] ?? null,
            'permission_hex' => $permissions['hex'] ?? null,
            'allowed' => $allowed,
            'denied' => $denied,
            'copy_or_extract_allowed' => in_array('copy_or_extract', $allowed, true),
            'accessibility_extract_allowed' => in_array('extract_for_accessibility', $allowed, true),
            'print_quality' => $permissions['print_quality'] ?? null,
            'perms_hash_present' => isset($encryption['perms']['sha256']),
            'requires_password_for_content_extraction' => (bool) ($encryption['requires_password_for_content_extraction'] ?? true),
            'review_only' => true,
            'raw_key_material_exposed' => false,
        ];
    }

    /**
     * @param list<array<string, mixed>> $fields
     * @return list<string>
     */
    private function lockedFieldNames(array $fields): array
    {
        $names = [];
        foreach ($fields as $field) {
            $lockState = is_array($field['signature_lock_state'] ?? null) ? $field['signature_lock_state'] : [];
            if (($lockState['effective_locked'] ?? false) !== true || !is_string($field['name'] ?? null)) {
                continue;
            }

            if (!in_array($field['name'], $names, true)) {
                $names[] = $field['name'];
            }
        }

        return $names;
    }

    /**
     * @param list<string> $lockedFieldNames
     * @param array<string, mixed> $permissionPreflight
     * @return list<string>
     */
    private function reviewReasons(
        bool $encrypted,
        int $signedSignatureCount,
        int $invalidByteRangeCount,
        array $lockedFieldNames,
        array $permissionPreflight
    ): array
    {
        $reasons = [];
        if ($encrypted) {
            $reasons[] = 'encrypted_document';
            $reasons[] = 'encrypted_text_extraction_blocked';
            $permissionPolicy = $permissionPreflight['policy'] ?? null;
            if ($permissionPolicy === 'copy_extract_denied_by_permissions') {
                $reasons[] = 'copy_or_extract_denied';
            }
            if ($permissionPolicy === 'copy_extract_allowed_after_decryption') {
                $reasons[] = 'copy_or_extract_allowed_but_decryption_required';
            } elseif ($permissionPolicy === 'permissions_unknown_blocked_without_decryption') {
                $reasons[] = 'encryption_permissions_unknown';
            }
        }
        if ($signedSignatureCount > 0 && !$encrypted) {
            $reasons[] = 'signed_signature_present';
        }
        if ($lockedFieldNames !== []) {
            $reasons[] = 'signed_field_locks_present';
        }
        if ($invalidByteRangeCount > 0) {
            $reasons[] = 'signature_byte_range_invalid';
        }

        return $reasons;
    }

    private function importDecision(bool $encrypted, int $invalidByteRangeCount, int $signedSignatureCount): string
    {
        if ($encrypted) {
            return 'block_encrypted_content_review_security_metadata';
        }
        if ($invalidByteRangeCount > 0) {
            return 'review_required_signature_boundary';
        }
        if ($signedSignatureCount > 0) {
            return 'review_required_signature_metadata';
        }

        return 'allow_native_import';
    }

    /**
     * @param list<array<string, mixed>> $signatures
     * @return list<string>
     */
    private function blockedOperations(bool $encrypted, array $signatures): array
    {
        $blocked = [];
        if ($encrypted) {
            $blocked[] = 'native_text_extraction';
            $blocked[] = 'decryption';
        }
        if ($signatures !== []) {
            $blocked[] = 'signature_validation';
            $blocked[] = 'signing';
        }

        return $blocked;
    }
}
