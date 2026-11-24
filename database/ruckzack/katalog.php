<?php

/*
 * Ruckzack text catalog: items (Artikel), notes and mail texts as code.
 * Applied by `php artisan ruckzack:katalog` (dry run unless --apply).
 *
 * Items are matched by their current name (and unit where names repeat), so
 * the command can run any number of times. Prices are not touched: existing
 * recurring invoices keep their own copies anyway, and prices stay a human
 * decision. Items are never deleted (old invoices refer to them); retired
 * ones get the prefix "Alt: " and are hidden in the offer editor.
 *
 * Hosting facts follow ruckzack.at/leistungen/hosting-betreuung (24.11.2026).
 * The names Hosting Solo, Businesscare Starter and Businesscare Plus must not
 * change: recurring invoices and the invoicing sync recognise packages by them.
 */

$backups = 'Backups mehrmals täglich auf getrenntem System';

$frameKonzept = implode("\n", [
    'Dieses Arbeitspaket klärt die Anforderungen mit allen, die mit der Website arbeiten.',
    '- Workshop: Ziele, Zielgruppen und Inhalte gemeinsam klären und priorisieren',
    '- Seitenstruktur: Navigation und Seitentypen festlegen',
    '- Zusammenfassung: Ergebnisse schriftlich festhalten, als Grundlage für Design und Umsetzung',
]);

$frameGoLive = implode("\n", [
    'Dieses Arbeitspaket stellt sicher, dass alles zum Start fehlerfrei läuft.',
    '- Tests: Prüfung in aktuellen Browsern und auf Mobilgeräten',
    '- Go-live: Umstellung zu einem abgestimmten Zeitpunkt, Kontrolle nach dem Start',
    '- Einschulung: Online-Einschulung zur Pflege der Inhalte',
    '- Nachbetreuung: Zwei Wochen Fehlerbehebung nach dem Go-live ohne Zusatzkosten',
]);

return [
    'items' => [
        // Hosting & Betreuung (monthly)
        ['match' => ['Hosting Solo'], 'name' => 'Hosting Solo', 'description' => implode("\n", [
            '- 10 GB Speicher', '- 1 Domain', '- 10 E-Mail-Adressen', "- {$backups}", '- Automatische Updates',
        ])],
        ['match' => ['Businesscare Starter'], 'name' => 'Businesscare Starter', 'description' => implode("\n", [
            '- 50 GB Speicher', '- 3 Domains', '- 50 E-Mail-Adressen', '- 0,5 h Betreuung im Monat',
            "- {$backups}", '- Automatische Updates', '- Aktive Wartung und Fehlerbehebung', '- Analytics',
        ])],
        ['match' => ['Businesscare Plus'], 'name' => 'Businesscare Plus', 'description' => implode("\n", [
            '- 100 GB Speicher', '- 5 Domains', '- Unbegrenzt E-Mail-Adressen', '- 2 h Betreuung im Monat',
            "- {$backups}", '- Automatische Updates', '- Aktive Wartung und Fehlerbehebung', '- Analytics',
            '- Monitoring', '- Bevorzugter Ticket-Support', '- Günstigerer Stundensatz für Änderungen',
        ])],
        ['match' => ['Cloud Plus'], 'name' => 'Cloud Plus', 'description' => 'Eingestelltes Paket, nur für bestehende Verträge.'],
        ['match' => ['Addon Domain'], 'unit' => 'Monat', 'name' => 'Zusatzdomain', 'description' => 'Weitere Domain zu einem Hosting-Paket'],

        // Frame and phase packages (offers and project invoices)
        ['match' => ['Planung und Anforderungsanalyse'], 'name' => 'Konzept und Workshop', 'description' => $frameKonzept],
        ['match' => ['Design und Prototyping'], 'name' => 'Design und Prototyping', 'description' => implode("\n", [
            'Dieses Arbeitspaket umfasst die Gestaltung als klickbaren Prototyp.',
            '- Klickbarer Prototyp: Gestaltung der wichtigsten Seiten für Desktop und Smartphone',
            '- Abstimmung: Gemeinsames Durchgehen und eine Korrekturrunde',
        ])],
        ['match' => ['Technische Entwicklung'], 'name' => 'Technische Entwicklung', 'description' => implode("\n", [
            'Dieses Arbeitspaket umfasst die technische Umsetzung auf Basis des freigegebenen Designs.',
            '- Einrichtung: System, Hosting und Staging-Umgebung aufsetzen',
            '- Umsetzung: Seitenvorlagen und Funktionen nach freigegebenem Design',
            '- Inhalte: Übernahme der gelieferten Texte und Bilder',
        ])],
        ['match' => ['Projektmanagement und Qualitätssicherung'], 'name' => 'Projektmanagement und Qualitätssicherung', 'description' => 'Abstimmung, Projektsteuerung und laufende Qualitätskontrolle.'],
        ['match' => ['Launch und Go-Live'], 'name' => 'Test, Go-live und Einschulung', 'description' => $frameGoLive],

        // Packages and add-ons
        ['match' => ['Web Entwicklung'], 'name' => 'Webdesign & Entwicklung (Pauschale)', 'description' => null],
        ['match' => ['VÖPP Website-Paket speziell für Psychotherapeut*innen'], 'name' => 'VÖPP Website-Paket speziell für Psychotherapeut*innen', 'description' => implode("\n", [
            'Website-Paket inklusive erstem Jahr Hosting:',
            '- Design und Umsetzung der Website',
            '- Domain (.at, .de, .ch oder .com)',
            '- 10 GB Speicher und 10 E-Mail-Adressen',
            '- Updates und Backups',
        ])],
        ['match' => ['Sprachwechsler / Mehrsprachigkeit (Optional)'], 'name' => 'Zusatz: Mehrsprachigkeit', 'description' => implode("\n", [
            '- Sprachwechsler auf der Website, etwa Deutsch und Englisch',
            '- Zweite Sprachversion aller Seiten einrichten',
            '- Menüs, Seiten und Inhalte mehrsprachig aufbauen',
        ])],
        ['match' => ['Online-Terminbuchung - Bookly (Optional)'], 'name' => 'Zusatz: Online-Terminbuchung', 'description' => implode("\n", [
            '- Termine direkt über die Website buchen',
            '- Freie Termine sind sofort sichtbar',
            '- Automatische Bestätigung per E-Mail',
            '- Verwaltung im WordPress-Dashboard',
        ])],
        ['match' => ['Kreativpaket – Logo, Mini-CI & Font-Auswahl (Optional)'], 'name' => 'Zusatz: Kreativpaket Logo und Mini-CI', 'description' => implode("\n", [
            '- Logo-Design',
            '- Farbkonzept und visueller Grundstil',
            '- Schriftwahl und Kombinationen',
            '- Alle Dateien zur weiteren Verwendung',
        ])],

        // Hourly rates and resale
        ['match' => ['IT Dienstleistungen Web Development'], 'name' => 'Webentwicklung', 'description' => null],
        ['match' => ['IT Dienstleistungen System Engineer'], 'name' => 'Systembetreuung', 'description' => null],
        ['match' => ['Kreativ Dienstleistungen Graphic Designer'], 'name' => 'Grafikdesign', 'description' => null],
        ['match' => ['Computer & Zubehör'], 'name' => 'Hardware (Weiterverkauf)', 'description' => null],

        // Retired: kept for old invoices, hidden in the offer editor
        ['match' => ['Psychotherapie Website - "Digitale Visitenkarte"'], 'archive' => true],
        ['match' => ['Event Stream'], 'archive' => true],
        ['match' => ['Shopify'], 'archive' => true],
        ['match' => ['Lizenzen monatlich'], 'archive' => true],
        ['match' => ['Printprodukte'], 'archive' => true],
        ['match' => ['Neujahrs-Angebot'], 'archive' => true],
        ['match' => ['Kretivdienstleistungen'], 'archive' => true],
        ['match' => ['Addon Domain'], 'unit' => 'Stk', 'archive' => true],
        ['match' => ['KMU.DIGITAL 4.0 & GREEN Strategieberatung - Tool GSB1: Nachhaltige Transformation von Geschäftsmodellen und Prozessen'], 'archive' => true],
        ['match' => ['KMU.DIGITAL 4.0 & GREEN Status- und Potentialanalyse - Tool GPA1: Nachhaltige Transformation von Geschäftsmodellen und Prozessen'], 'archive' => true],
    ],

    'notes' => [
        ['type' => 'Estimate', 'match' => ['Angebot'], 'name' => 'Angebot', 'notes' => '<p>Bitte bestätigen Sie das Angebot per E-Mail an office@ruckzack.at unter Angabe der Angebotsnummer. Für Fragen sind wir jederzeit für Sie da.</p><p>Es gelten unsere AGB, abrufbar unter ruckzack.at.</p>'],
        ['type' => 'Invoice', 'match' => ['Rechnung'], 'name' => 'Rechnung', 'notes' => '<p>Bitte überweisen Sie den Betrag bis zum Fälligkeitsdatum auf das angegebene Konto und geben Sie dabei die Rechnungsnummer an.</p><p>Vielen Dank für Ihr Vertrauen.</p>'],
        ['type' => 'Invoice', 'match' => ['Rechnung + Reverse-Charge'], 'name' => 'Rechnung + Reverse-Charge', 'notes' => '<p>Bitte überweisen Sie den Betrag bis zum Fälligkeitsdatum auf das angegebene Konto und geben Sie dabei die Rechnungsnummer an.</p><p>Übergang der Steuerschuld auf den Leistungsempfänger (Reverse Charge). {CONTACT_TAX_ID}</p><p>Vielen Dank für Ihr Vertrauen.</p>'],
        ['type' => 'Invoice', 'match' => ['SEPA'], 'name' => 'SEPA', 'notes' => '<p>Bitte überweisen Sie den Betrag nicht. Wir buchen ihn per SEPA-Lastschrift von Ihrem Konto ab.</p><p>Mandatsreferenz: {CUSTOM_CUSTOMER_MANDATS_REFERENZ}<br>Gläubiger-ID: AT48ZZZ00000072598</p>'],
    ],

    'settings' => [
        'estimate_mail_body' => '<p>Guten Tag,</p><p>anbei erhalten Sie unser Angebot {ESTIMATE_NUMBER}. Es gilt bis {ESTIMATE_EXPIRY_DATE}.</p><p>Bei Fragen melden Sie sich gerne bei uns.</p><p>Freundliche Grüße<br>Ihr Team von Ruckzack</p>',
        'invoice_mail_body' => '<p>Guten Tag,</p><p>anbei erhalten Sie unsere Rechnung {INVOICE_NUMBER}. Sie ist am {INVOICE_DUE_DATE} fällig.</p><p>Vielen Dank für die Zusammenarbeit.</p><p>Freundliche Grüße<br>Ihr Team von Ruckzack</p>',
        'payment_mail_body' => '<p>Guten Tag,</p><p>vielen Dank für Ihre Zahlung. Den Beleg finden Sie im Anhang.</p><p>Freundliche Grüße<br>Ihr Team von Ruckzack</p>',
    ],
];
