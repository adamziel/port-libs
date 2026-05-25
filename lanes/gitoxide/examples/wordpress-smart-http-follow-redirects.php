<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

$fixture = require __DIR__ . '/../fixtures/wordpress-smart-http-follow-redirects.php';

return [
    'requestMethods' => $fixture['requestMethods'],
    'requestUrls' => $fixture['requestUrls'],
    'redirectCookieHeader' => $fixture['redirectCookieHeader'],
    'expiredRedirectCookieOmitted' => $fixture['expiredRedirectCookieOmitted'],
    'postBodyPreserved' => $fixture['postBodyPreserved'],
    'rewritingPostRedirectRejected' => $fixture['rewritingPostRedirectRejected'],
    'rewritingRequestMethods' => $fixture['rewritingRequestMethods'],
    'permanentPostRedirectRejected' => $fixture['permanentPostRedirectRejected'],
    'permanentRequestMethods' => $fixture['permanentRequestMethods'],
    'seeOtherPostRedirectRejected' => $fixture['seeOtherPostRedirectRejected'],
    'seeOtherRequestMethods' => $fixture['seeOtherRequestMethods'],
    'wrongEndpointPostRedirectRejected' => $fixture['wrongEndpointPostRedirectRejected'],
    'wrongEndpointRequestMethods' => $fixture['wrongEndpointRequestMethods'],
    'credentialPostRedirectRejected' => $fixture['credentialPostRedirectRejected'],
    'credentialRequestMethods' => $fixture['credentialRequestMethods'],
    'fragmentPostRedirectRejected' => $fixture['fragmentPostRedirectRejected'],
    'fragmentRequestMethods' => $fixture['fragmentRequestMethods'],
    'missingLocationPostRedirectRejected' => $fixture['missingLocationPostRedirectRejected'],
    'missingLocationRequestMethods' => $fixture['missingLocationRequestMethods'],
    'responseSuccessful' => $fixture['responseSuccessful'],
];
