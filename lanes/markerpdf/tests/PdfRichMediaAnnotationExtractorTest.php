<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfRichMediaAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$richMediaAnnotationPdf = static function (): string {
    $pageContent = 'BT /F1 12 Tf 72 720 Td (Article Body) Tj ET';
    $screenAppearance = 'BT /F1 12 Tf 0 0 Td (Embedded Video Noise) Tj ET';
    $richMediaAppearance = 'BT /F1 12 Tf 0 0 Td (Rich Media Noise) Tj ET';
    $widgetAppearance = 'BT /F1 12 Tf 0 0 Td (Printable Widget Review) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 17 0 R >> >> /Annots [5 0 R 6 0 R 7 0 R 8 0 R] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Annot /Subtype /Screen /Rect [72 600 260 700] /T (Training video) /Contents (MP4 launch annotation) /A 12 0 R /AA << /PV 13 0 R >> /AP << /N 9 0 R >> >>\nendobj\n"
        . "6 0 obj\n<< /Type /Annot /Subtype /RichMedia /Rect [72 500 300 590] /Alt (Rich media package) /RichMediaContent 15 0 R /A << /S /URI /URI (https://cdn.example.com/asset.mp4) >> /AP << /N 10 0 R >> >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Link /Rect [72 680 170 698] /A << /S /URI /URI (https://example.com/docs) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Widget /Rect [72 460 260 490] /AP << /N 11 0 R >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 17 0 R >> >> /Length " . strlen($screenAppearance) . " >>\nstream\n{$screenAppearance}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 17 0 R >> >> /Length " . strlen($richMediaAppearance) . " >>\nstream\n{$richMediaAppearance}\nendstream\nendobj\n"
        . "11 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 17 0 R >> >> /Length " . strlen($widgetAppearance) . " >>\nstream\n{$widgetAppearance}\nendstream\nendobj\n"
        . "12 0 obj\n<< /S /Rendition /R << /C 16 0 R /OP 0 >> >>\nendobj\n"
        . "13 0 obj\n<< /S /JavaScript /JS (app.alert\\(\\'do not run\\'\\)) >>\nendobj\n"
        . "15 0 obj\n<< /RichMediaContent << /Assets << /Names [(intro-video.mp4) 16 0 R] >> >> >>\nendobj\n"
        . "16 0 obj\n<< /Type /Filespec /F (intro-video.mp4) /UF <FEFF0069006E00740072006F002D0076006900640065006F002E006D00700034> >>\nendobj\n"
        . "17 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$soundMovieAnnotationPdf = static function (): string {
    $pageContent = 'BT /F1 12 Tf 72 720 Td (Article Body) Tj ET';
    $movieAppearance = 'BT /F1 12 Tf 0 0 Td (Movie Poster Noise) Tj ET';
    $soundAppearance = 'BT /F1 12 Tf 0 0 Td (Sound Icon Noise) Tj ET';
    $soundBytes = "RIFF fake bytes with (Leaked Sound Text) Tj ET";

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 15 0 R >> >> /Annots [5 0 R 6 0 R] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Annot /Subtype /Movie /Rect [72 540 320 700] /T (Training clip) /Contents (Movie must be reviewed) /Movie 9 0 R /A 10 0 R /AP << /N 11 0 R >> >>\nendobj\n"
        . "6 0 obj\n<< /Type /Annot /Subtype /Sound /Rect [72 500 180 535] /T (Narration note) /Contents (Audio note) /Name /Speaker /Sound 12 0 R /AP << /N 13 0 R >> >>\nendobj\n"
        . "9 0 obj\n<< /F 14 0 R /T (Intro movie title) /Aspect [640 360] /Rotate 90 /Poster true >>\nendobj\n"
        . "10 0 obj\n<< /Start 1.5 /Duration 12 /Rate 1.25 /Volume .75 /ShowControls true /Mode /Once /Synchronous false /FWScale [1 1] /FWPosition [0.5 0.5] >>\nendobj\n"
        . "11 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 15 0 R >> >> /Length " . strlen($movieAppearance) . " >>\nstream\n{$movieAppearance}\nendstream\nendobj\n"
        . "12 0 obj\n<< /R 44100 /C 2 /B 16 /E /Signed /CO /FlateDecode /Length " . strlen($soundBytes) . " >>\nstream\n{$soundBytes}\nendstream\nendobj\n"
        . "13 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 15 0 R >> >> /Length " . strlen($soundAppearance) . " >>\nstream\n{$soundAppearance}\nendstream\nendobj\n"
        . "14 0 obj\n<< /Type /Filespec /F (training.mov) >>\nendobj\n"
        . "15 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$richMediaActionPopupPdf = static function (): string {
    $pageContent = 'BT /F1 12 Tf 72 720 Td (Article Body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 20 0 R >> >> /Annots [5 0 R 7 0 R] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Annot /Subtype /RichMedia /Rect [72 520 320 700] /T (Training player) /Contents (Embedded player requires review) /RichMediaContent 18 0 R /A 12 0 R /AA << /PV [13 0 R 14 0 R] /PI 16 0 R >> >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Popup /Parent 5 0 R /Rect [200 540 380 620] /Open true /Contents (Reviewer popup stays metadata) >>\nendobj\n"
        . "12 0 obj\n<< /S /RichMediaExecute /AN 5 0 R /CMD << /C (playVideo) >> /Next [13 0 R 15 0 R] >>\nendobj\n"
        . "13 0 obj\n<< /S /JavaScript /JS (app.alert\\('blocked media script'\\)) >>\nendobj\n"
        . "14 0 obj\n<< /S /URI /URI (https://cdn.example.com/training.mp4) /Next 15 0 R >>\nendobj\n"
        . "15 0 obj\n<< /S /Launch /F (helper.exe) /Win << /F (setup.exe) /O (open) >> /NewWindow true >>\nendobj\n"
        . "16 0 obj\n<< /S /URI /URI (javascript:alert\\(1\\)) /Next 16 0 R >>\nendobj\n"
        . "18 0 obj\n<< /RichMediaContent << /Assets << /Names [(training.mp4) 19 0 R] >> >> >>\nendobj\n"
        . "19 0 obj\n<< /Type /Filespec /F (training.mp4) >>\nendobj\n"
        . "20 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$currentAnnotationActionBoundaryPdf = static function (): string {
    $pageContent = 'BT /F1 12 Tf 72 720 Td (Article Body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 20 0 R >> >> /Annots [<< /Type /Annot /Subtype /RichMedia /Rect [72 520 320 700] /T (Current inline player) /Contents (Only this inline annotation belongs to the page) /RichMediaContent 30 0 R /A << /S /Rendition /R << /C 31 0 R /OP 0 >> /Next 14 0 R >> /AA << /PV [12 0 R << /S /RichMediaExecute /AN 50 0 R /CMD << /C (targetStalePlayer) >> >>] /PI 13 0 R >> /Popup << /Type /Annot /Subtype /Popup /Rect [200 540 380 620] /Open false /Contents (Inline popup metadata) >> >>] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "12 0 obj\n<< /S /URI /URI (https://cdn.example.com/current-inline.mp4) >>\nendobj\n"
        . "13 0 obj\n<< /S /Launch /F (current-helper.exe) /Win << /F (current-setup.exe) /O (open) >> >>\nendobj\n"
        . "14 0 obj\n<< /S /JavaScript /JS (app.alert\\('current only'\\)) >>\nendobj\n"
        . "30 0 obj\n<< /RichMediaContent << /Assets << /Names [(current-inline.mp4) 31 0 R] >> >> >>\nendobj\n"
        . "31 0 obj\n<< /Type /Filespec /F (current-rendition.mp4) >>\nendobj\n"
        . "50 0 obj\n<< /Type /Annot /Subtype /RichMedia /Rect [10 10 20 20] /T (Stale detached player) /Contents (Detached target must not become a page annotation) /RichMediaContent 51 0 R /A << /S /URI /URI (https://cdn.example.com/stale-detached.mp4) >> >>\nendobj\n"
        . "51 0 obj\n<< /RichMediaContent << /Assets << /Names [(stale-detached.mp4) 52 0 R] >> >> >>\nendobj\n"
        . "52 0 obj\n<< /Type /Filespec /F (stale-detached.mp4) >>\nendobj\n"
        . "20 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$movieSoundRenditionPopupPdf = static function (): string {
    $pageContent = 'BT /F1 12 Tf 72 720 Td (Article Body) Tj ET';
    $movieAppearance = 'BT /F1 12 Tf 0 0 Td (Movie Popup Payload Noise) Tj ET';
    $soundBytes = "WAVE bytes with (Sound Action Payload Noise) Tj ET";

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 40 0 R >> >> /Annots [5 0 R 6 0 R 7 0 R 8 0 R] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Annot /Subtype /Movie /Rect [320 700 72 540] /T (Inline movie) /Contents (Movie annotation requires review) /Movie 20 0 R /A 21 0 R /AA << /U 30 0 R >> /Popup 23 0 R /AP << /N 24 0 R >> >>\nendobj\n"
        . "6 0 obj\n<< /Type /Annot /Subtype /Sound /Rect [72 500 180 535] /T (Narration) /Contents (Sound annotation requires review) /Name /Speaker /Sound 22 0 R /A 31 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Type /Annot /Subtype /Screen /Rect [72 420 360 500] /T (Rendition player) /Contents (Rendition requires review) /A 32 0 R /Popup << /Type /Annot /Subtype /Popup /Rect [210 420 390 500] /Open false /Contents (Screen rendition popup) >> >>\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /Popup /Parent 6 0 R /Rect [180 500 340 560] /Open true /Contents (Sound popup stays metadata) >>\nendobj\n"
        . "20 0 obj\n<< /F 25 0 R /T (Movie dictionary title) /Aspect [1280 720] /Rotate 180 /Poster 26 0 R >>\nendobj\n"
        . "21 0 obj\n<< /Start 2 /Duration 30 /Rate 1 /Volume 0.8 /ShowControls false /Mode /Palindrome /Synchronous true /FWScale [2 2] /FWPosition [0.25 0.75] >>\nendobj\n"
        . "22 0 obj\n<< /R 22050 /C 1 /B 8 /E /Raw /Length " . strlen($soundBytes) . " >>\nstream\n{$soundBytes}\nendstream\nendobj\n"
        . "23 0 obj\n<< /Type /Annot /Subtype /Popup /Parent 5 0 R /Rect [300 548 460 628] /Open true /Contents (Movie popup stays metadata) >>\nendobj\n"
        . "24 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 40 0 R >> >> /Length " . strlen($movieAppearance) . " >>\nstream\n{$movieAppearance}\nendstream\nendobj\n"
        . "25 0 obj\n<< /Type /Filespec /F (movie-action.mov) /UF <FEFF006D006F007600690065002D0061006300740069006F006E002E006D006F0076> >>\nendobj\n"
        . "26 0 obj\n<< /Subtype /Image /Width 16 /Height 16 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Length 0 >>\nstream\n\nendstream\nendobj\n"
        . "30 0 obj\n<< /S /Movie /Annotation 5 0 R /T (Inline movie action) /Operation /Play >>\nendobj\n"
        . "31 0 obj\n<< /S /Sound /Sound 22 0 R /Volume .4 /Synchronous false /Repeat true /Mix false >>\nendobj\n"
        . "32 0 obj\n<< /S /Rendition /OP 0 /AN 7 0 R /R 33 0 R >>\nendobj\n"
        . "33 0 obj\n<< /S /MR /N (Training rendition) /C 34 0 R >>\nendobj\n"
        . "34 0 obj\n<< /S /MCD /N (Training movie clip) /D 35 0 R /CT (video/mp4) /Alt [(en-US) (Training video)] >>\nendobj\n"
        . "35 0 obj\n<< /Type /Filespec /F (training-rendition.mp4) /UF <FEFF0074007200610069006E0069006E0067002D00720065006E0064006900740069006F006E002E006D00700034> >>\nendobj\n"
        . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

$screenRenditionPlaybackPolicyPdf = static function (): string {
    $pageContent = 'BT /F1 12 Tf 72 720 Td (Article Body) Tj ET';
    $screenAppearance = 'BT /F1 12 Tf 0 0 Td (Playback Policy Noise) Tj ET';
    $mediaBytes = "MP3 bytes with (Leaked Playback Payload) Tj ET";

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Annots [5 0 R] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Annot /Subtype /Screen /Rect [72 500 360 650] /T (Screen training audio) /Contents (Playback settings require review) /A 10 0 R /AP << /N 6 0 R >> >>\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Form /Resources << /Font << /F1 30 0 R >> >> /Length " . strlen($screenAppearance) . " >>\nstream\n{$screenAppearance}\nendstream\nendobj\n"
        . "10 0 obj\n<< /S /Rendition /OP 0 /AN 5 0 R /R 11 0 R >>\nendobj\n"
        . "11 0 obj\n<< /S /MR /N (Policy rendition) /C 12 0 R /P 15 0 R /SP 18 0 R >>\nendobj\n"
        . "12 0 obj\n<< /S /MCD /N (Policy audio clip) /D 13 0 R /CT (audio/mpeg) /Alt [(en-US) (Training narration)] >>\nendobj\n"
        . "13 0 obj\n<< /Type /Filespec /F (training-audio.mp3) /EF << /F 14 0 R >> >>\nendobj\n"
        . "14 0 obj\n<< /Type /EmbeddedFile /Subtype /audio#2Fmpeg /Length " . strlen($mediaBytes) . " >>\nstream\n{$mediaBytes}\nendstream\nendobj\n"
        . "15 0 obj\n<< /MH 16 0 R /BE << /C false /V 0.4 /Mode /Once /T (Best effort playback) /Dur [0 15] >> >>\nendobj\n"
        . "16 0 obj\n<< /C true /V .85 /R false /T (Must honor playback) /Lang (en-US) >>\nendobj\n"
        . "18 0 obj\n<< /MH 19 0 R /BE 20 0 R >>\nendobj\n"
        . "19 0 obj\n<< /W /Window /O 0.9 /Title (Floating review player) >>\nendobj\n"
        . "20 0 obj\n<< /W /FullScreen /O 0.5 /C false >>\nendobj\n"
        . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF";
};

return [
    'extracts screen and rich media annotations as review-only metadata' => static function (TestRunner $t) use ($richMediaAnnotationPdf): void {
        $pages = (new PdfRichMediaAnnotationExtractor())->extractReviewAnnotations($richMediaAnnotationPdf());

        $t->same(1, count($pages));
        $t->same(0, $pages[0]['pnum']);
        $t->same(3, $pages[0]['page_object']);
        $t->same(['Screen', 'RichMedia'], array_column($pages[0]['annotations'], 'subtype'));

        $screen = $pages[0]['annotations'][0];
        $t->same(5, $screen['annotation_object']);
        $t->same([72.0, 600.0, 260.0, 700.0], $screen['rect']);
        $t->same('Training video', $screen['title']);
        $t->same('MP4 launch annotation', $screen['contents']);
        $t->same(['Rendition', 'JavaScript'], $screen['action_types']);
        $t->same(['intro-video.mp4'], $screen['file_names']);
        $t->same(false, $screen['executes_media']);
        $t->same(false, $screen['executes_javascript']);
        $t->same(true, $screen['requires_review']);

        $richMedia = $pages[0]['annotations'][1];
        $t->same('RichMedia', $richMedia['subtype']);
        $t->same('Rich media package', $richMedia['alternate_text']);
        $t->same(['URI'], $richMedia['action_types']);
        $t->same(['https://cdn.example.com/asset.mp4'], $richMedia['action_uris']);
        $t->same(['intro-video.mp4'], $richMedia['asset_names']);
        $t->same(['intro-video.mp4'], $richMedia['file_names']);
        $t->same(false, $richMedia['executes_media']);
        $t->same(false, $richMedia['executes_javascript']);
    },
    'reviews rich media annotation popups and chained action boundaries without executing them' => static function (TestRunner $t) use ($richMediaActionPopupPdf): void {
        $pages = (new PdfRichMediaAnnotationExtractor())->extractReviewAnnotations($richMediaActionPopupPdf());

        $t->same(1, count($pages));
        $t->same(1, count($pages[0]['annotations']), 'reverse-linked Popup annotation is nested, not emitted as a media annotation.');

        $annotation = $pages[0]['annotations'][0];
        $t->same('RichMedia', $annotation['subtype']);
        $t->same(5, $annotation['annotation_object']);
        $t->same([72.0, 520.0, 320.0, 700.0], $annotation['rect']);
        $t->same('Training player', $annotation['title']);
        $t->same('Embedded player requires review', $annotation['contents']);
        $t->same(['training.mp4'], $annotation['asset_names']);
        $t->same(['training.mp4', 'helper.exe', 'setup.exe'], $annotation['file_names']);
        $t->same(true, $annotation['requires_review']);
        $t->same(false, $annotation['executes_media']);
        $t->same(false, $annotation['executes_javascript']);
        $t->same(true, $annotation['has_rich_media_content']);

        $popup = $annotation['popup'];
        $t->same(7, $popup['object']);
        $t->same([200.0, 540.0, 380.0, 620.0], $popup['rect']);
        $t->same(true, $popup['open']);
        $t->same(5, $popup['parent_object']);
        $t->same('Reviewer popup stays metadata', $popup['contents']);

        $t->same(['RichMediaExecute', 'JavaScript', 'Launch', 'URI'], $annotation['action_types']);
        $t->same(['https://cdn.example.com/training.mp4', 'javascript:alert(1)'], $annotation['action_uris']);
        $t->same(7, count($annotation['actions']));

        $execute = $annotation['actions'][0];
        $t->same('annotation_action', $execute['source']);
        $t->same('A', $execute['event']);
        $t->same('annotation_activation', $execute['event_label']);
        $t->same('RichMediaExecute', $execute['action_type']);
        $t->same(12, $execute['action_object']);
        $t->same('rich-media-execute-review', $execute['safety']);
        $t->same(5, $execute['target_annotation_object']);
        $t->same('playVideo', $execute['command']);
        $t->same(false, $execute['executes_on_import']);
        $t->same(false, $execute['executes_media']);

        $script = $annotation['actions'][1];
        $t->same('JavaScript', $script['action_type']);
        $t->same(13, $script['action_object']);
        $t->same(true, $script['chained']);
        $t->same(1, $script['chain_index']);
        $t->same('blocked-javascript', $script['safety']);
        $t->same("app.alert('blocked media script')", $script['script_preview']);
        $t->same(hash('sha256', "app.alert('blocked media script')"), $script['script_sha256']);
        $t->same(false, $script['executes_javascript']);

        $launch = $annotation['actions'][2];
        $t->same('Launch', $launch['action_type']);
        $t->same('blocked-launch', $launch['safety']);
        $t->same('helper.exe', $launch['file']);
        $t->same('open', $launch['operation']);
        $t->same(true, $launch['new_window']);
        $t->same(true, $launch['chained']);

        $visible = $annotation['actions'][4];
        $t->same('URI', $visible['action_type']);
        $t->same('PV', $visible['event']);
        $t->same('annotation_page_visible', $visible['event_label']);
        $t->same('https://cdn.example.com/training.mp4', $visible['uri']);
        $t->same(true, $visible['is_safe_uri']);
        $t->same('review-uri', $visible['safety']);

        $hidden = $annotation['actions'][6];
        $t->same('PI', $hidden['event']);
        $t->same('annotation_page_invisible', $hidden['event_label']);
        $t->same('javascript:alert(1)', $hidden['uri']);
        $t->same(false, $hidden['is_safe_uri']);
        $t->same('blocked-unsafe-uri', $hidden['safety']);
        $t->same(1, $annotation['action_chain_safety']['cycle_edges_blocked']);
        $t->same(0, $annotation['action_chain_safety']['max_depth_edges_blocked']);

        $plainText = (new PdfTextExtractor())->extractPlainText($richMediaActionPopupPdf());
        $t->same(['Article Body'], (new PdfTextExtractor())->extractTextLines($richMediaActionPopupPdf()));
        $t->true(!str_contains($plainText, 'Reviewer popup stays metadata'));
        $t->true(!str_contains($plainText, 'blocked media script'));
    },
    'keeps inline annotation action targets inside the current page annotation boundary' => static function (TestRunner $t) use ($currentAnnotationActionBoundaryPdf): void {
        $pages = (new PdfRichMediaAnnotationExtractor())->extractReviewAnnotations($currentAnnotationActionBoundaryPdf());

        $t->same(1, count($pages));
        $t->same(0, $pages[0]['pnum']);
        $t->same(3, $pages[0]['page_object']);
        $t->same(1, count($pages[0]['annotations']), 'Only top-level /Annots entries are page annotations; nested /A and /AA references remain action metadata.');

        $annotation = $pages[0]['annotations'][0];
        $t->same('RichMedia', $annotation['subtype']);
        $t->same(null, $annotation['annotation_object']);
        $t->same('Current inline player', $annotation['title']);
        $t->same('Only this inline annotation belongs to the page', $annotation['contents']);
        $t->same(['current-inline.mp4'], $annotation['asset_names']);
        $t->same(['current-rendition.mp4', 'current-helper.exe', 'current-setup.exe'], $annotation['file_names']);
        $t->true(!in_array('stale-detached.mp4', $annotation['asset_names'], true));
        $t->true(!in_array('stale-detached.mp4', $annotation['file_names'], true));

        $t->same(['Rendition', 'JavaScript', 'URI', 'RichMediaExecute', 'Launch'], $annotation['action_types']);
        $t->same(['https://cdn.example.com/current-inline.mp4'], $annotation['action_uris']);
        $t->same(5, count($annotation['actions']));

        $targeted = $annotation['actions'][3];
        $t->same('RichMediaExecute', $targeted['action_type']);
        $t->same(50, $targeted['target_annotation_object']);
        $t->same('targetStalePlayer', $targeted['command']);
        $t->same(false, $targeted['executes_on_import']);

        $launch = $annotation['actions'][4];
        $t->same('Launch', $launch['action_type']);
        $t->same('blocked-launch', $launch['safety']);
        $t->same('current-helper.exe', $launch['file']);
        $t->same('open', $launch['operation']);

        $plainText = (new PdfTextExtractor())->extractPlainText($currentAnnotationActionBoundaryPdf());
        $t->same(['Article Body'], (new PdfTextExtractor())->extractTextLines($currentAnnotationActionBoundaryPdf()));
        $t->true(!str_contains($plainText, 'Detached target must not become a page annotation'));
    },
    'keeps rich media and screen appearances out of native text extraction' => static function (TestRunner $t) use ($richMediaAnnotationPdf): void {
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($richMediaAnnotationPdf());

        $t->same(['Article Body', 'Printable Widget Review'], $extractor->extractTextLines($richMediaAnnotationPdf()));
        $t->true(!str_contains($plainText, 'Embedded Video Noise'));
        $t->true(!str_contains($plainText, 'Rich Media Noise'));
        $t->true(str_contains($plainText, 'Printable Widget Review'));
    },
    'does not promote screen or rich media URI actions to Markdown links' => static function (TestRunner $t) use ($richMediaAnnotationPdf): void {
        $pages = [[
            'pnum' => 0,
            'blocks' => [[
                'type' => 'Text',
                'bbox' => [72.0, 680.0, 170.0, 698.0],
                'lines' => [[
                    'bbox' => [72.0, 680.0, 170.0, 698.0],
                    'spans' => [[
                        'text' => 'Article docs',
                        'bbox' => [72.0, 680.0, 170.0, 698.0],
                        'font' => 'Helvetica',
                    ]],
                ]],
            ]],
        ]];

        $linked = (new PdfLinkAnnotationExtractor())->applyLinksToPages($pages, $richMediaAnnotationPdf());

        $t->same(1, count($linked[0]['links']));
        $t->same('https://example.com/docs', $linked[0]['links'][0]['uri']);
        $t->same('https://example.com/docs', $linked[0]['blocks'][0]['lines'][0]['spans'][0]['link_uri']);
    },
    'extracts sound and movie annotations as review-only media dictionaries' => static function (TestRunner $t) use ($soundMovieAnnotationPdf): void {
        $pages = (new PdfRichMediaAnnotationExtractor())->extractReviewAnnotations($soundMovieAnnotationPdf());

        $t->same(1, count($pages));
        $t->same(0, $pages[0]['pnum']);
        $t->same(3, $pages[0]['page_object']);
        $t->same(['Movie', 'Sound'], array_column($pages[0]['annotations'], 'subtype'));

        $movie = $pages[0]['annotations'][0];
        $t->same(5, $movie['annotation_object']);
        $t->same([72.0, 540.0, 320.0, 700.0], $movie['rect']);
        $t->same('Training clip', $movie['title']);
        $t->same('Movie must be reviewed', $movie['contents']);
        $t->same([], $movie['action_types']);
        $t->same(['training.mov'], $movie['file_names']);
        $t->same(9, $movie['movie']['dictionary_object']);
        $t->same('Intro movie title', $movie['movie']['title']);
        $t->same(['training.mov'], $movie['movie']['file_names']);
        $t->same([640.0, 360.0], $movie['movie']['aspect']);
        $t->same(90, $movie['movie']['rotation']);
        $t->same(true, $movie['movie']['poster']);
        $t->same(10, $movie['movie']['activation']['dictionary_object']);
        $t->same(1.5, $movie['movie']['activation']['start']);
        $t->same(12.0, $movie['movie']['activation']['duration']);
        $t->same(1.25, $movie['movie']['activation']['rate']);
        $t->same(0.75, $movie['movie']['activation']['volume']);
        $t->same(true, $movie['movie']['activation']['show_controls']);
        $t->same('Once', $movie['movie']['activation']['mode']);
        $t->same(false, $movie['movie']['activation']['synchronous']);
        $t->same([1.0, 1.0], $movie['movie']['activation']['window_scale']);
        $t->same([0.5, 0.5], $movie['movie']['activation']['window_position']);
        $t->same(null, $movie['sound']);
        $t->same(false, $movie['executes_media']);

        $sound = $pages[0]['annotations'][1];
        $t->same(6, $sound['annotation_object']);
        $t->same([72.0, 500.0, 180.0, 535.0], $sound['rect']);
        $t->same('Narration note', $sound['title']);
        $t->same('Audio note', $sound['contents']);
        $t->same([], $sound['file_names']);
        $t->same(null, $sound['movie']);
        $t->same(12, $sound['sound']['stream_object']);
        $t->same('Speaker', $sound['sound']['icon_name']);
        $t->same(44100.0, $sound['sound']['sample_rate']);
        $t->same(2, $sound['sound']['channels']);
        $t->same(16, $sound['sound']['bits_per_sample']);
        $t->same('Signed', $sound['sound']['encoding']);
        $t->same('FlateDecode', $sound['sound']['compression']);
        $t->same(strlen("RIFF fake bytes with (Leaked Sound Text) Tj ET"), $sound['sound']['payload_length']);
        $t->same(false, $sound['executes_media']);
        $t->same(false, $sound['executes_javascript']);

        $plainText = (new PdfTextExtractor())->extractPlainText($soundMovieAnnotationPdf());
        $t->same(['Article Body'], (new PdfTextExtractor())->extractTextLines($soundMovieAnnotationPdf()));
        $t->true(!str_contains($plainText, 'Movie Poster Noise'));
        $t->true(!str_contains($plainText, 'Sound Icon Noise'));
        $t->true(!str_contains($plainText, 'Leaked Sound Text'));
    },
    'reviews movie sound and rendition action popup boundaries without executing media' => static function (TestRunner $t) use ($movieSoundRenditionPopupPdf): void {
        $pages = (new PdfRichMediaAnnotationExtractor())->extractReviewAnnotations($movieSoundRenditionPopupPdf());

        $t->same(1, count($pages));
        $t->same(['Movie', 'Sound', 'Screen'], array_column($pages[0]['annotations'], 'subtype'));

        $movie = $pages[0]['annotations'][0];
        $t->same([72.0, 540.0, 320.0, 700.0], $movie['rect'], 'Movie annotation rectangles are normalized before review output.');
        $t->same(['Movie'], $movie['action_types']);
        $t->same('stream', $movie['movie']['poster']);
        $t->same('Movie popup stays metadata', $movie['popup']['contents']);
        $t->same(5, $movie['actions'][0]['target_annotation_object']);
        $t->same('Inline movie action', $movie['actions'][0]['title']);
        $t->same('Play', $movie['actions'][0]['operation']);
        $t->same('movie-action-review', $movie['actions'][0]['safety']);
        $t->same('Movie dictionary title', $movie['actions'][0]['movie']['title']);
        $t->same(['movie-action.mov'], $movie['actions'][0]['movie']['file_names']);

        $sound = $pages[0]['annotations'][1];
        $t->same('Sound popup stays metadata', $sound['popup']['contents']);
        $t->same(['Sound'], $sound['action_types']);
        $t->same('sound-action-review', $sound['actions'][0]['safety']);
        $t->same(22, $sound['actions'][0]['sound']['stream_object']);
        $t->same(22050.0, $sound['actions'][0]['sound']['sample_rate']);
        $t->same(0.4, $sound['actions'][0]['volume']);
        $t->same(false, $sound['actions'][0]['synchronous']);
        $t->same(true, $sound['actions'][0]['repeat']);
        $t->same(false, $sound['actions'][0]['mix']);

        $screen = $pages[0]['annotations'][2];
        $t->same('Rendition player', $screen['title']);
        $t->same('Screen rendition popup', $screen['popup']['contents']);
        $t->same(['Rendition'], $screen['action_types']);
        $t->same('media-rendition-review', $screen['actions'][0]['safety']);
        $t->same(7, $screen['actions'][0]['target_annotation_object']);
        $t->same(0, $screen['actions'][0]['operation']);
        $t->same('play', $screen['actions'][0]['operation_label']);
        $t->same(33, $screen['actions'][0]['rendition']['dictionary_object']);
        $t->same('MR', $screen['actions'][0]['rendition']['subtype']);
        $t->same('Training rendition', $screen['actions'][0]['rendition']['name']);
        $t->same(['training-rendition.mp4'], $screen['actions'][0]['rendition']['file_names']);
        $t->same(34, $screen['actions'][0]['rendition']['media_clip']['dictionary_object']);
        $t->same('MCD', $screen['actions'][0]['rendition']['media_clip']['subtype']);
        $t->same('video/mp4', $screen['actions'][0]['rendition']['media_clip']['content_type']);
        $t->same('training-rendition.mp4', $screen['actions'][0]['rendition']['media_clip']['file']);
        $t->same(['en-US', 'Training video'], $screen['actions'][0]['rendition']['media_clip']['alternate_text']);
        $t->same(['training-rendition.mp4'], $screen['actions'][0]['file_names']);

        $plainText = (new PdfTextExtractor())->extractPlainText($movieSoundRenditionPopupPdf());
        $t->same(['Article Body'], (new PdfTextExtractor())->extractTextLines($movieSoundRenditionPopupPdf()));
        $t->true(!str_contains($plainText, 'Movie Popup Payload Noise'));
        $t->true(!str_contains($plainText, 'Sound Action Payload Noise'));
        $t->true(!str_contains($plainText, 'Movie popup stays metadata'));
        $t->true(!str_contains($plainText, 'Sound popup stays metadata'));
    },
    'reviews screen rendition play and screen parameter dictionaries without executing media' => static function (TestRunner $t) use ($screenRenditionPlaybackPolicyPdf): void {
        $pages = (new PdfRichMediaAnnotationExtractor())->extractReviewAnnotations($screenRenditionPlaybackPolicyPdf());

        $t->same(1, count($pages));
        $t->same(['Screen'], array_column($pages[0]['annotations'], 'subtype'));

        $screen = $pages[0]['annotations'][0];
        $t->same('Screen training audio', $screen['title']);
        $t->same(['Rendition'], $screen['action_types']);
        $t->same(['training-audio.mp3'], $screen['file_names']);
        $t->same(false, $screen['executes_media']);

        $action = $screen['actions'][0];
        $t->same('Rendition', $action['action_type']);
        $t->same('media-rendition-review', $action['safety']);
        $t->same(5, $action['target_annotation_object']);
        $t->same(11, $action['rendition']['dictionary_object']);
        $t->same('Policy rendition', $action['rendition']['name']);
        $t->same('audio/mpeg', $action['rendition']['media_clip']['content_type']);
        $t->same('training-audio.mp3', $action['rendition']['media_clip']['file']);
        $t->same(['en-US', 'Training narration'], $action['rendition']['media_clip']['alternate_text']);

        $play = $action['rendition']['play_parameters'];
        $t->same(15, $play['dictionary_object']);
        $t->same(16, $play['must_honor']['dictionary_object']);
        $t->same(['C', 'V', 'R', 'T', 'Lang'], $play['must_honor']['keys']);
        $t->same(['C' => true, 'R' => false], $play['must_honor']['booleans']);
        $t->same(['V' => 0.85], $play['must_honor']['numbers']);
        $t->same(['T' => 'Must honor playback', 'Lang' => 'en-US'], $play['must_honor']['strings']);
        $t->same(null, $play['must_honor']['names'] ?? null);
        $t->same(null, $play['must_honor']['number_arrays'] ?? null);
        $t->same(null, $play['best_effort']['dictionary_object']);
        $t->same(['C', 'V', 'Mode', 'T', 'Dur'], $play['best_effort']['keys']);
        $t->same(['C' => false], $play['best_effort']['booleans']);
        $t->same(['V' => 0.4], $play['best_effort']['numbers']);
        $t->same(['Mode' => 'Once'], $play['best_effort']['names']);
        $t->same(['T' => 'Best effort playback'], $play['best_effort']['strings']);
        $t->same(['Dur' => [0.0, 15.0]], $play['best_effort']['number_arrays']);

        $screenParameters = $action['rendition']['screen_parameters'];
        $t->same(18, $screenParameters['dictionary_object']);
        $t->same(19, $screenParameters['must_honor']['dictionary_object']);
        $t->same(['W', 'O', 'Title'], $screenParameters['must_honor']['keys']);
        $t->same(['W' => 'Window'], $screenParameters['must_honor']['names']);
        $t->same(['O' => 0.9], $screenParameters['must_honor']['numbers']);
        $t->same(['Title' => 'Floating review player'], $screenParameters['must_honor']['strings']);
        $t->same(20, $screenParameters['best_effort']['dictionary_object']);
        $t->same(['W', 'O', 'C'], $screenParameters['best_effort']['keys']);
        $t->same(['W' => 'FullScreen'], $screenParameters['best_effort']['names']);
        $t->same(['O' => 0.5], $screenParameters['best_effort']['numbers']);
        $t->same(['C' => false], $screenParameters['best_effort']['booleans']);

        $plainText = (new PdfTextExtractor())->extractPlainText($screenRenditionPlaybackPolicyPdf());
        $t->same(['Article Body'], (new PdfTextExtractor())->extractTextLines($screenRenditionPlaybackPolicyPdf()));
        $t->true(!str_contains($plainText, 'Playback Policy Noise'));
        $t->true(!str_contains($plainText, 'Leaked Playback Payload'));
    },
];
