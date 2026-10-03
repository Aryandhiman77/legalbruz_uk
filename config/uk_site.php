<?php

return [
    /*
    | Legacy India-only case-management services are retained in the codebase so
    | historical records remain readable, but they are not offered on the UK site.
    */
    'legacy_services_enabled' => env('UK_LEGACY_SERVICES_ENABLED', false),

    /* Existing authorised-signatory roles retained from the original form. */
    'authorised_person_positions' => [
        'Director' => 'Director',
        'Partner' => 'Partner',
        'Proprietor / Individual / Trader' => 'Proprietor / Individual / Trader',
        'Authorised Signatory' => 'Authorised Signatory',
    ],
];
