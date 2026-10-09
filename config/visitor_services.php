<?php

return [
    'trademark_registration' => [
        'label' => 'UK Trade Mark Filing',
        'description' => 'New UK trade mark applications',
        'icon' => 'bi-patch-check',
        'admin_route' => 'admin.all-applications',
    ],
    'classification_specification' => [
        'label' => 'Classification & Specification',
        'description' => 'Class recommendations and goods/services wording',
        'icon' => 'bi-list-check',
        'admin_route' => 'admin.contact-messages.index',
        'admin_parameters' => ['search' => 'Classification & Specification'],
    ],
    'trademark_search_report' => [
        'label' => 'Trademark Search Report',
        'description' => 'UK register search and written risk report',
        'icon' => 'bi-search',
        'admin_route' => 'admin.trademark-search-reports.index',
        'contact_visible' => false,
    ],
    'examination_report_reply' => [
        'label' => 'Examination Report',
        'description' => 'Review and response support for UKIPO reports',
        'icon' => 'bi-file-earmark-check',
        'admin_route' => 'admin.examination-reply.index',
    ],
    'opposition_management' => [
        'label' => 'Opposition Service',
        'description' => 'Defend or oppose a conflicting trade mark',
        'icon' => 'bi-shield-check',
        'admin_route' => 'admin.dashboard',
        'admin_fragment' => 'opposition-objections',
    ],
    'consultation_call' => [
        'label' => 'Book a Call',
        'description' => 'Paid consultation call bookings',
        'icon' => 'bi-telephone',
        'admin_route' => 'admin.contact-messages.index',
        'admin_parameters' => ['search' => 'Consultation Call'],
    ],
];
