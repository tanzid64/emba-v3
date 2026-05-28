<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Admission Result Configuration
    |--------------------------------------------------------------------------
    |
    | This file is for storing the configuration related to admission results.
    | You can specify settings such as passing marks, grading criteria, etc.
    |
    */
    // Fallback pass mark used only when a batch has no admission setting.
    // The authoritative, per-batch value lives on admission_settings.pass_mark.
    'passing_marks' => 40,
    // Fallback MCQ-for-viva eligibility threshold; per-batch value lives on
    // admission_settings.viva_mcq_threshold.
    'viva_mcq_threshold' => 25,
    'max_marks' => 100,
    'max_mcq_marks' => 55,
    'max_written_marks' => 25,
    'max_viva_marks' => 5,
    'max_schooling_marks' => 5,
    'max_experience_marks' => 10,
];
