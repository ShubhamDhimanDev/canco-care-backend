<?php

return [

    /*
    | Content-Security-Policy: "report-only" (default — the browser reports violations in its console but
    | blocks nothing), "enforce", or "off". Soak on staging in report-only, then set SECURITY_CSP=enforce.
    | Never sent when APP_ENV=local (the Vite dev server lives on another origin).
    */
    'csp' => env('SECURITY_CSP', 'report-only'),

    /*
    | Strict-Transport-Security, sent on every server (any APP_ENV except local) when the request or APP_URL is HTTPS:
    |   "short" (default) — 5 minutes, no subdomains: right for staging, and for a domain that hosts other sites;
    |   "full"            — 1 year + includeSubDomains: production only, once HTTPS works everywhere;
    |   "off"             — send nothing (use when the web server adds the header itself).
    */
    'hsts' => env('SECURITY_HSTS', 'short'),

];
