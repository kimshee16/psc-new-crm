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
            '+61' => 'Australia +61',
            '+63' => 'Philippines +63',
            '+64' => 'New Zealand +64',
            '+1' => 'United States / Canada +1',
            '+44' => 'United Kingdom +44',
            '+93' => 'Afghanistan +93',
            '+355' => 'Albania +355',
            '+213' => 'Algeria +213',
            '+376' => 'Andorra +376',
            '+244' => 'Angola +244',
            '+1-268' => 'Antigua and Barbuda +1-268',
            '+54' => 'Argentina +54',
            '+374' => 'Armenia +374',
            '+43' => 'Austria +43',
            '+994' => 'Azerbaijan +994',
            '+1-242' => 'Bahamas +1-242',
            '+973' => 'Bahrain +973',
            '+880' => 'Bangladesh +880',
            '+1-246' => 'Barbados +1-246',
            '+375' => 'Belarus +375',
            '+32' => 'Belgium +32',
            '+501' => 'Belize +501',
            '+229' => 'Benin +229',
            '+975' => 'Bhutan +975',
            '+591' => 'Bolivia +591',
            '+387' => 'Bosnia Herzegovina +387',
            '+267' => 'Botswana +267',
            '+55' => 'Brazil +55',
            '+673' => 'Brunei +673',
            '+359' => 'Bulgaria +359',
            '+226' => 'Burkina Faso +226',
            '+257' => 'Burundi +257',
            '+855' => 'Cambodia +855',
            '+237' => 'Cameroon +237',
            '+238' => 'Cape Verde +238',
            '+236' => 'Central African Rep +236',
            '+235' => 'Chad +235',
            '+56' => 'Chile +56',
            '+86' => 'China +86',
            '+57' => 'Colombia +57',
            '+269' => 'Comoros +269',
            '+242' => 'Congo +242',
            '+243' => 'Congo DR +243',
            '+506' => 'Costa Rica +506',
            '+385' => 'Croatia +385',
            '+53' => 'Cuba +53',
            '+357' => 'Cyprus +357',
            '+420' => 'Czech Republic +420',
            '+45' => 'Denmark +45',
            '+253' => 'Djibouti +253',
            '+1-767' => 'Dominica +1-767',
            '+1-809' => 'Dominican Republic +1-809',
            '+670' => 'East Timor +670',
            '+593' => 'Ecuador +593',
            '+20' => 'Egypt +20',
            '+503' => 'El Salvador +503',
            '+240' => 'Equatorial Guinea +240',
            '+291' => 'Eritrea +291',
            '+372' => 'Estonia +372',
            '+251' => 'Ethiopia +251',
            '+679' => 'Fiji +679',
            '+358' => 'Finland +358',
            '+33' => 'France +33',
            '+241' => 'Gabon +241',
            '+220' => 'Gambia +220',
            '+995' => 'Georgia +995',
            '+49' => 'Germany +49',
            '+233' => 'Ghana +233',
            '+30' => 'Greece +30',
            '+1-473' => 'Grenada +1-473',
            '+502' => 'Guatemala +502',
            '+224' => 'Guinea +224',
            '+245' => 'Guinea-Bissau +245',
            '+592' => 'Guyana +592',
            '+509' => 'Haiti +509',
            '+504' => 'Honduras +504',
            '+36' => 'Hungary +36',
            '+354' => 'Iceland +354',
            '+91' => 'India +91',
            '+62' => 'Indonesia +62',
            '+98' => 'Iran +98',
            '+964' => 'Iraq +964',
            '+353' => 'Ireland +353',
            '+972' => 'Israel +972',
            '+39' => 'Italy +39',
            '+225' => 'Ivory Coast +225',
            '+1-876' => 'Jamaica +1-876',
            '+81' => 'Japan +81',
            '+962' => 'Jordan +962',
            '+7' => 'Kazakhstan / Russia +7',
            '+254' => 'Kenya +254',
            '+686' => 'Kiribati +686',
            '+383' => 'Kosovo +383',
            '+965' => 'Kuwait +965',
            '+996' => 'Kyrgyzstan +996',
            '+856' => 'Laos +856',
            '+371' => 'Latvia +371',
            '+961' => 'Lebanon +961',
            '+266' => 'Lesotho +266',
            '+231' => 'Liberia +231',
            '+218' => 'Libya +218',
            '+423' => 'Liechtenstein +423',
            '+370' => 'Lithuania +370',
            '+352' => 'Luxembourg +352',
            '+389' => 'Macedonia +389',
            '+261' => 'Madagascar +261',
            '+265' => 'Malawi +265',
            '+60' => 'Malaysia +60',
            '+960' => 'Maldives +960',
            '+223' => 'Mali +223',
            '+356' => 'Malta +356',
            '+692' => 'Marshall Islands +692',
            '+222' => 'Mauritania +222',
            '+230' => 'Mauritius +230',
            '+52' => 'Mexico +52',
            '+691' => 'Micronesia +691',
            '+373' => 'Moldova +373',
            '+377' => 'Monaco +377',
            '+976' => 'Mongolia +976',
            '+382' => 'Montenegro +382',
            '+212' => 'Morocco +212',
            '+258' => 'Mozambique +258',
            '+95' => 'Myanmar +95',
            '+264' => 'Namibia +264',
            '+674' => 'Nauru +674',
            '+977' => 'Nepal +977',
            '+31' => 'Netherlands +31',
            '+505' => 'Nicaragua +505',
            '+227' => 'Niger +227',
            '+234' => 'Nigeria +234',
            '+47' => 'Norway +47',
            '+968' => 'Oman +968',
            '+92' => 'Pakistan +92',
            '+680' => 'Palau +680',
            '+507' => 'Panama +507',
            '+675' => 'Papua New Guinea +675',
            '+595' => 'Paraguay +595',
            '+51' => 'Peru +51',
            '+48' => 'Poland +48',
            '+351' => 'Portugal +351',
            '+974' => 'Qatar +974',
            '+40' => 'Romania +40',
            '+250' => 'Rwanda +250',
            '+1-869' => 'St Kitts and Nevis +1-869',
            '+1-758' => 'St Lucia +1-758',
            '+1-784' => 'St Vincent +1-784',
            '+685' => 'Samoa +685',
            '+378' => 'San Marino +378',
            '+239' => 'Sao Tome and Principe +239',
            '+966' => 'Saudi Arabia +966',
            '+221' => 'Senegal +221',
            '+381' => 'Serbia +381',
            '+248' => 'Seychelles +248',
            '+232' => 'Sierra Leone +232',
            '+65' => 'Singapore +65',
            '+421' => 'Slovakia +421',
            '+386' => 'Slovenia +386',
            '+677' => 'Solomon Islands +677',
            '+252' => 'Somalia +252',
            '+27' => 'South Africa +27',
            '+82' => 'South Korea +82',
            '+211' => 'South Sudan +211',
            '+34' => 'Spain +34',
            '+94' => 'Sri Lanka +94',
            '+249' => 'Sudan +249',
            '+597' => 'Suriname +597',
            '+268' => 'Swaziland +268',
            '+46' => 'Sweden +46',
            '+41' => 'Switzerland +41',
            '+963' => 'Syria +963',
            '+886' => 'Taiwan +886',
            '+992' => 'Tajikistan +992',
            '+255' => 'Tanzania +255',
            '+66' => 'Thailand +66',
            '+228' => 'Togo +228',
            '+676' => 'Tonga +676',
            '+1-868' => 'Trinidad and Tobago +1-868',
            '+216' => 'Tunisia +216',
            '+90' => 'Turkey +90',
            '+993' => 'Turkmenistan +993',
            '+688' => 'Tuvalu +688',
            '+256' => 'Uganda +256',
            '+380' => 'Ukraine +380',
            '+971' => 'United Arab Emirates +971',
            '+598' => 'Uruguay +598',
            '+998' => 'Uzbekistan +998',
            '+678' => 'Vanuatu +678',
            '+379' => 'Vatican City +379',
            '+58' => 'Venezuela +58',
            '+84' => 'Vietnam +84',
            '+967' => 'Yemen +967',
            '+260' => 'Zambia +260',
            '+263' => 'Zimbabwe +263',
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
