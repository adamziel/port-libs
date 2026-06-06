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
        $documentSecurityStore = (new PdfDocumentSecurityStoreExtractor())->extract($pdfBytes);
        $encryption = is_array($metadata['encryption'] ?? null) ? $metadata['encryption'] : null;
        $encrypted = $encryption !== null;
        $signatures = $this->signatureReviews($form['fields'] ?? [], $pdfBytes, $documentSecurityStore);
        $objectSpans = $this->pdfObjectByteSpans($pdfBytes);
        $signatureByteRangeRevisionReview = $this->signatureByteRangeRevisionReview($signatures);
        $documentSecurityStoreSignatureReview = $this->documentSecurityStoreSignatureReview($documentSecurityStore, $signatures, $objectSpans);
        $documentSecurityStoreSignatureReferenceTransformReview = $this->documentSecurityStoreSignatureReferenceTransformReview($documentSecurityStoreSignatureReview);
        $cryptFilterContentReview = $this->cryptFilterContentReview($encrypted, $encryption);
        $permissionPreflight = $this->permissionPreflight($encrypted, $encryption, $cryptFilterContentReview);
        $publicKeyDssPermissionBoundaryReview = $this->publicKeyDssPermissionBoundaryReview(
            $permissionPreflight,
            $documentSecurityStore,
            $documentSecurityStoreSignatureReview,
            $documentSecurityStoreSignatureReferenceTransformReview,
            $signatures
        );
        $documentActionReview = $this->documentActionSecurityReview(
            $pdfBytes,
            $signatures,
            $form,
            $documentSecurityStore,
            $documentSecurityStoreSignatureReview
        );
        $hasDocumentSecurityStore = ($documentSecurityStore['present'] ?? false) === true;
        $signedSignatureCount = count(array_filter(
            $signatures,
            static fn (array $signature): bool => ($signature['signed'] ?? false) === true
        ));
        $invalidByteRangeCount = count(array_filter(
            $signatures,
            static fn (array $signature): bool => ($signature['byte_range']['present'] ?? false) === true
                && ($signature['byte_range']['valid'] ?? false) !== true
        ));
        $referenceTransformCount = $this->referenceTransformCount($signatures);
        $lockedFieldNames = $this->lockedFieldNames($form['fields'] ?? []);
        $signatureByteRangeCount = $this->signatureByteRangeCount($signatures);
        $validSignatureByteRangeCount = $this->validSignatureByteRangeCount($signatures);
        $encryptedSignatureByteRangeReview = $this->encryptedSignatureByteRangeReview($encrypted, $permissionPreflight, $signatures);
        $fieldMdpByteRangeReview = $this->fieldMdpByteRangeReview($signatures, $form['fields'] ?? [], $objectSpans);
        $reviewReasons = $this->reviewReasons(
            $encrypted,
            $signedSignatureCount,
            $invalidByteRangeCount,
            $signatureByteRangeCount,
            $referenceTransformCount,
            $lockedFieldNames,
            $permissionPreflight,
            $hasDocumentSecurityStore,
            $documentActionReview
        );

        return [
            'source' => 'pdf_security_preflight',
            'pdf_bytes' => strlen($pdfBytes),
            'encrypted' => $encrypted,
            'content_extraction_allowed' => !$encrypted,
            'text_extraction_policy' => $encrypted ? 'blocked_without_decryption' : 'native_text_allowed',
            'form_value_import_policy' => $encrypted ? 'review_only_encrypted' : 'native_review_metadata',
            'import_decision' => $this->importDecision($encrypted, $invalidByteRangeCount, $signedSignatureCount, $hasDocumentSecurityStore, $documentActionReview),
            'review_reasons' => $reviewReasons,
            'blocked_operations' => $this->blockedOperations($encrypted, $signatures, $hasDocumentSecurityStore, $documentActionReview),
            'encryption' => $this->encryptionReview($encryption),
            'crypt_filter_content_review_count' => ($cryptFilterContentReview['present'] ?? false) === true ? 1 : 0,
            'crypt_filter_content_review' => $cryptFilterContentReview,
            'permission_preflight' => $permissionPreflight,
            'permission_handler_review' => is_array($permissionPreflight['permission_handler_review'] ?? null)
                ? $permissionPreflight['permission_handler_review']
                : [],
            'standard_authentication_review' => is_array($permissionPreflight['standard_authentication_review'] ?? null)
                ? $permissionPreflight['standard_authentication_review']
                : [],
            'signature_flags' => $form['signature_flags'] ?? [],
            'signature_field_count' => count($signatures),
            'signed_signature_count' => $signedSignatureCount,
            'signature_byte_range_count' => $signatureByteRangeCount,
            'valid_signature_byte_range_count' => $validSignatureByteRangeCount,
            'invalid_signature_byte_range_count' => $invalidByteRangeCount,
            'signature_byte_range_revision_review_count' => (int) $signatureByteRangeRevisionReview['byte_range_count'],
            'signature_prior_revision_count' => (int) $signatureByteRangeRevisionReview['prior_revision_signature_count'],
            'signature_current_revision_count' => (int) $signatureByteRangeRevisionReview['current_revision_signature_count'],
            'signature_invalid_revision_boundary_count' => (int) $signatureByteRangeRevisionReview['invalid_revision_boundary_count'],
            'signature_byte_range_revision_statuses' => $signatureByteRangeRevisionReview['revision_statuses'],
            'signature_byte_range_revision_review' => $signatureByteRangeRevisionReview,
            'signature_reference_transform_count' => $referenceTransformCount,
            'signature_reference_transform_methods' => $this->referenceTransformMethods($signatures),
            'locked_field_names' => $lockedFieldNames,
            'field_mdp_byte_range_review_count' => (int) $fieldMdpByteRangeReview['target_field_count'],
            'field_mdp_byte_range_conflict_count' => (int) $fieldMdpByteRangeReview['target_not_covered_count'],
            'field_mdp_byte_range_statuses' => $fieldMdpByteRangeReview['target_statuses'],
            'field_mdp_byte_range_review' => $fieldMdpByteRangeReview,
            'encrypted_signature_byte_range_review_count' => $encrypted ? (int) $encryptedSignatureByteRangeReview['byte_range_count'] : 0,
            'encrypted_signature_byte_range_review' => $encryptedSignatureByteRangeReview,
            'document_security_store_count' => $hasDocumentSecurityStore ? 1 : 0,
            'document_security_store' => $documentSecurityStore,
            'document_security_store_signature_review' => $documentSecurityStoreSignatureReview,
            'document_security_store_signature_match_count' => (int) $documentSecurityStoreSignatureReview['signature_vri_match_count'],
            'document_security_store_unmatched_vri_count' => (int) $documentSecurityStoreSignatureReview['unmatched_vri_count'],
            'document_security_store_signature_reference_transform_review' => $documentSecurityStoreSignatureReferenceTransformReview,
            'document_security_store_signature_reference_transform_count' => (int) $documentSecurityStoreSignatureReferenceTransformReview['signature_reference_transform_count'],
            'document_security_store_signature_reference_transform_methods' => $documentSecurityStoreSignatureReferenceTransformReview['signature_reference_transform_methods'],
            'public_key_dss_permission_boundary_review' => $publicKeyDssPermissionBoundaryReview,
            'public_key_dss_permission_boundary_review_count' => ($publicKeyDssPermissionBoundaryReview['present'] ?? false) === true ? 1 : 0,
            'document_security_store_vri_revision_review_count' => (int) $documentSecurityStoreSignatureReview['vri_revision_review_count'],
            'document_security_store_vri_after_signed_revision_count' => (int) $documentSecurityStoreSignatureReview['vri_after_signed_revision_count'],
            'document_security_store_vri_revision_statuses' => $documentSecurityStoreSignatureReview['vri_revision_statuses'],
            'signatures' => $signatures,
            'signature_security_review_count' => count($signatures),
            'signature_security_reviews' => $this->signatureSecurityReviews($signatures),
            'document_action_security_review' => $documentActionReview,
            'document_action_review_count' => (int) $documentActionReview['action_count'],
            'unsafe_document_action_count' => (int) $documentActionReview['unsafe_action_count'],
            'launch_action_count' => (int) $documentActionReview['launch_action_count'],
            'unsafe_uri_action_count' => (int) $documentActionReview['unsafe_uri_action_count'],
            'post_signature_action_count' => (int) $documentActionReview['post_signature_action_count'],
            'unsigned_action_byte_range_count' => (int) $documentActionReview['unsigned_action_byte_range_count'],
            'raw_owner_user_keys_exposed' => false,
            'recipient_bytes_exposed' => false,
            'raw_signature_validation_bytes_exposed' => false,
            'executes_decryption' => false,
            'executes_permission_enforcement' => false,
            'executes_signature_validation' => false,
            'executes_revocation_check' => false,
            'executes_trust_chain_validation' => false,
            'executes_signing' => false,
            'executes_javascript' => false,
            'executes_pdf_actions' => false,
            'executes_python_or_models' => false,
            'executes_external_pdf_tools' => false,
        ];
    }

    /**
     * @param array<string, mixed>|null $encryption
     * @return array<string, mixed>
     */
    private function permissionPreflight(bool $encrypted, ?array $encryption, array $cryptFilterContentReview): array
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
        $permissionWordReview = is_array($encryption['standard_permission_word_review'] ?? null)
            ? $encryption['standard_permission_word_review']
            : [];
        $permissionWordDeclared = (int) ($permissionWordReview['declared_entry_count'] ?? 0) > 0;
        $permissionsDecoded = $permissions !== [];
        $declared = $permissionsDecoded || $permissionWordDeclared;
        $handlerReview = $this->permissionHandlerReview($encryption, $permissions, $declared);
        $standardHandler = ($handlerReview['standard_handler'] ?? false) === true;
        $handlerSupported = ($handlerReview['handler_supported_for_native_permission_review'] ?? false) === true;
        $permissionWellFormed = $handlerReview['permission_word_well_formed'] ?? null;
        $permissionWordDuplicateEntries = ($permissionWordReview['duplicate_permission_entries'] ?? false) === true;
        $permissionWordAmbiguous = ($permissionWordReview['permission_word_ambiguous'] ?? false) === true;
        $permissionWordRangeValid = array_key_exists('permission_word_range_valid', $permissions)
            ? (bool) $permissions['permission_word_range_valid']
            : null;
        $standardParameterReview = is_array($encryption['standard_security_handler_parameter_review'] ?? null)
            ? $encryption['standard_security_handler_parameter_review']
            : [];
        $standardParametersMalformed = ($standardParameterReview['parameters_well_formed'] ?? null) === false;
        $publicKeyRecipientReview = is_array($encryption['public_key_recipient_review'] ?? null)
            ? $encryption['public_key_recipient_review']
            : [];
        $standardAuthenticationReview = is_array($encryption['standard_authentication_review'] ?? null)
            ? $encryption['standard_authentication_review']
            : [];
        $encryptMetadataDeclarationReview = is_array($encryption['encrypt_metadata_declaration_review'] ?? null)
            ? $encryption['encrypt_metadata_declaration_review']
            : [];
        $encryptMetadataDefaultedFailClosed = ($encryption['encrypt_metadata_defaulted_fail_closed'] ?? false) === true;
        $standardAuthenticationMaterialReview = $this->standardAuthenticationMaterialReview(
            $standardAuthenticationReview,
            ($handlerReview['standard_handler'] ?? false) === true
        );
        $recipientPermissionsDeclared = (int) ($publicKeyRecipientReview['recipient_count'] ?? 0) > 0;
        $selectedRecipientCount = (int) ($publicKeyRecipientReview['selected_recipient_count'] ?? 0);
        $permissionBitsReliable = $handlerSupported && !$standardParametersMalformed && $permissionWellFormed === true && $permissionWordRangeValid !== false;
        $copyAllowed = $permissionsDecoded && $permissionBitsReliable && !$permissionWordDuplicateEntries
            ? in_array('copy_or_extract', $allowed, true)
            : null;
        $accessibilityAllowed = $permissionsDecoded && $permissionBitsReliable && !$permissionWordDuplicateEntries
            ? in_array('extract_for_accessibility', $allowed, true)
            : null;
        $reviewAllowed = $permissionBitsReliable ? $allowed : [];
        $reviewDenied = $permissionBitsReliable ? $denied : [];
        $permissionBits = $permissionBitsReliable && is_array($permissions['permission_bits'] ?? null)
            ? $permissions['permission_bits']
            : [];
        $permissionAuthenticationTrustReview = $this->standardPermissionAuthenticationTrustReview(
            $encryption,
            $standardHandler,
            $permissionsDecoded,
            $permissionBitsReliable,
            $standardAuthenticationMaterialReview
        );

        if (!$declared && $recipientPermissionsDeclared) {
            $policy = 'public_key_recipient_permissions_blocked_without_private_key';
            $boundary = 'blocked_encrypted_public_key_recipient_permissions';
            $source = 'public_key_recipient_permissions';
        } elseif ($standardHandler && $standardParametersMalformed) {
            $policy = 'permissions_malformed_blocked_without_decryption';
            $boundary = 'blocked_encrypted_permissions_malformed';
            $source = 'standard_security_handler_malformed_parameters';
        } elseif (!$declared) {
            $policy = 'permissions_unknown_blocked_without_decryption';
            $boundary = 'blocked_encrypted_permissions_unknown';
            $source = 'encryption_dictionary_without_standard_permissions';
        } elseif (!$handlerSupported) {
            $policy = 'permissions_unsupported_handler_blocked_without_decryption';
            $boundary = 'blocked_encrypted_permissions_unsupported_handler';
            $source = 'unsupported_security_handler_permissions';
        } elseif ($permissionWellFormed !== true) {
            $policy = 'permissions_malformed_blocked_without_decryption';
            $boundary = 'blocked_encrypted_permissions_malformed';
            $source = 'standard_security_handler_malformed_permissions';
        } elseif ($copyAllowed) {
            $policy = 'copy_extract_allowed_after_decryption';
            $boundary = 'blocked_until_decryption_password_available';
            $source = 'standard_security_handler_permissions';
        } else {
            $policy = 'copy_extract_denied_by_permissions';
            $boundary = 'blocked_by_encryption_and_copy_permission';
            $source = 'standard_security_handler_permissions';
        }

        $cryptFilterTextPolicy = is_string($cryptFilterContentReview['text_content_policy'] ?? null)
            ? $cryptFilterContentReview['text_content_policy']
            : null;
        $cryptFilterTextBoundary = $this->cryptFilterContentExtractionBoundary($cryptFilterTextPolicy);
        $cryptFilterTextFailClosed = $cryptFilterTextBoundary !== null;
        $cryptFilterEmbeddedFilePayloadPolicy = is_string($cryptFilterContentReview['embedded_file_payload_policy'] ?? null)
            ? $cryptFilterContentReview['embedded_file_payload_policy']
            : null;
        $cryptFilterEmbeddedFileBoundary = $this->cryptFilterEmbeddedFileBoundary($cryptFilterEmbeddedFilePayloadPolicy);
        $cryptFilterEmbeddedFileFailClosed = $cryptFilterEmbeddedFileBoundary !== null;
        if ($policy === 'copy_extract_allowed_after_decryption' && $cryptFilterTextFailClosed) {
            $policy = 'copy_extract_allowed_but_crypt_filter_preflight_blocked';
            $boundary = $cryptFilterTextBoundary;
            $source = 'standard_security_handler_crypt_filter_preflight';
        }

        return [
            'source' => $source,
            'encrypted' => true,
            'handler' => $encryption['filter'] ?? null,
            'revision_label' => $encryption['revision_label'] ?? null,
            'permissions_declared' => $declared || $recipientPermissionsDeclared,
            'standard_permissions_declared' => $declared,
            'standard_permission_word_declared' => $permissionWordDeclared,
            'standard_permission_bits_decoded' => $permissionsDecoded,
            'recipient_permissions_declared' => $recipientPermissionsDeclared,
            'selected_recipient_permissions_declared' => $selectedRecipientCount > 0,
            'selected_public_key_recipient_count' => $selectedRecipientCount,
            'permission_hex' => $permissions['hex'] ?? null,
            'permission_signed' => $permissions['signed'] ?? null,
            'permission_unsigned' => $permissions['unsigned'] ?? null,
            'permission_word_form' => $permissions['declared_form'] ?? null,
            'permission_normalized_from_unsigned_decimal' => (bool) ($permissions['normalized_from_unsigned_decimal'] ?? false),
            'permission_word_range_valid' => $permissionWordRangeValid,
            'permission_word_range_status' => $permissions['permission_word_range_status'] ?? null,
            'permission_word_range' => is_array($permissions['word_range'] ?? null) ? $permissions['word_range'] : [],
            'standard_permission_word_review' => $permissionWordReview,
            'permission_word_duplicate_entries' => $permissionWordDuplicateEntries,
            'permission_word_ambiguous' => $permissionWordAmbiguous,
            'standard_security_handler_parameter_review' => $standardParameterReview,
            'standard_security_handler_parameters_well_formed' => $standardParameterReview['parameters_well_formed'] ?? null,
            'standard_security_handler_parameter_status' => $standardParameterReview['status'] ?? null,
            'standard_security_handler_version_supported' => $standardParameterReview['version_supported'] ?? null,
            'standard_security_handler_revision_supported' => $standardParameterReview['revision_supported'] ?? null,
            'standard_security_handler_version_revision_compatible' => $standardParameterReview['version_revision_compatible'] ?? null,
            'standard_security_handler_key_length_status' => $standardParameterReview['key_length_status'] ?? null,
            'standard_security_handler_key_length_explicit' => $standardParameterReview['key_length_explicit'] ?? null,
            'standard_security_handler_key_length_defaulted' => $standardParameterReview['key_length_defaulted'] ?? null,
            'standard_security_handler_key_length_source' => $standardParameterReview['key_length_source'] ?? null,
            'standard_security_handler_parameter_violations' => is_array($standardParameterReview['violations'] ?? null)
                ? $standardParameterReview['violations']
                : [],
            'standard_security_handler_parameter_declaration_review' => is_array($standardParameterReview['parameter_declaration_review'] ?? null)
                ? $standardParameterReview['parameter_declaration_review']
                : [],
            'standard_security_handler_duplicate_parameter_names' => is_array($standardParameterReview['duplicate_parameter_names'] ?? null)
                ? $standardParameterReview['duplicate_parameter_names']
                : [],
            'standard_security_handler_duplicate_parameter_count' => (int) ($standardParameterReview['duplicate_parameter_count'] ?? 0),
            'malformed_encrypt_dictionary' => (bool) ($encryption['malformed_encrypt_dictionary'] ?? false),
            'encrypt_dictionary_resolved' => array_key_exists('encrypt_dictionary_resolved', $encryption)
                ? (bool) $encryption['encrypt_dictionary_resolved']
                : null,
            'duplicate_encrypt_dictionary_entries' => (bool) ($encryption['duplicate_encrypt_dictionary_entries'] ?? false),
            'encrypt_dictionary_declared_entry_count' => (int) ($encryption['encrypt_dictionary_declared_entry_count'] ?? 0),
            'encrypt_dictionary_resolved_entry_count' => (int) ($encryption['encrypt_dictionary_resolved_entry_count'] ?? 0),
            'encrypt_dictionary_entry_statuses' => is_array($encryption['encrypt_dictionary_entry_statuses'] ?? null)
                ? $encryption['encrypt_dictionary_entry_statuses']
                : [],
            'encrypt_dictionary_entry_shapes' => is_array($encryption['encrypt_dictionary_entry_shapes'] ?? null)
                ? $encryption['encrypt_dictionary_entry_shapes']
                : [],
            'encrypt_operand_shape' => $encryption['encrypt_operand_shape'] ?? null,
            'encrypt_operand_status' => $encryption['encrypt_operand_status'] ?? null,
            'encrypt_metadata' => $encryption['encrypt_metadata'] ?? null,
            'encrypt_metadata_explicit' => (bool) ($encryption['encrypt_metadata_explicit'] ?? false),
            'encrypt_metadata_trusted' => (bool) ($encryption['encrypt_metadata_trusted'] ?? true),
            'encrypt_metadata_defaulted' => (bool) ($encryption['encrypt_metadata_defaulted'] ?? false),
            'encrypt_metadata_defaulted_fail_closed' => $encryptMetadataDefaultedFailClosed,
            'encrypt_metadata_status' => $encryption['encrypt_metadata_status'] ?? null,
            'encrypt_metadata_declaration_review' => $encryptMetadataDeclarationReview,
            'allowed' => $reviewAllowed,
            'denied' => $reviewDenied,
            'applicable_permission_names' => $permissionBitsReliable && is_array($permissions['applicable_permission_names'] ?? null)
                ? $permissions['applicable_permission_names']
                : [],
            'not_applicable_permission_names' => $permissionBitsReliable && is_array($permissions['not_applicable_permission_names'] ?? null)
                ? $permissions['not_applicable_permission_names']
                : [],
            'permission_bit_review_count' => count($permissionBits),
            'permission_bit_statuses' => $permissionBitsReliable && is_array($permissions['permission_bit_statuses'] ?? null)
                ? $permissions['permission_bit_statuses']
                : [],
            'permission_bits' => $permissionBits,
            'copy_or_extract_allowed' => $copyAllowed,
            'accessibility_extract_allowed' => $accessibilityAllowed,
            'print_quality' => $permissionBitsReliable ? ($permissions['print_quality'] ?? null) : null,
            'permission_bits_reliable' => $permissionBitsReliable,
            'permission_authentication_trust_review' => $permissionAuthenticationTrustReview,
            'permission_bits_authentication_required' => (bool) ($permissionAuthenticationTrustReview['authentication_required'] ?? false),
            'permission_bits_authenticated' => (bool) ($permissionAuthenticationTrustReview['permissions_authenticated'] ?? false),
            'authenticated_permission_bits_reliable' => (bool) ($permissionAuthenticationTrustReview['authenticated_permission_bits_reliable'] ?? false),
            'permission_authentication_status' => $permissionAuthenticationTrustReview['status'] ?? null,
            'permission_word_well_formed' => $standardParametersMalformed ? false : ($handlerSupported ? $permissionWellFormed : null),
            'permission_handler_review' => $handlerReview,
            'standard_authentication_review' => $standardAuthenticationReview,
            'standard_authentication_material_review' => $standardAuthenticationMaterialReview,
            'standard_authentication_ready_for_password_attempt' => ($standardAuthenticationMaterialReview['present'] ?? false) === true
                ? (bool) ($standardAuthenticationMaterialReview['ready_for_password_attempt'] ?? false)
                : null,
            'public_key_recipient_review' => $publicKeyRecipientReview,
            'public_key_crypt_filter_selection' => is_array($publicKeyRecipientReview['crypt_filter_selection'] ?? null)
                ? $publicKeyRecipientReview['crypt_filter_selection']
                : [],
            'crypt_filter_content_review' => $cryptFilterContentReview,
            'crypt_filter_text_policy' => $cryptFilterTextPolicy,
            'crypt_filter_text_fail_closed' => $cryptFilterTextFailClosed,
            'crypt_filter_content_extraction_boundary' => $cryptFilterTextBoundary,
            'crypt_filter_embedded_file_payload_policy' => $cryptFilterEmbeddedFilePayloadPolicy,
            'crypt_filter_embedded_file_fail_closed' => $cryptFilterEmbeddedFileFailClosed,
            'crypt_filter_embedded_file_boundary' => $cryptFilterEmbeddedFileBoundary,
            'crypt_filter_fail_closed_role_names' => is_array($cryptFilterContentReview['fail_closed_role_names'] ?? null)
                ? $cryptFilterContentReview['fail_closed_role_names']
                : [],
            'crypt_filter_fail_closed_filter_names' => is_array($cryptFilterContentReview['fail_closed_filter_names'] ?? null)
                ? $cryptFilterContentReview['fail_closed_filter_names']
                : [],
            'requires_password_for_content_extraction' => (bool) ($encryption['requires_password_for_content_extraction'] ?? true),
            'decryption_performed' => false,
            'native_text_extraction_allowed_now' => false,
            'policy' => $policy,
            'content_extraction_boundary' => $boundary,
            'review_only' => true,
            'raw_key_material_exposed' => false,
            'recipient_bytes_exposed' => false,
        ];
    }

    /**
     * @param array<string, mixed> $standardAuthenticationReview
     * @return array<string, mixed>
     */
    private function standardAuthenticationMaterialReview(array $standardAuthenticationReview, bool $standardHandler): array
    {
        $base = [
            'source' => 'standard_authentication_material_review',
            'present' => false,
            'standard_handler' => $standardHandler,
            'review_only' => true,
            'password_validation_performed' => false,
            'permissions_authenticated' => false,
            'decryption_performed' => false,
            'executes_decryption' => false,
            'executes_permission_enforcement' => false,
            'raw_owner_user_keys_exposed' => false,
            'raw_file_encryption_keys_exposed' => false,
        ];

        if (!$standardHandler) {
            return $base + [
                'status' => 'not_standard_security_handler',
                'ready_for_password_attempt' => null,
            ];
        }

        if ($standardAuthenticationReview === []) {
            return $base + [
                'status' => 'standard_authentication_review_unavailable',
                'ready_for_password_attempt' => false,
            ];
        }

        $entries = is_array($standardAuthenticationReview['entries'] ?? null)
            ? $standardAuthenticationReview['entries']
            : [];
        $requiredEntryCount = 0;
        $presentRequiredEntries = [];
        $missingRequiredEntries = [];
        $unresolvedRequiredEntries = [];
        $lengthMismatchEntries = [];
        $duplicateRequiredEntries = [];
        $requiredEntryStatuses = [];

        foreach ($entries as $name => $entry) {
            if (!is_string($name) || !is_array($entry) || ($entry['required_for_revision'] ?? false) !== true) {
                continue;
            }

            $requiredEntryCount++;
            $status = is_string($entry['status'] ?? null) ? $entry['status'] : null;
            if ($status !== null && !in_array($status, $requiredEntryStatuses, true)) {
                $requiredEntryStatuses[] = $status;
            }
            if (($entry['present'] ?? false) !== true) {
                $missingRequiredEntries[] = $name;
                continue;
            }

            $presentRequiredEntries[] = $name;
            if (($entry['duplicate_entries'] ?? false) === true) {
                $duplicateRequiredEntries[] = $name;
                continue;
            }
            if (($entry['bytes_resolved'] ?? false) !== true) {
                $unresolvedRequiredEntries[] = $name;
                continue;
            }
            if (($entry['length_valid'] ?? null) !== true) {
                $lengthMismatchEntries[] = $name;
            }
        }

        $permissionDigest = is_array($standardAuthenticationReview['permission_digest'] ?? null)
            ? $standardAuthenticationReview['permission_digest']
            : [];
        $permissionDigestRequired = is_int($permissionDigest['expected_bytes'] ?? null);
        $permissionDigestStatus = is_string($permissionDigest['status'] ?? null) ? $permissionDigest['status'] : null;
        $permissionDigestDuplicateEntries = ($permissionDigest['duplicate_entries'] ?? false) === true;
        $permissionDigestReady = !$permissionDigestRequired
            || (
                ($permissionDigest['present'] ?? false) === true
                && !$permissionDigestDuplicateEntries
                && ($permissionDigest['length_valid'] ?? null) === true
                && $permissionDigestStatus === 'permission_digest_ciphertext_review'
            );
        $ready = $requiredEntryCount > 0
            && $missingRequiredEntries === []
            && $unresolvedRequiredEntries === []
            && $lengthMismatchEntries === []
            && $duplicateRequiredEntries === []
            && $permissionDigestReady;

        return array_merge($base, [
            'present' => true,
            'revision' => $standardAuthenticationReview['revision'] ?? null,
            'revision_label' => $standardAuthenticationReview['revision_label'] ?? null,
            'algorithm' => $standardAuthenticationReview['algorithm'] ?? null,
            'key_length_bits' => $standardAuthenticationReview['key_length_bits'] ?? null,
            'required_entry_count' => $requiredEntryCount,
            'present_required_entry_count' => count($presentRequiredEntries),
            'present_required_entries' => $presentRequiredEntries,
            'missing_required_entries' => $missingRequiredEntries,
            'unresolved_required_entries' => $unresolvedRequiredEntries,
            'length_mismatch_required_entries' => $lengthMismatchEntries,
            'duplicate_required_entries' => $duplicateRequiredEntries,
            'duplicate_required_entry_count' => count($duplicateRequiredEntries),
            'required_entry_statuses' => $requiredEntryStatuses,
            'permission_digest_required' => $permissionDigestRequired,
            'permission_digest_present' => (bool) ($permissionDigest['present'] ?? false),
            'permission_digest_bytes' => $permissionDigest['bytes'] ?? null,
            'permission_digest_expected_bytes' => $permissionDigest['expected_bytes'] ?? null,
            'permission_digest_length_valid' => $permissionDigest['length_valid'] ?? null,
            'permission_digest_status' => $permissionDigestStatus,
            'permission_digest_declared_entry_count' => (int) ($permissionDigest['declared_entry_count'] ?? 0),
            'permission_digest_duplicate_entries' => $permissionDigestDuplicateEntries,
            'permission_digest_selected_entry_index' => $permissionDigest['selected_entry_index'] ?? null,
            'permission_digest_selected_entry_status' => $permissionDigest['selected_entry_status'] ?? null,
            'permission_digest_selected_entry_operand_shape' => $permissionDigest['selected_entry_operand_shape'] ?? null,
            'permission_digest_entry_statuses' => is_array($permissionDigest['entry_statuses'] ?? null)
                ? $permissionDigest['entry_statuses']
                : [],
            'permission_digest_entry_operand_shapes' => is_array($permissionDigest['entry_operand_shapes'] ?? null)
                ? $permissionDigest['entry_operand_shapes']
                : [],
            'ready_for_password_attempt' => $ready,
            'status' => $ready
                ? 'standard_authentication_material_ready_for_password_attempt'
                : 'standard_authentication_material_incomplete_or_malformed_review',
        ]);
    }

    /**
     * @param array<string, mixed> $encryption
     * @param array<string, mixed> $standardAuthenticationMaterialReview
     * @return array<string, mixed>
     */
    private function standardPermissionAuthenticationTrustReview(
        array $encryption,
        bool $standardHandler,
        bool $permissionWordDecoded,
        bool $syntacticPermissionBitsReliable,
        array $standardAuthenticationMaterialReview
    ): array {
        $revision = is_int($encryption['revision'] ?? null) ? $encryption['revision'] : null;
        $permissionDigestRequired = (bool) ($standardAuthenticationMaterialReview['permission_digest_required'] ?? false);
        $permissionDigestPresent = (bool) ($standardAuthenticationMaterialReview['permission_digest_present'] ?? false);
        $permissionDigestLengthValid = $standardAuthenticationMaterialReview['permission_digest_length_valid'] ?? null;
        $permissionDigestStatus = is_string($standardAuthenticationMaterialReview['permission_digest_status'] ?? null)
            ? $standardAuthenticationMaterialReview['permission_digest_status']
            : null;
        $authenticationMaterialReady = (bool) ($standardAuthenticationMaterialReview['ready_for_password_attempt'] ?? false);
        $authenticationRequired = $standardHandler && $permissionWordDecoded;
        $trustBoundary = $authenticationRequired
            ? 'blocked_until_password_validation_and_permission_authentication'
            : null;

        if (!$standardHandler) {
            $status = 'not_standard_security_handler';
            $trustBoundary = null;
        } elseif (!$permissionWordDecoded) {
            $status = 'no_decoded_standard_permission_bits';
            $trustBoundary = null;
        } elseif (!$syntacticPermissionBitsReliable) {
            $status = 'decoded_permission_bits_not_syntactically_reliable';
            $trustBoundary = 'blocked_by_malformed_permission_bits';
        } elseif (($standardAuthenticationMaterialReview['present'] ?? false) !== true) {
            $status = 'standard_authentication_material_unavailable_before_permission_authentication';
        } elseif ($permissionDigestRequired && !$permissionDigestPresent) {
            $status = 'required_permission_digest_missing_before_permission_authentication';
        } elseif (
            $permissionDigestRequired
            && ($permissionDigestLengthValid !== true || $permissionDigestStatus !== 'permission_digest_ciphertext_review')
        ) {
            $status = 'permission_digest_malformed_before_permission_authentication';
        } elseif ($permissionDigestRequired && $authenticationMaterialReady) {
            $status = 'permission_bits_decoded_but_unauthenticated_ready_for_password_attempt';
        } elseif (!$permissionDigestRequired && $authenticationMaterialReady) {
            $status = 'permission_bits_decoded_but_password_not_validated';
        } else {
            $status = 'permission_bits_decoded_but_authentication_material_incomplete';
        }

        return [
            'source' => 'standard_permission_authentication_trust_review',
            'present' => $standardHandler && ($permissionWordDecoded || $syntacticPermissionBitsReliable),
            'handler' => $encryption['filter'] ?? null,
            'revision' => $revision,
            'revision_label' => $encryption['revision_label'] ?? null,
            'permission_word_decoded' => $permissionWordDecoded,
            'syntactic_permission_bits_reliable' => $syntacticPermissionBitsReliable,
            'authentication_required' => $authenticationRequired,
            'permission_digest_required' => $permissionDigestRequired,
            'permission_digest_present' => $permissionDigestPresent,
            'permission_digest_bytes' => $standardAuthenticationMaterialReview['permission_digest_bytes'] ?? null,
            'permission_digest_expected_bytes' => $standardAuthenticationMaterialReview['permission_digest_expected_bytes'] ?? null,
            'permission_digest_length_valid' => $permissionDigestLengthValid,
            'permission_digest_status' => $permissionDigestStatus,
            'permission_digest_selected_entry_status' => $standardAuthenticationMaterialReview['permission_digest_selected_entry_status'] ?? null,
            'permission_digest_selected_entry_operand_shape' => $standardAuthenticationMaterialReview['permission_digest_selected_entry_operand_shape'] ?? null,
            'permission_digest_entry_operand_shapes' => is_array($standardAuthenticationMaterialReview['permission_digest_entry_operand_shapes'] ?? null)
                ? $standardAuthenticationMaterialReview['permission_digest_entry_operand_shapes']
                : [],
            'authentication_material_ready_for_password_attempt' => $authenticationMaterialReady,
            'password_validation_performed' => false,
            'permissions_authenticated' => false,
            'decryption_performed' => false,
            'executes_permission_enforcement' => false,
            'raw_owner_user_keys_exposed' => false,
            'raw_file_encryption_keys_exposed' => false,
            'authenticated_permission_bits_reliable' => false,
            'trust_boundary' => $trustBoundary,
            'status' => $status,
            'review_only' => true,
        ];
    }

    /**
     * @param array<string, mixed>|null $encryption
     * @return array<string, mixed>
     */
    private function cryptFilterContentReview(bool $encrypted, ?array $encryption): array
    {
        $base = [
            'source' => 'encryption_crypt_filter_content_review',
            'present' => false,
            'encrypted_document' => $encrypted,
            'review_only' => true,
            'native_text_extraction_allowed_now' => !$encrypted,
            'decryption_performed' => false,
            'executes_decryption' => false,
            'executes_permission_enforcement' => false,
            'executes_external_pdf_tools' => false,
            'roles' => [],
        ];

        if (!$encrypted || $encryption === null) {
            return $base + [
                'text_content_policy' => 'native_text_allowed',
                'embedded_file_payload_policy' => 'native_review_metadata',
            ];
        }

        $cryptFilters = is_array($encryption['crypt_filters'] ?? null) ? $encryption['crypt_filters'] : [];
        $cryptFilterDictionaryReview = is_array($encryption['crypt_filter_dictionary_declaration_review'] ?? null)
            ? $encryption['crypt_filter_dictionary_declaration_review']
            : [];
        $handler = is_string($encryption['filter'] ?? null) ? $encryption['filter'] : null;
        $version = is_int($encryption['version'] ?? null) ? $encryption['version'] : null;
        $revision = is_int($encryption['revision'] ?? null) ? $encryption['revision'] : null;
        $usesCryptFilterRoles = in_array($version, [4, 5], true)
            || $cryptFilters !== []
            || is_string($encryption['stream_filter'] ?? null)
            || is_string($encryption['string_filter'] ?? null)
            || is_string($encryption['embedded_file_filter'] ?? null);
        if (!$usesCryptFilterRoles) {
            return [
                'source' => 'encryption_crypt_filter_content_review',
                'present' => false,
                'encrypted_document' => true,
                'handler' => is_string($encryption['filter'] ?? null) ? $encryption['filter'] : null,
                'subfilter' => is_string($encryption['subfilter'] ?? null) ? $encryption['subfilter'] : null,
                'declared_crypt_filter_count' => 0,
                'role_count' => 0,
                'role_names' => [],
                'role_statuses' => [],
                'selected_filter_names' => [],
                'identity_role_names' => [],
                'encrypted_role_names' => [],
                'missing_role_names' => [],
                'unsupported_role_names' => [],
                'fail_closed_role_names' => [],
                'fail_closed_filter_names' => [],
                'fail_closed_role_count' => 0,
                'identity_filter_names' => [],
                'encrypted_filter_names' => [],
                'missing_filter_names' => [],
                'auth_event_statuses' => [],
                'auth_event_defaulted_role_names' => [],
                'auth_event_defaulted_filter_names' => [],
                'auth_event_mismatch_role_names' => [],
                'auth_event_mismatch_filter_names' => [],
                'crypt_filter_dictionary_declaration_review' => $cryptFilterDictionaryReview,
                'crypt_filter_dictionary_declaration_status' => $cryptFilterDictionaryReview['status'] ?? null,
                'crypt_filter_dictionary_declared_entry_count' => (int) ($cryptFilterDictionaryReview['declared_entry_count'] ?? 0),
                'crypt_filter_dictionary_duplicate_entries' => (bool) ($cryptFilterDictionaryReview['duplicate_entries'] ?? false),
                'crypt_filter_dictionary_malformed_entry_count' => (int) ($cryptFilterDictionaryReview['malformed_entry_count'] ?? 0),
                'crypt_filter_dictionary_fail_closed' => (bool) ($cryptFilterDictionaryReview['fail_closed'] ?? false),
                'text_content_policy' => 'review_only_encrypted_document_boundary',
                'embedded_file_payload_policy' => 'encrypted_filter_requires_decryption',
                'roles' => [],
                'review_only' => true,
                'native_text_extraction_allowed_now' => false,
                'decryption_performed' => false,
                'executes_decryption' => false,
                'executes_permission_enforcement' => false,
                'executes_external_pdf_tools' => false,
            ];
        }
        $roles = [];
        $roleDeclarationReview = is_array($encryption['crypt_filter_role_declaration_review'] ?? null)
            ? $encryption['crypt_filter_role_declaration_review']
            : [];
        $roleDeclarations = $this->cryptFilterRoleDeclarationsByRole($roleDeclarationReview);
        foreach ([
            'document_streams' => ['key' => 'stream_filter', 'pdf_name' => 'StmF'],
            'document_strings' => ['key' => 'string_filter', 'pdf_name' => 'StrF'],
            'embedded_file_streams' => ['key' => 'embedded_file_filter', 'pdf_name' => 'EFF'],
        ] as $role => $definition) {
            $filterName = is_string($encryption[$definition['key']] ?? null)
                ? $encryption[$definition['key']]
                : null;
            $roles[] = $this->cryptFilterContentRoleReview(
                $role,
                $definition['pdf_name'],
                $filterName,
                $cryptFilters,
                $roleDeclarations[$role] ?? [],
                $handler,
                $version,
                $revision
            );
        }
        if ($cryptFilterDictionaryReview !== []) {
            $dictionaryFields = $this->cryptFilterDictionaryContentFields($cryptFilterDictionaryReview);
            foreach ($roles as $index => $role) {
                $roles[$index] = array_merge($role, $dictionaryFields);
            }
        }

        $documentTextRows = array_values(array_filter(
            $roles,
            static fn (array $row): bool => in_array($row['role'] ?? null, ['document_streams', 'document_strings'], true)
        ));
        $embeddedFileRows = array_values(array_filter(
            $roles,
            static fn (array $row): bool => ($row['role'] ?? null) === 'embedded_file_streams'
        ));

        return [
            'source' => 'encryption_crypt_filter_content_review',
            'present' => true,
            'encrypted_document' => true,
            'handler' => $handler,
            'subfilter' => is_string($encryption['subfilter'] ?? null) ? $encryption['subfilter'] : null,
            'declared_crypt_filter_count' => count($cryptFilters),
            'role_count' => count($roles),
            'role_names' => $this->uniqueStringColumn($roles, 'role'),
            'role_statuses' => $this->uniqueStringColumn($roles, 'status'),
            'selected_filter_names' => $this->uniqueStringColumn($roles, 'filter_name'),
            'identity_role_names' => $this->cryptFilterRoleNamesByStatus($roles, 'identity_crypt_filter'),
            'encrypted_role_names' => $this->cryptFilterRoleNamesByStatus($roles, 'encrypted_crypt_filter'),
            'missing_role_names' => $this->cryptFilterRoleNamesByStatus($roles, 'missing_declared_crypt_filter'),
            'unsupported_role_names' => $this->cryptFilterRoleNamesByStatuses($roles, [
                'unsupported_crypt_filter_method_fail_closed',
                'unknown_crypt_filter_method_fail_closed',
                'undeclared_crypt_filter_fail_closed',
            ]),
            'fail_closed_role_names' => $this->cryptFilterFailClosedRoleNames($roles),
            'fail_closed_filter_names' => $this->cryptFilterFailClosedFilterNames($roles),
            'fail_closed_role_count' => $this->cryptFilterFailClosedRoleCount($roles),
            'identity_filter_names' => $this->cryptFilterNamesByStatus($roles, 'identity_crypt_filter'),
            'encrypted_filter_names' => $this->cryptFilterNamesByStatus($roles, 'encrypted_crypt_filter'),
            'missing_filter_names' => $this->cryptFilterNamesByStatus($roles, 'missing_declared_crypt_filter'),
            'auth_event_statuses' => $this->uniqueStringColumn($roles, 'auth_event_status'),
            'auth_event_defaulted_role_names' => $this->cryptFilterAuthEventDefaultedRoleNames($roles),
            'auth_event_defaulted_filter_names' => $this->cryptFilterAuthEventDefaultedFilterNames($roles),
            'auth_event_mismatch_role_names' => $this->cryptFilterAuthEventMismatchRoleNames($roles),
            'auth_event_mismatch_filter_names' => $this->cryptFilterAuthEventMismatchFilterNames($roles),
            'crypt_filter_dictionary_declaration_review' => $cryptFilterDictionaryReview,
            'crypt_filter_dictionary_declaration_status' => $cryptFilterDictionaryReview['status'] ?? null,
            'crypt_filter_dictionary_declared_entry_count' => (int) ($cryptFilterDictionaryReview['declared_entry_count'] ?? 0),
            'crypt_filter_dictionary_resolved_entry_count' => (int) ($cryptFilterDictionaryReview['resolved_dictionary_entry_count'] ?? 0),
            'crypt_filter_dictionary_duplicate_entries' => (bool) ($cryptFilterDictionaryReview['duplicate_entries'] ?? false),
            'crypt_filter_dictionary_malformed_entry_count' => (int) ($cryptFilterDictionaryReview['malformed_entry_count'] ?? 0),
            'crypt_filter_dictionary_entry_statuses' => is_array($cryptFilterDictionaryReview['entry_statuses'] ?? null)
                ? $cryptFilterDictionaryReview['entry_statuses']
                : [],
            'crypt_filter_dictionary_fail_closed' => (bool) ($cryptFilterDictionaryReview['fail_closed'] ?? false),
            'cfm_defaulted_role_names' => $this->cryptFilterCfmDefaultedRoleNames($roles),
            'cfm_defaulted_filter_names' => $this->cryptFilterCfmDefaultedFilterNames($roles),
            'key_length_statuses' => $this->uniqueStringColumn($roles, 'key_length_status'),
            'key_length_invalid_role_names' => $this->cryptFilterKeyLengthInvalidRoleNames($roles),
            'key_length_invalid_filter_names' => $this->cryptFilterKeyLengthInvalidFilterNames($roles),
            'method_generation_statuses' => $this->uniqueStringColumn($roles, 'method_generation_status'),
            'method_generation_fail_closed_role_names' => $this->cryptFilterMethodGenerationFailClosedRoleNames($roles),
            'method_generation_fail_closed_filter_names' => $this->cryptFilterMethodGenerationFailClosedFilterNames($roles),
            'role_declaration_statuses' => $this->uniqueStringColumn($roles, 'role_declaration_status'),
            'role_declaration_duplicate_role_names' => $this->cryptFilterRoleDeclarationNames($roles, 'role', true),
            'role_declaration_duplicate_pdf_names' => $this->cryptFilterRoleDeclarationNames($roles, 'pdf_name', true),
            'role_declaration_fail_closed_role_names' => $this->cryptFilterRoleDeclarationNames($roles, 'role', false),
            'role_declaration_fail_closed_pdf_names' => $this->cryptFilterRoleDeclarationNames($roles, 'pdf_name', false),
            'crypt_filter_parameter_statuses' => $this->uniqueStringColumn($roles, 'crypt_filter_parameter_declaration_status'),
            'crypt_filter_parameter_duplicate_role_names' => $this->cryptFilterParameterDuplicateRoleNames($roles),
            'crypt_filter_parameter_duplicate_filter_names' => $this->cryptFilterParameterDuplicateFilterNames($roles),
            'crypt_filter_parameter_duplicate_names' => $this->cryptFilterParameterDuplicateNames($roles),
            'text_content_policy' => $this->cryptFilterTextContentPolicy($documentTextRows),
            'embedded_file_payload_policy' => $this->cryptFilterEmbeddedFilePolicy($embeddedFileRows),
            'roles' => $roles,
            'review_only' => true,
            'native_text_extraction_allowed_now' => false,
            'decryption_performed' => false,
            'executes_decryption' => false,
            'executes_permission_enforcement' => false,
            'executes_external_pdf_tools' => false,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $cryptFilters
     * @return array<string, mixed>
     */
    private function cryptFilterContentRoleReview(
        string $role,
        string $pdfName,
        ?string $filterName,
        array $cryptFilters,
        array $roleDeclaration = [],
        ?string $handler = null,
        ?int $version = null,
        ?int $revision = null
    ): array {
        $row = [
            'source' => 'crypt_filter_content_role_review',
            'role' => $role,
            'pdf_name' => $pdfName,
            'filter_name' => $filterName,
            'crypt_filter_present' => false,
            'method' => null,
            'auth_event' => null,
            'key_length_bytes' => null,
            'content_encrypted' => true,
            'identity_crypt_filter' => false,
            'missing_declared_crypt_filter' => false,
            'standard_handler_version' => $version,
            'standard_handler_revision' => $revision,
            'method_compatible_with_standard_handler' => null,
            'method_generation_status' => null,
            'method_generation_fail_closed' => false,
            'crypt_filter_parameter_declaration_status' => null,
            'crypt_filter_parameter_fail_closed' => false,
            'crypt_filter_parameter_duplicate_names' => [],
            'crypt_filter_parameter_declaration_review' => [],
            'review_only' => true,
            'native_import_allowed_now' => false,
            'executes_decryption' => false,
        ];
        if ($roleDeclaration !== []) {
            $row = array_merge($row, $this->cryptFilterRoleDeclarationContentFields($roleDeclaration));
        }

        if ($filterName === null || $filterName === '') {
            return array_merge($row, [
                'status' => 'undeclared_crypt_filter_fail_closed',
            ]);
        }

        if ($filterName === 'Identity') {
            $generationReview = $this->cryptFilterMethodGenerationReview($handler, $version, $revision, 'Identity');
            return array_merge($row, [
                'crypt_filter_present' => true,
                'method' => 'Identity',
                'content_encrypted' => false,
                'identity_crypt_filter' => true,
                'status' => 'identity_crypt_filter',
            ], $generationReview);
        }

        $filter = is_array($cryptFilters[$filterName] ?? null) ? $cryptFilters[$filterName] : null;
        if ($filter === null) {
            return array_merge($row, [
                'missing_declared_crypt_filter' => true,
                'status' => 'missing_declared_crypt_filter',
            ]);
        }

        $method = is_string($filter['method'] ?? null) ? $filter['method'] : null;
        $authEvent = is_string($filter['auth_event'] ?? null) ? $filter['auth_event'] : null;
        $status = $this->cryptFilterMethodStatus($method);
        $identity = $status === 'identity_crypt_filter';
        $authEventStatus = $this->cryptFilterAuthEventStatus($authEvent, $role, $identity);
        $keyLengthBytes = is_int($filter['key_length_bytes'] ?? null) ? $filter['key_length_bytes'] : null;
        $keyLengthReview = $this->cryptFilterKeyLengthReview($method, $keyLengthBytes);
        $generationReview = $this->cryptFilterMethodGenerationReview($handler, $version, $revision, $method);
        $parameterDeclarationReview = is_array($filter['parameter_declaration_review'] ?? null)
            ? $filter['parameter_declaration_review']
            : [];

        return array_merge($row, [
            'crypt_filter_present' => true,
            'method' => $method,
            'auth_event' => $authEvent,
            'auth_event_defaulted' => ($filter['auth_event_defaulted'] ?? false) === true,
            'auth_event_source' => is_string($filter['auth_event_source'] ?? null) ? $filter['auth_event_source'] : null,
            'auth_event_status' => $authEventStatus,
            'auth_event_applies_to_role' => $this->cryptFilterAuthEventAppliesToRole($authEventStatus),
            'cfm_defaulted' => ($filter['cfm_defaulted'] ?? false) === true,
            'cfm_source' => is_string($filter['cfm_source'] ?? null) ? $filter['cfm_source'] : null,
            'key_length_bytes' => $keyLengthBytes,
            'minimum_key_length_bytes' => $keyLengthReview['minimum_key_length_bytes'],
            'maximum_key_length_bytes' => $keyLengthReview['maximum_key_length_bytes'],
            'key_length_valid' => $keyLengthReview['valid'],
            'key_length_status' => $keyLengthReview['status'],
            'key_length_fail_closed' => $keyLengthReview['fail_closed'],
            'content_encrypted' => !$identity,
            'identity_crypt_filter' => $identity,
            'status' => $status,
            'crypt_filter_parameter_declaration_review' => $parameterDeclarationReview,
            'crypt_filter_parameter_declaration_status' => is_string($filter['parameter_declaration_status'] ?? null)
                ? $filter['parameter_declaration_status']
                : null,
            'crypt_filter_parameter_fail_closed' => (bool) ($filter['parameter_declaration_fail_closed'] ?? false),
            'crypt_filter_parameter_duplicate_names' => is_array($filter['duplicate_parameter_names'] ?? null)
                ? $filter['duplicate_parameter_names']
                : [],
            'crypt_filter_parameter_duplicate_count' => (int) ($filter['duplicate_parameter_count'] ?? 0),
        ], $generationReview);
    }

    private function cryptFilterMethodStatus(?string $method): string
    {
        if ($method === 'Identity' || $method === 'None') {
            return 'identity_crypt_filter';
        }
        if (in_array($method, ['V2', 'AESV2', 'AESV3'], true)) {
            return 'encrypted_crypt_filter';
        }
        if ($method === null || $method === '') {
            return 'unknown_crypt_filter_method_fail_closed';
        }

        return 'unsupported_crypt_filter_method_fail_closed';
    }

    /**
     * @param array<string, mixed> $roleDeclarationReview
     * @return array<string, array<string, mixed>>
     */
    private function cryptFilterRoleDeclarationsByRole(array $roleDeclarationReview): array
    {
        $declarations = [];
        $roles = is_array($roleDeclarationReview['roles'] ?? null) ? $roleDeclarationReview['roles'] : [];
        foreach ($roles as $role) {
            if (!is_array($role) || !is_string($role['role'] ?? null)) {
                continue;
            }
            $declarations[$role['role']] = $role;
        }

        return $declarations;
    }

    /**
     * @param array<string, mixed> $declaration
     * @return array<string, mixed>
     */
    private function cryptFilterRoleDeclarationContentFields(array $declaration): array
    {
        return [
            'role_declaration_status' => is_string($declaration['status'] ?? null) ? $declaration['status'] : null,
            'role_declaration_declared' => (bool) ($declaration['declared'] ?? false),
            'role_declaration_declared_entry_count' => (int) ($declaration['declared_entry_count'] ?? 0),
            'role_declaration_duplicate_entries' => (bool) ($declaration['duplicate_entries'] ?? false),
            'role_declaration_malformed_entry_count' => (int) ($declaration['malformed_entry_count'] ?? 0),
            'role_declaration_defaulted' => (bool) ($declaration['defaulted'] ?? false),
            'role_declaration_source_policy' => is_string($declaration['source_policy'] ?? null)
                ? $declaration['source_policy']
                : null,
            'role_declaration_selected_filter_name' => is_string($declaration['selected_filter_name'] ?? null)
                ? $declaration['selected_filter_name']
                : null,
            'role_declaration_entry_filter_names' => is_array($declaration['entry_filter_names'] ?? null)
                ? $declaration['entry_filter_names']
                : [],
            'role_declaration_entry_statuses' => is_array($declaration['entry_statuses'] ?? null)
                ? $declaration['entry_statuses']
                : [],
            'role_declaration_fail_closed' => (bool) ($declaration['fail_closed'] ?? false),
        ];
    }

    /**
     * @param array<string, mixed> $declaration
     * @return array<string, mixed>
     */
    private function cryptFilterDictionaryContentFields(array $declaration): array
    {
        return [
            'crypt_filter_dictionary_declaration_status' => is_string($declaration['status'] ?? null)
                ? $declaration['status']
                : null,
            'crypt_filter_dictionary_declared_entry_count' => (int) ($declaration['declared_entry_count'] ?? 0),
            'crypt_filter_dictionary_resolved_entry_count' => (int) ($declaration['resolved_dictionary_entry_count'] ?? 0),
            'crypt_filter_dictionary_duplicate_entries' => (bool) ($declaration['duplicate_entries'] ?? false),
            'crypt_filter_dictionary_malformed_entry_count' => (int) ($declaration['malformed_entry_count'] ?? 0),
            'crypt_filter_dictionary_selected_entry_index' => $declaration['selected_entry_index'] ?? null,
            'crypt_filter_dictionary_selected_filter_names' => is_array($declaration['selected_filter_names'] ?? null)
                ? $declaration['selected_filter_names']
                : [],
            'crypt_filter_dictionary_entry_statuses' => is_array($declaration['entry_statuses'] ?? null)
                ? $declaration['entry_statuses']
                : [],
            'crypt_filter_dictionary_fail_closed' => (bool) ($declaration['fail_closed'] ?? false),
        ];
    }

    private function cryptFilterAuthEventStatus(?string $authEvent, string $role, bool $identity): string
    {
        if ($identity) {
            return 'identity_filter_no_authorization_event_required';
        }
        if ($authEvent === null || $authEvent === '') {
            return 'authorization_event_unavailable_review';
        }
        if ($authEvent === 'DocOpen') {
            return 'document_open_authorization';
        }
        if ($authEvent === 'EFOpen') {
            return $role === 'embedded_file_streams'
                ? 'embedded_file_open_authorization'
                : 'embedded_file_auth_event_on_document_content_review';
        }

        return 'unknown_authorization_event_review';
    }

    private function cryptFilterAuthEventAppliesToRole(string $status): ?bool
    {
        return match ($status) {
            'document_open_authorization',
            'embedded_file_open_authorization',
            'identity_filter_no_authorization_event_required' => true,
            'embedded_file_auth_event_on_document_content_review',
            'unknown_authorization_event_review' => false,
            default => null,
        };
    }

    /**
     * @return array{valid: bool|null, status: string, fail_closed: bool, minimum_key_length_bytes: int|null, maximum_key_length_bytes: int|null}
     */
    private function cryptFilterKeyLengthReview(?string $method, ?int $keyLengthBytes): array
    {
        if ($method === 'Identity' || $method === 'None') {
            return [
                'valid' => null,
                'status' => $keyLengthBytes === null
                    ? 'identity_filter_no_key_length_required'
                    : 'identity_filter_key_length_ignored',
                'fail_closed' => false,
                'minimum_key_length_bytes' => null,
                'maximum_key_length_bytes' => null,
            ];
        }

        $range = match ($method) {
            'V2', 'AESV2' => ['minimum' => 5, 'maximum' => 16],
            'AESV3' => ['minimum' => 32, 'maximum' => 32],
            default => null,
        };

        if ($range === null) {
            return [
                'valid' => null,
                'status' => 'crypt_filter_key_length_not_reviewed_for_method',
                'fail_closed' => false,
                'minimum_key_length_bytes' => null,
                'maximum_key_length_bytes' => null,
            ];
        }

        if ($keyLengthBytes === null) {
            return [
                'valid' => null,
                'status' => 'crypt_filter_key_length_default_or_unavailable_review',
                'fail_closed' => false,
                'minimum_key_length_bytes' => $range['minimum'],
                'maximum_key_length_bytes' => $range['maximum'],
            ];
        }

        $valid = $keyLengthBytes >= $range['minimum'] && $keyLengthBytes <= $range['maximum'];

        return [
            'valid' => $valid,
            'status' => $valid
                ? 'crypt_filter_key_length_supported'
                : 'invalid_crypt_filter_key_length_review',
            'fail_closed' => !$valid,
            'minimum_key_length_bytes' => $range['minimum'],
            'maximum_key_length_bytes' => $range['maximum'],
        ];
    }

    /**
     * @return array{method_compatible_with_standard_handler: bool|null, method_generation_status: string, method_generation_fail_closed: bool}
     */
    private function cryptFilterMethodGenerationReview(?string $handler, ?int $version, ?int $revision, ?string $method): array
    {
        if ($handler !== 'Standard') {
            return [
                'method_compatible_with_standard_handler' => null,
                'method_generation_status' => 'standard_handler_method_generation_not_applicable',
                'method_generation_fail_closed' => false,
            ];
        }

        if ($method === null || $method === '') {
            return [
                'method_compatible_with_standard_handler' => null,
                'method_generation_status' => 'crypt_filter_method_generation_unavailable_review',
                'method_generation_fail_closed' => false,
            ];
        }

        if (in_array($method, ['Identity', 'None'], true)) {
            return [
                'method_compatible_with_standard_handler' => true,
                'method_generation_status' => 'identity_crypt_filter_method_generation_compatible',
                'method_generation_fail_closed' => false,
            ];
        }

        if ($version === 5 || ($revision !== null && $revision >= 5)) {
            $compatible = $method === 'AESV3';
            return [
                'method_compatible_with_standard_handler' => in_array($method, ['V2', 'AESV2', 'AESV3'], true) ? $compatible : null,
                'method_generation_status' => $compatible
                    ? 'standard_aes256_crypt_filter_method_compatible'
                    : (in_array($method, ['V2', 'AESV2'], true)
                        ? 'standard_aes256_requires_aesv3_crypt_filter_review'
                        : 'standard_aes256_crypt_filter_method_not_reviewed'),
                'method_generation_fail_closed' => in_array($method, ['V2', 'AESV2'], true),
            ];
        }

        if ($version === 4 || $revision === 4) {
            $compatible = in_array($method, ['V2', 'AESV2'], true);
            return [
                'method_compatible_with_standard_handler' => in_array($method, ['V2', 'AESV2', 'AESV3'], true) ? $compatible : null,
                'method_generation_status' => $compatible
                    ? 'standard_revision4_crypt_filter_method_compatible'
                    : ($method === 'AESV3'
                        ? 'standard_revision4_disallows_aesv3_crypt_filter_review'
                        : 'standard_revision4_crypt_filter_method_not_reviewed'),
                'method_generation_fail_closed' => $method === 'AESV3',
            ];
        }

        return [
            'method_compatible_with_standard_handler' => null,
            'method_generation_status' => 'standard_crypt_filter_method_generation_not_reviewed',
            'method_generation_fail_closed' => false,
        ];
    }

    /**
     * @param list<array<string, mixed>> $roles
     * @return list<string>
     */
    private function cryptFilterRoleNamesByStatus(array $roles, string $status): array
    {
        return $this->cryptFilterRoleNamesByStatuses($roles, [$status]);
    }

    /**
     * @param list<array<string, mixed>> $roles
     * @param list<string> $statuses
     * @return list<string>
     */
    private function cryptFilterRoleNamesByStatuses(array $roles, array $statuses): array
    {
        $names = [];
        foreach ($roles as $role) {
            if (
                is_string($role['role'] ?? null)
                && in_array($role['status'] ?? null, $statuses, true)
                && !in_array($role['role'], $names, true)
            ) {
                $names[] = $role['role'];
            }
        }

        return $names;
    }

    /**
     * @param list<array<string, mixed>> $roles
     * @return list<string>
     */
    private function cryptFilterNamesByStatus(array $roles, string $status): array
    {
        $names = [];
        foreach ($roles as $role) {
            if (
                is_string($role['filter_name'] ?? null)
                && ($role['status'] ?? null) === $status
                && !in_array($role['filter_name'], $names, true)
            ) {
                $names[] = $role['filter_name'];
            }
        }

        return $names;
    }

    /**
     * @param list<array<string, mixed>> $roles
     * @return list<string>
     */
    private function cryptFilterRoleDeclarationNames(array $roles, string $key, bool $duplicatesOnly): array
    {
        $names = [];
        foreach ($roles as $role) {
            $flag = $duplicatesOnly ? 'role_declaration_duplicate_entries' : 'role_declaration_fail_closed';
            if (($role[$flag] ?? false) !== true || !is_string($role[$key] ?? null)) {
                continue;
            }
            if (!in_array($role[$key], $names, true)) {
                $names[] = $role[$key];
            }
        }

        return $names;
    }

    /**
     * @param list<array<string, mixed>> $roles
     * @return list<string>
     */
    private function cryptFilterParameterDuplicateRoleNames(array $roles): array
    {
        $names = [];
        foreach ($roles as $role) {
            if (($role['crypt_filter_parameter_fail_closed'] ?? false) !== true || !is_string($role['role'] ?? null)) {
                continue;
            }
            if (!in_array($role['role'], $names, true)) {
                $names[] = $role['role'];
            }
        }

        return $names;
    }

    /**
     * @param list<array<string, mixed>> $roles
     * @return list<string>
     */
    private function cryptFilterParameterDuplicateFilterNames(array $roles): array
    {
        $names = [];
        foreach ($roles as $role) {
            if (($role['crypt_filter_parameter_fail_closed'] ?? false) !== true || !is_string($role['filter_name'] ?? null)) {
                continue;
            }
            if (!in_array($role['filter_name'], $names, true)) {
                $names[] = $role['filter_name'];
            }
        }

        return $names;
    }

    /**
     * @param list<array<string, mixed>> $roles
     * @return list<string>
     */
    private function cryptFilterParameterDuplicateNames(array $roles): array
    {
        $names = [];
        foreach ($roles as $role) {
            $duplicates = is_array($role['crypt_filter_parameter_duplicate_names'] ?? null)
                ? $role['crypt_filter_parameter_duplicate_names']
                : [];
            foreach ($duplicates as $duplicate) {
                if (is_string($duplicate) && !in_array($duplicate, $names, true)) {
                    $names[] = $duplicate;
                }
            }
        }

        return $names;
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function cryptFilterTextContentPolicy(array $rows): string
    {
        if ($rows === []) {
            return 'encrypted_document_fail_closed';
        }
        foreach ($rows as $row) {
            if ($this->cryptFilterRoleFailsClosed($row)) {
                return $this->cryptFilterFailClosedPolicy($row);
            }
        }
        foreach ($rows as $row) {
            if (($row['identity_crypt_filter'] ?? false) !== true) {
                return 'review_only_encrypted_document_boundary';
            }
        }

        return 'identity_filters_review_only_encrypted_document_boundary';
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function cryptFilterEmbeddedFilePolicy(array $rows): string
    {
        if ($rows === []) {
            return 'encrypted_document_fail_closed';
        }

        $row = $rows[0];
        if ($this->cryptFilterRoleFailsClosed($row)) {
            return $this->cryptFilterFailClosedPolicy($row);
        }
        if (($row['identity_crypt_filter'] ?? false) === true) {
            return 'identity_filter_review_only_payload_boundary';
        }

        return 'encrypted_filter_requires_decryption';
    }

    /**
     * @param array<string, mixed> $row
     */
    private function cryptFilterRoleFailsClosed(array $row): bool
    {
        if (($row['crypt_filter_dictionary_fail_closed'] ?? false) === true) {
            return true;
        }
        if (($row['role_declaration_fail_closed'] ?? false) === true) {
            return true;
        }
        if (($row['crypt_filter_parameter_fail_closed'] ?? false) === true) {
            return true;
        }
        if (in_array($row['status'] ?? null, [
            'undeclared_crypt_filter_fail_closed',
            'missing_declared_crypt_filter',
            'unknown_crypt_filter_method_fail_closed',
            'unsupported_crypt_filter_method_fail_closed',
        ], true)) {
            return true;
        }
        if (($row['key_length_fail_closed'] ?? false) === true) {
            return true;
        }
        if (($row['method_generation_fail_closed'] ?? false) === true) {
            return true;
        }

        return ($row['auth_event_applies_to_role'] ?? null) === false;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function cryptFilterFailClosedPolicy(array $row): string
    {
        $status = is_string($row['status'] ?? null) ? $row['status'] : null;
        $authEventStatus = is_string($row['auth_event_status'] ?? null) ? $row['auth_event_status'] : null;
        $dictionaryStatus = is_string($row['crypt_filter_dictionary_declaration_status'] ?? null)
            ? $row['crypt_filter_dictionary_declaration_status']
            : null;
        $roleDeclarationStatus = is_string($row['role_declaration_status'] ?? null)
            ? $row['role_declaration_status']
            : null;

        if (($row['crypt_filter_dictionary_fail_closed'] ?? false) === true) {
            return match ($dictionaryStatus) {
                'duplicate_crypt_filter_dictionary_entries_review' => 'duplicate_crypt_filter_dictionary_entries_fail_closed',
                'malformed_crypt_filter_dictionary_entry_review' => 'malformed_crypt_filter_dictionary_entry_fail_closed',
                default => 'malformed_crypt_filter_dictionary_entry_fail_closed',
            };
        }

        if (($row['role_declaration_fail_closed'] ?? false) === true) {
            return match ($roleDeclarationStatus) {
                'duplicate_crypt_filter_role_entries_review' => 'duplicate_crypt_filter_role_entries_fail_closed',
                'malformed_crypt_filter_role_entry_review' => 'malformed_crypt_filter_role_entry_fail_closed',
                default => 'malformed_crypt_filter_role_entry_fail_closed',
            };
        }

        if (($row['crypt_filter_parameter_fail_closed'] ?? false) === true) {
            return match ($row['crypt_filter_parameter_declaration_status'] ?? null) {
                'duplicate_crypt_filter_parameter_entries_review' => 'duplicate_crypt_filter_parameter_entries_fail_closed',
                default => 'malformed_crypt_filter_parameter_entry_fail_closed',
            };
        }

        $statusPolicy = match ($status) {
            'missing_declared_crypt_filter' => 'missing_declared_filter_fail_closed',
            'undeclared_crypt_filter_fail_closed' => 'undeclared_crypt_filter_fail_closed',
            'unknown_crypt_filter_method_fail_closed' => 'unknown_crypt_filter_method_fail_closed',
            'unsupported_crypt_filter_method_fail_closed' => 'unsupported_crypt_filter_method_fail_closed',
            default => null,
        };
        if ($statusPolicy !== null) {
            return $statusPolicy;
        }
        if (($row['key_length_fail_closed'] ?? false) === true) {
            return 'invalid_crypt_filter_key_length_fail_closed';
        }
        if (($row['method_generation_fail_closed'] ?? false) === true) {
            return 'crypt_filter_method_generation_mismatch_fail_closed';
        }

        return match ($authEventStatus) {
            'embedded_file_auth_event_on_document_content_review' => 'authorization_event_role_mismatch_fail_closed',
            'unknown_authorization_event_review' => 'unknown_authorization_event_fail_closed',
            'authorization_event_unavailable_review' => 'authorization_event_unavailable_fail_closed',
            default => 'encrypted_document_fail_closed',
        };
    }

    private function cryptFilterContentExtractionBoundary(?string $textPolicy): ?string
    {
        return match ($textPolicy) {
            'duplicate_crypt_filter_dictionary_entries_fail_closed' => 'blocked_by_duplicate_document_crypt_filter_dictionary',
            'malformed_crypt_filter_dictionary_entry_fail_closed' => 'blocked_by_malformed_document_crypt_filter_dictionary',
            'missing_declared_filter_fail_closed' => 'blocked_by_missing_document_crypt_filter',
            'undeclared_crypt_filter_fail_closed' => 'blocked_by_undeclared_document_crypt_filter',
            'unknown_crypt_filter_method_fail_closed' => 'blocked_by_unknown_document_crypt_filter_method',
            'unsupported_crypt_filter_method_fail_closed' => 'blocked_by_unsupported_document_crypt_filter_method',
            'invalid_crypt_filter_key_length_fail_closed' => 'blocked_by_invalid_document_crypt_filter_key_length',
            'crypt_filter_method_generation_mismatch_fail_closed' => 'blocked_by_incompatible_document_crypt_filter_method',
            'duplicate_crypt_filter_role_entries_fail_closed' => 'blocked_by_duplicate_document_crypt_filter_roles',
            'malformed_crypt_filter_role_entry_fail_closed' => 'blocked_by_malformed_document_crypt_filter_role',
            'duplicate_crypt_filter_parameter_entries_fail_closed' => 'blocked_by_duplicate_document_crypt_filter_parameters',
            'malformed_crypt_filter_parameter_entry_fail_closed' => 'blocked_by_malformed_document_crypt_filter_parameter',
            'authorization_event_role_mismatch_fail_closed' => 'blocked_by_document_crypt_filter_auth_event_mismatch',
            'unknown_authorization_event_fail_closed' => 'blocked_by_unknown_document_crypt_filter_auth_event',
            'authorization_event_unavailable_fail_closed' => 'blocked_by_unavailable_document_crypt_filter_auth_event',
            'encrypted_document_fail_closed' => 'blocked_by_unresolved_document_crypt_filter',
            default => null,
        };
    }

    private function cryptFilterEmbeddedFileBoundary(?string $payloadPolicy): ?string
    {
        return match ($payloadPolicy) {
            'duplicate_crypt_filter_dictionary_entries_fail_closed' => 'blocked_by_duplicate_embedded_file_crypt_filter_dictionary',
            'malformed_crypt_filter_dictionary_entry_fail_closed' => 'blocked_by_malformed_embedded_file_crypt_filter_dictionary',
            'missing_declared_filter_fail_closed' => 'blocked_by_missing_embedded_file_crypt_filter',
            'undeclared_crypt_filter_fail_closed' => 'blocked_by_undeclared_embedded_file_crypt_filter',
            'unknown_crypt_filter_method_fail_closed' => 'blocked_by_unknown_embedded_file_crypt_filter_method',
            'unsupported_crypt_filter_method_fail_closed' => 'blocked_by_unsupported_embedded_file_crypt_filter_method',
            'invalid_crypt_filter_key_length_fail_closed' => 'blocked_by_invalid_embedded_file_crypt_filter_key_length',
            'crypt_filter_method_generation_mismatch_fail_closed' => 'blocked_by_incompatible_embedded_file_crypt_filter_method',
            'duplicate_crypt_filter_role_entries_fail_closed' => 'blocked_by_duplicate_embedded_file_crypt_filter_roles',
            'malformed_crypt_filter_role_entry_fail_closed' => 'blocked_by_malformed_embedded_file_crypt_filter_role',
            'duplicate_crypt_filter_parameter_entries_fail_closed' => 'blocked_by_duplicate_embedded_file_crypt_filter_parameters',
            'malformed_crypt_filter_parameter_entry_fail_closed' => 'blocked_by_malformed_embedded_file_crypt_filter_parameter',
            'authorization_event_role_mismatch_fail_closed' => 'blocked_by_embedded_file_crypt_filter_auth_event_mismatch',
            'unknown_authorization_event_fail_closed' => 'blocked_by_unknown_embedded_file_crypt_filter_auth_event',
            'authorization_event_unavailable_fail_closed' => 'blocked_by_unavailable_embedded_file_crypt_filter_auth_event',
            'encrypted_document_fail_closed' => 'blocked_by_unresolved_embedded_file_crypt_filter',
            default => null,
        };
    }

    /**
     * @param list<array<string, mixed>> $roles
     */
    private function cryptFilterFailClosedRoleCount(array $roles): int
    {
        return count(array_filter(
            $roles,
            fn (array $row): bool => $this->cryptFilterRoleFailsClosed($row)
        ));
    }

    /**
     * @param list<array<string, mixed>> $roles
     * @return list<string>
     */
    private function cryptFilterFailClosedRoleNames(array $roles): array
    {
        $names = [];
        foreach ($roles as $role) {
            if (
                $this->cryptFilterRoleFailsClosed($role)
                && is_string($role['role'] ?? null)
                && !in_array($role['role'], $names, true)
            ) {
                $names[] = $role['role'];
            }
        }

        return $names;
    }

    /**
     * @param list<array<string, mixed>> $roles
     * @return list<string>
     */
    private function cryptFilterFailClosedFilterNames(array $roles): array
    {
        $names = [];
        foreach ($roles as $role) {
            if (
                $this->cryptFilterRoleFailsClosed($role)
                && is_string($role['filter_name'] ?? null)
                && !in_array($role['filter_name'], $names, true)
            ) {
                $names[] = $role['filter_name'];
            }
        }

        return $names;
    }

    /**
     * @param list<array<string, mixed>> $roles
     * @return list<string>
     */
    private function cryptFilterAuthEventDefaultedRoleNames(array $roles): array
    {
        $names = [];
        foreach ($roles as $role) {
            if (
                ($role['auth_event_defaulted'] ?? false) === true
                && is_string($role['role'] ?? null)
                && !in_array($role['role'], $names, true)
            ) {
                $names[] = $role['role'];
            }
        }

        return $names;
    }

    /**
     * @param list<array<string, mixed>> $roles
     * @return list<string>
     */
    private function cryptFilterAuthEventDefaultedFilterNames(array $roles): array
    {
        $names = [];
        foreach ($roles as $role) {
            if (
                ($role['auth_event_defaulted'] ?? false) === true
                && is_string($role['filter_name'] ?? null)
                && !in_array($role['filter_name'], $names, true)
            ) {
                $names[] = $role['filter_name'];
            }
        }

        return $names;
    }

    /**
     * @param list<array<string, mixed>> $roles
     * @return list<string>
     */
    private function cryptFilterAuthEventMismatchRoleNames(array $roles): array
    {
        $names = [];
        foreach ($roles as $role) {
            if (
                ($role['auth_event_applies_to_role'] ?? null) === false
                && is_string($role['role'] ?? null)
                && !in_array($role['role'], $names, true)
            ) {
                $names[] = $role['role'];
            }
        }

        return $names;
    }

    /**
     * @param list<array<string, mixed>> $roles
     * @return list<string>
     */
    private function cryptFilterAuthEventMismatchFilterNames(array $roles): array
    {
        $names = [];
        foreach ($roles as $role) {
            if (
                ($role['auth_event_applies_to_role'] ?? null) === false
                && is_string($role['filter_name'] ?? null)
                && !in_array($role['filter_name'], $names, true)
            ) {
                $names[] = $role['filter_name'];
            }
        }

        return $names;
    }

    /**
     * @param list<array<string, mixed>> $roles
     * @return list<string>
     */
    private function cryptFilterCfmDefaultedRoleNames(array $roles): array
    {
        $names = [];
        foreach ($roles as $role) {
            if (
                ($role['cfm_defaulted'] ?? false) === true
                && is_string($role['role'] ?? null)
                && !in_array($role['role'], $names, true)
            ) {
                $names[] = $role['role'];
            }
        }

        return $names;
    }

    /**
     * @param list<array<string, mixed>> $roles
     * @return list<string>
     */
    private function cryptFilterCfmDefaultedFilterNames(array $roles): array
    {
        $names = [];
        foreach ($roles as $role) {
            if (
                ($role['cfm_defaulted'] ?? false) === true
                && is_string($role['filter_name'] ?? null)
                && !in_array($role['filter_name'], $names, true)
            ) {
                $names[] = $role['filter_name'];
            }
        }

        return $names;
    }

    /**
     * @param list<array<string, mixed>> $roles
     * @return list<string>
     */
    private function cryptFilterKeyLengthInvalidRoleNames(array $roles): array
    {
        $names = [];
        foreach ($roles as $role) {
            if (
                ($role['key_length_fail_closed'] ?? false) === true
                && is_string($role['role'] ?? null)
                && !in_array($role['role'], $names, true)
            ) {
                $names[] = $role['role'];
            }
        }

        return $names;
    }

    /**
     * @param list<array<string, mixed>> $roles
     * @return list<string>
     */
    private function cryptFilterKeyLengthInvalidFilterNames(array $roles): array
    {
        $names = [];
        foreach ($roles as $role) {
            if (
                ($role['key_length_fail_closed'] ?? false) === true
                && is_string($role['filter_name'] ?? null)
                && !in_array($role['filter_name'], $names, true)
            ) {
                $names[] = $role['filter_name'];
            }
        }

        return $names;
    }

    /**
     * @param list<array<string, mixed>> $roles
     * @return list<string>
     */
    private function cryptFilterMethodGenerationFailClosedRoleNames(array $roles): array
    {
        $names = [];
        foreach ($roles as $role) {
            if (
                ($role['method_generation_fail_closed'] ?? false) === true
                && is_string($role['role'] ?? null)
                && !in_array($role['role'], $names, true)
            ) {
                $names[] = $role['role'];
            }
        }

        return $names;
    }

    /**
     * @param list<array<string, mixed>> $roles
     * @return list<string>
     */
    private function cryptFilterMethodGenerationFailClosedFilterNames(array $roles): array
    {
        $names = [];
        foreach ($roles as $role) {
            if (
                ($role['method_generation_fail_closed'] ?? false) === true
                && is_string($role['filter_name'] ?? null)
                && !in_array($role['filter_name'], $names, true)
            ) {
                $names[] = $role['filter_name'];
            }
        }

        return $names;
    }

    /**
     * @param array<string, mixed> $encryption
     * @param array<string, mixed> $permissions
     * @return array<string, mixed>
     */
    private function permissionHandlerReview(array $encryption, array $permissions, bool $declared): array
    {
        $handler = is_string($encryption['filter'] ?? null) ? $encryption['filter'] : null;
        $standardHandler = $handler === 'Standard';
        $wellFormed = array_key_exists('reserved_bits_valid', $permissions)
            ? (bool) $permissions['reserved_bits_valid']
            : null;
        $reservedBits = is_array($permissions['reserved_bits'] ?? null) ? $permissions['reserved_bits'] : [];
        $permissionWordReview = is_array($encryption['standard_permission_word_review'] ?? null)
            ? $encryption['standard_permission_word_review']
            : [];
        $permissionWordDuplicateEntries = ($permissionWordReview['duplicate_permission_entries'] ?? false) === true;
        $permissionWordAmbiguous = ($permissionWordReview['permission_word_ambiguous'] ?? false) === true;
        $permissionWordRangeValid = array_key_exists('permission_word_range_valid', $permissions)
            ? (bool) $permissions['permission_word_range_valid']
            : null;
        $standardParameterReview = is_array($encryption['standard_security_handler_parameter_review'] ?? null)
            ? $encryption['standard_security_handler_parameter_review']
            : [];
        $standardParametersMalformed = ($standardParameterReview['parameters_well_formed'] ?? null) === false;
        $publicKeyRecipientReview = is_array($encryption['public_key_recipient_review'] ?? null)
            ? $encryption['public_key_recipient_review']
            : [];
        $publicKeyRecipientCount = (int) ($publicKeyRecipientReview['recipient_count'] ?? 0);
        $selectedPublicKeyRecipientCount = (int) ($publicKeyRecipientReview['selected_recipient_count'] ?? 0);
        $recipientPermissionsDeclared = $publicKeyRecipientCount > 0;
        $standardAuthenticationReview = is_array($encryption['standard_authentication_review'] ?? null)
            ? $encryption['standard_authentication_review']
            : [];
        $permissionDigest = is_array($standardAuthenticationReview['permission_digest'] ?? null)
            ? $standardAuthenticationReview['permission_digest']
            : [];
        if (!$declared && $recipientPermissionsDeclared) {
            $status = 'public_key_recipient_permissions_undecoded_review';
            $reviewWellFormed = null;
        } elseif ($standardHandler && $standardParametersMalformed) {
            $status = 'malformed_standard_security_handler_parameters_review';
            $reviewWellFormed = false;
        } elseif (!$declared) {
            $status = 'permissions_unavailable_review';
            $reviewWellFormed = null;
        } elseif (!$standardHandler) {
            $status = 'unsupported_security_handler_permissions_review';
            $reviewWellFormed = null;
        } elseif ($permissionWordDuplicateEntries) {
            $status = 'duplicate_standard_permission_entries_review';
            $reviewWellFormed = false;
        } elseif ($permissionWordRangeValid === false) {
            $status = 'permission_word_out_of_range_review';
            $reviewWellFormed = false;
        } elseif ($permissionWordAmbiguous && $permissions === []) {
            $status = 'malformed_standard_permission_word_review';
            $reviewWellFormed = false;
        } elseif ($wellFormed !== true) {
            $status = 'malformed_reserved_bits_review';
            $reviewWellFormed = false;
        } else {
            $status = 'well_formed_standard_permissions';
            $reviewWellFormed = true;
        }
        $permissionBitsTrusted = $standardHandler
            && $declared
            && !$standardParametersMalformed
            && $reviewWellFormed === true
            && $permissionWordRangeValid !== false;
        $permissionBits = $permissionBitsTrusted && is_array($permissions['permission_bits'] ?? null)
            ? $permissions['permission_bits']
            : [];

        return [
            'source' => 'permission_handler_review',
            'handler' => $handler,
            'subfilter' => $encryption['subfilter'] ?? null,
            'revision' => $encryption['revision'] ?? null,
            'revision_label' => $encryption['revision_label'] ?? null,
            'standard_handler' => $standardHandler,
            'permissions_declared' => $declared || $recipientPermissionsDeclared,
            'standard_permissions_declared' => $declared,
            'recipient_permissions_declared' => $recipientPermissionsDeclared,
            'permission_hex' => $permissions['hex'] ?? null,
            'permission_signed' => $permissions['signed'] ?? null,
            'permission_unsigned' => $permissions['unsigned'] ?? null,
            'permission_word_form' => $permissions['declared_form'] ?? null,
            'permission_normalized_from_unsigned_decimal' => (bool) ($permissions['normalized_from_unsigned_decimal'] ?? false),
            'permission_word_range_valid' => $permissionWordRangeValid,
            'permission_word_range_status' => $permissions['permission_word_range_status'] ?? null,
            'permission_word_range' => is_array($permissions['word_range'] ?? null) ? $permissions['word_range'] : [],
            'handler_supported_for_native_permission_review' => $standardHandler && $declared,
            'permission_word_well_formed' => $reviewWellFormed,
            'permission_word_status' => $status,
            'standard_permission_word_review' => $permissionWordReview,
            'permission_word_duplicate_entries' => $permissionWordDuplicateEntries,
            'permission_word_ambiguous' => $permissionWordAmbiguous,
            'standard_security_handler_parameter_review' => $standardParameterReview,
            'standard_security_handler_parameters_well_formed' => $standardParameterReview['parameters_well_formed'] ?? null,
            'standard_security_handler_parameter_status' => $standardParameterReview['status'] ?? null,
            'standard_security_handler_version_supported' => $standardParameterReview['version_supported'] ?? null,
            'standard_security_handler_revision_supported' => $standardParameterReview['revision_supported'] ?? null,
            'standard_security_handler_version_revision_compatible' => $standardParameterReview['version_revision_compatible'] ?? null,
            'standard_security_handler_key_length_status' => $standardParameterReview['key_length_status'] ?? null,
            'standard_security_handler_key_length_explicit' => $standardParameterReview['key_length_explicit'] ?? null,
            'standard_security_handler_key_length_defaulted' => $standardParameterReview['key_length_defaulted'] ?? null,
            'standard_security_handler_key_length_source' => $standardParameterReview['key_length_source'] ?? null,
            'standard_security_handler_parameter_violations' => is_array($standardParameterReview['violations'] ?? null)
                ? $standardParameterReview['violations']
                : [],
            'standard_security_handler_parameter_declaration_review' => is_array($standardParameterReview['parameter_declaration_review'] ?? null)
                ? $standardParameterReview['parameter_declaration_review']
                : [],
            'standard_security_handler_duplicate_parameter_names' => is_array($standardParameterReview['duplicate_parameter_names'] ?? null)
                ? $standardParameterReview['duplicate_parameter_names']
                : [],
            'standard_security_handler_duplicate_parameter_count' => (int) ($standardParameterReview['duplicate_parameter_count'] ?? 0),
            'applicable_permission_names' => $permissionBitsTrusted && is_array($permissions['applicable_permission_names'] ?? null)
                ? $permissions['applicable_permission_names']
                : [],
            'not_applicable_permission_names' => $permissionBitsTrusted && is_array($permissions['not_applicable_permission_names'] ?? null)
                ? $permissions['not_applicable_permission_names']
                : [],
            'permission_bit_review_count' => count($permissionBits),
            'permission_bit_statuses' => $permissionBitsTrusted && is_array($permissions['permission_bit_statuses'] ?? null)
                ? $permissions['permission_bit_statuses']
                : [],
            'permission_bits' => $permissionBits,
            'reserved_bits' => $reservedBits,
            'standard_authentication_present' => $standardAuthenticationReview !== [],
            'standard_authentication_revision' => $standardAuthenticationReview['revision'] ?? null,
            'standard_authentication_auth_events' => $standardAuthenticationReview['auth_events'] ?? [],
            'standard_authentication_credential_entries' => $standardAuthenticationReview['credential_entries_present'] ?? [],
            'standard_permission_digest_present' => (bool) ($permissionDigest['present'] ?? false),
            'standard_permission_digest_status' => $permissionDigest['status'] ?? null,
            'password_validation_performed' => false,
            'permissions_authenticated' => false,
            'public_key_recipient_count' => $publicKeyRecipientCount,
            'selected_public_key_recipient_count' => $selectedPublicKeyRecipientCount,
            'public_key_recipient_permission_decode_status' => $publicKeyRecipientReview['permission_decode_status'] ?? null,
            'public_key_recipient_source_policy' => $publicKeyRecipientReview['recipient_source_policy'] ?? null,
            'public_key_selected_crypt_filter_recipient_filter_names' => $publicKeyRecipientReview['selected_crypt_filter_recipient_filter_names'] ?? [],
            'public_key_unselected_crypt_filter_recipient_filter_names' => $publicKeyRecipientReview['unselected_crypt_filter_recipient_filter_names'] ?? [],
            'status' => $status,
            'review_only' => true,
            'decryption_performed' => false,
            'executes_decryption' => false,
            'executes_permission_enforcement' => false,
            'executes_cms_parse' => false,
            'recipient_bytes_exposed' => false,
        ];
    }

    /**
     * @param list<array<string, mixed>> $fields
     * @param array<string, mixed> $documentSecurityStore
     * @return list<array<string, mixed>>
     */
    private function signatureReviews(array $fields, string $pdfBytes, array $documentSecurityStore): array
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
            $referenceTransforms = $this->signatureReferenceTransforms($signature);
            $byteRangeBoundary = $this->byteRangeBoundary($byteRange, $pdfBytes);
            $contentsDigest = is_array($signature['contents_digest'] ?? null)
                ? $signature['contents_digest']
                : $this->emptySignatureContentsDigest();
            $securityReview = $this->signatureSecurityReview(
                $field,
                $signature,
                $state,
                $byteRangeBoundary,
                $referenceTransforms,
                $contentsDigest,
                $documentSecurityStore
            );

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
                'contents_digest' => $contentsDigest,
                'byte_range' => $byteRangeBoundary,
                'reference_transform_count' => count($referenceTransforms),
                'reference_transform_methods' => $this->transformMethods($referenceTransforms),
                'reference_transforms' => $referenceTransforms,
                'seed_required_constraints' => $seed['required_constraints'] ?? [],
                'lock_action' => $lock['action'] ?? null,
                'lock_field_names' => $lock['field_names'] ?? [],
                'lock_permission_label' => $lock['permission_label'] ?? null,
                'cryptographic_signature_validated' => false,
                'executes_signature_validation' => false,
                'executes_signing' => false,
                'executes_action' => false,
                'signature_security_review' => $securityReview,
            ];
        }

        return $reviews;
    }

    /**
     * @param list<array<string, mixed>> $signatures
     */
    private function signatureByteRangeCount(array $signatures): int
    {
        return count(array_filter(
            $signatures,
            static fn (array $signature): bool => ($signature['byte_range']['present'] ?? false) === true
        ));
    }

    /**
     * @param list<array<string, mixed>> $signatures
     */
    private function validSignatureByteRangeCount(array $signatures): int
    {
        return count(array_filter(
            $signatures,
            static fn (array $signature): bool => ($signature['byte_range']['present'] ?? false) === true
                && ($signature['byte_range']['valid'] ?? false) === true
        ));
    }

    /**
     * @param list<array<string, mixed>> $signatures
     * @return array<string, mixed>
     */
    private function signatureByteRangeRevisionReview(array $signatures): array
    {
        $rows = [];
        foreach ($signatures as $signature) {
            $byteRange = is_array($signature['byte_range'] ?? null) ? $signature['byte_range'] : [];
            if (($byteRange['present'] ?? false) !== true) {
                continue;
            }

            $rows[] = [
                'source' => 'signature_byte_range_revision_row',
                'field_name' => is_string($signature['field_name'] ?? null) ? $signature['field_name'] : null,
                'field_object' => is_int($signature['field_object'] ?? null) ? $signature['field_object'] : null,
                'signature_object' => is_int($signature['signature_object'] ?? null) ? $signature['signature_object'] : null,
                'signed' => ($signature['signed'] ?? false) === true,
                'byte_range_status' => is_string($byteRange['status'] ?? null) ? $byteRange['status'] : null,
                'revision_status' => is_string($byteRange['revision_status'] ?? null) ? $byteRange['revision_status'] : null,
                'signed_revision_valid' => ($byteRange['signed_revision_valid'] ?? false) === true,
                'covers_current_revision' => ($byteRange['covers_current_revision'] ?? false) === true,
                'signed_revision_end' => is_int($byteRange['signed_revision_end'] ?? null) ? $byteRange['signed_revision_end'] : null,
                'signed_revision_length' => is_int($byteRange['signed_revision_length'] ?? null) ? $byteRange['signed_revision_length'] : null,
                'current_file_bytes' => is_int($byteRange['file_bytes'] ?? null) ? $byteRange['file_bytes'] : null,
                'current_revision_tail_bytes' => is_int($byteRange['current_revision_tail_bytes'] ?? null) ? $byteRange['current_revision_tail_bytes'] : null,
                'signature_contents_gap_count' => (int) ($byteRange['gap_count'] ?? 0),
                'signature_contents_gap_present' => ($byteRange['has_signature_contents_gap'] ?? false) === true,
                'review_only' => true,
                'revision_tail_imported_as_signed' => false,
                'cryptographic_signature_validated' => false,
                'executes_signature_validation' => false,
                'executes_signing' => false,
            ];
        }

        $currentRevisionCount = count(array_filter(
            $rows,
            static fn (array $row): bool => ($row['signed_revision_valid'] ?? false) === true
                && ($row['covers_current_revision'] ?? false) === true
        ));
        $priorRevisionCount = count(array_filter(
            $rows,
            static fn (array $row): bool => ($row['signed_revision_valid'] ?? false) === true
                && ($row['covers_current_revision'] ?? false) !== true
        ));

        return [
            'source' => 'signature_byte_range_revision_review',
            'present' => $rows !== [],
            'signature_count' => count($signatures),
            'byte_range_count' => count($rows),
            'current_revision_signature_count' => $currentRevisionCount,
            'prior_revision_signature_count' => $priorRevisionCount,
            'invalid_revision_boundary_count' => count($rows) - $currentRevisionCount - $priorRevisionCount,
            'revision_statuses' => $this->uniqueStringColumn($rows, 'revision_status'),
            'field_names' => $this->uniqueStringColumn($rows, 'field_name'),
            'review_only' => true,
            'executes_signature_validation' => false,
            'executes_signing' => false,
            'rows' => $rows,
        ];
    }

    /**
     * @param list<array<string, mixed>> $signatures
     * @return list<array<string, mixed>>
     */
    private function signatureSecurityReviews(array $signatures): array
    {
        $reviews = [];
        foreach ($signatures as $signature) {
            if (is_array($signature['signature_security_review'] ?? null)) {
                $reviews[] = $signature['signature_security_review'];
            }
        }

        return $reviews;
    }

    /**
     * @param array<string, mixed> $permissionPreflight
     * @param list<array<string, mixed>> $signatures
     * @return array<string, mixed>
     */
    private function encryptedSignatureByteRangeReview(bool $encrypted, array $permissionPreflight, array $signatures): array
    {
        $base = [
            'source' => 'encrypted_signature_byte_range_review',
            'present' => false,
            'encrypted' => $encrypted,
            'signature_count' => count($signatures),
            'signed_signature_count' => count(array_filter(
                $signatures,
                static fn (array $signature): bool => ($signature['signed'] ?? false) === true
            )),
            'byte_range_count' => 0,
            'valid_byte_range_count' => 0,
            'invalid_byte_range_count' => 0,
            'byte_range_statuses' => [],
            'field_names' => [],
            'policy' => $permissionPreflight['policy'] ?? null,
            'content_extraction_boundary' => $permissionPreflight['content_extraction_boundary'] ?? null,
            'requires_password_for_content_extraction' => (bool) ($permissionPreflight['requires_password_for_content_extraction'] ?? false),
            'content_extraction_allowed' => !$encrypted,
            'text_extraction_policy' => $encrypted ? 'blocked_without_decryption' : 'native_text_allowed',
            'review_only' => true,
            'byte_range_does_not_grant_import' => $encrypted,
            'raw_signature_contents_exposed' => false,
            'raw_key_material_exposed' => false,
            'executes_decryption' => false,
            'executes_signature_validation' => false,
            'executes_signing' => false,
            'executes_external_pdf_tools' => false,
            'rows' => [],
        ];

        if (!$encrypted) {
            return $base;
        }

        $rows = [];
        foreach ($signatures as $signature) {
            $byteRange = is_array($signature['byte_range'] ?? null) ? $signature['byte_range'] : [];
            if (($byteRange['present'] ?? false) !== true) {
                continue;
            }

            $securityReview = is_array($signature['signature_security_review'] ?? null)
                ? $signature['signature_security_review']
                : [];
            $rows[] = [
                'field_name' => is_string($signature['field_name'] ?? null) ? $signature['field_name'] : null,
                'field_object' => is_int($signature['field_object'] ?? null) ? $signature['field_object'] : null,
                'signature_object' => is_int($signature['signature_object'] ?? null) ? $signature['signature_object'] : null,
                'signed' => ($signature['signed'] ?? false) === true,
                'filter' => is_string($signature['filter'] ?? null) ? $signature['filter'] : null,
                'subfilter' => is_string($signature['subfilter'] ?? null) ? $signature['subfilter'] : null,
                'byte_range_present' => true,
                'byte_range_valid' => ($byteRange['valid'] ?? false) === true,
                'byte_range_status' => is_string($byteRange['status'] ?? null) ? $byteRange['status'] : null,
                'byte_range_segment_count' => (int) ($byteRange['segment_count'] ?? 0),
                'byte_range_gap_count' => (int) ($byteRange['gap_count'] ?? 0),
                'byte_range_covers_signature_contents' => ($byteRange['has_signature_contents_gap'] ?? false) === true,
                'signature_review_decision' => is_string($securityReview['review_decision'] ?? null) ? $securityReview['review_decision'] : null,
                'content_extraction_boundary' => $permissionPreflight['content_extraction_boundary'] ?? null,
                'requires_password_for_content_extraction' => (bool) ($permissionPreflight['requires_password_for_content_extraction'] ?? true),
                'native_text_extraction_allowed_now' => false,
                'byte_range_structural_review_only' => true,
                'byte_range_does_not_grant_import' => true,
                'cryptographic_signature_validated' => false,
                'executes_decryption' => false,
                'executes_signature_validation' => false,
                'executes_signing' => false,
                'raw_signature_contents_exposed' => false,
                'raw_key_material_exposed' => false,
            ];
        }

        $base['present'] = $rows !== [];
        $base['byte_range_count'] = count($rows);
        $base['valid_byte_range_count'] = count(array_filter(
            $rows,
            static fn (array $row): bool => ($row['byte_range_valid'] ?? false) === true
        ));
        $base['invalid_byte_range_count'] = count($rows) - (int) $base['valid_byte_range_count'];
        $base['byte_range_statuses'] = $this->uniqueStringColumn($rows, 'byte_range_status');
        $base['field_names'] = $this->uniqueStringColumn($rows, 'field_name');
        $base['rows'] = $rows;

        return $base;
    }

    /**
     * @param array<string, mixed> $permissionPreflight
     * @param array<string, mixed> $documentSecurityStore
     * @param array<string, mixed> $documentSecurityStoreSignatureReview
     * @param array<string, mixed> $documentSecurityStoreSignatureReferenceTransformReview
     * @param list<array<string, mixed>> $signatures
     * @return array<string, mixed>
     */
    private function publicKeyDssPermissionBoundaryReview(
        array $permissionPreflight,
        array $documentSecurityStore,
        array $documentSecurityStoreSignatureReview,
        array $documentSecurityStoreSignatureReferenceTransformReview,
        array $signatures
    ): array {
        $recipientReview = is_array($permissionPreflight['public_key_recipient_review'] ?? null)
            ? $permissionPreflight['public_key_recipient_review']
            : [];
        $selection = is_array($recipientReview['crypt_filter_selection'] ?? null)
            ? $recipientReview['crypt_filter_selection']
            : [];
        $signaturePermissionTransformReview = $this->signaturePermissionTransformReview($signatures);
        $selectedRecipientCount = (int) ($recipientReview['selected_recipient_count'] ?? 0);
        $dssPresent = ($documentSecurityStore['present'] ?? false) === true;
        $signatureTransformCount = (int) ($signaturePermissionTransformReview['transform_count'] ?? 0);
        $dssReferenceTransformCount = (int) ($documentSecurityStoreSignatureReferenceTransformReview['signature_reference_transform_count'] ?? 0);
        $present = $selectedRecipientCount > 0 && ($dssPresent || $signatureTransformCount > 0 || $dssReferenceTransformCount > 0);

        return [
            'source' => 'public_key_dss_permission_boundary_review',
            'present' => $present,
            'encrypted' => ($permissionPreflight['encrypted'] ?? false) === true,
            'permission_policy' => is_string($permissionPreflight['policy'] ?? null) ? $permissionPreflight['policy'] : null,
            'content_extraction_boundary' => is_string($permissionPreflight['content_extraction_boundary'] ?? null)
                ? $permissionPreflight['content_extraction_boundary']
                : null,
            'boundary_decision' => $present
                ? 'blocked_public_key_dss_permission_review_only'
                : 'public_key_dss_permission_boundary_not_applicable',
            'native_text_extraction_allowed_now' => $present
                ? false
                : (($permissionPreflight['native_text_extraction_allowed_now'] ?? false) === true),
            'requires_private_key_for_permission_review' => ($recipientReview['requires_private_key_for_permission_review'] ?? false) === true,
            'recipient_permissions_decoded' => ($recipientReview['permissions_decoded'] ?? false) === true,
            'recipient_permission_decode_status' => is_string($recipientReview['permission_decode_status'] ?? null)
                ? $recipientReview['permission_decode_status']
                : null,
            'recipient_source_policy' => is_string($recipientReview['recipient_source_policy'] ?? null)
                ? $recipientReview['recipient_source_policy']
                : null,
            'selected_recipient_source_policy' => is_string($recipientReview['selected_recipient_source_policy'] ?? null)
                ? $recipientReview['selected_recipient_source_policy']
                : null,
            'recipient_count' => (int) ($recipientReview['recipient_count'] ?? 0),
            'selected_recipient_count' => $selectedRecipientCount,
            'unselected_recipient_count' => max(0, (int) ($recipientReview['recipient_count'] ?? 0) - $selectedRecipientCount),
            'top_level_recipient_count' => (int) ($recipientReview['top_level_recipient_count'] ?? 0),
            'top_level_recipients_selected' => ($recipientReview['top_level_recipients_selected'] ?? false) === true,
            'crypt_filter_recipient_filter_names' => $this->stringList($recipientReview['crypt_filter_recipient_filter_names'] ?? []),
            'selected_crypt_filter_recipient_filter_names' => $this->stringList($recipientReview['selected_crypt_filter_recipient_filter_names'] ?? []),
            'unselected_crypt_filter_recipient_filter_names' => $this->stringList($recipientReview['unselected_crypt_filter_recipient_filter_names'] ?? []),
            'declared_content_filters' => is_array($selection['declared_content_filters'] ?? null)
                ? $selection['declared_content_filters']
                : [],
            'selected_recipient_sha256' => $this->stringList($recipientReview['selected_recipient_sha256'] ?? []),
            'selected_recipient_bytes' => (int) ($recipientReview['selected_recipient_bytes'] ?? 0),
            'document_security_store_present' => $dssPresent,
            'document_security_store_validation_stream_count' => (int) ($documentSecurityStore['total_validation_stream_count'] ?? 0),
            'document_security_store_vri_count' => (int) ($documentSecurityStore['vri_count'] ?? 0),
            'document_security_store_signature_match_count' => (int) ($documentSecurityStoreSignatureReview['signature_vri_match_count'] ?? 0),
            'document_security_store_unmatched_vri_count' => (int) ($documentSecurityStoreSignatureReview['unmatched_vri_count'] ?? 0),
            'document_security_store_signature_reference_transform_count' => $dssReferenceTransformCount,
            'document_security_store_signature_reference_transform_methods' => $this->stringList(
                $documentSecurityStoreSignatureReferenceTransformReview['signature_reference_transform_methods'] ?? []
            ),
            'signature_permission_transform_count' => $signatureTransformCount,
            'signature_permission_transform_methods' => $this->stringList($signaturePermissionTransformReview['methods'] ?? []),
            'field_mdp_field_names' => $this->stringList($signaturePermissionTransformReview['field_mdp_field_names'] ?? []),
            'usage_right_categories' => $this->stringList($signaturePermissionTransformReview['usage_right_categories'] ?? []),
            'public_key_permissions_do_not_authorize_import_without_private_key' => $selectedRecipientCount > 0,
            'dss_validation_material_does_not_authorize_decryption' => $dssPresent,
            'signature_permissions_do_not_grant_text_import' => $signatureTransformCount > 0 || $dssReferenceTransformCount > 0,
            'review_only' => true,
            'recipient_bytes_exposed' => false,
            'raw_signature_contents_exposed' => false,
            'raw_validation_bytes_exposed' => false,
            'executes_cms_parse' => false,
            'executes_decryption' => false,
            'executes_permission_enforcement' => false,
            'executes_rights_enforcement' => false,
            'executes_signature_validation' => false,
            'executes_revocation_check' => false,
            'executes_trust_chain_validation' => false,
            'executes_external_pdf_tools' => false,
        ];
    }

    /**
     * @param list<array<string, mixed>> $signatures
     * @return array<string, mixed>
     */
    private function documentActionSecurityReview(
        string $pdfBytes,
        array $signatures,
        array $form,
        array $documentSecurityStore,
        array $documentSecurityStoreSignatureReview
    ): array {
        $actions = [];
        $outline = new PdfOutlineExtractor();
        $navigationReview = $outline->getNavigationReviewMetadata($pdfBytes);
        $catalogObject = $this->catalogObjectNumber($pdfBytes);
        foreach ($navigationReview['open_action_review_actions'] ?? [] as $action) {
            if (!is_array($action)) {
                continue;
            }

            $this->addDocumentActionReviewRow($actions, $action, 'catalog_open_action', [
                'catalog_object' => $catalogObject,
            ]);
        }

        foreach ($navigationReview['outline_action_review_actions'] ?? [] as $action) {
            if (!is_array($action)) {
                continue;
            }

            $this->addDocumentActionReviewRow($actions, $action, 'outline_action', $this->outlineActionContext($action));
        }

        foreach ($outline->getPageTransitionActionMetadata($pdfBytes) as $page) {
            $pageContext = [
                'pnum' => $page['pnum'] ?? null,
                'page_object' => $page['page_object'] ?? null,
                'page_label' => $page['page_label'] ?? null,
            ];
            foreach ($page['actions'] ?? [] as $action) {
                if (is_array($action)) {
                    $this->addDocumentActionReviewRow($actions, $action, 'page_additional_action', $pageContext);
                }
            }
        }

        foreach ((new PdfAnnotationExtractor())->extractPageAnnotations($pdfBytes) as $page) {
            $pageContext = [
                'pnum' => $page['pnum'] ?? null,
                'page_object' => $page['page_object'] ?? null,
            ];
            foreach ($page['annotations'] ?? [] as $annotation) {
                if (!is_array($annotation)) {
                    continue;
                }

                $annotationContext = $pageContext + [
                    'annotation_object' => $annotation['annotation_object'] ?? null,
                    'annotation_subtype' => $annotation['subtype'] ?? null,
                ];

                foreach ($annotation['actions'] ?? [] as $action) {
                    if (is_array($action)) {
                        $this->addDocumentActionReviewRow($actions, $action, 'page_annotation_action', $annotationContext);
                    }
                }

                foreach ($annotation['additional_actions'] ?? [] as $action) {
                    if (is_array($action)) {
                        $this->addDocumentActionReviewRow($actions, $action, 'page_annotation_additional_action', $annotationContext);
                    }
                }
            }
        }

        $this->addAcroFormActionReviewRows($actions, $form);
        $actions = $this->annotateDocumentActionByteRangeReviews(
            $actions,
            $signatures,
            $this->pdfObjectByteSpans($pdfBytes)
        );
        $dssCertificateReview = $this->documentSecurityStoreCertificateReview(
            $documentSecurityStore,
            $documentSecurityStoreSignatureReview
        );
        $signaturePermissionTransformReview = $this->signaturePermissionTransformReview($signatures);
        $actions = $this->annotateDocumentActionPermissionContext(
            $actions,
            $dssCertificateReview,
            $signaturePermissionTransformReview
        );
        $actionFileSpecSecurityReview = $this->actionFileSpecSecurityReview($actions);
        $certPermissionOpenActionReview = $this->certPermissionOpenActionReview($actions);
        $outlineActionSecurityReview = $this->outlineActionSecurityReview($actions);
        $postSignatureActionObjects = $this->postSignatureActionObjects($actions);
        $postSignatureActionCount = $this->postSignatureActionCount($actions);
        $unsafeActionCount = count(array_filter($actions, fn (array $action): bool => $this->isUnsafeDocumentAction($action)));
        $targetAssociatedFiles = $this->uniqueDestinationActionTargetAssociatedFiles($actions);
        $permissionDssActionChainReview = $this->permissionDssActionChainReview(
            $actions,
            $dssCertificateReview,
            $signaturePermissionTransformReview
        );

        return [
            'source' => 'pdf_document_action_security_review',
            'present' => $actions !== [],
            'action_count' => count($actions),
            'open_action_count' => $this->documentActionCountBySource($actions, 'catalog_open_action'),
            'outline_action_count' => (int) $outlineActionSecurityReview['outline_action_count'],
            'annotation_action_count' => $this->documentActionCountBySources($actions, ['page_annotation_action', 'page_annotation_additional_action']),
            'page_additional_action_count' => $this->documentActionCountBySource($actions, 'page_additional_action'),
            'acroform_action_count' => $this->documentActionCountBySources($actions, ['acroform_field_action', 'acroform_widget_action']),
            'acroform_field_action_count' => $this->documentActionCountBySource($actions, 'acroform_field_action'),
            'acroform_widget_action_count' => $this->documentActionCountBySource($actions, 'acroform_widget_action'),
            'signed_locked_field_action_count' => count(array_filter(
                $actions,
                static fn (array $action): bool => ($action['field_locked_by_signed_signature'] ?? false) === true
            )),
            'launch_action_count' => $this->documentActionCountByType($actions, 'Launch'),
            'uri_action_count' => $this->documentActionCountByType($actions, 'URI'),
            'javascript_action_count' => $this->documentActionCountByType($actions, 'JavaScript'),
            'form_submit_action_count' => $this->documentActionCountByType($actions, 'SubmitForm'),
            'form_reset_action_count' => $this->documentActionCountByType($actions, 'ResetForm'),
            'import_data_action_count' => $this->documentActionCountByType($actions, 'ImportData'),
            'hide_action_count' => $this->documentActionCountByType($actions, 'Hide'),
            'safe_uri_action_count' => count(array_filter(
                $actions,
                static fn (array $action): bool => ($action['action_type'] ?? null) === 'URI'
                    && (($action['safety'] ?? null) === 'review-uri' || ($action['is_safe_uri'] ?? null) === true)
            )),
            'unsafe_uri_action_count' => count(array_filter(
                $actions,
                static fn (array $action): bool => ($action['action_type'] ?? null) === 'URI'
                    && ($action['safety'] ?? null) === 'blocked-unsafe-uri'
            )),
            'unsafe_action_count' => $unsafeActionCount,
            'action_byte_range_review_count' => count(array_filter(
                $actions,
                static fn (array $action): bool => is_array($action['signature_byte_range_reviews'] ?? null)
                    && $action['signature_byte_range_reviews'] !== []
            )),
            'post_signature_action_count' => $postSignatureActionCount,
            'unsigned_action_byte_range_count' => count(array_filter(
                $actions,
                static fn (array $action): bool => ($action['outside_any_signature_byte_range'] ?? false) === true
            )),
            'post_signature_action_objects' => $postSignatureActionObjects,
            'action_byte_range_statuses' => $this->uniqueStringColumn($actions, 'signature_byte_range_coverage_status'),
            'has_post_signature_actions' => $postSignatureActionCount > 0,
            'action_types' => $this->uniqueStringColumn($actions, 'action_type'),
            'safety_labels' => $this->uniqueStringColumn($actions, 'safety'),
            'destination_action_target_page_associated_file_count' => count($targetAssociatedFiles),
            'destination_action_target_page_associated_file_filenames' => $this->associatedFileStringColumn($targetAssociatedFiles, 'filename'),
            'destination_action_target_page_associated_file_relationships' => $this->associatedFileStringColumn($targetAssociatedFiles, 'relationship'),
            'destination_action_target_page_associated_file_checksum_statuses' => $this->associatedFileChecksumStatuses($targetAssociatedFiles),
            'certifying_signature_count' => count(array_filter(
                $signatures,
                static fn (array $signature): bool => ($signature['certifying_signature'] ?? false) === true
            )),
            'certifying_permission_labels' => $this->certifyingPermissionLabels($signatures),
            'signature_reference_transform_methods' => $this->referenceTransformMethods($signatures),
            'dss_certificate_count' => (int) $dssCertificateReview['certificate_count'],
            'dss_certificate_hashes' => $dssCertificateReview['certificate_hashes'],
            'dss_vri_signature_match_count' => (int) $dssCertificateReview['matched_signature_count'],
            'dss_certificate_review' => $dssCertificateReview,
            'signature_permission_transform_count' => (int) $signaturePermissionTransformReview['transform_count'],
            'cert_permission_open_action_count' => (int) $certPermissionOpenActionReview['open_action_count'],
            'cert_permission_open_action_review' => $certPermissionOpenActionReview,
            'outline_action_security_review' => $outlineActionSecurityReview,
            'field_mdp_transform_count' => (int) $signaturePermissionTransformReview['field_mdp_transform_count'],
            'field_mdp_action_labels' => $signaturePermissionTransformReview['field_mdp_action_labels'],
            'field_mdp_field_names' => $signaturePermissionTransformReview['field_mdp_field_names'],
            'field_mdp_included_fields' => $signaturePermissionTransformReview['field_mdp_included_fields'],
            'field_mdp_excluded_fields' => $signaturePermissionTransformReview['field_mdp_excluded_fields'],
            'usage_rights_transform_count' => (int) $signaturePermissionTransformReview['usage_rights_transform_count'],
            'usage_right_categories' => $signaturePermissionTransformReview['usage_right_categories'],
            'usage_right_count' => (int) $signaturePermissionTransformReview['usage_right_count'],
            'signature_permission_transform_review' => $signaturePermissionTransformReview,
            'action_file_spec_count' => (int) $actionFileSpecSecurityReview['file_spec_count'],
            'action_file_spec_objects' => $actionFileSpecSecurityReview['file_spec_objects'],
            'action_file_spec_filenames' => $actionFileSpecSecurityReview['filenames'],
            'action_file_spec_relationships' => $actionFileSpecSecurityReview['relationships'],
            'action_embedded_file_count' => (int) $actionFileSpecSecurityReview['embedded_file_count'],
            'action_embedded_file_objects' => $actionFileSpecSecurityReview['embedded_file_objects'],
            'action_embedded_file_hashes' => $actionFileSpecSecurityReview['embedded_file_hashes'],
            'action_file_spec_security_review' => $actionFileSpecSecurityReview,
            'dss_certificate_action_permission_review' => [
                'source' => 'dss_certificate_action_permission_review',
                'present' => $actions !== []
                    && (
                        (int) $dssCertificateReview['certificate_count'] > 0
                        || (int) $signaturePermissionTransformReview['transform_count'] > 0
                    ),
                'action_count' => count($actions),
                'unsafe_action_count' => $unsafeActionCount,
                'dss_present' => ($documentSecurityStore['present'] ?? false) === true,
                'dss_certificate_count' => (int) $dssCertificateReview['certificate_count'],
                'dss_certificate_hashes' => $dssCertificateReview['certificate_hashes'],
                'dss_vri_signature_match_count' => (int) $dssCertificateReview['matched_signature_count'],
                'signature_permission_transform_count' => (int) $signaturePermissionTransformReview['transform_count'],
                'signature_permission_transform_methods' => $signaturePermissionTransformReview['methods'],
                'post_signature_action_count' => (int) $permissionDssActionChainReview['post_signature_action_count'],
                'unsigned_action_byte_range_count' => (int) $permissionDssActionChainReview['unsigned_action_byte_range_count'],
                'post_signature_unsafe_action_count' => (int) $permissionDssActionChainReview['post_signature_unsafe_action_count'],
                'post_signature_action_objects' => $permissionDssActionChainReview['post_signature_action_objects'],
                'action_byte_range_statuses' => $permissionDssActionChainReview['action_byte_range_statuses'],
                'post_signature_action_types' => $permissionDssActionChainReview['post_signature_action_types'],
                'post_signature_safety_labels' => $permissionDssActionChainReview['post_signature_safety_labels'],
                'post_signature_actions_granted_by_permissions' => false,
                'dss_validation_grants_action_execution' => false,
                'action_file_spec_count' => (int) $actionFileSpecSecurityReview['file_spec_count'],
                'action_embedded_file_count' => (int) $actionFileSpecSecurityReview['embedded_file_count'],
                'action_embedded_file_objects' => $actionFileSpecSecurityReview['embedded_file_objects'],
                'field_mdp_action_labels' => $signaturePermissionTransformReview['field_mdp_action_labels'],
                'field_mdp_field_names' => $signaturePermissionTransformReview['field_mdp_field_names'],
                'usage_right_categories' => $signaturePermissionTransformReview['usage_right_categories'],
                'review_only' => true,
                'executes_pdf_actions' => false,
                'executes_signature_validation' => false,
                'executes_revocation_check' => false,
                'executes_trust_chain_validation' => false,
                'executes_rights_enforcement' => false,
            ],
            'permission_dss_action_chain_review' => $permissionDssActionChainReview,
            'acroform_action_field_names' => $this->uniqueNestedStringColumn($actions, 'action_field_names'),
            'signed_locked_field_permission_labels' => $this->uniqueNestedStringColumn($actions, 'permission_labels'),
            'signed_locked_by_signatures' => $this->uniqueNestedStringColumn($actions, 'locked_by_signatures'),
            'actions' => $actions,
            'all_actions_review_only' => true,
            'executes_actions_on_import' => false,
            'executes_javascript' => false,
            'executes_external_pdf_tools' => false,
        ];
    }

    /**
     * @param array<string, mixed> $documentSecurityStore
     * @param array<string, mixed> $documentSecurityStoreSignatureReview
     * @return array<string, mixed>
     */
    private function documentSecurityStoreCertificateReview(
        array $documentSecurityStore,
        array $documentSecurityStoreSignatureReview
    ): array {
        $globalCertificates = array_values(array_filter(
            $documentSecurityStore['global_certificates'] ?? [],
            static fn (mixed $row): bool => is_array($row)
        ));
        $certificateRows = [];
        $this->collectDssCertificateRows($certificateRows, $globalCertificates, 'global_dss_certs', null);

        $vriCertificateCount = 0;
        foreach ($documentSecurityStore['vri'] ?? [] as $vri) {
            if (!is_array($vri)) {
                continue;
            }

            $vriKey = is_string($vri['key'] ?? null) ? $vri['key'] : null;
            $vriCertificates = array_values(array_filter(
                $vri['certificates'] ?? [],
                static fn (mixed $row): bool => is_array($row)
            ));
            $vriCertificateCount += count($vriCertificates);
            $this->collectDssCertificateRows($certificateRows, $vriCertificates, 'vri_dss_certs', $vriKey);
        }

        $certificateRows = $this->uniqueStreamReviewRows($certificateRows);

        return [
            'source' => 'document_security_store_certificate_review',
            'present' => ($documentSecurityStore['present'] ?? false) === true,
            'certificate_count' => count($certificateRows),
            'global_certificate_count' => count($globalCertificates),
            'vri_certificate_count' => $vriCertificateCount,
            'certificate_objects' => $this->streamObjectNumbers($certificateRows),
            'certificate_hashes' => $this->streamHashes($certificateRows),
            'unresolved_cert_refs' => $this->integerList($documentSecurityStore['unresolved_cert_refs'] ?? []),
            'vri_count' => (int) ($documentSecurityStore['vri_count'] ?? 0),
            'matched_signature_count' => (int) ($documentSecurityStoreSignatureReview['signature_vri_match_count'] ?? 0),
            'matched_signature_objects' => $this->integerList($documentSecurityStoreSignatureReview['matched_signature_objects'] ?? []),
            'matched_field_names' => $this->stringList($documentSecurityStoreSignatureReview['matched_field_names'] ?? []),
            'certificate_rows' => $certificateRows,
            'review_only' => true,
            'raw_certificate_bytes_exposed' => false,
            'raw_validation_bytes_exposed' => false,
            'executes_signature_validation' => false,
            'executes_revocation_check' => false,
            'executes_trust_chain_validation' => false,
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<array<string, mixed>> $streams
     */
    private function collectDssCertificateRows(array &$rows, array $streams, string $scope, ?string $vriKey): void
    {
        foreach ($streams as $stream) {
            $rows[] = $stream + [
                'dss_scope' => $scope,
                'vri_key' => $vriKey,
                'raw_bytes_exposed' => false,
            ];
        }
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function uniqueStreamReviewRows(array $rows): array
    {
        $seen = [];
        $unique = [];
        foreach ($rows as $row) {
            $key = null;
            if (is_int($row['object_number'] ?? null)) {
                $key = 'obj:' . $row['object_number'];
            } elseif (is_string($row['sha256'] ?? null)) {
                $key = 'sha256:' . $row['sha256'];
            }

            if ($key !== null && isset($seen[$key])) {
                continue;
            }
            if ($key !== null) {
                $seen[$key] = true;
            }
            $unique[] = $row;
        }

        return $unique;
    }

    /**
     * @param list<array<string, mixed>> $streams
     * @return list<int>
     */
    private function streamObjectNumbers(array $streams): array
    {
        $objects = [];
        foreach ($streams as $stream) {
            if (is_int($stream['object_number'] ?? null) && !in_array($stream['object_number'], $objects, true)) {
                $objects[] = $stream['object_number'];
            }
        }

        return $objects;
    }

    /**
     * @param list<array<string, mixed>> $signatures
     * @return array<string, mixed>
     */
    private function signaturePermissionTransformReview(array $signatures): array
    {
        $methods = [];
        $docMdpPermissionLabels = [];
        $docMdpAllowedChanges = [];
        $fieldMdpActionLabels = [];
        $fieldMdpFieldNames = [];
        $fieldMdpIncludedFields = [];
        $fieldMdpExcludedFields = [];
        $usageRightCategories = [];
        $usageRights = [
            'document' => [],
            'form' => [],
            'signature' => [],
            'annotations' => [],
            'embedded_files' => [],
        ];
        $transformCount = 0;
        $fieldMdpCount = 0;
        $usageRightsCount = 0;
        $usageRightCount = 0;
        $locksAllFields = false;

        foreach ($signatures as $signature) {
            foreach ($signature['reference_transforms'] ?? [] as $transform) {
                if (!is_array($transform)) {
                    continue;
                }

                $method = is_string($transform['transform_method'] ?? null) ? $transform['transform_method'] : null;
                if ($method === null) {
                    continue;
                }

                $transformCount++;
                $this->appendUniqueString($methods, $method);
                if ($method === 'DocMDP') {
                    if (is_string($transform['permission_label'] ?? null)) {
                        $this->appendUniqueString($docMdpPermissionLabels, $transform['permission_label']);
                    }
                    foreach ($this->stringList($transform['allowed_changes'] ?? []) as $change) {
                        $this->appendUniqueString($docMdpAllowedChanges, $change);
                    }
                    continue;
                }
                if ($method === 'FieldMDP') {
                    $fieldMdpCount++;
                    if (is_string($transform['action_label'] ?? null)) {
                        $this->appendUniqueString($fieldMdpActionLabels, $transform['action_label']);
                    }
                    foreach ($this->stringList($transform['field_names'] ?? []) as $fieldName) {
                        $this->appendUniqueString($fieldMdpFieldNames, $fieldName);
                    }
                    foreach ($this->stringList($transform['included_fields'] ?? []) as $fieldName) {
                        $this->appendUniqueString($fieldMdpIncludedFields, $fieldName);
                    }
                    foreach ($this->stringList($transform['excluded_fields'] ?? []) as $fieldName) {
                        $this->appendUniqueString($fieldMdpExcludedFields, $fieldName);
                    }
                    $locksAllFields = $locksAllFields || (($transform['locks_all_fields'] ?? false) === true);
                    continue;
                }

                if ($method !== 'UR' && $method !== 'UR3') {
                    continue;
                }

                $usageRightsCount++;
                $rights = is_array($transform['rights'] ?? null) ? $transform['rights'] : [];
                foreach ($usageRights as $category => $_) {
                    foreach ($this->stringList($rights[$category] ?? []) as $right) {
                        $this->appendUniqueString($usageRights[$category], $right);
                        $usageRightCount++;
                    }
                    if ($usageRights[$category] !== []) {
                        $this->appendUniqueString($usageRightCategories, $category);
                    }
                }
            }
        }

        return [
            'source' => 'signature_permission_transform_review',
            'present' => $transformCount > 0,
            'transform_count' => $transformCount,
            'methods' => $methods,
            'doc_mdp_permission_labels' => $docMdpPermissionLabels,
            'doc_mdp_allowed_changes' => $docMdpAllowedChanges,
            'field_mdp_transform_count' => $fieldMdpCount,
            'field_mdp_action_labels' => $fieldMdpActionLabels,
            'field_mdp_field_names' => $fieldMdpFieldNames,
            'field_mdp_included_fields' => $fieldMdpIncludedFields,
            'field_mdp_excluded_fields' => $fieldMdpExcludedFields,
            'field_mdp_locks_all_fields' => $locksAllFields,
            'usage_rights_transform_count' => $usageRightsCount,
            'usage_right_categories' => $usageRightCategories,
            'usage_rights' => $usageRights,
            'usage_right_count' => $usageRightCount,
            'review_only' => true,
            'executes_rights_enforcement' => false,
            'executes_signature_validation' => false,
            'executes_action' => false,
        ];
    }

    /**
     * @param list<array<string, mixed>> $signatures
     * @param list<array<string, mixed>> $fields
     * @param array<int, array{offset: int, end: int, length: int, generation: int}> $objectSpans
     * @return array<string, mixed>
     */
    private function fieldMdpByteRangeReview(array $signatures, array $fields, array $objectSpans): array
    {
        $fieldsByName = $this->fieldsByName($fields);
        $rows = [];
        foreach ($signatures as $signature) {
            $byteRange = is_array($signature['byte_range'] ?? null) ? $signature['byte_range'] : [];
            foreach ($signature['reference_transforms'] ?? [] as $transform) {
                if (!is_array($transform) || ($transform['transform_method'] ?? null) !== 'FieldMDP') {
                    continue;
                }

                $targetNames = $this->fieldMdpTargetFieldNames($transform, $fields);
                foreach ($targetNames as $fieldName) {
                    $field = $fieldsByName[$fieldName] ?? null;
                    $fieldObject = is_array($field) && is_int($field['object'] ?? null) ? $field['object'] : null;
                    $fieldCoverage = $this->fieldMdpObjectByteRangeCoverage($fieldObject, $objectSpans, $byteRange, 'field_object');
                    $widgetCoverages = [];
                    if (is_array($field)) {
                        foreach ($this->fieldMdpWidgetObjects($field) as $widgetObject) {
                            $widgetCoverages[] = $this->fieldMdpObjectByteRangeCoverage($widgetObject, $objectSpans, $byteRange, 'widget_object');
                        }
                    }
                    $targetStatus = $this->fieldMdpTargetByteRangeStatus($fieldCoverage, $widgetCoverages);

                    $rows[] = [
                        'source' => 'field_mdp_byte_range_target_review',
                        'field_name' => $fieldName,
                        'field_object' => $fieldObject,
                        'field_resolved' => is_array($field),
                        'field_type' => is_array($field) && is_string($field['field_type'] ?? null) ? $field['field_type'] : null,
                        'signature_field_name' => is_string($signature['field_name'] ?? null) ? $signature['field_name'] : null,
                        'signature_object' => is_int($signature['signature_object'] ?? null) ? $signature['signature_object'] : null,
                        'transform_params_object' => is_int($transform['transform_params_object'] ?? null) ? $transform['transform_params_object'] : null,
                        'transform_data_object' => is_int($transform['data_object'] ?? null) ? $transform['data_object'] : null,
                        'field_mdp_action' => is_string($transform['action'] ?? null) ? $transform['action'] : null,
                        'field_mdp_action_label' => is_string($transform['action_label'] ?? null) ? $transform['action_label'] : null,
                        'field_mdp_action_valid' => ($transform['action_valid'] ?? false) === true,
                        'declared_field_names' => $this->stringList($transform['field_names'] ?? []),
                        'byte_range_present' => ($byteRange['present'] ?? false) === true,
                        'byte_range_status' => is_string($byteRange['status'] ?? null) ? $byteRange['status'] : null,
                        'byte_range_revision_status' => is_string($byteRange['revision_status'] ?? null) ? $byteRange['revision_status'] : null,
                        'signed_revision_valid' => ($byteRange['signed_revision_valid'] ?? false) === true,
                        'signed_revision_end' => is_int($byteRange['signed_revision_end'] ?? null) ? $byteRange['signed_revision_end'] : null,
                        'current_revision_tail_bytes' => is_int($byteRange['current_revision_tail_bytes'] ?? null) ? $byteRange['current_revision_tail_bytes'] : null,
                        'field_object_span' => $fieldCoverage['object_span'],
                        'field_object_coverage_status' => $fieldCoverage['coverage_status'],
                        'field_object_covered_by_signed_revision' => ($fieldCoverage['covered_by_signed_revision'] ?? false) === true,
                        'widget_object_count' => count($widgetCoverages),
                        'widget_object_coverage_statuses' => $this->uniqueStringColumn($widgetCoverages, 'coverage_status'),
                        'widget_object_coverage' => $widgetCoverages,
                        'target_status' => $targetStatus,
                        'target_covered_by_signed_revision' => $targetStatus === 'field_mdp_target_covered_by_signed_revision',
                        'target_outside_signed_revision' => $targetStatus === 'field_mdp_target_outside_signed_revision',
                        'target_inside_signature_contents_gap' => $targetStatus === 'field_mdp_target_inside_signature_contents_gap',
                        'review_only' => true,
                        'field_permission_enforced' => false,
                        'cryptographic_signature_validated' => false,
                        'executes_signature_validation' => false,
                        'executes_rights_enforcement' => false,
                    ];
                }
            }
        }

        $coveredCount = count(array_filter(
            $rows,
            static fn (array $row): bool => ($row['target_status'] ?? null) === 'field_mdp_target_covered_by_signed_revision'
        ));

        return [
            'source' => 'field_mdp_byte_range_review',
            'present' => $rows !== [],
            'signature_count' => count($signatures),
            'field_mdp_transform_count' => $this->fieldMdpTransformCount($signatures),
            'target_field_count' => count($rows),
            'target_covered_count' => $coveredCount,
            'target_not_covered_count' => count($rows) - $coveredCount,
            'target_outside_signed_revision_count' => count(array_filter(
                $rows,
                static fn (array $row): bool => ($row['target_outside_signed_revision'] ?? false) === true
            )),
            'target_inside_signature_contents_gap_count' => count(array_filter(
                $rows,
                static fn (array $row): bool => ($row['target_inside_signature_contents_gap'] ?? false) === true
            )),
            'target_unresolved_count' => count(array_filter(
                $rows,
                static fn (array $row): bool => ($row['field_resolved'] ?? false) !== true
            )),
            'target_field_names' => $this->uniqueStringColumn($rows, 'field_name'),
            'target_statuses' => $this->uniqueStringColumn($rows, 'target_status'),
            'field_mdp_action_labels' => $this->uniqueStringColumn($rows, 'field_mdp_action_label'),
            'review_only' => true,
            'field_permissions_enforced' => false,
            'executes_signature_validation' => false,
            'executes_rights_enforcement' => false,
            'rows' => $rows,
        ];
    }

    /**
     * @param list<array<string, mixed>> $fields
     * @return array<string, array<string, mixed>>
     */
    private function fieldsByName(array $fields): array
    {
        $byName = [];
        foreach ($fields as $field) {
            if (!is_array($field) || !is_string($field['name'] ?? null) || isset($byName[$field['name']])) {
                continue;
            }

            $byName[$field['name']] = $field;
        }

        return $byName;
    }

    /**
     * @param array<string, mixed> $transform
     * @param list<array<string, mixed>> $fields
     * @return list<string>
     */
    private function fieldMdpTargetFieldNames(array $transform, array $fields): array
    {
        $declared = $this->stringList($transform['field_names'] ?? []);
        $action = is_string($transform['action'] ?? null) ? $transform['action'] : null;
        $allFieldNames = [];
        foreach ($fields as $field) {
            if (is_array($field) && is_string($field['name'] ?? null) && !in_array($field['name'], $allFieldNames, true)) {
                $allFieldNames[] = $field['name'];
            }
        }

        if ($action === 'All') {
            return $allFieldNames;
        }
        if ($action === 'Exclude') {
            return array_values(array_filter(
                $allFieldNames,
                static fn (string $fieldName): bool => !in_array($fieldName, $declared, true)
            ));
        }

        return $declared;
    }

    /**
     * @param array<string, mixed> $field
     * @return list<int>
     */
    private function fieldMdpWidgetObjects(array $field): array
    {
        $objects = [];
        foreach ($field['widgets'] ?? [] as $widget) {
            if (is_array($widget) && is_int($widget['object'] ?? null) && !in_array($widget['object'], $objects, true)) {
                $objects[] = $widget['object'];
            }
        }

        return $objects;
    }

    /**
     * @param array<int, array{offset: int, end: int, length: int, generation: int}> $objectSpans
     * @param array<string, mixed> $byteRange
     * @return array<string, mixed>
     */
    private function fieldMdpObjectByteRangeCoverage(?int $objectNumber, array $objectSpans, array $byteRange, string $objectKind): array
    {
        $base = [
            'object_kind' => $objectKind,
            'object_number' => $objectNumber,
            'object_span' => null,
            'coverage_status' => $objectKind . '_unresolved',
            'covered_by_signed_revision' => false,
            'outside_signed_revision' => false,
            'inside_signature_contents_gap' => false,
        ];
        if ($objectNumber === null) {
            return $base;
        }

        $span = $objectSpans[$objectNumber] ?? null;
        if ($span === null) {
            $base['coverage_status'] = $objectKind . '_span_unresolved';
            return $base;
        }

        $coverage = $this->signatureByteRangeSpanCoverage($span, $byteRange);

        return array_merge($base, [
            'object_span' => $span,
            'coverage_status' => $coverage['status'],
            'covered_by_signed_revision' => ($coverage['covered'] ?? false) === true,
            'outside_signed_revision' => ($coverage['status'] ?? null) === 'outside_signed_revision',
            'inside_signature_contents_gap' => ($coverage['status'] ?? null) === 'inside_unsigned_gap',
        ]);
    }

    /**
     * @param array<string, mixed> $fieldCoverage
     * @param list<array<string, mixed>> $widgetCoverages
     */
    private function fieldMdpTargetByteRangeStatus(array $fieldCoverage, array $widgetCoverages): string
    {
        $coverages = array_merge([$fieldCoverage], $widgetCoverages);
        foreach ($coverages as $coverage) {
            if (($coverage['outside_signed_revision'] ?? false) === true) {
                return 'field_mdp_target_outside_signed_revision';
            }
        }
        foreach ($coverages as $coverage) {
            if (($coverage['inside_signature_contents_gap'] ?? false) === true) {
                return 'field_mdp_target_inside_signature_contents_gap';
            }
        }
        foreach ($coverages as $coverage) {
            $status = is_string($coverage['coverage_status'] ?? null) ? $coverage['coverage_status'] : '';
            if (str_contains($status, '_unresolved')) {
                return 'field_mdp_target_unresolved';
            }
        }
        foreach ($coverages as $coverage) {
            if (($coverage['covered_by_signed_revision'] ?? false) !== true) {
                return 'field_mdp_target_not_fully_covered_by_signed_revision';
            }
        }

        return 'field_mdp_target_covered_by_signed_revision';
    }

    /**
     * @param list<array<string, mixed>> $signatures
     */
    private function fieldMdpTransformCount(array $signatures): int
    {
        $count = 0;
        foreach ($signatures as $signature) {
            foreach ($signature['reference_transforms'] ?? [] as $transform) {
                if (is_array($transform) && ($transform['transform_method'] ?? null) === 'FieldMDP') {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * @param list<string> $values
     */
    private function appendUniqueString(array &$values, string $value): void
    {
        if (!in_array($value, $values, true)) {
            $values[] = $value;
        }
    }

    /**
     * @param list<array<string, mixed>> $actions
     * @return list<array<string, mixed>>
     */
    private function annotateDocumentActionPermissionContext(
        array $actions,
        array $dssCertificateReview,
        array $signaturePermissionTransformReview
    ): array {
        foreach ($actions as $index => $action) {
            $actions[$index]['dss_certificate_count'] = (int) $dssCertificateReview['certificate_count'];
            $actions[$index]['dss_certificate_hashes'] = $dssCertificateReview['certificate_hashes'];
            $actions[$index]['dss_vri_signature_match_count'] = (int) $dssCertificateReview['matched_signature_count'];
            $actions[$index]['signature_permission_transform_methods'] = $signaturePermissionTransformReview['methods'];
            $actions[$index]['doc_mdp_permission_labels'] = $signaturePermissionTransformReview['doc_mdp_permission_labels'];
            $actions[$index]['doc_mdp_allowed_changes'] = $signaturePermissionTransformReview['doc_mdp_allowed_changes'];
            $actions[$index]['field_mdp_action_labels'] = $signaturePermissionTransformReview['field_mdp_action_labels'];
            $actions[$index]['field_mdp_field_names'] = $signaturePermissionTransformReview['field_mdp_field_names'];
            $actions[$index]['field_mdp_included_fields'] = $signaturePermissionTransformReview['field_mdp_included_fields'];
            $actions[$index]['field_mdp_excluded_fields'] = $signaturePermissionTransformReview['field_mdp_excluded_fields'];
            $actions[$index]['usage_right_categories'] = $signaturePermissionTransformReview['usage_right_categories'];
            $actions[$index]['usage_right_count'] = (int) $signaturePermissionTransformReview['usage_right_count'];
            $actions[$index]['open_action_permission_status'] = $this->openActionPermissionStatus($actions[$index], $signaturePermissionTransformReview);
            $actions[$index]['open_action_allowed_by_cert_permissions'] = false;
            $actions[$index]['open_action_requires_security_review'] = ($actions[$index]['source'] ?? null) === 'catalog_open_action';
            $actions[$index]['outline_action_permission_status'] = $this->outlineActionPermissionStatus($actions[$index], $signaturePermissionTransformReview);
            $actions[$index]['outline_action_allowed_by_cert_permissions'] = false;
            $actions[$index]['outline_action_requires_security_review'] = ($actions[$index]['source'] ?? null) === 'outline_action';
            $actions[$index]['cert_permissions_grant_action_execution'] = false;
            $actions[$index]['signature_permission_review_only'] = true;
            $actions[$index]['dss_validation_review_only'] = ((int) $dssCertificateReview['certificate_count']) > 0;
            $actions[$index]['executes_rights_enforcement'] = false;
            $actions[$index]['executes_trust_chain_validation'] = false;
        }

        return $actions;
    }

    /**
     * @param list<array<string, mixed>> $actions
     * @return array<string, mixed>
     */
    private function permissionDssActionChainReview(
        array $actions,
        array $dssCertificateReview,
        array $signaturePermissionTransformReview
    ): array {
        $postSignatureActions = array_values(array_filter(
            $actions,
            fn (array $action): bool => $this->actionIsOutsideSignedRevision($action)
        ));
        $permissionTransformCount = (int) ($signaturePermissionTransformReview['transform_count'] ?? 0);
        $dssCertificateCount = (int) ($dssCertificateReview['certificate_count'] ?? 0);
        $postSignatureUnsafeActionCount = count(array_filter(
            $postSignatureActions,
            fn (array $action): bool => $this->isUnsafeDocumentAction($action)
        ));

        return [
            'source' => 'permission_dss_action_chain_review',
            'present' => $postSignatureActions !== [] && ($dssCertificateCount > 0 || $permissionTransformCount > 0),
            'action_count' => count($actions),
            'post_signature_action_count' => count($postSignatureActions),
            'post_signature_unsafe_action_count' => $postSignatureUnsafeActionCount,
            'unsigned_action_byte_range_count' => count(array_filter(
                $postSignatureActions,
                static fn (array $action): bool => ($action['outside_any_signature_byte_range'] ?? false) === true
            )),
            'post_signature_action_objects' => $this->postSignatureActionObjects($postSignatureActions),
            'post_signature_action_types' => $this->uniqueStringColumn($postSignatureActions, 'action_type'),
            'post_signature_safety_labels' => $this->uniqueStringColumn($postSignatureActions, 'safety'),
            'action_byte_range_statuses' => $this->uniqueStringColumn($postSignatureActions, 'signature_byte_range_coverage_status'),
            'dss_present' => $dssCertificateCount > 0,
            'dss_certificate_count' => $dssCertificateCount,
            'dss_certificate_hashes' => $dssCertificateReview['certificate_hashes'] ?? [],
            'dss_vri_signature_match_count' => (int) ($dssCertificateReview['matched_signature_count'] ?? 0),
            'signature_permission_transform_count' => $permissionTransformCount,
            'signature_permission_transform_methods' => $signaturePermissionTransformReview['methods'] ?? [],
            'field_mdp_action_labels' => $signaturePermissionTransformReview['field_mdp_action_labels'] ?? [],
            'field_mdp_field_names' => $signaturePermissionTransformReview['field_mdp_field_names'] ?? [],
            'usage_right_categories' => $signaturePermissionTransformReview['usage_right_categories'] ?? [],
            'review_only' => true,
            'post_signature_actions_granted_by_permissions' => false,
            'dss_validation_grants_action_execution' => false,
            'executes_pdf_actions' => false,
            'executes_signature_validation' => false,
            'executes_revocation_check' => false,
            'executes_trust_chain_validation' => false,
            'executes_rights_enforcement' => false,
        ];
    }

    /**
     * @param array<string, mixed> $action
     * @return array<string, mixed>
     */
    private function outlineActionContext(array $action): array
    {
        return [
            'outline_title' => is_string($action['outline_title'] ?? null) ? $action['outline_title'] : null,
            'outline_level' => is_int($action['outline_level'] ?? null) ? $action['outline_level'] : null,
            'outline_object' => is_int($action['outline_object'] ?? null) ? $action['outline_object'] : null,
            'outline_parent_object' => is_int($action['outline_parent_object'] ?? null) ? $action['outline_parent_object'] : null,
            'outline_destination_name' => is_string($action['destination_action_name'] ?? null)
                ? $action['destination_action_name']
                : (is_string($action['destination'] ?? null) ? $action['destination'] : null),
        ];
    }

    /**
     * @param list<array<string, mixed>> $actions
     * @return array<string, mixed>
     */
    private function outlineActionSecurityReview(array $actions): array
    {
        $outlineActions = array_values(array_filter(
            $actions,
            static fn (array $action): bool => ($action['source'] ?? null) === 'outline_action'
        ));
        $targetAssociatedFiles = $this->uniqueDestinationActionTargetAssociatedFiles($outlineActions);

        return [
            'source' => 'outline_action_security_review',
            'present' => $outlineActions !== [],
            'outline_action_count' => count($outlineActions),
            'unsafe_outline_action_count' => count(array_filter(
                $outlineActions,
                fn (array $action): bool => $this->isUnsafeDocumentAction($action)
            )),
            'outline_titles' => $this->uniqueStringColumn($outlineActions, 'outline_title'),
            'outline_objects' => $this->uniqueIntegerColumn($outlineActions, 'outline_object'),
            'outline_action_objects' => $this->uniqueIntegerColumn($outlineActions, 'action_object'),
            'outline_action_types' => $this->uniqueStringColumn($outlineActions, 'action_type'),
            'outline_action_safety_labels' => $this->uniqueStringColumn($outlineActions, 'safety'),
            'destination_action_names' => $this->uniqueStringColumn($outlineActions, 'destination_action_name'),
            'destination_action_target_pages' => $this->uniqueIntegerColumn($outlineActions, 'destination_action_target_page'),
            'destination_action_target_page_labels' => $this->uniqueStringColumn($outlineActions, 'destination_action_target_page_label'),
            'destination_action_target_transition_styles' => $this->transitionStyleColumn($outlineActions, 'destination_action_target_page_transition'),
            'destination_action_target_article_thread_titles' => $this->uniqueNestedStringColumn($outlineActions, 'destination_action_target_article_thread_titles'),
            'destination_action_target_page_associated_file_count' => count($targetAssociatedFiles),
            'destination_action_target_page_associated_file_filenames' => $this->associatedFileStringColumn($targetAssociatedFiles, 'filename'),
            'destination_action_target_page_associated_file_relationships' => $this->associatedFileStringColumn($targetAssociatedFiles, 'relationship'),
            'destination_action_target_page_associated_file_checksum_statuses' => $this->associatedFileChecksumStatuses($targetAssociatedFiles),
            'signature_permission_transform_methods' => $this->uniqueNestedStringColumn($outlineActions, 'signature_permission_transform_methods'),
            'outline_action_permission_statuses' => $this->uniqueStringColumn($outlineActions, 'outline_action_permission_status'),
            'cert_permissions_grant_outline_action_execution' => false,
            'rights_enforced_for_outline_action' => false,
            'review_only' => true,
            'executes_pdf_actions' => false,
            'executes_rights_enforcement' => false,
            'executes_signature_validation' => false,
            'executes_trust_chain_validation' => false,
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private function transitionStyleColumn(array $rows, string $key): array
    {
        $styles = [];
        foreach ($rows as $row) {
            $transition = $row[$key] ?? null;
            $style = is_array($transition) && is_string($transition['style'] ?? null)
                ? $transition['style']
                : null;
            if ($style !== null && !in_array($style, $styles, true)) {
                $styles[] = $style;
            }
        }

        return $styles;
    }

    /**
     * @param list<array<string, mixed>> $actions
     * @return array<string, mixed>
     */
    private function certPermissionOpenActionReview(array $actions): array
    {
        $openActions = array_values(array_filter(
            $actions,
            static fn (array $action): bool => ($action['source'] ?? null) === 'catalog_open_action'
        ));
        $targetAssociatedFiles = $this->uniqueDestinationActionTargetAssociatedFiles($openActions);

        return [
            'source' => 'cert_permission_open_action_review',
            'present' => $openActions !== [],
            'open_action_count' => count($openActions),
            'unsafe_open_action_count' => count(array_filter(
                $openActions,
                fn (array $action): bool => $this->isUnsafeDocumentAction($action)
            )),
            'open_action_objects' => $this->uniqueIntegerColumn($openActions, 'action_object'),
            'open_action_types' => $this->uniqueStringColumn($openActions, 'action_type'),
            'open_action_safety_labels' => $this->uniqueStringColumn($openActions, 'safety'),
            'open_action_permission_statuses' => $this->uniqueStringColumn($openActions, 'open_action_permission_status'),
            'destination_action_target_page_associated_file_count' => count($targetAssociatedFiles),
            'destination_action_target_page_associated_file_filenames' => $this->associatedFileStringColumn($targetAssociatedFiles, 'filename'),
            'destination_action_target_page_associated_file_relationships' => $this->associatedFileStringColumn($targetAssociatedFiles, 'relationship'),
            'destination_action_target_page_associated_file_checksum_statuses' => $this->associatedFileChecksumStatuses($targetAssociatedFiles),
            'doc_mdp_permission_labels' => $this->uniqueNestedStringColumn($openActions, 'doc_mdp_permission_labels'),
            'doc_mdp_allowed_changes' => $this->uniqueNestedStringColumn($openActions, 'doc_mdp_allowed_changes'),
            'field_mdp_action_labels' => $this->uniqueNestedStringColumn($openActions, 'field_mdp_action_labels'),
            'field_mdp_field_names' => $this->uniqueNestedStringColumn($openActions, 'field_mdp_field_names'),
            'usage_right_categories' => $this->uniqueNestedStringColumn($openActions, 'usage_right_categories'),
            'signature_permission_transform_methods' => $this->uniqueNestedStringColumn($openActions, 'signature_permission_transform_methods'),
            'cert_permissions_grant_open_action_execution' => false,
            'cert_permissions_allow_catalog_open_action_mutation' => false,
            'rights_enforced_for_open_action' => false,
            'review_only' => true,
            'executes_pdf_actions' => false,
            'executes_rights_enforcement' => false,
            'executes_signature_validation' => false,
            'executes_trust_chain_validation' => false,
        ];
    }

    /**
     * @param array<string, mixed> $action
     * @param array<string, mixed> $signaturePermissionTransformReview
     */
    private function openActionPermissionStatus(array $action, array $signaturePermissionTransformReview): ?string
    {
        if (($action['source'] ?? null) !== 'catalog_open_action') {
            return null;
        }

        if ((int) ($signaturePermissionTransformReview['transform_count'] ?? 0) === 0) {
            return 'catalog_open_action_review_only_no_cert_permission_context';
        }

        return 'catalog_open_action_review_only_not_granted_by_cert_permissions';
    }

    /**
     * @param array<string, mixed> $action
     * @param array<string, mixed> $signaturePermissionTransformReview
     */
    private function outlineActionPermissionStatus(array $action, array $signaturePermissionTransformReview): ?string
    {
        if (($action['source'] ?? null) !== 'outline_action') {
            return null;
        }

        if ((int) ($signaturePermissionTransformReview['transform_count'] ?? 0) === 0) {
            return 'outline_action_review_only_no_cert_permission_context';
        }

        return 'outline_action_review_only_not_granted_by_cert_permissions';
    }

    /**
     * @param list<array<string, mixed>> $actions
     * @param array<string, mixed> $form
     */
    private function addAcroFormActionReviewRows(array &$actions, array $form): void
    {
        $widgetContexts = [];
        foreach ($form['fields'] ?? [] as $field) {
            if (!is_array($field)) {
                continue;
            }

            $fieldContext = $this->acroFormFieldActionContext($field);
            foreach ($field['widgets'] ?? [] as $widget) {
                if (!is_array($widget) || !is_int($widget['object'] ?? null)) {
                    continue;
                }

                $widgetContexts[$widget['object']] = $fieldContext + [
                    'widget_object' => $widget['object'],
                ];
            }
        }

        $pageAnnotationActionWidgetObjects = [];
        foreach ($actions as &$row) {
            if (!in_array($row['source'] ?? null, ['page_annotation_action', 'page_annotation_additional_action'], true)) {
                continue;
            }

            $annotationObject = is_int($row['annotation_object'] ?? null) ? $row['annotation_object'] : null;
            if ($annotationObject === null || !isset($widgetContexts[$annotationObject])) {
                continue;
            }

            $pageAnnotationActionWidgetObjects[$annotationObject] = true;
            $this->applyAcroFormActionContext($row, $widgetContexts[$annotationObject]);
        }
        unset($row);

        foreach ($form['fields'] ?? [] as $field) {
            if (!is_array($field)) {
                continue;
            }

            $fieldContext = $this->acroFormFieldActionContext($field);
            foreach ($field['actions'] ?? [] as $action) {
                if (is_array($action)) {
                    $this->addDocumentActionReviewRow($actions, $action, 'acroform_field_action', $fieldContext);
                }
            }

            foreach ($field['widgets'] ?? [] as $widget) {
                if (!is_array($widget)) {
                    continue;
                }

                $widgetObject = is_int($widget['object'] ?? null) ? $widget['object'] : null;
                if ($widgetObject !== null && isset($pageAnnotationActionWidgetObjects[$widgetObject])) {
                    continue;
                }

                $widgetContext = $fieldContext + ['widget_object' => $widgetObject];
                foreach ($widget['actions'] ?? [] as $action) {
                    if (is_array($action)) {
                        $this->addDocumentActionReviewRow($actions, $action, 'acroform_widget_action', $widgetContext);
                    }
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $field
     * @return array<string, mixed>
     */
    private function acroFormFieldActionContext(array $field): array
    {
        $lockState = is_array($field['signature_lock_state'] ?? null) ? $field['signature_lock_state'] : [];

        return [
            'field_name' => is_string($field['name'] ?? null) ? $field['name'] : null,
            'field_object' => is_int($field['object'] ?? null) ? $field['object'] : null,
            'field_locked_by_signed_signature' => ($lockState['effective_locked'] ?? false) === true,
            'locked_by_signatures' => $this->stringList($lockState['locked_by_signatures'] ?? []),
            'permission_labels' => $this->stringList($lockState['permission_labels'] ?? []),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $context
     */
    private function applyAcroFormActionContext(array &$row, array $context): void
    {
        $row['field_name'] = is_string($context['field_name'] ?? null) ? $context['field_name'] : null;
        $row['field_object'] = is_int($context['field_object'] ?? null) ? $context['field_object'] : null;
        $row['widget_object'] = is_int($context['widget_object'] ?? null) ? $context['widget_object'] : null;
        $row['field_locked_by_signed_signature'] = ($context['field_locked_by_signed_signature'] ?? false) === true;
        $row['locked_by_signatures'] = $this->stringList($context['locked_by_signatures'] ?? []);
        $row['permission_labels'] = $this->stringList($context['permission_labels'] ?? []);
    }

    /**
     * @param list<array<string, mixed>> $actions
     * @param array<string, mixed> $action
     * @param array<string, mixed> $context
     */
    private function addDocumentActionReviewRow(array &$actions, array $action, string $source, array $context = []): void
    {
        $targetAssociatedFiles = $this->targetPageAssociatedFileReviews($action);
        $actionWithSource = $action;
        $actionWithSource['source'] = $source;
        $fileSpecs = $this->documentActionFileSpecReviews($actionWithSource);
        $primaryFileSpec = $fileSpecs[0] ?? null;
        $row = [
            'source' => $source,
            'pnum' => $context['pnum'] ?? null,
            'page_object' => $context['page_object'] ?? null,
            'page_label' => is_string($context['page_label'] ?? null) ? $context['page_label'] : null,
            'annotation_object' => $context['annotation_object'] ?? null,
            'annotation_subtype' => $context['annotation_subtype'] ?? null,
            'catalog_object' => is_int($context['catalog_object'] ?? null) ? $context['catalog_object'] : null,
            'outline_title' => is_string($context['outline_title'] ?? null) ? $context['outline_title'] : null,
            'outline_level' => is_int($context['outline_level'] ?? null) ? $context['outline_level'] : null,
            'outline_object' => is_int($context['outline_object'] ?? null) ? $context['outline_object'] : null,
            'outline_parent_object' => is_int($context['outline_parent_object'] ?? null) ? $context['outline_parent_object'] : null,
            'outline_destination_name' => is_string($context['outline_destination_name'] ?? null) ? $context['outline_destination_name'] : null,
            'event' => $action['event'] ?? null,
            'event_label' => $action['event_label'] ?? null,
            'trigger' => is_string($action['trigger'] ?? null) ? $action['trigger'] : null,
            'trigger_label' => is_string($action['trigger_label'] ?? null) ? $action['trigger_label'] : null,
            'field_name' => is_string($context['field_name'] ?? null) ? $context['field_name'] : null,
            'field_object' => is_int($context['field_object'] ?? null) ? $context['field_object'] : null,
            'widget_object' => is_int($context['widget_object'] ?? null) ? $context['widget_object'] : null,
            'field_locked_by_signed_signature' => ($context['field_locked_by_signed_signature'] ?? false) === true,
            'locked_by_signatures' => $this->stringList($context['locked_by_signatures'] ?? []),
            'permission_labels' => $this->stringList($context['permission_labels'] ?? []),
            'action_type' => is_string($action['action_type'] ?? null) ? $action['action_type'] : null,
            'safety' => $this->documentActionSafety($action),
            'action_object' => is_int($action['action_object'] ?? null) ? $action['action_object'] : null,
            'uri' => $this->documentActionUri($action),
            'file' => $this->documentActionFile($action),
            'target' => is_string($action['target'] ?? null) ? $action['target'] : null,
            'target_scheme' => is_string($action['target_scheme'] ?? null) ? $action['target_scheme'] : null,
            'action_file_spec_present' => $fileSpecs !== [],
            'action_file_spec' => $primaryFileSpec,
            'action_file_specs' => $fileSpecs,
            'action_file_spec_count' => count($fileSpecs),
            'action_file_spec_object' => is_array($primaryFileSpec) && is_int($primaryFileSpec['file_spec_object'] ?? null)
                ? $primaryFileSpec['file_spec_object']
                : null,
            'action_file_spec_filename' => is_array($primaryFileSpec) && is_string($primaryFileSpec['filename'] ?? null)
                ? $primaryFileSpec['filename']
                : null,
            'action_file_spec_relationship' => is_array($primaryFileSpec) && is_string($primaryFileSpec['relationship'] ?? null)
                ? $primaryFileSpec['relationship']
                : null,
            'action_embedded_file_count' => array_sum(array_map(
                static fn (array $fileSpec): int => (int) ($fileSpec['embedded_file_count'] ?? 0),
                $fileSpecs
            )),
            'action_embedded_file_objects' => $this->uniqueIntegersFromRows($fileSpecs, 'embedded_file_objects'),
            'action_embedded_file_hashes' => $this->uniqueStringsFromRows($fileSpecs, 'embedded_file_hashes'),
            'action_file_spec_review_only' => $fileSpecs !== [],
            'action_file_spec_payload_text_exposed' => false,
            'operation' => is_string($action['operation'] ?? null) ? $action['operation'] : null,
            'destination' => is_string($action['destination'] ?? null) ? $action['destination'] : null,
            'destination_action_name' => is_string($action['destination_action_name'] ?? null) ? $action['destination_action_name'] : null,
            'destination_action_target_page' => is_int($action['destination_action_target_page'] ?? null) ? $action['destination_action_target_page'] : null,
            'destination_action_target_page_label' => is_string($action['destination_action_target_page_label'] ?? null) ? $action['destination_action_target_page_label'] : null,
            'destination_action_target_display_duration' => is_int($action['destination_action_target_display_duration'] ?? null) || is_float($action['destination_action_target_display_duration'] ?? null)
                ? (float) $action['destination_action_target_display_duration']
                : null,
            'destination_action_target_page_transition' => is_array($action['destination_action_target_page_transition'] ?? null)
                ? $action['destination_action_target_page_transition']
                : null,
            'destination_action_target_page_actions' => is_array($action['destination_action_target_page_actions'] ?? null)
                ? $action['destination_action_target_page_actions']
                : [],
            'destination_action_target_article_beads' => is_array($action['destination_action_target_article_beads'] ?? null)
                ? $action['destination_action_target_article_beads']
                : [],
            'destination_action_target_article_thread_titles' => $this->stringList($action['destination_action_target_article_thread_titles'] ?? []),
            'destination_page' => is_int($action['destination_page'] ?? null) ? $action['destination_page'] : null,
            'page' => is_int($action['page'] ?? null) ? $action['page'] : null,
            'target_display_duration' => is_int($action['target_display_duration'] ?? null) || is_float($action['target_display_duration'] ?? null)
                ? (float) $action['target_display_duration']
                : null,
            'target_page_transition' => is_array($action['target_page_transition'] ?? null) ? $action['target_page_transition'] : null,
            'target_page_actions' => is_array($action['target_page_actions'] ?? null) ? $action['target_page_actions'] : [],
            'target_article_beads' => is_array($action['target_article_beads'] ?? null) ? $action['target_article_beads'] : [],
            'target_article_thread_titles' => $this->stringList($action['target_article_thread_titles'] ?? []),
            'new_window' => is_bool($action['new_window'] ?? null) ? $action['new_window'] : null,
            'is_safe_uri' => $this->documentActionSafeUri($action),
            'chained' => is_bool($action['chained'] ?? null) ? $action['chained'] : false,
            'chain_index' => is_int($action['chain_index'] ?? null) ? $action['chain_index'] : null,
            'action_field_objects' => $this->integerList($action['field_objects'] ?? []),
            'action_field_names' => $this->stringList($action['field_names'] ?? []),
            'unresolved_field_objects' => $this->integerList($action['unresolved_field_objects'] ?? []),
            'flags' => is_int($action['flags'] ?? null) ? $action['flags'] : null,
            'flag_names' => $this->stringList($action['flag_names'] ?? []),
            'fields_mode' => is_string($action['fields_mode'] ?? null) ? $action['fields_mode'] : null,
            'submit_format' => is_string($action['submit_format'] ?? null) ? $action['submit_format'] : null,
            'include_no_value_fields' => is_bool($action['include_no_value_fields'] ?? null) ? $action['include_no_value_fields'] : null,
            'reset_to_default' => is_bool($action['reset_to_default'] ?? null) ? $action['reset_to_default'] : null,
            'review_only' => true,
            'executes_on_import' => false,
            'executes_action' => false,
        ];
        if ($targetAssociatedFiles !== []) {
            $row['destination_action_target_page_associated_file_count'] = count($targetAssociatedFiles);
            $row['destination_action_target_page_associated_files'] = $targetAssociatedFiles;
            $row['destination_action_target_page_associated_file_filenames'] = $this->associatedFileStringColumn($targetAssociatedFiles, 'filename');
            $row['destination_action_target_page_associated_file_relationships'] = $this->associatedFileStringColumn($targetAssociatedFiles, 'relationship');
            $row['destination_action_target_page_associated_file_checksum_statuses'] = $this->associatedFileChecksumStatuses($targetAssociatedFiles);
            $row['destination_action_target_page_associated_file_review_only'] = true;
        }
        $row['action_container_object'] = $this->documentActionContainerObject($row);
        $row['action_container_source'] = $this->documentActionContainerSource($row);

        $actions[] = $row;
    }

    /**
     * @param list<array<string, mixed>> $actions
     * @return array<string, mixed>
     */
    private function actionFileSpecSecurityReview(array $actions): array
    {
        $fileSpecs = [];
        foreach ($actions as $action) {
            foreach ($action['action_file_specs'] ?? [] as $fileSpec) {
                if (is_array($fileSpec)) {
                    $fileSpecs[] = $fileSpec;
                }
            }
        }

        return [
            'source' => 'document_action_filespec_security_review',
            'present' => $fileSpecs !== [],
            'file_spec_count' => count($fileSpecs),
            'file_spec_objects' => $this->uniqueIntegerColumn($fileSpecs, 'file_spec_object'),
            'filenames' => $this->uniqueStringColumn($fileSpecs, 'filename'),
            'relationships' => $this->uniqueStringColumn($fileSpecs, 'relationship'),
            'scopes' => $this->uniqueStringColumn($fileSpecs, 'review_scope'),
            'action_sources' => $this->uniqueStringColumn($fileSpecs, 'action_source'),
            'action_types' => $this->uniqueStringColumn($fileSpecs, 'action_type'),
            'embedded_file_count' => array_sum(array_map(
                static fn (array $fileSpec): int => (int) ($fileSpec['embedded_file_count'] ?? 0),
                $fileSpecs
            )),
            'embedded_file_objects' => $this->uniqueIntegersFromRows($fileSpecs, 'embedded_file_objects'),
            'embedded_file_hashes' => $this->uniqueStringsFromRows($fileSpecs, 'embedded_file_hashes'),
            'related_file_count' => array_sum(array_map(
                static fn (array $fileSpec): int => (int) ($fileSpec['related_file_count'] ?? 0),
                $fileSpecs
            )),
            'related_file_objects' => $this->uniqueIntegersFromRows($fileSpecs, 'related_file_objects'),
            'related_file_hashes' => $this->uniqueStringsFromRows($fileSpecs, 'related_file_hashes'),
            'file_specs' => $fileSpecs,
            'review_only' => true,
            'payload_text_exposed' => false,
            'embedded_payload_text_exposed' => false,
            'content_returned' => false,
            'executes_pdf_actions' => false,
            'executes_external_file_launch' => false,
            'executes_signature_validation' => false,
            'executes_trust_chain_validation' => false,
        ];
    }

    /**
     * @param array<string, mixed> $action
     * @return list<array<string, mixed>>
     */
    private function documentActionFileSpecReviews(array $action): array
    {
        $reviews = [];
        foreach ([
            'target_file_spec' => $action['file_spec'] ?? null,
            'platform_file_spec' => $action['platform_file_spec'] ?? null,
            'attachment_file_spec' => $action['attachment'] ?? null,
        ] as $scope => $fileSpec) {
            if (!is_array($fileSpec)) {
                continue;
            }

            $review = $this->documentActionFileSpecReview($fileSpec, $scope, $action);
            if ($review !== null) {
                $reviews[] = $review;
            }
        }

        return $this->dedupeDocumentActionFileSpecs($reviews);
    }

    /**
     * @param array<string, mixed> $fileSpec
     * @param array<string, mixed> $action
     * @return array<string, mixed>|null
     */
    private function documentActionFileSpecReview(array $fileSpec, string $scope, array $action): ?array
    {
        $filename = $this->firstStringValue($fileSpec, ['filename', 'unicode_filename', 'file']);
        $fileSpecObject = is_int($fileSpec['file_spec_object'] ?? null) ? $fileSpec['file_spec_object'] : null;
        if ($filename === null && $fileSpecObject === null) {
            return null;
        }

        $embeddedObjects = $this->integerList($fileSpec['embedded_file_objects'] ?? []);
        $embeddedHashes = $this->fileSpecEmbeddedHashes($fileSpec);
        $related = $this->fileSpecRelatedReview($fileSpec);

        return array_filter([
            'source' => 'document_action_filespec_review',
            'review_scope' => $scope,
            'action_source' => is_string($action['source'] ?? null) ? $action['source'] : null,
            'action_type' => is_string($action['action_type'] ?? null) ? $action['action_type'] : null,
            'action_object' => is_int($action['action_object'] ?? null) ? $action['action_object'] : null,
            'field_name' => is_string($action['field_name'] ?? null) ? $action['field_name'] : null,
            'file_spec_source' => is_string($fileSpec['source'] ?? null) ? $fileSpec['source'] : null,
            'file_spec_object' => $fileSpecObject,
            'type' => is_string($fileSpec['type'] ?? null) ? $fileSpec['type'] : null,
            'file_system' => is_string($fileSpec['file_system'] ?? null) ? $fileSpec['file_system'] : null,
            'filename' => $filename,
            'unicode_filename' => is_string($fileSpec['unicode_filename'] ?? null) ? $fileSpec['unicode_filename'] : null,
            'description' => is_string($fileSpec['description'] ?? null) ? $fileSpec['description'] : null,
            'relationship' => is_string($fileSpec['relationship'] ?? null) ? $fileSpec['relationship'] : null,
            'platform_filenames' => is_array($fileSpec['platform_filenames'] ?? null) ? $fileSpec['platform_filenames'] : [],
            'embedded_file_count' => (int) ($fileSpec['embedded_file_count'] ?? count($embeddedObjects)),
            'embedded_file_objects' => $embeddedObjects,
            'embedded_file_hashes' => $embeddedHashes,
            'embedded_file_mime_types' => $this->fileSpecEmbeddedMimeTypes($fileSpec),
            'related_file_count' => (int) ($fileSpec['related_file_count'] ?? count($related['objects'])),
            'related_file_objects' => $related['objects'],
            'related_file_hashes' => $related['hashes'],
            'content_returned' => false,
            'payload_text_exposed' => false,
            'embedded_payload_text_exposed' => false,
            'review_only' => true,
            'executes_action' => false,
        ], static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * @param list<array<string, mixed>> $reviews
     * @return list<array<string, mixed>>
     */
    private function dedupeDocumentActionFileSpecs(array $reviews): array
    {
        $seen = [];
        $deduped = [];
        foreach ($reviews as $review) {
            $key = (string) ($review['review_scope'] ?? 'scope') . ':';
            $key .= is_int($review['file_spec_object'] ?? null)
                ? 'obj:' . $review['file_spec_object']
                : 'name:' . (string) ($review['filename'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $deduped[] = $review;
        }

        return $deduped;
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string> $keys
     */
    private function firstStringValue(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (is_string($row[$key] ?? null) && $row[$key] !== '') {
                return $row[$key];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $fileSpec
     * @return list<string>
     */
    private function fileSpecEmbeddedHashes(array $fileSpec): array
    {
        $hashes = [];
        foreach ($fileSpec['embedded_files'] ?? [] as $embedded) {
            if (!is_array($embedded)) {
                continue;
            }
            foreach (['decoded_sha256', 'sha256'] as $key) {
                if (is_string($embedded[$key] ?? null) && !in_array($embedded[$key], $hashes, true)) {
                    $hashes[] = $embedded[$key];
                }
            }
        }

        foreach ($fileSpec['embedded_file_streams'] ?? [] as $embedded) {
            if (!is_array($embedded)) {
                continue;
            }
            foreach (['decoded_sha256', 'sha256'] as $key) {
                if (is_string($embedded[$key] ?? null) && !in_array($embedded[$key], $hashes, true)) {
                    $hashes[] = $embedded[$key];
                }
            }
        }

        return $hashes;
    }

    /**
     * @param array<string, mixed> $fileSpec
     * @return list<string>
     */
    private function fileSpecEmbeddedMimeTypes(array $fileSpec): array
    {
        $mimeTypes = $this->stringList($fileSpec['mime_types'] ?? []);
        foreach ($fileSpec['embedded_files'] ?? [] as $embedded) {
            if (!is_array($embedded)) {
                continue;
            }
            if (is_string($embedded['subtype'] ?? null) && !in_array($embedded['subtype'], $mimeTypes, true)) {
                $mimeTypes[] = $embedded['subtype'];
            }
        }

        return $mimeTypes;
    }

    /**
     * @param array<string, mixed> $fileSpec
     * @return array{objects: list<int>, hashes: list<string>}
     */
    private function fileSpecRelatedReview(array $fileSpec): array
    {
        $objects = [];
        $hashes = [];
        foreach ($fileSpec['related_files'] ?? [] as $related) {
            if (!is_array($related) || !is_array($related['embedded_file'] ?? null)) {
                continue;
            }

            $embedded = $related['embedded_file'];
            if (is_int($embedded['object'] ?? null) && !in_array($embedded['object'], $objects, true)) {
                $objects[] = $embedded['object'];
            }
            foreach (['decoded_sha256', 'sha256'] as $key) {
                if (is_string($embedded[$key] ?? null) && !in_array($embedded[$key], $hashes, true)) {
                    $hashes[] = $embedded[$key];
                }
            }
        }

        return ['objects' => $objects, 'hashes' => $hashes];
    }

    /**
     * @param array<string, mixed> $action
     * @return list<array<string, mixed>>
     */
    private function targetPageAssociatedFileReviews(array $action): array
    {
        foreach (['destination_action_target_page_review', 'target_page_review'] as $reviewKey) {
            $review = $action[$reviewKey] ?? null;
            if (!is_array($review) || !is_array($review['page_associated_files'] ?? null)) {
                continue;
            }

            return $this->uniqueAssociatedFileReviews(array_map(
                fn (mixed $file): array => is_array($file) ? $this->compactAssociatedFileReview($file) : [],
                $review['page_associated_files']
            ));
        }

        return [];
    }

    /**
     * @param array<string, mixed> $file
     * @return array<string, mixed>
     */
    private function compactAssociatedFileReview(array $file): array
    {
        $review = [];
        foreach ([
            'source',
            'associated_file',
            'associated_file_index',
            'name',
            'filename',
            'unicode_filename',
            'description',
            'relationship',
            'relationship_role',
            'mime_type',
            'size',
            'checksum_algorithm',
            'checksum',
            'computed_checksum',
            'checksum_matches',
            'content_sha256',
            'modified_at',
            'file_spec_object',
            'embedded_file_object',
        ] as $key) {
            if (array_key_exists($key, $file)) {
                $review[$key] = $file[$key];
            }
        }

        $review['payload_content_exposed'] = false;
        $review['review_only'] = true;

        return array_filter($review, static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * @param list<array<string, mixed>> $actions
     * @return list<array<string, mixed>>
     */
    private function uniqueDestinationActionTargetAssociatedFiles(array $actions): array
    {
        $files = [];
        foreach ($actions as $action) {
            foreach ($action['destination_action_target_page_associated_files'] ?? [] as $file) {
                if (is_array($file)) {
                    $files[] = $file;
                }
            }
        }

        return $this->uniqueAssociatedFileReviews($files);
    }

    /**
     * @param list<array<string, mixed>> $files
     * @return list<array<string, mixed>>
     */
    private function uniqueAssociatedFileReviews(array $files): array
    {
        $unique = [];
        $seen = [];
        foreach ($files as $file) {
            if ($file === []) {
                continue;
            }

            $key = $this->associatedFileReviewKey($file);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $file;
        }

        return $unique;
    }

    /**
     * @param array<string, mixed> $file
     */
    private function associatedFileReviewKey(array $file): string
    {
        return implode('|', [
            is_int($file['file_spec_object'] ?? null) ? (string) $file['file_spec_object'] : '',
            is_string($file['filename'] ?? null) ? $file['filename'] : '',
            is_string($file['content_sha256'] ?? null) ? $file['content_sha256'] : '',
        ]);
    }

    /**
     * @param list<array<string, mixed>> $files
     * @return list<string>
     */
    private function associatedFileStringColumn(array $files, string $key): array
    {
        $values = [];
        foreach ($files as $file) {
            if (is_string($file[$key] ?? null) && !in_array($file[$key], $values, true)) {
                $values[] = $file[$key];
            }
        }

        return $values;
    }

    /**
     * @param list<array<string, mixed>> $files
     * @return list<string>
     */
    private function associatedFileChecksumStatuses(array $files): array
    {
        $statuses = [];
        foreach ($files as $file) {
            if (array_key_exists('checksum_matches', $file)) {
                $status = ($file['checksum_matches'] ?? false) === true ? 'checksum_matched' : 'checksum_mismatch';
            } elseif (is_string($file['checksum'] ?? null) || is_string($file['computed_checksum'] ?? null)) {
                $status = 'checksum_present_unverified';
            } else {
                $status = 'checksum_absent';
            }

            if (!in_array($status, $statuses, true)) {
                $statuses[] = $status;
            }
        }

        return $statuses;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function documentActionContainerObject(array $row): ?int
    {
        foreach (['annotation_object', 'widget_object', 'field_object', 'page_object', 'outline_object', 'catalog_object'] as $key) {
            if (is_int($row[$key] ?? null)) {
                return $row[$key];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function documentActionContainerSource(array $row): ?string
    {
        foreach (['annotation_object', 'widget_object', 'field_object', 'page_object', 'outline_object', 'catalog_object'] as $key) {
            if (is_int($row[$key] ?? null)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $action
     */
    private function documentActionSafety(array $action): ?string
    {
        if (is_string($action['safety'] ?? null)) {
            return $action['safety'];
        }

        return match ($action['action_type'] ?? null) {
            'JavaScript' => 'blocked-javascript',
            'Launch' => 'launch-action-review',
            'SubmitForm' => 'submit-form-action-review',
            'ResetForm' => 'reset-form-action-review',
            'ImportData' => 'import-data-action-review',
            'Hide' => 'hide-action-review',
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $action
     */
    private function documentActionUri(array $action): ?string
    {
        if (is_string($action['uri'] ?? null)) {
            return $action['uri'];
        }

        return ($action['action_type'] ?? null) === 'URI' && is_string($action['target'] ?? null)
            ? $action['target']
            : null;
    }

    /**
     * @param array<string, mixed> $action
     */
    private function documentActionFile(array $action): ?string
    {
        if (is_string($action['file'] ?? null)) {
            return $action['file'];
        }

        return in_array($action['action_type'] ?? null, ['GoToR', 'Launch', 'ImportData'], true) && is_string($action['target'] ?? null)
            ? $action['target']
            : null;
    }

    /**
     * @param array<string, mixed> $action
     */
    private function documentActionSafeUri(array $action): ?bool
    {
        if (is_bool($action['is_safe_uri'] ?? null)) {
            return $action['is_safe_uri'];
        }
        if (is_bool($action['safe_uri'] ?? null)) {
            return $action['safe_uri'];
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $actions
     * @param list<array<string, mixed>> $signatures
     * @param array<int, array{offset: int, end: int, length: int, generation: int}> $objectSpans
     * @return list<array<string, mixed>>
     */
    private function annotateDocumentActionByteRangeReviews(array $actions, array $signatures, array $objectSpans): array
    {
        foreach ($actions as $index => $action) {
            $review = $this->documentActionByteRangeReview($action, $signatures, $objectSpans);
            $actions[$index]['action_object_span'] = $review['action_object_span'];
            $actions[$index]['action_byte_range_review_object'] = $review['action_byte_range_review_object'];
            $actions[$index]['action_byte_range_review_source'] = $review['action_byte_range_review_source'];
            $actions[$index]['signature_byte_range_coverage_status'] = $review['status'];
            $actions[$index]['covered_by_all_signature_byte_ranges'] = $review['covered_by_all_signature_byte_ranges'];
            $actions[$index]['outside_any_signature_byte_range'] = $review['outside_any_signature_byte_range'];
            $actions[$index]['signature_byte_range_signed_coverage_count'] = $review['signed_coverage_count'];
            $actions[$index]['signature_byte_range_unsigned_coverage_count'] = $review['unsigned_coverage_count'];
            $actions[$index]['signature_byte_range_reviews'] = $review['signature_reviews'];
        }

        return $actions;
    }

    /**
     * @param array<string, mixed> $action
     * @param list<array<string, mixed>> $signatures
     * @param array<int, array{offset: int, end: int, length: int, generation: int}> $objectSpans
     * @return array{
     *     action_object_span: array{offset: int, end: int, length: int, generation: int}|null,
     *     action_byte_range_review_object: int|null,
     *     action_byte_range_review_source: string|null,
     *     status: string,
     *     covered_by_all_signature_byte_ranges: bool,
     *     outside_any_signature_byte_range: bool,
     *     signed_coverage_count: int,
     *     unsigned_coverage_count: int,
     *     signature_reviews: list<array<string, mixed>>
     * }
     */
    private function documentActionByteRangeReview(array $action, array $signatures, array $objectSpans): array
    {
        $actionObject = is_int($action['action_object'] ?? null) ? $action['action_object'] : null;
        $containerObject = is_int($action['action_container_object'] ?? null) ? $action['action_container_object'] : null;
        $reviewObject = $actionObject ?? $containerObject;
        $reviewSource = $actionObject !== null ? 'action_object' : ($reviewObject === null ? null : 'action_container_object');
        if ($reviewObject === null) {
            return [
                'action_object_span' => null,
                'action_byte_range_review_object' => null,
                'action_byte_range_review_source' => null,
                'status' => 'action_object_unresolved',
                'covered_by_all_signature_byte_ranges' => false,
                'outside_any_signature_byte_range' => false,
                'signed_coverage_count' => 0,
                'unsigned_coverage_count' => 0,
                'signature_reviews' => [],
            ];
        }

        $span = $objectSpans[$reviewObject] ?? null;
        if ($span === null) {
            return [
                'action_object_span' => null,
                'action_byte_range_review_object' => $reviewObject,
                'action_byte_range_review_source' => $reviewSource,
                'status' => $reviewSource === 'action_container_object'
                    ? 'action_container_object_span_unresolved'
                    : 'action_object_span_unresolved',
                'covered_by_all_signature_byte_ranges' => false,
                'outside_any_signature_byte_range' => false,
                'signed_coverage_count' => 0,
                'unsigned_coverage_count' => 0,
                'signature_reviews' => [],
            ];
        }

        $signatureReviews = [];
        $signedCoverageCount = 0;
        $unsignedCoverageCount = 0;
        foreach ($signatures as $signature) {
            $byteRange = is_array($signature['byte_range'] ?? null) ? $signature['byte_range'] : [];
            if (($byteRange['present'] ?? false) !== true) {
                continue;
            }

            $coverage = $this->signatureByteRangeSpanCoverage($span, $byteRange);
            if ($coverage['covered']) {
                $signedCoverageCount++;
            } else {
                $unsignedCoverageCount++;
            }

            $signatureReviews[] = [
                'field_name' => $signature['field_name'] ?? null,
                'signature_object' => $signature['signature_object'] ?? null,
                'byte_range_status' => $byteRange['status'] ?? null,
                'byte_range_valid' => (bool) ($byteRange['valid'] ?? false),
                'coverage_status' => $coverage['status'],
                'covered' => $coverage['covered'],
                'outside_signed_revision' => $coverage['status'] === 'outside_signed_revision',
            ];
        }

        $reviewCount = count($signatureReviews);
        if ($reviewCount === 0) {
            $status = 'no_signature_byte_range';
        } elseif ($signedCoverageCount === $reviewCount) {
            $status = 'covered_by_all_signature_byte_ranges';
        } elseif ($signedCoverageCount > 0) {
            $status = 'covered_by_some_signature_byte_ranges';
        } else {
            $status = 'outside_all_signature_byte_ranges';
        }

        return [
            'action_object_span' => $span,
            'action_byte_range_review_object' => $reviewObject,
            'action_byte_range_review_source' => $reviewSource,
            'status' => $status,
            'covered_by_all_signature_byte_ranges' => $reviewCount > 0 && $signedCoverageCount === $reviewCount,
            'outside_any_signature_byte_range' => $unsignedCoverageCount > 0,
            'signed_coverage_count' => $signedCoverageCount,
            'unsigned_coverage_count' => $unsignedCoverageCount,
            'signature_reviews' => $signatureReviews,
        ];
    }

    /**
     * @param array{offset: int, end: int, length: int, generation: int} $span
     * @param array<string, mixed> $byteRange
     * @return array{status: string, covered: bool}
     */
    private function signatureByteRangeSpanCoverage(array $span, array $byteRange): array
    {
        $segments = array_values(array_filter(
            $byteRange['segments'] ?? [],
            static fn (mixed $segment): bool => is_array($segment)
                && is_int($segment['offset'] ?? null)
                && is_int($segment['end'] ?? null)
        ));
        if ($segments === []) {
            return ['status' => 'signature_byte_range_unresolved', 'covered' => false];
        }

        if ($this->spanCoveredByByteRangeSegments($span, $segments)) {
            return ['status' => 'covered_by_signed_segments', 'covered' => true];
        }

        $lastEnd = max(array_map(static fn (array $segment): int => (int) $segment['end'], $segments));
        if ($span['offset'] >= $lastEnd) {
            return ['status' => 'outside_signed_revision', 'covered' => false];
        }

        foreach ($byteRange['gaps'] ?? [] as $gap) {
            if (
                is_array($gap)
                && is_int($gap['offset'] ?? null)
                && is_int($gap['end'] ?? null)
                && $span['offset'] >= $gap['offset']
                && $span['end'] <= $gap['end']
            ) {
                return ['status' => 'inside_unsigned_gap', 'covered' => false];
            }
            if (
                is_array($gap)
                && is_int($gap['offset'] ?? null)
                && is_int($gap['end'] ?? null)
                && $span['offset'] < $gap['end']
                && $span['end'] > $gap['offset']
            ) {
                return ['status' => 'crosses_unsigned_gap', 'covered' => false];
            }
        }

        foreach ($segments as $segment) {
            if ($span['offset'] < $segment['end'] && $span['end'] > $segment['offset']) {
                return ['status' => 'partially_covered_by_signed_segments', 'covered' => false];
            }
        }

        return ['status' => 'outside_signed_segments', 'covered' => false];
    }

    /**
     * @param array{offset: int, end: int, length: int, generation: int} $span
     * @param list<array<string, mixed>> $segments
     */
    private function spanCoveredByByteRangeSegments(array $span, array $segments): bool
    {
        $cursor = $span['offset'];
        foreach ($segments as $segment) {
            $start = (int) $segment['offset'];
            $end = (int) $segment['end'];
            if ($cursor < $start) {
                return false;
            }
            if ($cursor >= $start && $cursor < $end) {
                $cursor = max($cursor, min($span['end'], $end));
                if ($cursor >= $span['end']) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $actions
     * @return list<int>
     */
    private function postSignatureActionObjects(array $actions): array
    {
        $objects = [];
        foreach ($actions as $action) {
            $reviewObject = is_int($action['action_byte_range_review_object'] ?? null)
                ? $action['action_byte_range_review_object']
                : (is_int($action['action_object'] ?? null) ? $action['action_object'] : null);
            if (!$this->actionIsOutsideSignedRevision($action) || $reviewObject === null) {
                continue;
            }
            if (!in_array($reviewObject, $objects, true)) {
                $objects[] = $reviewObject;
            }
        }

        return $objects;
    }

    /**
     * @param list<array<string, mixed>> $actions
     */
    private function postSignatureActionCount(array $actions): int
    {
        return count(array_filter(
            $actions,
            fn (array $action): bool => $this->actionIsOutsideSignedRevision($action)
        ));
    }

    /**
     * @param array<string, mixed> $action
     */
    private function actionIsOutsideSignedRevision(array $action): bool
    {
        foreach ($action['signature_byte_range_reviews'] ?? [] as $review) {
            if (is_array($review) && ($review['coverage_status'] ?? null) === 'outside_signed_revision') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array{offset: int, end: int, length: int, generation: int}>
     */
    private function pdfObjectByteSpans(string $pdfBytes): array
    {
        $matchCount = preg_match_all('/(\d+)\s+(\d+)\s+obj\b.*?\bendobj/s', $pdfBytes, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        if ($matchCount === false || $matchCount === 0) {
            return [];
        }

        $spans = [];
        foreach ($matches as $match) {
            $object = (int) $match[1][0];
            $offset = $match[0][1];
            $length = strlen($match[0][0]);
            $spans[$object] = [
                'offset' => $offset,
                'end' => $offset + $length,
                'length' => $length,
                'generation' => (int) $match[2][0],
            ];
        }

        return $spans;
    }

    private function catalogObjectNumber(string $pdfBytes): ?int
    {
        $matchCount = preg_match_all('/(\d+)\s+\d+\s+obj\b(.*?)\bendobj/s', $pdfBytes, $matches, PREG_SET_ORDER);
        if ($matchCount === false || $matchCount === 0) {
            return null;
        }

        foreach ($matches as $match) {
            if (preg_match('/\/Type\s*\/Catalog\b/', $match[2]) === 1) {
                return (int) $match[1];
            }
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $actions
     */
    private function documentActionCountBySource(array $actions, string $source): int
    {
        return count(array_filter(
            $actions,
            static fn (array $action): bool => ($action['source'] ?? null) === $source
        ));
    }

    /**
     * @param list<array<string, mixed>> $actions
     * @param list<string> $sources
     */
    private function documentActionCountBySources(array $actions, array $sources): int
    {
        return count(array_filter(
            $actions,
            static fn (array $action): bool => in_array($action['source'] ?? null, $sources, true)
        ));
    }

    /**
     * @param list<array<string, mixed>> $actions
     */
    private function documentActionCountByType(array $actions, string $type): int
    {
        return count(array_filter(
            $actions,
            static fn (array $action): bool => ($action['action_type'] ?? null) === $type
        ));
    }

    /**
     * @param array<string, mixed> $action
     */
    private function isUnsafeDocumentAction(array $action): bool
    {
        $safety = $action['safety'] ?? null;
        $type = $action['action_type'] ?? null;

        return in_array($safety, [
            'blocked-launch',
            'launch-action-review',
            'blocked-javascript',
            'blocked-unsafe-uri',
            'submit-form-action-review',
            'import-data-action-review',
            'reset-form-action-review',
            'hide-action-review',
        ], true)
            || in_array($type, ['Launch', 'JavaScript', 'SubmitForm', 'ImportData', 'ResetForm', 'Hide'], true);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private function uniqueStringColumn(array $rows, string $key): array
    {
        $values = [];
        foreach ($rows as $row) {
            if (!is_string($row[$key] ?? null) || in_array($row[$key], $values, true)) {
                continue;
            }

            $values[] = $row[$key];
        }

        return $values;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<int>
     */
    private function uniqueIntegerColumn(array $rows, string $key): array
    {
        $values = [];
        foreach ($rows as $row) {
            if (!is_int($row[$key] ?? null) || in_array($row[$key], $values, true)) {
                continue;
            }

            $values[] = $row[$key];
        }

        return $values;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private function uniqueNestedStringColumn(array $rows, string $key): array
    {
        $values = [];
        foreach ($rows as $row) {
            foreach ($this->stringList($row[$key] ?? []) as $value) {
                if (!in_array($value, $values, true)) {
                    $values[] = $value;
                }
            }
        }

        return $values;
    }

    /**
     * @param list<array<string, mixed>> $matches
     * @return list<int>
     */
    private function signatureMatchIntegers(array $matches, string $key): array
    {
        $values = [];
        foreach ($matches as $match) {
            if (!is_int($match[$key] ?? null) || in_array($match[$key], $values, true)) {
                continue;
            }

            $values[] = $match[$key];
        }

        return $values;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            $value,
            static fn (mixed $item): bool => is_string($item)
        ));
    }

    /**
     * @return list<int>
     */
    private function integerList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            $value,
            static fn (mixed $item): bool => is_int($item)
        ));
    }

    /**
     * @param list<array<string, mixed>> $matches
     * @return list<string>
     */
    private function signatureMatchStrings(array $matches, string $key): array
    {
        $values = [];
        foreach ($matches as $match) {
            if (!is_string($match[$key] ?? null) || in_array($match[$key], $values, true)) {
                continue;
            }

            $values[] = $match[$key];
        }

        return $values;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<int>
     */
    private function uniqueIntegersFromRows(array $rows, string $key): array
    {
        $values = [];
        foreach ($rows as $row) {
            foreach ($row[$key] ?? [] as $value) {
                if (!is_int($value) || in_array($value, $values, true)) {
                    continue;
                }

                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private function uniqueStringsFromRows(array $rows, string $key): array
    {
        $values = [];
        foreach ($rows as $row) {
            foreach ($row[$key] ?? [] as $value) {
                if (!is_string($value) || in_array($value, $values, true)) {
                    continue;
                }

                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * @param list<array<string, mixed>> $signatures
     * @return list<string>
     */
    private function certifyingPermissionLabels(array $signatures): array
    {
        $labels = [];
        foreach ($signatures as $signature) {
            if (($signature['certifying_signature'] ?? false) !== true) {
                continue;
            }

            foreach ($signature['reference_transforms'] ?? [] as $transform) {
                if (
                    is_array($transform)
                    && ($transform['transform_method'] ?? null) === 'DocMDP'
                    && is_string($transform['permission_label'] ?? null)
                    && !in_array($transform['permission_label'], $labels, true)
                ) {
                    $labels[] = $transform['permission_label'];
                }
            }
        }

        return $labels;
    }

    /**
     * @param array<string, mixed> $field
     * @param array<string, mixed> $signature
     * @param array<string, mixed> $state
     * @param array<string, mixed> $byteRange
     * @param list<array<string, mixed>> $referenceTransforms
     * @param array<string, mixed> $contentsDigest
     * @param array<string, mixed> $documentSecurityStore
     * @return array<string, mixed>
     */
    private function signatureSecurityReview(
        array $field,
        array $signature,
        array $state,
        array $byteRange,
        array $referenceTransforms,
        array $contentsDigest,
        array $documentSecurityStore
    ): array {
        $docMdp = $this->docMdpTransform($referenceTransforms);
        $dssMatch = $this->dssVriMatch($contentsDigest, $documentSecurityStore);
        $certifying = (bool) ($state['certifying_signature'] ?? ($field['certifying_signature'] ?? false));

        return [
            'source' => 'byte_range_dss_doc_mdp_signature_review',
            'field_name' => $field['name'] ?? null,
            'field_object' => $field['object'] ?? null,
            'signature_object' => $state['signature_object'] ?? ($signature['object'] ?? null),
            'signed' => (bool) ($state['signed'] ?? false),
            'filter' => $signature['filter'] ?? null,
            'subfilter' => $signature['subfilter'] ?? null,
            'byte_range_present' => (bool) ($byteRange['present'] ?? false),
            'byte_range_valid' => (bool) ($byteRange['valid'] ?? false),
            'byte_range_status' => $byteRange['status'] ?? null,
            'byte_range_gap_count' => (int) ($byteRange['gap_count'] ?? 0),
            'byte_range_covers_signature_contents' => (bool) ($byteRange['has_signature_contents_gap'] ?? false),
            'contents_digest_present' => (bool) ($contentsDigest['present'] ?? false),
            'contents_digest_bytes' => $contentsDigest['bytes'] ?? null,
            'doc_mdp_present' => $docMdp !== null,
            'certifying_signature' => $certifying,
            'doc_mdp_permission_level' => $docMdp['permission_level'] ?? null,
            'doc_mdp_permission_label' => $docMdp['permission_label'] ?? null,
            'doc_mdp_allowed_changes' => $docMdp['allowed_changes'] ?? [],
            'doc_mdp_transform_params_version' => $docMdp['transform_params_version'] ?? null,
            'dss_present' => (bool) ($documentSecurityStore['present'] ?? false),
            'dss_vri_match_status' => $dssMatch['status'],
            'dss_vri_key' => $dssMatch['key'],
            'dss_vri_validation_stream_count' => $dssMatch['validation_stream_count'],
            'dss_vri_validation_hashes' => $dssMatch['validation_hashes'],
            'dss_vri_timestamp_update' => $dssMatch['timestamp_update'],
            'review_decision' => $this->signatureReviewDecision($byteRange, $docMdp, $dssMatch),
            'cryptographic_signature_validated' => false,
            'executes_signature_validation' => false,
            'executes_revocation_check' => false,
            'executes_trust_chain_validation' => false,
            'executes_signing' => false,
            'raw_signature_contents_exposed' => false,
            'raw_validation_bytes_exposed' => false,
        ];
    }

    /**
     * @param list<array<string, mixed>> $referenceTransforms
     * @return array<string, mixed>|null
     */
    private function docMdpTransform(array $referenceTransforms): ?array
    {
        foreach ($referenceTransforms as $transform) {
            if (($transform['transform_method'] ?? null) === 'DocMDP') {
                return $transform;
            }
        }

        return null;
    }

    /**
     * @return array{present: bool, bytes: int|null, sha1: string|null, sha256: string|null, raw_bytes_exposed: bool}
     */
    private function emptySignatureContentsDigest(): array
    {
        return [
            'present' => false,
            'bytes' => null,
            'sha1' => null,
            'sha256' => null,
            'raw_bytes_exposed' => false,
        ];
    }

    /**
     * @param array<string, mixed> $contentsDigest
     * @param array<string, mixed> $documentSecurityStore
     * @return array{status: string, key: string|null, validation_stream_count: int, validation_hashes: array<string, mixed>, timestamp_update: string|null}
     */
    private function dssVriMatch(array $contentsDigest, array $documentSecurityStore): array
    {
        $empty = [
            'status' => 'dss_absent',
            'key' => null,
            'validation_stream_count' => 0,
            'validation_hashes' => [
                'certificates' => [],
                'ocsps' => [],
                'crls' => [],
                'timestamp_token' => null,
            ],
            'timestamp_update' => null,
        ];

        if (($documentSecurityStore['present'] ?? false) !== true) {
            return $empty;
        }
        if (($contentsDigest['present'] ?? false) !== true) {
            $empty['status'] = 'signature_contents_digest_unavailable';
            return $empty;
        }

        $targets = [];
        if (is_string($contentsDigest['sha1'] ?? null) && $contentsDigest['sha1'] !== '') {
            $targets[strtoupper($contentsDigest['sha1'])] = 'matched_signature_contents_sha1';
        }
        if (is_string($contentsDigest['sha256'] ?? null) && $contentsDigest['sha256'] !== '') {
            $targets[strtoupper($contentsDigest['sha256'])] = 'matched_signature_contents_sha256';
        }

        $vriRows = array_values(array_filter(
            $documentSecurityStore['vri'] ?? [],
            static fn (mixed $row): bool => is_array($row)
        ));
        if ($vriRows === []) {
            $empty['status'] = 'dss_vri_absent';
            return $empty;
        }

        foreach ($vriRows as $row) {
            if (!is_string($row['key'] ?? null)) {
                continue;
            }

            $normalized = strtoupper($row['key']);
            if (!isset($targets[$normalized])) {
                continue;
            }

            return [
                'status' => $targets[$normalized],
                'key' => $row['key'],
                'validation_stream_count' => $this->dssVriValidationStreamCount($row),
                'validation_hashes' => $this->dssVriValidationHashes($row),
                'timestamp_update' => is_string($row['timestamp_update'] ?? null) ? $row['timestamp_update'] : null,
            ];
        }

        $empty['status'] = 'dss_vri_not_matched';
        return $empty;
    }

    /**
     * @param array<string, mixed> $documentSecurityStore
     * @param list<array<string, mixed>> $signatures
     * @param array<int, array{offset: int, end: int, length: int, generation: int}> $objectSpans
     * @return array<string, mixed>
     */
    private function documentSecurityStoreSignatureReview(array $documentSecurityStore, array $signatures, array $objectSpans): array
    {
        $present = ($documentSecurityStore['present'] ?? false) === true;
        $vriRows = array_values(array_filter(
            $documentSecurityStore['vri'] ?? [],
            static fn (mixed $row): bool => is_array($row)
        ));
        $signatureDigestIndex = $this->signatureDigestIndex($signatures);
        $rows = [];
        foreach ($vriRows as $vri) {
            $key = is_string($vri['key'] ?? null) ? $vri['key'] : '';
            $normalizedKey = strtoupper($key);
            $matches = $normalizedKey === '' ? [] : ($signatureDigestIndex[$normalizedKey] ?? []);
            $matchStatus = 'no_matching_signature_contents_digest';
            $algorithm = null;
            if ($matches !== []) {
                $first = $matches[0];
                $algorithm = is_string($first['digest_algorithm'] ?? null) ? $first['digest_algorithm'] : null;
                $matchStatus = $algorithm === 'sha256'
                    ? 'matched_signature_contents_sha256'
                    : 'matched_signature_contents_sha1';
            }
            $revisionReviews = $this->dssVriRevisionCoverageReviews($vri, $matches, $objectSpans);
            $revisionStatus = $this->dssVriRevisionStatus($revisionReviews, $matches);
            $referenceTransformRows = $this->dssSignatureReferenceTransformRows($matches);

            $rows[] = [
                'source' => 'dss_vri_signature_digest_review',
                'key' => $key,
                'normalized_key' => $normalizedKey,
                'vri_object_number' => $vri['object_number'] ?? null,
                'match_status' => $matchStatus,
                'signature_digest_algorithm' => $algorithm,
                'matched_signature_count' => count($matches),
                'matched_signature_objects' => $this->signatureMatchIntegers($matches, 'signature_object'),
                'matched_field_names' => $this->signatureMatchStrings($matches, 'field_name'),
                'signature_reference_transform_count' => count($referenceTransformRows),
                'signature_reference_transform_methods' => $this->uniqueStringColumn($referenceTransformRows, 'transform_method'),
                'signature_reference_transform_rows' => $referenceTransformRows,
                'vri_revision_status' => $revisionStatus,
                'vri_in_signed_revision' => $revisionStatus === 'vri_covered_by_signed_revision',
                'vri_after_signed_revision' => $revisionStatus === 'vri_after_signed_revision',
                'revision_coverage_review_count' => count($revisionReviews),
                'revision_coverage_reviews' => $revisionReviews,
                'validation_stream_count' => $this->dssVriValidationStreamCount($vri),
                'validation_hashes' => $this->dssVriValidationHashes($vri),
                'timestamp_update' => is_string($vri['timestamp_update'] ?? null) ? $vri['timestamp_update'] : null,
                'review_only' => true,
                'cryptographic_signature_validated' => false,
                'executes_signature_validation' => false,
                'executes_revocation_check' => false,
                'executes_trust_chain_validation' => false,
                'raw_signature_contents_exposed' => false,
                'raw_validation_bytes_exposed' => false,
            ];
        }

        $matchedRows = array_values(array_filter(
            $rows,
            static fn (array $row): bool => ($row['matched_signature_count'] ?? 0) > 0
        ));
        $unmatchedRows = array_values(array_filter(
            $rows,
            static fn (array $row): bool => ($row['matched_signature_count'] ?? 0) === 0
        ));

        return [
            'source' => 'document_security_store_signature_review',
            'present' => $present,
            'signature_count' => count($signatures),
            'signed_signature_count' => count(array_filter(
                $signatures,
                static fn (array $signature): bool => ($signature['signed'] ?? false) === true
            )),
            'vri_count' => count($rows),
            'matched_vri_count' => count($matchedRows),
            'unmatched_vri_count' => count($unmatchedRows),
            'signature_vri_match_count' => array_sum(array_map(
                static fn (array $row): int => (int) ($row['matched_signature_count'] ?? 0),
                $rows
            )),
            'matched_signature_objects' => $this->uniqueIntegersFromRows($matchedRows, 'matched_signature_objects'),
            'matched_field_names' => $this->uniqueStringsFromRows($matchedRows, 'matched_field_names'),
            'unmatched_vri_keys' => $this->uniqueStringColumn($unmatchedRows, 'key'),
            'vri_match_statuses' => $this->uniqueStringColumn($rows, 'match_status'),
            'vri_with_reference_transform_count' => count(array_filter(
                $rows,
                static fn (array $row): bool => (int) ($row['signature_reference_transform_count'] ?? 0) > 0
            )),
            'signature_reference_transform_count' => array_sum(array_map(
                static fn (array $row): int => (int) ($row['signature_reference_transform_count'] ?? 0),
                $rows
            )),
            'signature_reference_transform_methods' => $this->uniqueStringsFromRows($rows, 'signature_reference_transform_methods'),
            'vri_revision_review_count' => count(array_filter(
                $rows,
                static fn (array $row): bool => (int) ($row['revision_coverage_review_count'] ?? 0) > 0
            )),
            'vri_after_signed_revision_count' => count(array_filter(
                $rows,
                static fn (array $row): bool => ($row['vri_after_signed_revision'] ?? false) === true
            )),
            'vri_in_signed_revision_count' => count(array_filter(
                $rows,
                static fn (array $row): bool => ($row['vri_in_signed_revision'] ?? false) === true
            )),
            'vri_revision_statuses' => $this->uniqueStringColumn($rows, 'vri_revision_status'),
            'vri_signature_rows' => $rows,
            'review_only' => true,
            'executes_signature_validation' => false,
            'executes_revocation_check' => false,
            'executes_trust_chain_validation' => false,
            'raw_signature_contents_exposed' => false,
            'raw_validation_bytes_exposed' => false,
        ];
    }

    /**
     * @param array<string, mixed> $documentSecurityStoreSignatureReview
     * @return array<string, mixed>
     */
    private function documentSecurityStoreSignatureReferenceTransformReview(array $documentSecurityStoreSignatureReview): array
    {
        $vriRows = array_values(array_filter(
            $documentSecurityStoreSignatureReview['vri_signature_rows'] ?? [],
            static fn (mixed $row): bool => is_array($row)
        ));
        $rows = [];
        foreach ($vriRows as $vriRow) {
            foreach ($vriRow['signature_reference_transform_rows'] ?? [] as $row) {
                if (is_array($row)) {
                    $rows[] = $row + [
                        'vri_key' => is_string($vriRow['key'] ?? null) ? $vriRow['key'] : null,
                        'vri_object_number' => is_int($vriRow['vri_object_number'] ?? null) ? $vriRow['vri_object_number'] : null,
                    ];
                }
            }
        }

        $vriWithTransforms = array_values(array_filter(
            $vriRows,
            static fn (array $row): bool => (int) ($row['signature_reference_transform_count'] ?? 0) > 0
        ));

        return [
            'source' => 'document_security_store_signature_reference_transform_review',
            'present' => $rows !== [],
            'vri_count' => count($vriRows),
            'matched_vri_count' => (int) ($documentSecurityStoreSignatureReview['matched_vri_count'] ?? 0),
            'vri_with_reference_transform_count' => count($vriWithTransforms),
            'signature_reference_transform_count' => count($rows),
            'signature_reference_transform_methods' => $this->uniqueStringColumn($rows, 'transform_method'),
            'signature_reference_transform_categories' => $this->uniqueStringColumn($rows, 'transform_category'),
            'doc_mdp_reference_transform_count' => count(array_filter(
                $rows,
                static fn (array $row): bool => ($row['transform_method'] ?? null) === 'DocMDP'
            )),
            'field_mdp_reference_transform_count' => count(array_filter(
                $rows,
                static fn (array $row): bool => ($row['transform_method'] ?? null) === 'FieldMDP'
            )),
            'usage_rights_reference_transform_count' => count(array_filter(
                $rows,
                static fn (array $row): bool => in_array($row['transform_method'] ?? null, ['UR', 'UR3'], true)
            )),
            'vri_keys' => $this->uniqueStringColumn($rows, 'vri_key'),
            'matched_signature_objects' => $this->uniqueIntegerColumn($rows, 'signature_object'),
            'matched_field_names' => $this->uniqueStringColumn($rows, 'field_name'),
            'review_only' => true,
            'executes_signature_validation' => false,
            'executes_revocation_check' => false,
            'executes_trust_chain_validation' => false,
            'executes_rights_enforcement' => false,
            'executes_signing' => false,
            'raw_signature_contents_exposed' => false,
            'raw_digest_values_exposed' => false,
            'raw_validation_bytes_exposed' => false,
            'rows' => $rows,
        ];
    }

    /**
     * @param list<array<string, mixed>> $matches
     * @return list<array<string, mixed>>
     */
    private function dssSignatureReferenceTransformRows(array $matches): array
    {
        $rows = [];
        $seen = [];
        foreach ($matches as $match) {
            $signatureObject = is_int($match['signature_object'] ?? null) ? $match['signature_object'] : null;
            foreach ($match['reference_transforms'] ?? [] as $transform) {
                if (!is_array($transform) || !is_string($transform['transform_method'] ?? null)) {
                    continue;
                }

                $dedupeKey = implode(':', [
                    (string) ($signatureObject ?? 'null'),
                    (string) ($match['digest_algorithm'] ?? 'unknown'),
                    (string) ($transform['object'] ?? 'direct'),
                    $transform['transform_method'],
                ]);
                if (isset($seen[$dedupeKey])) {
                    continue;
                }
                $seen[$dedupeKey] = true;
                $rows[] = $this->dssSignatureReferenceTransformRow($match, $transform);
            }
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $match
     * @param array<string, mixed> $transform
     * @return array<string, mixed>
     */
    private function dssSignatureReferenceTransformRow(array $match, array $transform): array
    {
        $method = is_string($transform['transform_method'] ?? null) ? $transform['transform_method'] : null;
        $row = [
            'source' => 'dss_signature_reference_transform_review_row',
            'field_name' => is_string($match['field_name'] ?? null) ? $match['field_name'] : null,
            'signature_object' => is_int($match['signature_object'] ?? null) ? $match['signature_object'] : null,
            'signature_digest_algorithm' => is_string($match['digest_algorithm'] ?? null) ? $match['digest_algorithm'] : null,
            'transform_object' => is_int($transform['object'] ?? null) ? $transform['object'] : null,
            'transform_type' => is_string($transform['type'] ?? null) ? $transform['type'] : null,
            'transform_method' => $method,
            'transform_category' => is_string($transform['transform_category'] ?? null) ? $transform['transform_category'] : 'unknown',
            'data_object' => is_int($transform['data_object'] ?? null) ? $transform['data_object'] : null,
            'digest_method' => is_string($transform['digest_method'] ?? null) ? $transform['digest_method'] : null,
            'digest_value_present' => ($transform['digest_value_present'] ?? false) === true,
            'digest_value_exposed' => false,
            'transform_params_object' => is_int($transform['transform_params_object'] ?? null) ? $transform['transform_params_object'] : null,
            'transform_params_type' => is_string($transform['transform_params_type'] ?? null) ? $transform['transform_params_type'] : null,
            'transform_params_version' => is_string($transform['transform_params_version'] ?? null) ? $transform['transform_params_version'] : null,
            'review_only' => true,
            'cryptographic_signature_validated' => false,
            'executes_signature_validation' => false,
            'executes_revocation_check' => false,
            'executes_trust_chain_validation' => false,
            'executes_rights_enforcement' => false,
            'executes_signing' => false,
            'raw_signature_contents_exposed' => false,
            'raw_digest_value_exposed' => false,
            'raw_validation_bytes_exposed' => false,
        ];

        if ($method === 'DocMDP') {
            $row += [
                'permission_level' => is_int($transform['permission_level'] ?? null) ? $transform['permission_level'] : null,
                'permission_label' => is_string($transform['permission_label'] ?? null) ? $transform['permission_label'] : null,
                'permission_valid' => ($transform['permission_valid'] ?? false) === true,
                'allowed_changes' => $this->stringList($transform['allowed_changes'] ?? []),
            ];
        } elseif ($method === 'FieldMDP') {
            $row += [
                'field_mdp_action' => is_string($transform['action'] ?? null) ? $transform['action'] : null,
                'field_mdp_action_valid' => ($transform['action_valid'] ?? false) === true,
                'field_mdp_action_label' => is_string($transform['action_label'] ?? null) ? $transform['action_label'] : null,
                'field_mdp_field_names' => $this->stringList($transform['field_names'] ?? []),
                'field_mdp_included_fields' => $this->stringList($transform['included_fields'] ?? []),
                'field_mdp_excluded_fields' => $this->stringList($transform['excluded_fields'] ?? []),
                'field_mdp_locks_all_fields' => ($transform['locks_all_fields'] ?? false) === true,
            ];
        } elseif ($method === 'UR' || $method === 'UR3') {
            $rights = is_array($transform['rights'] ?? null) ? $transform['rights'] : [];
            $row += [
                'usage_right_categories' => $this->stringList($transform['right_categories'] ?? []),
                'usage_right_count' => (int) ($transform['right_count'] ?? 0),
                'usage_rights' => [
                    'document' => $this->stringList($rights['document'] ?? []),
                    'form' => $this->stringList($rights['form'] ?? []),
                    'signature' => $this->stringList($rights['signature'] ?? []),
                    'annotations' => $this->stringList($rights['annotations'] ?? []),
                    'embedded_files' => $this->stringList($rights['embedded_files'] ?? []),
                ],
                'message_present' => is_string($transform['message'] ?? null) && $transform['message'] !== '',
            ];
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $vri
     * @param list<array<string, mixed>> $matches
     * @param array<int, array{offset: int, end: int, length: int, generation: int}> $objectSpans
     * @return list<array<string, mixed>>
     */
    private function dssVriRevisionCoverageReviews(array $vri, array $matches, array $objectSpans): array
    {
        $vriObject = is_int($vri['object_number'] ?? null) ? $vri['object_number'] : null;
        $span = $vriObject === null ? null : ($objectSpans[$vriObject] ?? null);
        $reviews = [];
        foreach ($matches as $match) {
            $byteRange = is_array($match['byte_range'] ?? null) ? $match['byte_range'] : [];
            $coverage = $span === null
                ? ['status' => 'vri_object_span_unresolved', 'covered' => false]
                : $this->signatureByteRangeSpanCoverage($span, $byteRange);

            $reviews[] = [
                'source' => 'dss_vri_signed_revision_coverage_review',
                'vri_key' => is_string($vri['key'] ?? null) ? $vri['key'] : null,
                'vri_object_number' => $vriObject,
                'vri_object_span' => $span,
                'field_name' => is_string($match['field_name'] ?? null) ? $match['field_name'] : null,
                'signature_object' => is_int($match['signature_object'] ?? null) ? $match['signature_object'] : null,
                'signature_digest_algorithm' => is_string($match['digest_algorithm'] ?? null) ? $match['digest_algorithm'] : null,
                'byte_range_status' => is_string($byteRange['status'] ?? null) ? $byteRange['status'] : null,
                'byte_range_revision_status' => is_string($byteRange['revision_status'] ?? null) ? $byteRange['revision_status'] : null,
                'signed_revision_valid' => ($byteRange['signed_revision_valid'] ?? false) === true,
                'signed_revision_end' => is_int($byteRange['signed_revision_end'] ?? null) ? $byteRange['signed_revision_end'] : null,
                'current_revision_tail_bytes' => is_int($byteRange['current_revision_tail_bytes'] ?? null) ? $byteRange['current_revision_tail_bytes'] : null,
                'coverage_status' => $coverage['status'],
                'covered_by_signed_revision' => ($coverage['covered'] ?? false) === true,
                'outside_signed_revision' => ($coverage['status'] ?? null) === 'outside_signed_revision',
                'inside_signature_contents_gap' => ($coverage['status'] ?? null) === 'inside_unsigned_gap',
                'review_only' => true,
                'executes_signature_validation' => false,
                'executes_revocation_check' => false,
                'executes_trust_chain_validation' => false,
            ];
        }

        return $reviews;
    }

    /**
     * @param list<array<string, mixed>> $revisionReviews
     * @param list<array<string, mixed>> $matches
     */
    private function dssVriRevisionStatus(array $revisionReviews, array $matches): string
    {
        if ($matches === []) {
            return 'no_matching_signature_contents_digest';
        }
        if ($revisionReviews === []) {
            return 'vri_revision_unreviewed';
        }
        foreach ($revisionReviews as $review) {
            if (($review['coverage_status'] ?? null) === 'outside_signed_revision') {
                return 'vri_after_signed_revision';
            }
        }
        foreach ($revisionReviews as $review) {
            if (($review['coverage_status'] ?? null) === 'inside_unsigned_gap') {
                return 'vri_inside_signature_contents_gap';
            }
        }
        foreach ($revisionReviews as $review) {
            if (($review['covered_by_signed_revision'] ?? false) === true) {
                return 'vri_covered_by_signed_revision';
            }
        }

        return 'vri_not_covered_by_signed_revision';
    }

    /**
     * @param list<array<string, mixed>> $signatures
     * @return array<string, list<array<string, mixed>>>
     */
    private function signatureDigestIndex(array $signatures): array
    {
        $index = [];
        foreach ($signatures as $signature) {
            $digest = is_array($signature['contents_digest'] ?? null) ? $signature['contents_digest'] : [];
            foreach (['sha1', 'sha256'] as $algorithm) {
                if (!is_string($digest[$algorithm] ?? null) || $digest[$algorithm] === '') {
                    continue;
                }

                $key = strtoupper($digest[$algorithm]);
                $index[$key][] = [
                    'digest_algorithm' => $algorithm,
                    'field_name' => $signature['field_name'] ?? null,
                    'field_object' => $signature['field_object'] ?? null,
                    'signature_object' => $signature['signature_object'] ?? null,
                    'byte_range' => is_array($signature['byte_range'] ?? null) ? $signature['byte_range'] : [],
                    'reference_transform_count' => (int) ($signature['reference_transform_count'] ?? 0),
                    'reference_transform_methods' => $this->stringList($signature['reference_transform_methods'] ?? []),
                    'reference_transforms' => $this->signatureReferenceTransforms($signature),
                ];
            }
        }

        return $index;
    }

    /**
     * @param array<string, mixed> $vri
     */
    private function dssVriValidationStreamCount(array $vri): int
    {
        $count = 0;
        foreach (['certificates', 'ocsps', 'crls'] as $key) {
            $count += count(array_filter(
                $vri[$key] ?? [],
                static fn (mixed $row): bool => is_array($row)
            ));
        }

        return $count + (is_array($vri['timestamp_token'] ?? null) ? 1 : 0);
    }

    /**
     * @param array<string, mixed> $vri
     * @return array<string, mixed>
     */
    private function dssVriValidationHashes(array $vri): array
    {
        return [
            'certificates' => $this->streamHashes($vri['certificates'] ?? []),
            'ocsps' => $this->streamHashes($vri['ocsps'] ?? []),
            'crls' => $this->streamHashes($vri['crls'] ?? []),
            'timestamp_token' => is_array($vri['timestamp_token'] ?? null) && is_string($vri['timestamp_token']['sha256'] ?? null)
                ? $vri['timestamp_token']['sha256']
                : null,
        ];
    }

    /**
     * @return list<string>
     */
    private function streamHashes(mixed $streams): array
    {
        if (!is_array($streams)) {
            return [];
        }

        $hashes = [];
        foreach ($streams as $stream) {
            if (is_array($stream) && is_string($stream['sha256'] ?? null)) {
                $hashes[] = $stream['sha256'];
            }
        }

        return $hashes;
    }

    /**
     * @param array<string, mixed> $byteRange
     * @param array<string, mixed>|null $docMdp
     * @param array{status: string, key: string|null, validation_stream_count: int, validation_hashes: array<string, mixed>, timestamp_update: string|null} $dssMatch
     */
    private function signatureReviewDecision(array $byteRange, ?array $docMdp, array $dssMatch): string
    {
        if (($byteRange['present'] ?? false) === true && ($byteRange['valid'] ?? false) !== true) {
            return 'review_required_signature_boundary';
        }

        $matchedDss = str_starts_with($dssMatch['status'], 'matched_signature_contents_');
        if ($docMdp !== null && $matchedDss) {
            return 'review_required_certifying_signature_with_dss';
        }
        if ($docMdp !== null) {
            return 'review_required_certifying_signature';
        }
        if ($matchedDss) {
            return 'review_required_signature_with_dss';
        }

        return 'review_required_signature_metadata';
    }

    /**
     * @param array<string, mixed> $signature
     * @return list<array<string, mixed>>
     */
    private function signatureReferenceTransforms(array $signature): array
    {
        return array_values(array_filter(
            $signature['reference_transforms'] ?? [],
            static fn (mixed $transform): bool => is_array($transform)
        ));
    }

    /**
     * @param list<array<string, mixed>> $signatures
     */
    private function referenceTransformCount(array $signatures): int
    {
        $count = 0;
        foreach ($signatures as $signature) {
            $count += count(array_filter(
                $signature['reference_transforms'] ?? [],
                static fn (mixed $transform): bool => is_array($transform)
            ));
        }

        return $count;
    }

    /**
     * @param list<array<string, mixed>> $signatures
     * @return list<string>
     */
    private function referenceTransformMethods(array $signatures): array
    {
        $methods = [];
        foreach ($signatures as $signature) {
            foreach ($signature['reference_transforms'] ?? [] as $transform) {
                if (!is_array($transform) || !is_string($transform['transform_method'] ?? null)) {
                    continue;
                }

                if (!in_array($transform['transform_method'], $methods, true)) {
                    $methods[] = $transform['transform_method'];
                }
            }
        }

        return $methods;
    }

    /**
     * @param list<array<string, mixed>> $transforms
     * @return list<string>
     */
    private function transformMethods(array $transforms): array
    {
        $methods = [];
        foreach ($transforms as $transform) {
            if (!is_string($transform['transform_method'] ?? null)) {
                continue;
            }

            if (!in_array($transform['transform_method'], $methods, true)) {
                $methods[] = $transform['transform_method'];
            }
        }

        return $methods;
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
            'signed_revision_valid' => false,
            'revision_status' => is_array($byteRange) ? 'invalid_shape' : 'missing',
            'signed_revision_end' => null,
            'signed_revision_length' => null,
            'covers_current_revision' => false,
            'current_revision_tail_bytes' => null,
            'revision_tail_review_only' => true,
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
        $signedRevisionEnd = is_array($last) && is_int($last['end'] ?? null) ? (int) $last['end'] : null;
        $endsAtFileEnd = is_array($last) && ($last['end'] ?? null) === $fileBytes;
        $hasSignatureContentsGap = $this->hasSignatureContentsGap($pdfBytes, $gaps);
        $signedRevisionValid = $nonNegative && $withinFile && $sorted && $startsAtZero && count($gaps) === 1 && $hasSignatureContentsGap;
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
        $base['signed_revision_valid'] = $signedRevisionValid;
        $base['revision_status'] = $this->byteRangeRevisionStatus($signedRevisionValid, $endsAtFileEnd, $nonNegative, $withinFile, $sorted, $startsAtZero, $gaps, $hasSignatureContentsGap);
        $base['signed_revision_end'] = $signedRevisionEnd;
        $base['signed_revision_length'] = $signedRevisionEnd;
        $base['covers_current_revision'] = $signedRevisionValid && $endsAtFileEnd;
        $base['current_revision_tail_bytes'] = $signedRevisionEnd === null ? null : max(0, $fileBytes - $signedRevisionEnd);
        $base['valid'] = $valid;
        $base['status'] = $this->byteRangeStatus($valid, $nonNegative, $withinFile, $sorted, $startsAtZero, $endsAtFileEnd, $gaps, $hasSignatureContentsGap);

        return $base;
    }

    /**
     * @param list<array{offset: int, length: int, end: int}> $gaps
     */
    private function byteRangeRevisionStatus(
        bool $signedRevisionValid,
        bool $endsAtFileEnd,
        bool $nonNegative,
        bool $withinFile,
        bool $sorted,
        bool $startsAtZero,
        array $gaps,
        bool $hasSignatureContentsGap
    ): string {
        if ($signedRevisionValid && $endsAtFileEnd) {
            return 'covers_current_revision_except_signature_contents';
        }
        if ($signedRevisionValid) {
            return 'covers_prior_revision_except_signature_contents';
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
        if (!$startsAtZero) {
            return 'incomplete_initial_revision_coverage';
        }
        if (count($gaps) !== 1 || !$hasSignatureContentsGap) {
            return 'review_required_non_signature_gap';
        }

        return 'invalid_shape';
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
        $standardHandler = ($encryption['filter'] ?? null) === 'Standard';
        $permissionWellFormed = array_key_exists('reserved_bits_valid', $permissions)
            ? (bool) $permissions['reserved_bits_valid']
            : null;
        $reservedBits = is_array($permissions['reserved_bits'] ?? null) ? $permissions['reserved_bits'] : [];
        $permissionWordReview = is_array($encryption['standard_permission_word_review'] ?? null)
            ? $encryption['standard_permission_word_review']
            : [];
        $permissionWordDuplicateEntries = ($permissionWordReview['duplicate_permission_entries'] ?? false) === true;
        $permissionWordAmbiguous = ($permissionWordReview['permission_word_ambiguous'] ?? false) === true;
        $permissionWordRangeValid = array_key_exists('permission_word_range_valid', $permissions)
            ? (bool) $permissions['permission_word_range_valid']
            : null;
        $standardParameterReview = is_array($encryption['standard_security_handler_parameter_review'] ?? null)
            ? $encryption['standard_security_handler_parameter_review']
            : [];
        $standardParametersMalformed = ($standardParameterReview['parameters_well_formed'] ?? null) === false;
        if ($permissionWordDuplicateEntries) {
            $permissionWellFormed = false;
        }
        $reservedViolations = array_values(array_filter(
            $reservedBits['violations'] ?? [],
            static fn (mixed $value): bool => is_string($value)
        ));
        $permissionBitsReliable = $standardHandler
            && $permissions !== []
            && !$standardParametersMalformed
            && $permissionWellFormed === true
            && !$permissionWordDuplicateEntries
            && $permissionWordRangeValid !== false;
        $reviewAllowed = $permissionBitsReliable ? $allowed : [];
        $reviewDenied = $permissionBitsReliable ? $denied : [];
        $permissionBits = $permissionBitsReliable && is_array($permissions['permission_bits'] ?? null)
            ? $permissions['permission_bits']
            : [];
        $publicKeyRecipientReview = is_array($encryption['public_key_recipient_review'] ?? null)
            ? $encryption['public_key_recipient_review']
            : [];
        $standardAuthenticationReview = is_array($encryption['standard_authentication_review'] ?? null)
            ? $encryption['standard_authentication_review']
            : [];
        $standardAuthenticationMaterialReview = $this->standardAuthenticationMaterialReview(
            $standardAuthenticationReview,
            $standardHandler
        );
        $cryptFilterContentReview = $this->cryptFilterContentReview(true, $encryption);
        $cryptFilterTextPolicy = is_string($cryptFilterContentReview['text_content_policy'] ?? null)
            ? $cryptFilterContentReview['text_content_policy']
            : null;
        $cryptFilterEmbeddedFilePayloadPolicy = is_string($cryptFilterContentReview['embedded_file_payload_policy'] ?? null)
            ? $cryptFilterContentReview['embedded_file_payload_policy']
            : null;
        $cryptFilterEmbeddedFileBoundary = $this->cryptFilterEmbeddedFileBoundary($cryptFilterEmbeddedFilePayloadPolicy);
        $cryptFilterDictionaryDeclarationReview = is_array($encryption['crypt_filter_dictionary_declaration_review'] ?? null)
            ? $encryption['crypt_filter_dictionary_declaration_review']
            : [];
        $cryptFilterRoleDeclarationReview = is_array($encryption['crypt_filter_role_declaration_review'] ?? null)
            ? $encryption['crypt_filter_role_declaration_review']
            : [];
        $permissionAuthenticationTrustReview = $this->standardPermissionAuthenticationTrustReview(
            $encryption,
            $standardHandler,
            $permissions !== [],
            $permissionBitsReliable,
            $standardAuthenticationMaterialReview
        );

        return [
            'is_encrypted' => true,
            'source' => $encryption['source'] ?? null,
            'object_number' => $encryption['object_number'] ?? null,
            'object_generation' => $encryption['object_generation'] ?? null,
            'filter' => $encryption['filter'] ?? null,
            'subfilter' => $encryption['subfilter'] ?? null,
            'algorithm' => $encryption['algorithm'] ?? null,
            'revision_label' => $encryption['revision_label'] ?? null,
            'key_length_bits' => $encryption['key_length_bits'] ?? null,
            'key_length_explicit' => $encryption['key_length_explicit'] ?? null,
            'key_length_defaulted' => $encryption['key_length_defaulted'] ?? null,
            'key_length_source' => $encryption['key_length_source'] ?? null,
            'encrypt_metadata' => $encryption['encrypt_metadata'] ?? null,
            'encrypt_metadata_explicit' => (bool) ($encryption['encrypt_metadata_explicit'] ?? false),
            'encrypt_metadata_trusted' => (bool) ($encryption['encrypt_metadata_trusted'] ?? true),
            'encrypt_metadata_defaulted' => (bool) ($encryption['encrypt_metadata_defaulted'] ?? false),
            'encrypt_metadata_defaulted_fail_closed' => (bool) ($encryption['encrypt_metadata_defaulted_fail_closed'] ?? false),
            'encrypt_metadata_status' => $encryption['encrypt_metadata_status'] ?? null,
            'encrypt_metadata_declaration_review' => is_array($encryption['encrypt_metadata_declaration_review'] ?? null)
                ? $encryption['encrypt_metadata_declaration_review']
                : [],
            'stream_filter' => $encryption['stream_filter'] ?? null,
            'string_filter' => $encryption['string_filter'] ?? null,
            'embedded_file_filter' => $encryption['embedded_file_filter'] ?? null,
            'permission_hex' => $permissions['hex'] ?? null,
            'permission_signed' => $permissions['signed'] ?? null,
            'permission_unsigned' => $permissions['unsigned'] ?? null,
            'permission_word_form' => $permissions['declared_form'] ?? null,
            'permission_normalized_from_unsigned_decimal' => (bool) ($permissions['normalized_from_unsigned_decimal'] ?? false),
            'permission_word_range_valid' => $permissionWordRangeValid,
            'permission_word_range_status' => $permissions['permission_word_range_status'] ?? null,
            'permission_word_range' => is_array($permissions['word_range'] ?? null) ? $permissions['word_range'] : [],
            'allowed' => $reviewAllowed,
            'denied' => $reviewDenied,
            'standard_permission_word_review' => $permissionWordReview,
            'permission_word_duplicate_entries' => $permissionWordDuplicateEntries,
            'permission_word_ambiguous' => $permissionWordAmbiguous,
            'standard_security_handler_parameter_review' => $standardParameterReview,
            'standard_security_handler_parameters_well_formed' => $standardParameterReview['parameters_well_formed'] ?? null,
            'standard_security_handler_parameter_status' => $standardParameterReview['status'] ?? null,
            'standard_security_handler_version_supported' => $standardParameterReview['version_supported'] ?? null,
            'standard_security_handler_revision_supported' => $standardParameterReview['revision_supported'] ?? null,
            'standard_security_handler_version_revision_compatible' => $standardParameterReview['version_revision_compatible'] ?? null,
            'standard_security_handler_key_length_status' => $standardParameterReview['key_length_status'] ?? null,
            'standard_security_handler_key_length_explicit' => $standardParameterReview['key_length_explicit'] ?? null,
            'standard_security_handler_key_length_defaulted' => $standardParameterReview['key_length_defaulted'] ?? null,
            'standard_security_handler_key_length_source' => $standardParameterReview['key_length_source'] ?? null,
            'standard_security_handler_parameter_violations' => is_array($standardParameterReview['violations'] ?? null)
                ? $standardParameterReview['violations']
                : [],
            'standard_security_handler_parameter_declaration_review' => is_array($standardParameterReview['parameter_declaration_review'] ?? null)
                ? $standardParameterReview['parameter_declaration_review']
                : [],
            'standard_security_handler_duplicate_parameter_names' => is_array($standardParameterReview['duplicate_parameter_names'] ?? null)
                ? $standardParameterReview['duplicate_parameter_names']
                : [],
            'standard_security_handler_duplicate_parameter_count' => (int) ($standardParameterReview['duplicate_parameter_count'] ?? 0),
            'malformed_encrypt_dictionary' => (bool) ($encryption['malformed_encrypt_dictionary'] ?? false),
            'encrypt_dictionary_resolved' => array_key_exists('encrypt_dictionary_resolved', $encryption)
                ? (bool) $encryption['encrypt_dictionary_resolved']
                : null,
            'duplicate_encrypt_dictionary_entries' => (bool) ($encryption['duplicate_encrypt_dictionary_entries'] ?? false),
            'encrypt_dictionary_declared_entry_count' => (int) ($encryption['encrypt_dictionary_declared_entry_count'] ?? 0),
            'encrypt_dictionary_resolved_entry_count' => (int) ($encryption['encrypt_dictionary_resolved_entry_count'] ?? 0),
            'encrypt_dictionary_entry_statuses' => is_array($encryption['encrypt_dictionary_entry_statuses'] ?? null)
                ? $encryption['encrypt_dictionary_entry_statuses']
                : [],
            'encrypt_dictionary_entry_shapes' => is_array($encryption['encrypt_dictionary_entry_shapes'] ?? null)
                ? $encryption['encrypt_dictionary_entry_shapes']
                : [],
            'encrypt_operand_shape' => $encryption['encrypt_operand_shape'] ?? null,
            'encrypt_operand_status' => $encryption['encrypt_operand_status'] ?? null,
            'applicable_permission_names' => $permissionBitsReliable && is_array($permissions['applicable_permission_names'] ?? null)
                ? $permissions['applicable_permission_names']
                : [],
            'not_applicable_permission_names' => $permissionBitsReliable && is_array($permissions['not_applicable_permission_names'] ?? null)
                ? $permissions['not_applicable_permission_names']
                : [],
            'permission_bit_review_count' => count($permissionBits),
            'permission_bit_statuses' => $permissionBitsReliable && is_array($permissions['permission_bit_statuses'] ?? null)
                ? $permissions['permission_bit_statuses']
                : [],
            'permission_bits' => $permissionBits,
            'copy_or_extract_allowed' => $permissionBitsReliable
                ? in_array('copy_or_extract', $allowed, true)
                : null,
            'accessibility_extract_allowed' => $permissionBitsReliable
                ? in_array('extract_for_accessibility', $allowed, true)
                : null,
            'print_quality' => $permissionBitsReliable ? ($permissions['print_quality'] ?? null) : null,
            'permission_word_status' => $permissionWordDuplicateEntries
                ? 'duplicate_standard_permission_entries_review'
                : ($permissions['permission_word_status'] ?? (
                    $permissionWordAmbiguous ? ($permissionWordReview['status'] ?? null) : null
                )),
            'permission_word_well_formed' => $standardParametersMalformed
                ? false
                : (
                    $standardHandler && $permissions !== []
                        ? $permissionWellFormed
                        : ($standardHandler && $permissionWordAmbiguous ? false : null)
                ),
            'permission_bits_reliable' => $permissionBitsReliable,
            'permission_authentication_trust_review' => $permissionAuthenticationTrustReview,
            'permission_bits_authentication_required' => (bool) ($permissionAuthenticationTrustReview['authentication_required'] ?? false),
            'permission_bits_authenticated' => (bool) ($permissionAuthenticationTrustReview['permissions_authenticated'] ?? false),
            'authenticated_permission_bits_reliable' => (bool) ($permissionAuthenticationTrustReview['authenticated_permission_bits_reliable'] ?? false),
            'permission_authentication_status' => $permissionAuthenticationTrustReview['status'] ?? null,
            'reserved_bit_violations' => $reservedViolations,
            'perms_hash_present' => isset($encryption['perms']['sha256']),
            'standard_authentication_review' => $standardAuthenticationReview,
            'standard_authentication_material_review' => $standardAuthenticationMaterialReview,
            'standard_authentication_ready_for_password_attempt' => ($standardAuthenticationMaterialReview['present'] ?? false) === true
                ? (bool) ($standardAuthenticationMaterialReview['ready_for_password_attempt'] ?? false)
                : null,
            'public_key_recipient_count' => (int) ($publicKeyRecipientReview['recipient_count'] ?? 0),
            'selected_public_key_recipient_count' => (int) ($publicKeyRecipientReview['selected_recipient_count'] ?? 0),
            'public_key_recipient_permission_decode_status' => $publicKeyRecipientReview['permission_decode_status'] ?? null,
            'public_key_recipient_review' => $publicKeyRecipientReview,
            'public_key_crypt_filter_selection' => is_array($publicKeyRecipientReview['crypt_filter_selection'] ?? null)
                ? $publicKeyRecipientReview['crypt_filter_selection']
                : [],
            'crypt_filter_dictionary_declaration_review' => $cryptFilterDictionaryDeclarationReview,
            'crypt_filter_dictionary_status' => $cryptFilterDictionaryDeclarationReview['status'] ?? null,
            'crypt_filter_dictionary_declared_entry_count' => (int) ($cryptFilterDictionaryDeclarationReview['declared_entry_count'] ?? 0),
            'crypt_filter_dictionary_duplicate_entries' => (bool) ($cryptFilterDictionaryDeclarationReview['duplicate_entries'] ?? false),
            'crypt_filter_dictionary_malformed_entry_count' => (int) ($cryptFilterDictionaryDeclarationReview['malformed_entry_count'] ?? 0),
            'crypt_filter_dictionary_fail_closed' => (bool) ($cryptFilterDictionaryDeclarationReview['fail_closed'] ?? false),
            'crypt_filter_role_declaration_review' => $cryptFilterRoleDeclarationReview,
            'crypt_filter_role_declaration_statuses' => is_array($cryptFilterRoleDeclarationReview['role_statuses'] ?? null)
                ? $cryptFilterRoleDeclarationReview['role_statuses']
                : [],
            'crypt_filter_role_declaration_duplicate_role_names' => is_array($cryptFilterRoleDeclarationReview['duplicate_role_names'] ?? null)
                ? $cryptFilterRoleDeclarationReview['duplicate_role_names']
                : [],
            'crypt_filter_role_declaration_duplicate_pdf_names' => is_array($cryptFilterRoleDeclarationReview['duplicate_pdf_names'] ?? null)
                ? $cryptFilterRoleDeclarationReview['duplicate_pdf_names']
                : [],
            'crypt_filter_content_review' => $cryptFilterContentReview,
            'crypt_filter_text_policy' => $cryptFilterTextPolicy,
            'crypt_filter_text_fail_closed' => $this->cryptFilterContentExtractionBoundary($cryptFilterTextPolicy) !== null,
            'crypt_filter_embedded_file_payload_policy' => $cryptFilterEmbeddedFilePayloadPolicy,
            'crypt_filter_embedded_file_fail_closed' => $cryptFilterEmbeddedFileBoundary !== null,
            'crypt_filter_embedded_file_boundary' => $cryptFilterEmbeddedFileBoundary,
            'requires_password_for_content_extraction' => (bool) ($encryption['requires_password_for_content_extraction'] ?? true),
            'review_only' => true,
            'raw_key_material_exposed' => false,
            'recipient_bytes_exposed' => false,
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
        int $signatureByteRangeCount,
        int $referenceTransformCount,
        array $lockedFieldNames,
        array $permissionPreflight,
        bool $hasDocumentSecurityStore,
        array $documentActionReview
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
            } elseif ($permissionPolicy === 'copy_extract_allowed_but_crypt_filter_preflight_blocked') {
                $reasons[] = 'copy_or_extract_allowed_but_crypt_filter_fail_closed';
            } elseif ($permissionPolicy === 'public_key_recipient_permissions_blocked_without_private_key') {
                $reasons[] = 'public_key_recipient_permissions_undecoded';
            } elseif ($permissionPolicy === 'permissions_unknown_blocked_without_decryption') {
                $reasons[] = 'encryption_permissions_unknown';
                if (($permissionPreflight['duplicate_encrypt_dictionary_entries'] ?? false) === true) {
                    $reasons[] = 'duplicate_encrypt_dictionary_entries';
                }
            } elseif ($permissionPolicy === 'permissions_malformed_blocked_without_decryption') {
                if (($permissionPreflight['standard_security_handler_parameters_well_formed'] ?? null) === false) {
                    $reasons[] = 'standard_security_handler_parameters_malformed';
                    $parameterViolations = is_array($permissionPreflight['standard_security_handler_parameter_violations'] ?? null)
                        ? $permissionPreflight['standard_security_handler_parameter_violations']
                        : [];
                    if (in_array('standard_security_handler_version_revision_mismatch', $parameterViolations, true)) {
                        $reasons[] = 'standard_security_handler_version_revision_mismatch';
                    }
                } elseif (($permissionPreflight['permission_word_duplicate_entries'] ?? false) === true) {
                    $reasons[] = 'permission_word_duplicate_entries';
                } elseif (($permissionPreflight['permission_word_range_valid'] ?? null) === false) {
                    $reasons[] = 'permission_word_out_of_range';
                } else {
                    $permissionWordReview = is_array($permissionPreflight['standard_permission_word_review'] ?? null)
                        ? $permissionPreflight['standard_permission_word_review']
                        : [];
                    $entryStatuses = array_values(array_filter(
                        $permissionWordReview['entry_statuses'] ?? [],
                        static fn (mixed $status): bool => is_string($status)
                    ));
                    if (in_array('permission_word_unresolved_reference', $entryStatuses, true)) {
                        $reasons[] = 'permission_word_unresolved_reference';
                    } elseif (in_array('permission_word_composite_operand_review', $entryStatuses, true)) {
                        $reasons[] = 'permission_word_composite_operand';
                    } elseif (in_array('permission_word_non_integer_review', $entryStatuses, true)) {
                        $reasons[] = 'permission_word_non_integer';
                    } else {
                        $reasons[] = 'permission_word_reserved_bits_malformed';
                    }
                }
            } elseif ($permissionPolicy === 'permissions_unsupported_handler_blocked_without_decryption') {
                $reasons[] = 'encryption_handler_permissions_unsupported';
            }
            if ($signatureByteRangeCount > 0) {
                $reasons[] = 'encrypted_signature_byte_range_present';
            }
            if (
                ($permissionPreflight['crypt_filter_text_fail_closed'] ?? false) === true
                && $permissionPolicy !== 'public_key_recipient_permissions_blocked_without_private_key'
            ) {
                $reasons[] = 'crypt_filter_text_fail_closed';
            }
            if (($permissionPreflight['encrypt_metadata_defaulted_fail_closed'] ?? false) === true) {
                $reasons[] = 'encrypt_metadata_fail_closed';
            }
        }
        if ($signedSignatureCount > 0 && !$encrypted) {
            $reasons[] = 'signed_signature_present';
        }
        if ($referenceTransformCount > 0) {
            $reasons[] = 'signature_reference_transforms_present';
        }
        if ($lockedFieldNames !== []) {
            $reasons[] = 'signed_field_locks_present';
        }
        if ($invalidByteRangeCount > 0) {
            $reasons[] = 'signature_byte_range_invalid';
        }
        if ($hasDocumentSecurityStore) {
            $reasons[] = 'document_security_store_present';
        }
        if ((int) ($documentActionReview['acroform_action_count'] ?? 0) > 0) {
            $reasons[] = 'acroform_actions_present';
        }
        if ((int) ($documentActionReview['signed_locked_field_action_count'] ?? 0) > 0) {
            $reasons[] = 'signed_locked_field_actions_present';
        }
        if (
            (int) ($documentActionReview['form_submit_action_count'] ?? 0) > 0
            || (int) ($documentActionReview['import_data_action_count'] ?? 0) > 0
        ) {
            $reasons[] = 'form_data_actions_present';
        }
        if ((int) ($documentActionReview['unsafe_action_count'] ?? 0) > 0) {
            $reasons[] = 'unsafe_pdf_actions_present';
        }
        if ((int) ($documentActionReview['launch_action_count'] ?? 0) > 0) {
            $reasons[] = 'launch_actions_present';
        }
        if ((int) ($documentActionReview['unsafe_uri_action_count'] ?? 0) > 0) {
            $reasons[] = 'unsafe_uri_actions_present';
        }
        if ((int) ($documentActionReview['post_signature_action_count'] ?? 0) > 0) {
            $reasons[] = 'post_signature_pdf_actions_present';
        }

        return $reasons;
    }

    private function importDecision(
        bool $encrypted,
        int $invalidByteRangeCount,
        int $signedSignatureCount,
        bool $hasDocumentSecurityStore,
        array $documentActionReview
    ): string
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
        if ($hasDocumentSecurityStore) {
            return 'review_required_signature_metadata';
        }
        if ((int) ($documentActionReview['unsafe_action_count'] ?? 0) > 0) {
            return 'review_required_pdf_action_security';
        }

        return 'allow_native_import';
    }

    /**
     * @param list<array<string, mixed>> $signatures
     * @return list<string>
     */
    private function blockedOperations(bool $encrypted, array $signatures, bool $hasDocumentSecurityStore, array $documentActionReview): array
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
        if ($hasDocumentSecurityStore) {
            $blocked[] = 'revocation_check';
            $blocked[] = 'trust_chain_validation';
        }
        if ((int) ($documentActionReview['unsafe_action_count'] ?? 0) > 0) {
            $blocked[] = 'pdf_action_execution';
        }
        if ((int) ($documentActionReview['acroform_action_count'] ?? 0) > 0) {
            $blocked[] = 'form_action_execution';
        }

        return $blocked;
    }
}
