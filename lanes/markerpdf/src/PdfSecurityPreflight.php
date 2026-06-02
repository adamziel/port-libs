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
        $signatures = $this->signatureReviews($form['fields'] ?? [], $pdfBytes, $documentSecurityStore);
        $documentSecurityStoreSignatureReview = $this->documentSecurityStoreSignatureReview($documentSecurityStore, $signatures);
        $documentActionReview = $this->documentActionSecurityReview($pdfBytes, $signatures, $form);
        $encrypted = $encryption !== null;
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
        $permissionPreflight = $this->permissionPreflight($encrypted, $encryption);
        $signatureByteRangeCount = $this->signatureByteRangeCount($signatures);
        $validSignatureByteRangeCount = $this->validSignatureByteRangeCount($signatures);
        $encryptedSignatureByteRangeReview = $this->encryptedSignatureByteRangeReview($encrypted, $permissionPreflight, $signatures);
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
            'signature_reference_transform_count' => $referenceTransformCount,
            'signature_reference_transform_methods' => $this->referenceTransformMethods($signatures),
            'locked_field_names' => $lockedFieldNames,
            'encrypted_signature_byte_range_review_count' => $encrypted ? (int) $encryptedSignatureByteRangeReview['byte_range_count'] : 0,
            'encrypted_signature_byte_range_review' => $encryptedSignatureByteRangeReview,
            'document_security_store_count' => $hasDocumentSecurityStore ? 1 : 0,
            'document_security_store' => $documentSecurityStore,
            'document_security_store_signature_review' => $documentSecurityStoreSignatureReview,
            'document_security_store_signature_match_count' => (int) $documentSecurityStoreSignatureReview['signature_vri_match_count'],
            'document_security_store_unmatched_vri_count' => (int) $documentSecurityStoreSignatureReview['unmatched_vri_count'],
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
        $handlerReview = $this->permissionHandlerReview($encryption, $permissions, $declared);
        $handlerSupported = ($handlerReview['handler_supported_for_native_permission_review'] ?? false) === true;
        $permissionWellFormed = $handlerReview['permission_word_well_formed'] ?? null;
        $publicKeyRecipientReview = is_array($encryption['public_key_recipient_review'] ?? null)
            ? $encryption['public_key_recipient_review']
            : [];
        $standardAuthenticationReview = is_array($encryption['standard_authentication_review'] ?? null)
            ? $encryption['standard_authentication_review']
            : [];
        $recipientPermissionsDeclared = (int) ($publicKeyRecipientReview['recipient_count'] ?? 0) > 0;
        $selectedRecipientCount = (int) ($publicKeyRecipientReview['selected_recipient_count'] ?? 0);
        $permissionBitsReliable = $handlerSupported && $permissionWellFormed === true;
        $copyAllowed = $declared && $handlerSupported ? in_array('copy_or_extract', $allowed, true) : null;
        $accessibilityAllowed = $declared && $handlerSupported ? in_array('extract_for_accessibility', $allowed, true) : null;
        $reviewAllowed = $handlerSupported ? $allowed : [];
        $reviewDenied = $handlerSupported ? $denied : [];

        if (!$declared && $recipientPermissionsDeclared) {
            $policy = 'public_key_recipient_permissions_blocked_without_private_key';
            $boundary = 'blocked_encrypted_public_key_recipient_permissions';
            $source = 'public_key_recipient_permissions';
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

        return [
            'source' => $source,
            'encrypted' => true,
            'handler' => $encryption['filter'] ?? null,
            'revision_label' => $encryption['revision_label'] ?? null,
            'permissions_declared' => $declared || $recipientPermissionsDeclared,
            'standard_permissions_declared' => $declared,
            'recipient_permissions_declared' => $recipientPermissionsDeclared,
            'selected_recipient_permissions_declared' => $selectedRecipientCount > 0,
            'selected_public_key_recipient_count' => $selectedRecipientCount,
            'permission_hex' => $permissions['hex'] ?? null,
            'allowed' => $reviewAllowed,
            'denied' => $reviewDenied,
            'copy_or_extract_allowed' => $copyAllowed,
            'accessibility_extract_allowed' => $accessibilityAllowed,
            'print_quality' => $handlerSupported ? ($permissions['print_quality'] ?? null) : null,
            'permission_bits_reliable' => $permissionBitsReliable,
            'permission_word_well_formed' => $handlerSupported ? $permissionWellFormed : null,
            'permission_handler_review' => $handlerReview,
            'standard_authentication_review' => $standardAuthenticationReview,
            'public_key_recipient_review' => $publicKeyRecipientReview,
            'public_key_crypt_filter_selection' => is_array($publicKeyRecipientReview['crypt_filter_selection'] ?? null)
                ? $publicKeyRecipientReview['crypt_filter_selection']
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
        } elseif (!$declared) {
            $status = 'permissions_unavailable_review';
            $reviewWellFormed = null;
        } elseif (!$standardHandler) {
            $status = 'unsupported_security_handler_permissions_review';
            $reviewWellFormed = null;
        } elseif ($wellFormed !== true) {
            $status = 'malformed_reserved_bits_review';
            $reviewWellFormed = false;
        } else {
            $status = 'well_formed_standard_permissions';
            $reviewWellFormed = true;
        }

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
            'handler_supported_for_native_permission_review' => $standardHandler && $declared,
            'permission_word_well_formed' => $reviewWellFormed,
            'permission_word_status' => $permissions['permission_word_status'] ?? null,
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
     * @param list<array<string, mixed>> $signatures
     * @return array<string, mixed>
     */
    private function documentActionSecurityReview(string $pdfBytes, array $signatures, array $form): array
    {
        $actions = [];
        $outline = new PdfOutlineExtractor();
        foreach ($outline->getOpenActionReviewActions($pdfBytes) as $action) {
            $this->addDocumentActionReviewRow($actions, $action, 'catalog_open_action');
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
        $postSignatureActionObjects = $this->postSignatureActionObjects($actions);
        $postSignatureActionCount = $this->postSignatureActionCount($actions);

        return [
            'source' => 'pdf_document_action_security_review',
            'present' => $actions !== [],
            'action_count' => count($actions),
            'open_action_count' => $this->documentActionCountBySource($actions, 'catalog_open_action'),
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
            'unsafe_action_count' => count(array_filter($actions, fn (array $action): bool => $this->isUnsafeDocumentAction($action))),
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
            'certifying_signature_count' => count(array_filter(
                $signatures,
                static fn (array $signature): bool => ($signature['certifying_signature'] ?? false) === true
            )),
            'certifying_permission_labels' => $this->certifyingPermissionLabels($signatures),
            'signature_reference_transform_methods' => $this->referenceTransformMethods($signatures),
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
        $row = [
            'source' => $source,
            'pnum' => $context['pnum'] ?? null,
            'page_object' => $context['page_object'] ?? null,
            'page_label' => is_string($context['page_label'] ?? null) ? $context['page_label'] : null,
            'annotation_object' => $context['annotation_object'] ?? null,
            'annotation_subtype' => $context['annotation_subtype'] ?? null,
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
            'operation' => is_string($action['operation'] ?? null) ? $action['operation'] : null,
            'destination' => is_string($action['destination'] ?? null) ? $action['destination'] : null,
            'destination_page' => is_int($action['destination_page'] ?? null) ? $action['destination_page'] : null,
            'page' => is_int($action['page'] ?? null) ? $action['page'] : null,
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

        $actions[] = $row;
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
        if ($actionObject === null) {
            return [
                'action_object_span' => null,
                'status' => 'action_object_unresolved',
                'covered_by_all_signature_byte_ranges' => false,
                'outside_any_signature_byte_range' => false,
                'signed_coverage_count' => 0,
                'unsigned_coverage_count' => 0,
                'signature_reviews' => [],
            ];
        }

        $span = $objectSpans[$actionObject] ?? null;
        if ($span === null) {
            return [
                'action_object_span' => null,
                'status' => 'action_object_span_unresolved',
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
            if (!$this->actionIsOutsideSignedRevision($action) || !is_int($action['action_object'] ?? null)) {
                continue;
            }
            if (!in_array($action['action_object'], $objects, true)) {
                $objects[] = $action['action_object'];
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
     * @return array<string, mixed>
     */
    private function documentSecurityStoreSignatureReview(array $documentSecurityStore, array $signatures): array
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
                    'signature_object' => $signature['signature_object'] ?? null,
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
        $standardHandler = ($encryption['filter'] ?? null) === 'Standard';
        $permissionWellFormed = array_key_exists('reserved_bits_valid', $permissions)
            ? (bool) $permissions['reserved_bits_valid']
            : null;
        $reservedBits = is_array($permissions['reserved_bits'] ?? null) ? $permissions['reserved_bits'] : [];
        $reservedViolations = array_values(array_filter(
            $reservedBits['violations'] ?? [],
            static fn (mixed $value): bool => is_string($value)
        ));
        $reviewAllowed = $standardHandler ? $allowed : [];
        $reviewDenied = $standardHandler ? $denied : [];
        $publicKeyRecipientReview = is_array($encryption['public_key_recipient_review'] ?? null)
            ? $encryption['public_key_recipient_review']
            : [];
        $standardAuthenticationReview = is_array($encryption['standard_authentication_review'] ?? null)
            ? $encryption['standard_authentication_review']
            : [];

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
            'allowed' => $reviewAllowed,
            'denied' => $reviewDenied,
            'copy_or_extract_allowed' => $standardHandler && $permissions !== [] ? in_array('copy_or_extract', $allowed, true) : null,
            'accessibility_extract_allowed' => $standardHandler && $permissions !== [] ? in_array('extract_for_accessibility', $allowed, true) : null,
            'print_quality' => $standardHandler ? ($permissions['print_quality'] ?? null) : null,
            'permission_word_status' => $permissions['permission_word_status'] ?? null,
            'permission_word_well_formed' => $standardHandler && $permissions !== [] ? $permissionWellFormed : null,
            'permission_bits_reliable' => $standardHandler && $permissions !== [] && $permissionWellFormed === true,
            'reserved_bit_violations' => $reservedViolations,
            'perms_hash_present' => isset($encryption['perms']['sha256']),
            'standard_authentication_review' => $standardAuthenticationReview,
            'public_key_recipient_count' => (int) ($publicKeyRecipientReview['recipient_count'] ?? 0),
            'selected_public_key_recipient_count' => (int) ($publicKeyRecipientReview['selected_recipient_count'] ?? 0),
            'public_key_recipient_permission_decode_status' => $publicKeyRecipientReview['permission_decode_status'] ?? null,
            'public_key_recipient_review' => $publicKeyRecipientReview,
            'public_key_crypt_filter_selection' => is_array($publicKeyRecipientReview['crypt_filter_selection'] ?? null)
                ? $publicKeyRecipientReview['crypt_filter_selection']
                : [],
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
            } elseif ($permissionPolicy === 'public_key_recipient_permissions_blocked_without_private_key') {
                $reasons[] = 'public_key_recipient_permissions_undecoded';
            } elseif ($permissionPolicy === 'permissions_unknown_blocked_without_decryption') {
                $reasons[] = 'encryption_permissions_unknown';
            } elseif ($permissionPolicy === 'permissions_malformed_blocked_without_decryption') {
                $reasons[] = 'permission_word_reserved_bits_malformed';
            } elseif ($permissionPolicy === 'permissions_unsupported_handler_blocked_without_decryption') {
                $reasons[] = 'encryption_handler_permissions_unsupported';
            }
            if ($signatureByteRangeCount > 0) {
                $reasons[] = 'encrypted_signature_byte_range_present';
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
