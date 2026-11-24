<?php

use App\Models\CompanySetting;
use App\Models\Item;
use App\Models\Note;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
    $this->companyId = User::find(1)->companies()->first()->id;

    $this->monthly = Unit::factory()->create(['name' => 'Monat(e)', 'company_id' => $this->companyId]);
    $this->piece = Unit::factory()->create(['name' => 'Stk', 'company_id' => $this->companyId]);

    $item = fn ($name, $unit, $price = 1000, $description = 'alt') => Item::factory()->create([
        'company_id' => $this->companyId, 'name' => $name, 'unit_id' => $unit->id, 'price' => $price, 'description' => $description,
    ]);

    $this->solo = $item('Hosting Solo', $this->monthly, 1332, "- 10GB SSD Webspace\n- Regelmäßige Backups (14 Tage rückwirkend)");
    $this->planung = $item('Planung und Anforderungsanalyse', $this->piece, 0, null);
    $this->domainMonthly = $item('Addon Domain', $this->monthly, 416);
    $this->domainPiece = $item('Addon Domain', $this->piece, 500);
    $this->neujahr = $item('Neujahrs-Angebot', $this->piece, 150000);

    Note::query()->create(['company_id' => $this->companyId, 'type' => 'Invoice', 'name' => 'SEPA', 'notes' => '<p>alt</p>']);
    CompanySetting::setSettings(['estimate_mail_body' => '<p>Sehr geehrte/r Herr/Frau</p>'], $this->companyId);
});

test('the dry run changes nothing', function () {
    $this->artisan('ruckzack:katalog', ['--company' => $this->companyId]);

    expect($this->solo->fresh()->description)->toContain('SSD Webspace')
        ->and($this->planung->fresh()->name)->toBe('Planung und Anforderungsanalyse')
        ->and(Note::query()->where('name', 'SEPA')->value('notes'))->toBe('<p>alt</p>')
        ->and(CompanySetting::getSetting('estimate_mail_body', $this->companyId))->toBe('<p>Sehr geehrte/r Herr/Frau</p>');
});

test('apply renames, rewrites and archives, without touching prices', function () {
    $this->artisan('ruckzack:katalog', ['--company' => $this->companyId, '--apply' => true]);

    expect($this->solo->fresh())
        ->name->toBe('Hosting Solo')
        ->description->toContain('Backups mehrmals täglich auf getrenntem System')
        ->price->toBe(1332);

    expect($this->planung->fresh()->name)->toBe('Konzept und Workshop')
        ->and($this->planung->fresh()->description)->toContain('- Workshop:')
        ->and($this->domainMonthly->fresh()->name)->toBe('Zusatzdomain')
        ->and($this->domainPiece->fresh()->name)->toBe('Alt: Addon Domain')
        ->and($this->neujahr->fresh()->name)->toBe('Alt: Neujahrs-Angebot')
        ->and($this->neujahr->fresh()->price)->toBe(150000)
        ->and(Note::query()->where('name', 'SEPA')->value('notes'))->toContain('AT48ZZZ00000072598')
        ->and(Note::query()->where('name', 'Rechnung')->exists())->toBeTrue()
        ->and(CompanySetting::getSetting('estimate_mail_body', $this->companyId))->toContain('{ESTIMATE_NUMBER}');
});

test('apply is idempotent', function () {
    $this->artisan('ruckzack:katalog', ['--company' => $this->companyId, '--apply' => true]);
    $items = Item::query()->where('company_id', $this->companyId)->orderBy('id')->get()->map->only(['id', 'name', 'description', 'price'])->all();

    $this->artisan('ruckzack:katalog', ['--company' => $this->companyId, '--apply' => true])
        ->expectsOutputToContain('Alt: Neujahrs-Angebot: unchanged');

    expect(Item::query()->where('company_id', $this->companyId)->orderBy('id')->get()->map->only(['id', 'name', 'description', 'price'])->all())->toBe($items)
        ->and(Item::query()->where('name', 'like', 'Alt: Alt:%')->exists())->toBeFalse();
});

test('catalog texts follow the customer text rules', function () {
    $catalog = require database_path('ruckzack/katalog.php');
    $texts = array_merge(
        array_filter(array_column($catalog['items'], 'description')),
        array_column($catalog['notes'], 'notes'),
        array_values($catalog['settings']),
    );

    foreach ($texts as $text) {
        $plain = strip_tags(str_replace(['<br>', '</p>'], ' ', $text));
        expect($plain)->not->toContain('–')
            ->not->toContain('!')
            ->not->toMatch('/, und /')
            ->not->toMatch('/\b(ganzheitlich|maßgeschneidert|innovativ|kostenlos)/iu');
    }

    foreach (['Hosting Solo', 'Businesscare Starter', 'Businesscare Plus'] as $name) {
        expect(collect($catalog['items'])->firstWhere('name', $name))->not->toBeNull();
    }
});
