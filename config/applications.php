<?php

return [
    // PSC-33 choices. Add subclasses here, or restrict/extend them per workflow.
    'creation_options' => [
        'visa_subclass' => [
            '500 - Student Visa',
            '590 - Student Guardian Visa',
            '482 - Skills in Demand (SID) Visa',
            '186 - Employer Nomination Scheme Visa',
            '494 - Skilled Employer Sponsored Regional Visa',
            'DAMA - Designated Area Migration Agreement Visa',
            '407 - Training Visa',
            '417 - Working Holiday Visa',
            '462 - Work and Holiday Visa',
            '400 - Temporary Work (Short Stay Specialist) Visa',
            '189 - SkillSelect – Independent Visa',
            '190 - SkillSelect – Skilled Nominated Visa',
            '491 - SkillSelect – Skilled Regional Visa',
            '191 - Permanent Residence (Skilled Regional) Visa',
            '485 - Temporary Graduate Visa (Post higher education)',
            '485 - Temporary Graduate Visa (Post vocational)',
            'GTI - Global Talent – Independent Program',
            '820/801 - Partner Visa - Onshore',
            '309/100 - Partner Visa - Offshore',
            '300 - Prospective Marriage/Fiancé Visa',
            '143 - Contributory Parent Visa',
        ],
        'assessment_body' => [
            'VETASSESS', 'Engineers Australia', 'ACS', 'CPA Australia/ CA ANZ/ IPA',
            'TRA', 'AHPRA/NMBA', 'ANMAC', 'AITSL',
        ],
    ],
    // Inclusive calendar-day thresholds. Pending the category-specific PSC-4 rules;
    // leave future dates neutral instead of inventing red/amber cutoffs.
    'critical_date_thresholds' => [
        'Study' => [],
        'Visa' => [],
        'Citizenship' => [],
        'Assessment' => [],
        'Nomination' => [],
        'SBS' => [],
    ],
];
