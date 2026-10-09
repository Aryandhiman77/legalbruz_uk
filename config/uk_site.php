<?php

return [
    /*
    | Legacy India-only case-management services are retained in the codebase so
    | historical records remain readable, but they are not offered on the UK site.
    */
    'legacy_services_enabled' => env('UK_LEGACY_SERVICES_ENABLED', false),
    'trademark_search_widget_enabled' => env('UK_TRADEMARK_SEARCH_WIDGET_ENABLED', false),

    /* Shared by the client intake and admin review editor so select values cannot drift. */
    'applicant_types' => [
        'individual' => 'Individual',
        'limited_company' => 'Limited company',
        'llp' => 'Limited liability partnership',
        'partnership' => 'Partnership',
        'charity' => 'Charity',
        'joint_applicants' => 'Joint applicants',
        'other' => 'Other',
    ],

    /* Existing authorised-signatory roles retained from the original form. */
    'authorised_person_positions' => [
        'Director' => 'Director',
        'Partner' => 'Partner',
        'Proprietor / Individual / Trader' => 'Proprietor / Individual / Trader',
        'Authorised Signatory' => 'Authorised Signatory',
    ],
];
