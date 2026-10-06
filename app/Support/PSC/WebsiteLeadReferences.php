<?php

namespace App\Support\PSC;

use App\Models\Location;

class WebsiteLeadReferences
{
    /**
     * @return list<string>
     */
    public static function countries(): array
    {
        return [
            'Afghanistan', 'Albania', 'Algeria', 'Andorra', 'Angola', 'Antigua & Deps', 'Argentina', 'Armenia',
            'Australia', 'Austria', 'Azerbaijan', 'Bahamas', 'Bahrain', 'Bangladesh', 'Barbados', 'Belarus',
            'Belgium', 'Belize', 'Benin', 'Bhutan', 'Bolivia', 'Bosnia Herzegovina', 'Botswana', 'Brazil',
            'Brunei', 'Bulgaria', 'Burkina', 'Burundi', 'Cambodia', 'Cameroon', 'Canada', 'Cape Verde',
            'Central African Rep', 'Chad', 'Chile', 'China', 'Colombia', 'Comoros', 'Congo', 'Congo {Democratic Rep}',
            'Costa Rica', 'Croatia', 'Cuba', 'Cyprus', 'Czech Republic', 'Denmark', 'Djibouti', 'Dominica',
            'Dominican Republic', 'East Timor', 'Ecuador', 'Egypt', 'El Salvador', 'Equatorial Guinea', 'Eritrea',
            'Estonia', 'Ethiopia', 'Fiji', 'Finland', 'France', 'Gabon', 'Gambia', 'Georgia', 'Germany', 'Ghana',
            'Greece', 'Grenada', 'Guatemala', 'Guinea', 'Guinea-Bissau', 'Guyana', 'Haiti', 'Honduras', 'Hungary',
            'Iceland', 'India', 'Indonesia', 'Iran', 'Iraq', 'Ireland {Republic}', 'Israel', 'Italy', 'Ivory Coast',
            'Jamaica', 'Japan', 'Jordan', 'Kazakhstan', 'Kenya', 'Kiribati', 'Korea North', 'Korea South', 'Kosovo',
            'Kuwait', 'Kyrgyzstan', 'Laos', 'Latvia', 'Lebanon', 'Lesotho', 'Liberia', 'Libya', 'Liechtenstein',
            'Lithuania', 'Luxembourg', 'Macedonia', 'Madagascar', 'Malawi', 'Malaysia', 'Maldives', 'Mali', 'Malta',
            'Marshall Islands', 'Mauritania', 'Mauritius', 'Mexico', 'Micronesia', 'Moldova', 'Monaco', 'Mongolia',
            'Montenegro', 'Morocco', 'Mozambique', 'Myanmar, {Burma}', 'Namibia', 'Nauru', 'Nepal', 'Netherlands',
            'New Zealand', 'Nicaragua', 'Niger', 'Nigeria', 'Norway', 'Oman', 'Pakistan', 'Palau', 'Panama',
            'Papua New Guinea', 'Paraguay', 'Peru', 'Philippines', 'Poland', 'Portugal', 'Qatar', 'Romania',
            'Russian Federation', 'Rwanda', 'St Kitts & Nevis', 'St Lucia', 'Saint Vincent & the Grenadines',
            'Samoa', 'San Marino', 'Sao Tome & Principe', 'Saudi Arabia', 'Senegal', 'Serbia', 'Seychelles',
            'Sierra Leone', 'Singapore', 'Slovakia', 'Slovenia', 'Solomon Islands', 'Somalia', 'South Africa',
            'South Sudan', 'Spain', 'Sri Lanka', 'Sudan', 'Suriname', 'Swaziland', 'Sweden', 'Switzerland',
            'Syria', 'Taiwan', 'Tajikistan', 'Tanzania', 'Thailand', 'Togo', 'Tonga', 'Trinidad & Tobago',
            'Tunisia', 'Turkey', 'Turkmenistan', 'Tuvalu', 'Uganda', 'Ukraine', 'United Arab Emirates',
            'United Kingdom', 'United States', 'Uruguay', 'Uzbekistan', 'Vanuatu', 'Vatican City', 'Venezuela',
            'Vietnam', 'Yemen', 'Zambia', 'Zimbabwe',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function phoneCountryCodes(): array
    {
        return [
            '+61' => 'Australia (+61)',
            '+63' => 'Philippines (+63)',
            '+64' => 'New Zealand (+64)',
            '+1' => 'United States / Canada (+1)',
            '+44' => 'United Kingdom (+44)',
            '+55' => 'Brazil (+55)',
            '+56' => 'Chile (+56)',
            '+57' => 'Colombia (+57)',
            '+51' => 'Peru (+51)',
            '+52' => 'Mexico (+52)',
            '+54' => 'Argentina (+54)',
            '+62' => 'Indonesia (+62)',
            '+60' => 'Malaysia (+60)',
            '+65' => 'Singapore (+65)',
            '+66' => 'Thailand (+66)',
            '+84' => 'Vietnam (+84)',
            '+86' => 'China (+86)',
            '+91' => 'India (+91)',
            '+977' => 'Nepal (+977)',
            '+880' => 'Bangladesh (+880)',
            '+81' => 'Japan (+81)',
            '+82' => 'Korea South (+82)',
            '+971' => 'United Arab Emirates (+971)',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function offices(): array
    {
        return [
            'MEL' => 'Melbourne',
            'SYD' => 'Sydney',
            'BNE' => 'Brisbane',
            'PER' => 'Perth',
            'ADL' => 'Adelaide',
            'MANILA' => 'Manila',
            'PHIL-OFFSHORE' => 'Philippines Offshore',
            'LATAM' => 'LATAM',
        ];
    }

    /**
     * @return list<array{name: string, office_code: string}>
     */
    public static function defaultLocations(): array
    {
        return [
            ['name' => 'Melbourne', 'office_code' => 'MEL'],
            ['name' => 'Sydney', 'office_code' => 'SYD'],
            ['name' => 'Brisbane', 'office_code' => 'BNE'],
            ['name' => 'Perth', 'office_code' => 'PER'],
            ['name' => 'Adelaide', 'office_code' => 'ADL'],
            ['name' => 'Manila', 'office_code' => 'MANILA'],
            ['name' => 'Philippines Offshore', 'office_code' => 'PHIL-OFFSHORE'],
            ['name' => 'Jakarta', 'office_code' => 'MEL'],
            ['name' => 'Kathmandu', 'office_code' => 'MEL'],
            ['name' => 'Mumbai', 'office_code' => 'MEL'],
            ['name' => 'Beijing', 'office_code' => 'MEL'],
            ['name' => 'Offshore', 'office_code' => 'MEL'],
            ['name' => 'LATAM', 'office_code' => 'LATAM'],
        ];
    }

    /**
     * @return list<string>
     */
    public function locationNames(): array
    {
        $locations = Location::query()
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name')
            ->all();

        if ($locations !== []) {
            return array_values(array_map('strval', $locations));
        }

        return array_column(self::defaultLocations(), 'name');
    }

    public function officeCodeForLocation(string $location): string
    {
        $configured = Location::query()
            ->where('active', true)
            ->where('name', $location)
            ->value('office_code');

        if (is_string($configured) && $configured !== '') {
            return strtoupper($configured);
        }

        foreach (self::defaultLocations() as $defaultLocation) {
            if ($defaultLocation['name'] === $location) {
                return $defaultLocation['office_code'];
            }
        }

        return 'MEL';
    }
}
