<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

$fixture = require __DIR__ . '/../fixtures/wordpress-smart-http-follow-redirects.php';

return [
    'requestMethods' => $fixture['requestMethods'],
    'requestUrls' => $fixture['requestUrls'],
    'postBodyPreserved' => $fixture['postBodyPreserved'],
    'rewritingPostRedirectRejected' => $fixture['rewritingPostRedirectRejected'],
    'rewritingRequestMethods' => $fixture['rewritingRequestMethods'],
    'seeOtherPostRedirectRejected' => $fixture['seeOtherPostRedirectRejected'],
    'seeOtherRequestMethods' => $fixture['seeOtherRequestMethods'],
    'responseSuccessful' => $fixture['responseSuccessful'],
];
