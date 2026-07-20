<?php

function mapSpreadsheetMedium(string $value): string
{
    $value = strtolower(trim($value));

    return match ($value) {
        'olt'            => 'fiber_olt',
        'mediacon'       => 'fiber_mediacon',
        'wireless radio' => 'wireless_radio',
        default          => 'fiber_olt',
    };
}

function parseSpreadsheetAddress(string $raw): array
{
    $raw = trim(preg_replace('/\s+/', ' ', $raw));
    $default = [
        'address'  => 'Maralag',
        'barangay' => 'Maralag',
        'city'     => 'Dumingag',
        'province' => 'Zamboanga Del Sur',
    ];

    if ($raw === '') {
        return $default;
    }

    if (stripos($raw, 'Pugwan') !== false && stripos($raw, 'Mahayag') !== false) {
        return [
            'address'  => 'Pugwan',
            'barangay' => 'Pugwan',
            'city'     => 'Mahayag',
            'province' => 'Zamboanga Del Sur',
        ];
    }

    if (stripos($raw, 'Manguiles') !== false) {
        return [
            'address'  => 'Manguiles',
            'barangay' => 'Manguiles',
            'city'     => 'Dumingag',
            'province' => 'Zamboanga Del Sur',
        ];
    }

    if (stripos($raw, 'Lawis') !== false) {
        return [
            'address'  => 'Lawis',
            'barangay' => 'Maralag',
            'city'     => 'Dumingag',
            'province' => 'Zamboanga Del Sur',
        ];
    }

    if (preg_match('/^(P\d+)\b/i', $raw, $match)) {
        return [
            'address'  => strtoupper($match[1]),
            'barangay' => 'Maralag',
            'city'     => 'Dumingag',
            'province' => 'Zamboanga Del Sur',
        ];
    }

    return $default;
}

$maralagCustomerRecords = [
    ['Jaime Joaquin', 'Maralag, Dumingag, Zamboanga Del Sur', '2020-12-27', 'Mediacon', 'Standard 10Mbps'],
    ['Wilbert Rupinta', 'Maralag, Dumingag, Zamboanga Del Sur', '2021-01-01', 'Mediacon', 'Basic 5Mbps'],
    ['Narding Uy', 'Maralag, Dumingag, Zamboanga Del Sur', '2021-11-02', 'Mediacon', 'Standard 10Mbps'],
    ['Badilla', 'Maralag, Dumingag, Zamboanga Del Sur', '2021-11-03', 'OLT', 'Standard 10Mbps'],
    ['Alfren Sumolung', 'Maralag, Dumingag, Zamboanga Del Sur', '2022-02-03', 'OLT', 'Standard 10Mbps'],
    ['Jillboy Gallego', 'Maralag, Dumingag, Zamboanga Del Sur', '2022-02-03', 'OLT', 'Others'],
    ['Jelma Abellana', 'Maralag, Dumingag, Zamboanga Del Sur', '2022-08-03', 'OLT', 'Standard 10Mbps'],
    ['Sarona', 'Maralag, Dumingag, Zamboanga Del Sur', '2021-11-04', 'OLT', 'Standard 10Mbps'],
    ['Kuya Dodong', 'Manguiles, Zamboanga Del Sur', '2020-11-05', 'Wireless Radio', 'Premium 25Mbps'],
    ['Maam Aloyan', 'Maralag, Dumingag, Zamboanga Del Sur', '2021-10-06', 'OLT', 'Standard 10Mbps'],
    ['Dyna Ostan', 'Maralag, Dumingag, Zamboanga Del Sur', '2021-08-07', 'Mediacon', 'Standard 10Mbps'],
    ['Boboy Insek', 'Maralag, Dumingag, Zamboanga Del Sur', '2021-08-07', 'Wireless Radio', 'Standard 10Mbps'],
    ['Janice Uy', 'Maralag, Dumingag, Zamboanga Del Sur', '2021-08-07', 'Wireless Radio', 'Standard 10Mbps'],
    ['Dongie Agnes', 'Maralag, Dumingag, Zamboanga Del Sur', '2021-10-07', 'Mediacon', 'Standard 10Mbps'],
    ['Peta Baran', 'Maralag, Dumingag, Zamboanga Del Sur', '2022-02-07', 'OLT', 'Standard 10Mbps'],
    ['Felipe Rabanal', 'Maralag, Dumingag, Zamboanga Del Sur', '2022-08-09', 'OLT', 'Standard 10Mbps'],
    ['Diding Coop', 'Maralag, Dumingag, Zamboanga Del Sur', '2021-07-10', 'Mediacon', 'Premium 25Mbps'],
    ['Regie Gose', 'Maralag, Dumingag, Zamboanga Del Sur', '2021-08-10', 'Mediacon', 'Standard 10Mbps'],
    ['Alrey Arsenal', 'Maralag, Dumingag, Zamboanga Del Sur', '2021-12-10', 'Wireless Radio', 'Premium 25Mbps'],
    ['Siarez', 'Maralag, Dumingag, Zamboanga Del Sur', '2021-10-10', 'Mediacon', 'Premium 25Mbps'],
    ['Velez', 'Maralag, Dumingag, Zamboanga Del Sur', '2021-10-10', 'Mediacon', 'Standard 10Mbps'],
    ['Loloy Jamero', 'Maralag, Dumingag, Zamboanga Del Sur', '2021-10-10', 'Mediacon', 'Premium 25Mbps'],
    ['Erma Jalem', 'Maralag, Dumingag, Zamboanga Del Sur', '2022-04-10', 'Wireless Radio', 'Standard 10Mbps'],
    ['Ducks Gose', 'Maralag, Dumingag, Zamboanga Del Sur', '2021-08-12', 'Mediacon', 'Standard 10Mbps'],
    ['Jojo Jamero', 'Maralag, Dumingag, Zamboanga Del Sur', '2021-09-12', 'Mediacon', 'Premium 25Mbps'],
    ['Melita Agnes', 'Maralag, Dumingag, Zamboanga Del Sur', '2022-03-12', 'OLT', 'Standard 10Mbps'],
    ['Oging Abellana', 'Maralag, Dumingag, Zamboanga Del Sur', '2022-03-12', 'OLT', 'Standard 10Mbps'],
    ['Ronnie Demegillo', 'Maralag, Dumingag, Zamboanga Del Sur', '2021-09-13', 'Mediacon', 'Standard 10Mbps'],
    ['Gagang Joaquin', 'Maralag, Dumingag, Zamboanga Del Sur', '2021-10-13', 'OLT', 'Standard 10Mbps'],
    ['Gomez Ubos', 'Maralag, Dumingag, Zamboanga Del Sur', '2021-10-13', 'Mediacon', 'Standard 10Mbps'],
    ['Jerry Gallego', 'Maralag, Dumingag, Zamboanga Del Sur', '2022-01-14', 'OLT', 'Standard 10Mbps'],
    ['Monico Adaza', 'Maralag, Dumingag, Zamboanga Del Sur', '2023-01-14', 'OLT', 'Premium 25Mbps'],
    ['Dondong Papa', 'Maralag, Dumingag, Zamboanga Del Sur', '2023-01-15', 'OLT', 'Standard 10Mbps'],
    ['Pedyu Ebisa', 'Maralag, Dumingag, Zamboanga Del Sur', '2023-01-15', 'OLT', 'Standard 10Mbps'],
    ['Joy Gose', 'Maralag, Dumingag, Zamboanga Del Sur', '2021-09-15', 'Mediacon', 'Standard 10Mbps'],
    ['Andrade', 'Maralag, Dumingag, Zamboanga Del Sur', '2021-10-16', 'Mediacon', 'Standard 10Mbps'],
    ['Malacad', 'Maralag, Dumingag, Zamboanga Del Sur', '2022-09-16', 'OLT', 'Standard 10Mbps'],
    ['Restauro', 'Maralag, Dumingag, Zamboanga Del Sur', '2022-09-16', 'OLT', 'Standard 10Mbps'],
    ['Tonyo Sabayle', 'Maralag, Dumingag, Zamboanga Del Sur', '2021-01-17', 'OLT', 'Standard 10Mbps'],
    ['Jocel Sareno', 'Maralag, Dumingag, Zamboanga Del Sur', '2022-06-18', 'OLT', 'Standard 10Mbps'],
    ['Castro', 'Maralag, Dumingag, Zamboanga Del Sur', '2021-12-19', 'OLT', 'Standard 10Mbps'],
    ['Amar Alcorin Dool Drier', 'Maralag, Dumingag, Zamboanga Del Sur', '2022-09-19', 'OLT', 'Standard 10Mbps'],
    ['Aranas', 'Maralag, Dumingag, Zamboanga Del Sur', '2021-12-20', 'Mediacon', 'Standard 10Mbps'],
    ['Janen Tala', 'Maralag, Dumingag, Zamboanga Del Sur', '2020-12-20', 'Wireless Radio', 'Premium 25Mbps'],
    ['Geraldizo', 'Maralag, Dumingag, Zamboanga Del Sur', '2021-06-20', 'Wireless Radio', 'Premium 25Mbps'],
    ['Sarry Lemetares', 'Maralag, Dumingag, Zamboanga Del Sur', '2022-05-21', 'OLT', 'Standard 10Mbps'],
    ['Alcorin Nilo', 'Maralag, Dumingag, Zamboanga Del Sur', '2022-05-21', 'OLT', 'Premium 25Mbps'],
    ['Caberte Dodong Drier', 'Maralag, Dumingag, Zamboanga Del Sur', '2021-05-22', 'OLT', 'Standard 10Mbps'],
    ['Riza Ledesma', 'Maralag, Dumingag, Zamboanga Del Sur', '2021-08-22', 'OLT', 'Standard 10Mbps'],
    ['Jhunax', 'Maralag, Dumingag, Zamboanga Del Sur', '2021-01-23', 'Mediacon', 'Premium 25Mbps'],
    ['Basin', 'Maralag, Dumingag, Zamboanga Del Sur', '2021-06-23', 'Mediacon', 'Premium 25Mbps'],
    ['Bon2x Galliguit', 'Maralag, Dumingag, Zamboanga Del Sur', '2021-09-23', 'OLT', 'Premium 25Mbps'],
    ['Tarik Ebisa', 'Maralag, Dumingag, Zamboanga Del Sur', '2020-11-24', 'Mediacon', 'Basic 5Mbps'],
    ['Neneng Capalihan', 'Maralag, Dumingag, Zamboanga Del Sur', '2021-03-25', 'Wireless Radio', 'Others'],
    ['Manalo', 'Maralag, Dumingag, Zamboanga Del Sur', '2021-04-25', 'Wireless Radio', 'Premium 25Mbps'],
    ['Gallego Pulis', 'Maralag, Dumingag, Zamboanga Del Sur', '2021-09-26', 'OLT', 'Standard 10Mbps'],
    ['Titing Alcorin', 'Maralag, Dumingag, Zamboanga Del Sur', '2021-09-26', 'OLT', 'Premium 25Mbps'],
    ['Maam Palma', 'Maralag, Dumingag, Zamboanga Del Sur', '2021-09-26', 'OLT', 'Premium 25Mbps'],
    ['Manang Vek2x', 'Maralag, Dumingag, Zamboanga Del Sur', '2021-07-28', 'Wireless Radio', 'Premium 25Mbps'],
    ['Plondaya', 'Maralag, Dumingag, Zamboanga Del Sur', '2021-08-29', 'Mediacon', 'Standard 10Mbps'],
    ['Jeza Montimayor', 'Maralag, Dumingag, Zamboanga Del Sur', '2023-02-16', 'OLT', 'Standard 10Mbps'],
    ['Pare Bulloy Adaza', 'Maralag, Dumingag, Zamboanga Del Sur', '2021-07-01', 'Mediacon', 'Basic 5Mbps'],
    ['SB Pakit', 'Maralag, Dumingag, Zamboanga Del Sur', '2023-03-12', 'Mediacon', 'Others'],
    ['Roselyn Manalo', 'Maralag, Dumingag, Zamboanga Del Sur', '2023-04-18', 'OLT', 'Premium 25Mbps'],
    ['Agudera', 'P3, Maralag, Dumingag Zamboanga Del Sur', '2023-06-01', 'OLT', 'Standard 10Mbps'],
    ['Durnala', 'P7, Maralag, Dumingag, Zamboanga Del Sur', '2023-06-02', 'OLT', 'Standard 10Mbps'],
    ['Gloria', 'Maralag, Dumingag, Zamboanga Del Sur', '2023-06-03', 'OLT', 'Standard 10Mbps'],
    ['Duerme Joel', 'P3, Maralag, Dumingag, Zamboanga Del Sur', '2023-06-22', 'Mediacon', 'Premium 25Mbps'],
    ['Kulano Arsenal', '', '2023-07-22', 'OLT', 'Standard 10Mbps'],
    ['Albert Arsenal', 'Maralag, Dumingag, Zambo. Sur', '2023-07-23', 'OLT', 'Standard 10Mbps'],
    ['Bontuyan Rhoem', 'Maralag, Dumingag, Zambo. Sur', '2023-07-30', 'OLT', 'Standard 10Mbps'],
    ['May2x Moring', 'Lawis, Maralag, Dumingag, Zambo. Sur', '2023-10-04', 'OLT', 'Standard 10Mbps'],
    ['Gege Basin', 'Lawis, Maralag, Dumingag, Zambo. Sur', '2023-10-04', 'OLT', 'Standard 10Mbps'],
    ['Maralag ES', 'Maralag, Dumingag, ZDS', '2023-12-15', 'Wireless Radio', 'Premium 25Mbps'],
    ['Maralag Baranggay', 'Maralag, Dumingag, ZDS', '2024-01-06', 'OLT', 'Premium 25Mbps'],
    ['Benjie Insek', 'Maralag Dumingag ZDS', '2024-01-13', 'OLT', 'Standard 10Mbps'],
    ['Romero Lucille', 'Maralag, DZDS', '2023-12-04', 'OLT', 'Premium 25Mbps'],
    ['Ricky Ayunan', '', '2024-02-04', 'OLT', 'Premium 25Mbps'],
    ['Eda Manalo', 'Maralag, Dumingag, Zamboanga Del Sur', '2024-03-17', 'OLT', 'Premium 25Mbps'],
    ['Muno Pikas Mar.', 'Maralag, Dumingag, Zambo. Sur', '2024-02-23', 'OLT', 'Standard 10Mbps'],
    ['Manang Minang', 'Pugwan, Mahayag, ZDS', '2024-04-07', 'OLT', 'Standard 10Mbps'],
    ['Jeje Arsenal', 'Maralag, Duminga, Zambo Sur', '2024-05-26', 'Wireless Radio', 'Standard 10Mbps'],
    ['Langga Ayunan', 'Maralag, DZDS', '2024-06-24', 'OLT', 'Standard 10Mbps'],
];
