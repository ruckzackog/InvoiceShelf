<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Note;
use Illuminate\Console\Command;

/**
 * Creates the standard text blocks ("Bausteine") for the offer document as
 * notes of type EstimateBlock. Idempotent by name: a Baustein that already
 * exists is never changed, because after creation it belongs to the people
 * who edit it in the settings.
 *
 * Dry run by default; --apply writes.
 */
class SeedAngebotBausteine extends Command
{
    protected $signature = 'angebot:bausteine
        {--company= : Company id (default: every company)}
        {--apply : Create the missing Bausteine instead of only listing them}';

    protected $description = 'Create the standard Bausteine for the offer document (dry run unless --apply)';

    public const TYPE = 'EstimateBlock';

    public function handle(): int
    {
        $companies = Company::query()
            ->when($this->option('company'), fn ($query, $id) => $query->whereKey($id))
            ->get();

        if ($companies->isEmpty()) {
            $this->error('No company found.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');

        foreach ($companies as $company) {
            foreach (self::bausteine() as $name => $html) {
                $exists = Note::query()
                    ->where('company_id', $company->id)
                    ->where('type', self::TYPE)
                    ->where('name', $name)
                    ->exists();

                if ($exists) {
                    $this->line("skip   {$company->name}: {$name} (exists)");

                    continue;
                }

                if ($apply) {
                    Note::query()->create([
                        'company_id' => $company->id,
                        'type' => self::TYPE,
                        'name' => $name,
                        'notes' => $html,
                        'is_default' => false,
                    ]);
                }

                $this->line(($apply ? 'create ' : 'would  ')."{$company->name}: {$name}");
            }
        }

        if (! $apply) {
            $this->info('Dry run. Run again with --apply to create the missing Bausteine.');
        }

        return self::SUCCESS;
    }

    /**
     * Name => HTML. Customer text rules: Sie, short sentences, no dash, no
     * comma before "und", no invented numbers.
     *
     * @return array<string, string>
     */
    public static function bausteine(): array
    {
        return [
            'Einleitung' => '<p>Sehr geehrte Damen und Herren,</p>'
                .'<p>vielen Dank für das Gespräch. Auf dieser Grundlage haben wir das folgende Angebot erstellt. '
                .'Es beschreibt den Umfang, den Zeitplan und alle Kosten, auch die laufenden. Für Fragen sind wir jederzeit für Sie da.</p>',

            'Projektbeschreibung und Ziel' => '<h2>Projektbeschreibung</h2>'
                .'<p>Wir setzen die neue Website mit WordPress um. Sie ist auf Smartphone und Desktop gut bedienbar.</p>'
                .'<h3>Projektziel</h3>'
                .'<p>Die neue Website soll:</p>'
                .'<ul><li><p>Ihr Angebot klar und modern zeigen</p></li><li><p>Anfragen erleichtern</p></li><li><p>in Suchmaschinen besser gefunden werden</p></li></ul>',

            'Seitenstruktur und Funktionen' => '<h2>Seitenstruktur und Funktionen</h2>'
                .'<h3>Seitenstruktur</h3>'
                .'<ul><li><p>Startseite</p></li><li><p>Leistungen</p></li><li><p>Über uns</p></li><li><p>Kontakt</p></li><li><p>Impressum und Datenschutz</p></li></ul>'
                .'<h3>Funktionen</h3>'
                .'<ul><li><p>Kontaktformular mit Spamschutz ohne Tracking</p></li></ul>',

            'Zeitplan Hinweis' => '<p>Der Zeitplan gilt ab Auftragserteilung. Er setzt voraus, dass Inhalte und Freigaben rechtzeitig vorliegen.</p>',

            'Leistungen nach Aufwand' => '<p>Leistungen über den beschriebenen Umfang hinaus verrechnen wir nach Aufwand zum vereinbarten Stundensatz. '
                .'Wir stimmen sie vorher schriftlich mit Ihnen ab.</p>',

            'Hosting & Betreuung' => '<h2>Hosting &amp; Betreuung</h2>'
                .'<p>Nach dem Go-live betreuen wir Ihre Website im Hosting weiter. Dafür gibt es einen eigenen Vertrag.</p>'
                .'<p>In allen Paketen sind unbegrenzter Traffic, mehrmals tägliche Backups auf einem getrennten System und Sicherheitsupdates enthalten. '
                .'Das Hosting läuft in Europa bei Hetzner in Deutschland. Der Vertrag ist unbefristet und hat drei Monate Mindestlaufzeit. '
                .'Danach ist er mit einem Monat zum Monatsende kündbar. Ein Paketwechsel ist jederzeit möglich. Website und Daten gehören Ihnen.</p>',

            'Nicht im Angebot enthalten' => '<h2>Nicht im Angebot enthalten</h2>'
                .'<ul><li><p>Texte, Fotos und Videos über den vereinbarten Umfang hinaus</p></li>'
                .'<li><p>Übersetzungen und weitere Sprachen</p></li>'
                .'<li><p>Marketing wie Kampagnen, Social Media oder Anzeigen</p></li>'
                .'<li><p>Lizenzen und Gebühren von Drittanbietern</p></li>'
                .'<li><p>Rechtliche Prüfung von Inhalten, Impressum und Datenschutzerklärung</p></li>'
                .'<li><p>Pflege der Inhalte nach der Einschulung</p></li></ul>',

            'Inhalte durch den Kunden' => '<h3>Inhalte</h3>'
                .'<p>Sie erstellen die Texte und Bilder und stellen sie uns rechtzeitig zur Verfügung. '
                .'Sie müssen den vereinbarten technischen Vorgaben entsprechen.</p>',

            'Inhalte durch Ruckzack' => '<h3>Inhalte</h3>'
                .'<p>Wir erstellen die Inhalte für Sie. Das umfasst Konzept, Umsetzung und Lieferung zum vereinbarten Termin.</p>',

            'Rahmenbedingungen' => '<h2>Rahmenbedingungen</h2>'
                .'<h3>Mitwirkung</h3>'
                .'<p>Sie stellen Inhalte, Zugänge und Freigaben rechtzeitig zur Verfügung. Verzögerungen dabei verschieben den Zeitplan.</p>'
                .'<h3>Änderungen</h3>'
                .'<p>Änderungen am Umfang vereinbaren wir schriftlich, bevor wir sie umsetzen. '
                .'Funktionen außerhalb des beschriebenen Umfangs verrechnen wir gesondert.</p>'
                .'<h3>Datenschutz</h3>'
                .'<p>Wir setzen Impressum, Datenschutzerklärung und Cookie-Hinweis technisch um. Die inhaltliche Verantwortung liegt bei Ihnen. '
                .'Einen Vertrag zur Auftragsverarbeitung nach Art. 28 DSGVO schließen wir auf Wunsch ab.</p>'
                .'<h3>Förderungen</h3>'
                .'<p>Ob für Ihr Projekt eine Förderung in Frage kommt, prüfen wir gerne gemeinsam mit Ihnen. Eine Zusage können wir nicht geben.</p>',

            'DSGVO und Datenschutzmaßnahmen' => '<h3>Datenschutz in der Umsetzung</h3>'
                .'<p>Wir halten bei der Umsetzung die Vorgaben der DSGVO ein. Dazu gehören:</p>'
                .'<ul><li><p><strong>Verschlüsselung:</strong> Personenbezogene Daten werden bei Übertragung und Speicherung verschlüsselt.</p></li>'
                .'<li><p><strong>Speicherdauer:</strong> Daten aus Server-Logs und Formularen speichern wir nur so lange wie nötig. Danach werden sie automatisch gelöscht.</p></li>'
                .'<li><p><strong>Rechtliche Inhalte:</strong> Impressum, Datenschutzhinweis und Cookie-Hinweis gestalten Sie inhaltlich. Wir setzen sie technisch um.</p></li></ul>'
                .'<p>Das Angebot umfasst keine Rechtsberatung.</p>',

            'Zahlung nach Projektabschluss' => '<h3>Zahlung</h3>'
                .'<p>Der vereinbarte Betrag ist innerhalb von 30 Tagen nach Übergabe und Abnahme des Projekts fällig.</p>',

            'Zahlung mit Anzahlung' => '<h3>Zahlung</h3>'
                .'<p>50 % des Gesamtbetrags sind als Anzahlung vor Projektbeginn fällig. Die restlichen 50 % sind nach der Abnahme fällig.</p>',

            'Zahlung nach Meilensteinen' => '<h3>Zahlung</h3>'
                .'<p>Wir verrechnen in drei Teilen: 30 % bei Projektstart, 30 % nach Freigabe des Designs und 40 % nach der Abnahme.</p>',

            'Schlussbestimmungen' => '<h3>Schlussbestimmungen</h3>'
                .'<ul><li><p><strong>Vertraulichkeit:</strong> Beide Seiten behandeln alle Projektdetails vertraulich.</p></li>'
                .'<li><p><strong>Haftung:</strong> Die Haftung für leichte Fahrlässigkeit ist ausgeschlossen.</p></li>'
                .'<li><p><strong>Recht:</strong> Es gilt österreichisches Recht.</p></li></ul>'
                .'<p>Dieses Angebot ist vier Wochen gültig. Es gelten unsere AGB, abrufbar unter ruckzack.at.</p>',

            'Annahme' => '<h2>Annahme</h2>'
                .'<p>Bitte bestätigen Sie das Angebot mit Ihrer Unterschrift oder per E-Mail an office@ruckzack.at unter Angabe der Angebotsnummer. '
                .'Wir freuen uns auf die Zusammenarbeit.</p>'
                .'<p>Mit freundlichen Grüßen</p>',
        ];
    }
}
