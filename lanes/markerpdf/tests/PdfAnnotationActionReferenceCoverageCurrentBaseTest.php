<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$annotationActionReferenceCoveragePdf = static function (): string {
    $pageContent = 'BT /F1 12 Tf 72 720 Td (Annotation action reference body) Tj ET';
    $soundPayload = 'WAVE bytes with (Hidden Sound Action Payload) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 8 0 R /Annots [5 0 R 6 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "5 0 obj\n<< /Type /Annot /Subtype /Text /Rect [72 660 260 720] /Contents (Embedded action review note) /A 10 0 R /AA << /E 11 0 R /D 12 0 R /U 13 0 R /Fo 14 0 R /Bl 15 0 R >> >>\nendobj\n"
        . "6 0 obj\n<< /Type /Annot /Subtype /Screen /Rect [72 520 320 650] /Contents (Screen action review note) /A 16 0 R /AA << /PV 17 0 R >> >>\nendobj\n"
        . "8 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "10 0 obj\n<< /S /GoToE /F 21 0 R /D [0 /FitH 612] /T << /R /C /N (review-pack.pdf) /P 0 >> /NewWindow true >>\nendobj\n"
        . "11 0 obj\n<< /S /Thread /D (Reviewer article thread) /B 20 0 R >>\nendobj\n"
        . "12 0 obj\n<< /S /SetOCGState /State [/OFF 30 0 R /ON 31 0 R] /PreserveRB false >>\nendobj\n"
        . "13 0 obj\n<< /S /Trans /D .35 /Dm /V /M /O /Di 90 /SS .5 /B true >>\nendobj\n"
        . "14 0 obj\n<< /S /Movie /Annotation 6 0 R /T (Screen training clip) /Operation /Play >>\nendobj\n"
        . "15 0 obj\n<< /S /Sound /Sound 40 0 R /Volume .25 /Synchronous false /Repeat true /Mix false >>\nendobj\n"
        . "16 0 obj\n<< /S /Rendition /OP 4 /AN 6 0 R /R 50 0 R /JS (player.playOrResume\\(\\)) >>\nendobj\n"
        . "17 0 obj\n<< /S /RichMediaExecute /TA 6 0 R /TI 61 0 R /C (playIntro) /A [(intro) 12 true] >>\nendobj\n"
        . "20 0 obj\n<< /Type /Bead /R [72 660 260 720] >>\nendobj\n"
        . "21 0 obj\n<< /Type /Filespec /F (fallback-pack.pdf) /UF <FEFF007200650076006900650077002D007000610063006B002E007000640066> >>\nendobj\n"
        . "30 0 obj\n<< /Type /OCG /Name (Reviewer layer off) >>\nendobj\n"
        . "31 0 obj\n<< /Type /OCG /Name (Reviewer layer on) >>\nendobj\n"
        . "40 0 obj\n<< /R 22050 /C 1 /B 8 /E /Raw /Length " . strlen($soundPayload) . " >>\nstream\n{$soundPayload}\nendstream\nendobj\n"
        . "50 0 obj\n<< /S /MR /N (Current training rendition) /C 51 0 R >>\nendobj\n"
        . "51 0 obj\n<< /S /MCD /N (Current clip) /D 52 0 R /CT (video/mp4) >>\nendobj\n"
        . "52 0 obj\n<< /Type /Filespec /F (training-rendition.mp4) >>\nendobj\n"
        . "61 0 obj\n<< /Type /RichMediaInstance /Subtype /Video >>\nendobj\n"
        . "%%EOF";
};

return [
    'reviews broader annotation action references as inert metadata' => static function (TestRunner $t) use ($annotationActionReferenceCoveragePdf): void {
        $pdf = $annotationActionReferenceCoveragePdf();
        $pages = (new PdfAnnotationExtractor())->extractPageAnnotations($pdf);

        $t->same(1, count($pages));
        $t->same(['Text', 'Screen'], array_column($pages[0]['annotations'], 'subtype'));

        $text = $pages[0]['annotations'][0];
        $t->same(['GoToE'], array_column($text['actions'], 'action_type'));
        $embedded = $text['actions'][0];
        $t->same('embedded-document-review', $embedded['safety']);
        $t->same('review-pack.pdf', $embedded['file']);
        $t->same(0, $embedded['destination_page']);
        $t->same('FitH', $embedded['view_mode']);
        $t->same([612.0], $embedded['view_position']);
        $t->same(['top' => 612.0], $embedded['view_parameters']);
        $t->same(['relation' => 'C', 'relation_label' => 'child', 'name' => 'review-pack.pdf', 'page' => 0], $embedded['target']);
        $t->same(true, $embedded['new_window']);
        $t->same(false, $embedded['executes_embedded_document']);
        $t->same(false, $embedded['executes_on_import']);

        $t->same(['Thread', 'SetOCGState', 'Trans', 'Movie', 'Sound'], array_column($text['additional_actions'], 'action_type'));
        $thread = $text['additional_actions'][0];
        $t->same('article-thread-review', $thread['safety']);
        $t->same('Reviewer article thread', $thread['destination']);
        $t->same(20, $thread['thread_bead_object']);
        $t->same(false, $thread['enters_article_thread_mode_on_import']);

        $ocg = $text['additional_actions'][1];
        $t->same('optional-content-state-review', $ocg['safety']);
        $t->same(['OFF', 'ON'], $ocg['operations']);
        $t->same([30, 31], $ocg['target_optional_content_objects']);
        $t->same(false, $ocg['preserve_radio_button_state']);
        $t->same(false, $ocg['changes_optional_content_on_import']);

        $transition = $text['additional_actions'][2];
        $t->same('page-transition-review', $transition['safety']);
        $t->same('Trans', $transition['action_type']);
        $t->same(0.35, $transition['duration']);
        $t->same('V', $transition['dimension']);
        $t->same('O', $transition['motion']);
        $t->same(90.0, $transition['direction']);
        $t->same(0.5, $transition['scale']);
        $t->same(true, $transition['rectangular']);
        $t->same(false, $transition['applies_page_transition_on_import']);

        $movie = $text['additional_actions'][3];
        $t->same('movie-action-review', $movie['safety']);
        $t->same(6, $movie['target_annotation_object']);
        $t->same('Screen training clip', $movie['title']);
        $t->same('Play', $movie['operation']);
        $t->same(false, $movie['executes_media']);

        $sound = $text['additional_actions'][4];
        $t->same('sound-action-review', $sound['safety']);
        $t->same(40, $sound['sound_object']);
        $t->same(0.25, $sound['volume']);
        $t->same(false, $sound['synchronous']);
        $t->same(true, $sound['repeat']);
        $t->same(false, $sound['mix']);
        $t->same(false, $sound['executes_media']);

        $screen = $pages[0]['annotations'][1];
        $t->same(['Rendition'], array_column($screen['actions'], 'action_type'));
        $rendition = $screen['actions'][0];
        $t->same('media-rendition-review', $rendition['safety']);
        $t->same(4, $rendition['operation_code']);
        $t->same('play_or_resume', $rendition['operation_label']);
        $t->same(6, $rendition['target_annotation_object']);
        $t->same(['training-rendition.mp4'], $rendition['file_names']);
        $t->same('player.playOrResume()', $rendition['script_preview']);
        $t->same(hash('sha256', 'player.playOrResume()'), $rendition['script_sha256']);
        $t->same(false, $rendition['executes_media']);
        $t->same(false, $rendition['executes_javascript']);

        $t->same(['RichMediaExecute'], array_column($screen['additional_actions'], 'action_type'));
        $execute = $screen['additional_actions'][0];
        $t->same('rich-media-execute-review', $execute['safety']);
        $t->same(6, $execute['target_annotation_object']);
        $t->same(61, $execute['target_instance_object']);
        $t->same('playIntro', $execute['command']);
        $t->same(3, $execute['argument_count']);
        $t->same(false, $execute['executes_media']);
        $t->same(false, $execute['executes_on_import']);
    },
    'keeps broader annotation action operands out of visible PDF text' => static function (TestRunner $t) use ($annotationActionReferenceCoveragePdf): void {
        $pdf = $annotationActionReferenceCoveragePdf();
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->same(['Annotation action reference body'], (new PdfTextExtractor())->extractTextLines($pdf));
        $t->contains('Annotation action reference body', $plainText);
        $t->true(!str_contains($plainText, 'Embedded action review note'));
        $t->true(!str_contains($plainText, 'review-pack.pdf'));
        $t->true(!str_contains($plainText, 'Reviewer article thread'));
        $t->true(!str_contains($plainText, 'Screen training clip'));
        $t->true(!str_contains($plainText, 'Hidden Sound Action Payload'));
        $t->true(!str_contains($plainText, 'training-rendition.mp4'));
        $t->true(!str_contains($plainText, 'player.playOrResume'));
        $t->true(!str_contains($plainText, 'playIntro'));
    },
];
