<?php

return [
    [
        'title' => 'Account Registered',
        'description' => 'The applicant has registered an account in the system.',
        'icon' => 'user-plus',
        'model' => 'App\Models\Applicant',
    ],
    [
        'title' => 'Profile Completed',
        'description' => 'The applicant has completed their profile information.',
        'icon' => 'user-check',
        'model' => 'App\Models\ApplicantProfile',
    ],
    [
        'title' => 'Application Submitted',
        'description' => 'The applicant has submitted their application for review.',
        'icon' => 'send-horizontal',
        'model' => 'App\Models\Application',
    ],
];
