<?php

$degrees = [
    [
        'label' => 'S.S.C / Equivalent',
        'description' => 'The applicant has completed their Secondary School Certificate or an equivalent qualification.',
        'has_options' => false,
        'board_type' => 'select',
        'sub_type' => 'select',
        'subjects' => [
            'Science',
            'Humanities',
            'Business Studies',
            'Technical / Vocational',
            'Madrasah',
            'Others'
        ],
        'boards' => [
            'Dhaka',
            'Chittagong',
            'Rajshahi',
            'Khulna',
            'Comilla',
            'Jessore',
            'Mymensingh',
            'Rajshahi',
            'Rangpur',
            'Barisal',
            'Sylhet',
            'Dinajpur',
            'Madrasah',
            'Technical',
            'Others'
        ],
    ],
    [
        'label' => 'Higher Secondary / Equivalent',
        'description' => 'The applicant has completed their Higher Secondary Certificate or an equivalent qualification.',
        'has_options' => false,
        'board_type' => 'select',
        'sub_type' => 'select',
        'subjects' => [
            'Science',
            'Humanities',
            'Business Studies',
            'Technical / Vocational',
            'Madrasah',
            'Others'
        ],
        'boards' => [
            'Dhaka',
            'Chittagong',
            'Rajshahi',
            'Khulna',
            'Comilla',
            'Jessore',
            'Mymensingh',
            'Rajshahi',
            'Rangpur',
            'Barisal',
            'Sylhet',
            'Dinajpur',
            'Madrasah',
            'Technical',
            'Others'
        ],
    ],
    [
        'label' => 'Bachelor\'s Degree',
        'description' => 'The applicant has completed a Bachelor\'s degree program.',
        'has_options' => false,
        'board_type' => 'input',
        'sub_type' => 'input',
    ],
    [
        'label' => 'Master\'s Degree',
        'description' => 'The applicant has completed a Master\'s degree program.',
        'has_options' => false,
        'board_type' => 'input',
        'sub_type' => 'input',

    ],
    [
        'label' => 'Other Degree',
        'description' => 'The applicant has completed a Doctor of Philosophy (Ph.D.) or an equivalent research degree.',
        'has_options' => true,
        'board_type' => 'input',
        'sub_type' => 'select',
        'options' => [
            'Doctoral / Ph.D.',
            'M.Phil.',
            'Ed.D.',
            'D.Sc.',
            'D.Litt.',
            'Diploma',
            'MBBS',
            'CA / CMA'
        ],
    ]
];

$scales = [
    [
        'label' => 'Out of 10.00',
        'value' => '10.00',
    ],
    [
        'label' => 'Out of 5.00',
        'value' => '5.00',
    ],
    [
        'label' => 'Out of 4.00',
        'value' => '4.00',
    ],
    [
        'label' => 'Class System',
        'value' => 'Class System',
    ]
];

return [
    'degrees' => $degrees,
    'scales' => $scales,
];
