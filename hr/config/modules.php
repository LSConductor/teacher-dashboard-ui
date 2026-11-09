<?php

/**
 * Casual HR is the live module. Catalyst is the later program builder.
 * Those desks stay off so this app does not replace the primary portal.
 */
return [
    'hr' => [
        'enabled' => true,
        'label' => 'Casual HR',
    ],

    'catalyst' => [
        'enabled' => false,
        'label' => 'Catalyst program builder',
        'note' => 'Planned on this same app and database. The primary WordPress program builder is not modified by this release.',
        'future' => [
            'report_writer' => 'Report writer',
            'markbook' => 'Markbook',
            'rubrics' => 'Rubrics',
            'scope_and_sequence' => 'Scope and sequence',
            'outcome_audits' => 'Outcome audits',
        ],
    ],
];
