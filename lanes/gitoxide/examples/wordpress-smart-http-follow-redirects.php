<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

$fixture = require __DIR__ . '/../fixtures/wordpress-smart-http-follow-redirects.php';

return [
    'requestMethods' => $fixture['requestMethods'],
    'requestUrls' => $fixture['requestUrls'],
    'redirectCookieHeader' => $fixture['redirectCookieHeader'],
    'expiredRedirectCookieOmitted' => $fixture['expiredRedirectCookieOmitted'],
    'maxAgeRedirectCookieRetained' => $fixture['maxAgeRedirectCookieRetained'],
    'pathScopedRedirectCookieOmitted' => $fixture['pathScopedRedirectCookieOmitted'],
    'foreignDomainRedirectCookieOmitted' => $fixture['foreignDomainRedirectCookieOmitted'],
    'malformedPathRedirectCookiesOmitted' => $fixture['malformedPathRedirectCookiesOmitted'],
    'secureCookiePlainRedirectOmitted' => $fixture['secureCookiePlainRedirectOmitted'],
    'defaultPathRedirectCookieOmitted' => $fixture['defaultPathRedirectCookieOmitted'],
    'postRedirectDefaultPathCookieOmitted' => $fixture['postRedirectDefaultPathCookieOmitted'],
    'redirectChainCookiesRecomputed' => $fixture['redirectChainCookiesRecomputed'],
    'redirectChainRequestMethods' => $fixture['redirectChainRequestMethods'],
    'redirectChainFirstRetryCookieHeader' => $fixture['redirectChainFirstRetryCookieHeader'],
    'redirectChainFinalCookieHeader' => $fixture['redirectChainFinalCookieHeader'],
    'sameNameScopedRedirectCookieRetained' => $fixture['sameNameScopedRedirectCookieRetained'],
    'sameScopeRedirectCookieReplaced' => $fixture['sameScopeRedirectCookieReplaced'],
    'callerCookieHeaderPreserved' => $fixture['callerCookieHeaderPreserved'],
    'pathSpecificRedirectCookiesFirst' => $fixture['pathSpecificRedirectCookiesFirst'],
    'dotSegmentPostRedirectNormalized' => $fixture['dotSegmentPostRedirectNormalized'],
    'curlDefaultRedirectLimitAccepted' => $fixture['curlDefaultRedirectLimitAccepted'],
    'curlDefaultRedirectLimitRequestCount' => $fixture['curlDefaultRedirectLimitRequestCount'],
    'curlDefaultRedirectOverflowRejected' => $fixture['curlDefaultRedirectOverflowRejected'],
    'curlDefaultRedirectOverflowRequestCount' => $fixture['curlDefaultRedirectOverflowRequestCount'],
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
