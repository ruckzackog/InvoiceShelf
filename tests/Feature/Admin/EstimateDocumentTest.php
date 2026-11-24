<?php

use App\Console\Commands\SeedAngebotBausteine;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\EstimateItem;
use App\Models\Item;
use App\Models\Note;
use App\Models\Tax;
use App\Models\User;
use App\Rules\EstimateDocument;
use App\Services\EstimateDocument\DocumentRenderer;
use App\Services\PDFDrivers\GotenbergPDFDriver;
use App\Services\PDFService;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->companyId = $user->companies()->first()->id;
    $this->withHeaders(['company' => $this->companyId]);
    Sanctum::actingAs($user, ['*']);
});

function doc(array ...$content): array
{
    return ['type' => 'doc', 'content' => $content];
}

function paragraph(string $text, array $marks = [], array $attrs = []): array
{
    return ['type' => 'paragraph', 'attrs' => $attrs, 'content' => [
        ['type' => 'text', 'text' => $text, 'marks' => $marks],
    ]];
}

function documentEstimate(array $attributes = []): Estimate
{
    $estimate = Estimate::factory()->create($attributes + ['template_name' => 'angebot-dokument']);

    EstimateItem::factory()->create([
        'estimate_id' => $estimate->id,
        'company_id' => $estimate->company_id,
        'name' => 'Design',
        'description' => "Gestaltung als klickbarer Prototyp.\nPrototyp: Wichtigste Seiten für Desktop und Smartphone\nAbstimmung: Eine Korrekturrunde",
        'quantity' => 1,
        'price' => 240000,
        'total' => 240000,
    ]);

    return $estimate->fresh();
}

function render(Estimate $estimate, array $document): string
{
    return (new DocumentRenderer($estimate))->render($document);
}

test('create and load an estimate with a document', function () {
    $document = doc(
        ['type' => 'heading', 'attrs' => ['level' => 1], 'content' => [['type' => 'text', 'text' => 'Neue Website']]],
        ['type' => 'estimateItems'],
        paragraph('Danke', [['type' => 'bold']], ['textAlign' => 'center']),
    );

    $estimate = Estimate::factory()->raw([
        'estimate_number' => 'EST-DOC-1',
        'template_name' => 'angebot-dokument',
        'items' => [EstimateItem::factory()->raw()],
        'taxes' => [Tax::factory()->raw()],
        'document' => $document,
    ]);

    $id = postJson('api/v1/estimates', $estimate)->assertStatus(201)->json('data.id');

    expect(Estimate::find($id)->document)->toBe($document);
    expect(getJson("api/v1/estimates/{$id}")->assertOk()->json('data.document'))->toBe($document);
});

test('update keeps the document unchanged on a round trip', function () {
    $estimate = documentEstimate(['document' => doc(paragraph('Erste Fassung'))]);
    $document = doc(
        ['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'Sehr geehrter Herr '],
            ['type' => 'field', 'attrs' => ['key' => 'PRIMARY_CONTACT_NAME']],
            ['type' => 'text', 'text' => ', '],
        ]],
        ['type' => 'pageBreak'],
        ['type' => 'estimateTotals'],
    );

    $payload = Estimate::factory()->raw([
        'estimate_number' => $estimate->estimate_number,
        'customer_id' => $estimate->customer_id,
        'template_name' => 'angebot-dokument',
        'items' => [EstimateItem::factory()->raw()],
        'taxes' => [Tax::factory()->raw()],
        'document' => $document,
    ]);

    putJson("api/v1/estimates/{$estimate->id}", $payload)->assertOk();

    expect($estimate->fresh()->document)->toBe($document);
});

test('a document must be a doc and not too large', function () {
    $base = Estimate::factory()->raw([
        'estimate_number' => 'EST-DOC-2',
        'items' => [EstimateItem::factory()->raw()],
        'taxes' => [Tax::factory()->raw()],
    ]);

    postJson('api/v1/estimates', $base + ['document' => ['type' => 'paragraph']])
        ->assertStatus(422)->assertJsonValidationErrors('document');

    $huge = doc(paragraph(str_repeat('x', EstimateDocument::MAX_BYTES)));
    postJson('api/v1/estimates', $base + ['document' => $huge])
        ->assertStatus(422)->assertJsonValidationErrors('document');

    $deep = ['type' => 'text', 'text' => 'x'];
    for ($i = 0; $i < DocumentRenderer::MAX_DEPTH + 2; $i++) {
        $deep = ['type' => 'blockquote', 'content' => [$deep]];
    }
    postJson('api/v1/estimates', $base + ['document' => doc($deep)])
        ->assertStatus(422)->assertJsonValidationErrors('document');
});

test('clone copies the document', function () {
    $estimate = documentEstimate(['document' => doc(paragraph('Vorlage'))]);

    $id = $this->post("/api/v1/estimates/{$estimate->id}/clone")->json('data.id');

    expect(Estimate::find($id)->document)->toBe($estimate->document);
});

test('convert to invoice still works with a document', function () {
    $estimate = documentEstimate(['document' => doc(paragraph('Angebot'))]);

    $this->post("/api/v1/estimates/{$estimate->id}/convert-to-invoice")->assertOk();
});

test('the template list offers the document template', function () {
    $names = collect(getJson('api/v1/estimates/templates')->assertOk()->json('estimateTemplates'))->pluck('name');

    expect($names)->toContain('angebot-dokument');
});

test('text is escaped and unknown nodes are dropped with their content', function () {
    $html = render(documentEstimate(), doc(
        paragraph('<script>alert(1)</script>'),
        ['type' => 'image', 'attrs' => ['src' => 'https://example.com/x.png']],
        ['type' => 'iframe', 'content' => [paragraph('versteckt')]],
        ['type' => 'paragraph', 'attrs' => ['style' => 'background:url(https://example.com)'], 'content' => [['type' => 'text', 'text' => 'ok']]],
    ));

    expect($html)
        ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->not->toContain('<script')
        ->not->toContain('example.com')
        ->not->toContain('versteckt')
        ->not->toContain('style=')
        ->toContain('<p>ok</p>');
});

test('marks, links, headings, alignment and tables render', function () {
    $html = render(documentEstimate(), doc(
        paragraph('fett', [['type' => 'bold'], ['type' => 'italic']]),
        paragraph('web', [['type' => 'link', 'attrs' => ['href' => 'https://ruckzack.at']]]),
        paragraph('böse', [['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']]]),
        ['type' => 'heading', 'attrs' => ['level' => 6, 'textAlign' => 'right'], 'content' => [['type' => 'text', 'text' => 'Titel']]],
        ['type' => 'table', 'content' => [
            ['type' => 'tableRow', 'content' => [
                ['type' => 'tableHeader', 'attrs' => ['colspan' => 2], 'content' => [paragraph('Phase')]],
            ]],
            ['type' => 'tableRow', 'content' => [
                ['type' => 'tableCell', 'attrs' => ['colspan' => 999], 'content' => [paragraph('KW 49')]],
            ]],
        ]],
        ['type' => 'pageBreak'],
        paragraph('Neue Seite'),
        ['type' => 'pageBreak'],
        ['type' => 'signature'],
    ));

    expect($html)
        ->toContain('<p><em><strong>fett</strong></em></p>')
        ->toContain('<a href="https://ruckzack.at">web</a>')
        ->not->toContain('javascript:')
        ->toContain('<h3 class="align-right">Titel</h3>')
        ->toContain('<th colspan="2"><p>Phase</p></th>')
        ->toContain('<td><p>KW 49</p></td>')
        ->toContain('<div class="page-break"><p>Neue Seite</p></div>')
        ->toContain('doc-signature')
        ->and(substr_count($html, 'page-break'))->toBe(2);
});

test('fields resolve and are escaped', function () {
    $estimate = documentEstimate();
    Customer::find($estimate->customer_id)->update(['contact_name' => 'Hans <b>Muster</b>']);

    $html = render($estimate->fresh(), doc(['type' => 'paragraph', 'content' => [
        ['type' => 'text', 'text' => 'Sehr geehrter '],
        ['type' => 'field', 'attrs' => ['key' => 'PRIMARY_CONTACT_NAME']],
        ['type' => 'field', 'attrs' => ['key' => 'ESTIMATE_NUMBER']],
        ['type' => 'field', 'attrs' => ['key' => 'UNKNOWN']],
    ]]));

    expect($html)
        ->toContain('Hans &lt;b&gt;Muster&lt;/b&gt;')
        ->toContain(e($estimate->estimate_number))
        ->not->toContain('UNKNOWN');
});

test('positions and cost overview come from the estimate items', function () {
    $estimate = documentEstimate();

    $html = render($estimate, doc(['type' => 'estimateItems'], ['type' => 'estimateTotals']));

    expect($html)
        ->toContain('<h3>Design</h3>')
        ->toContain('<p>Gestaltung als klickbarer Prototyp.</p>')
        ->toContain('<td>Prototyp</td>')
        ->toContain('<td>Wichtigste Seiten für Desktop und Smartphone</td>')
        ->toContain('<td>Abstimmung</td>')
        ->toContain('doc-costs')
        ->toContain(DocumentRenderer::money($estimate->total, $estimate->customer->currency));
});

test('hosting packages use the current item prices of the company only', function () {
    $estimate = documentEstimate();
    $starter = Item::factory()->create(['company_id' => $estimate->company_id, 'name' => 'Businesscare Starter', 'price' => 6249, 'description' => "- 50 GB Speicher\n- 3 Domains"]);
    $solo = Item::factory()->create(['company_id' => $estimate->company_id, 'name' => 'Hosting Solo', 'price' => 1332]);
    $foreign = Item::factory()->create(['company_id' => Company::factory()->create()->id, 'name' => 'Fremdes Paket']);

    $html = render($estimate, doc(['type' => 'hostingPackages', 'attrs' => [
        'itemIds' => [$solo->id, $starter->id, $foreign->id, 'x'],
        'recommendedId' => $starter->id,
    ]]));

    expect($html)
        ->toContain('Hosting Solo')
        ->toContain('Businesscare Starter')
        ->toContain('50 GB Speicher, 3 Domains')
        ->toContain('doc-recommended')
        ->not->toContain('Fremdes Paket')
        ->and(strpos($html, 'Hosting Solo'))->toBeLessThan(strpos($html, 'Businesscare Starter'));
});

test('work package descriptions split into intro and activities', function () {
    // Written by the editor: rows start with "- ", so an intro line may contain a colon.
    expect(DocumentRenderer::workPackage("Die Website soll: schnell sein\n- Kick-off: Ziele klären\n- Seitenstruktur"))->toBe([
        'intro' => ['Die Website soll: schnell sein'],
        'rows' => [
            ['title' => 'Kick-off', 'text' => 'Ziele klären'],
            ['title' => 'Seitenstruktur', 'text' => ''],
        ],
    ]);

    // Older descriptions without "- ": split on the first line with a colon.
    expect(DocumentRenderer::workPackage("Einleitung\nKick-off: Ziele klären\nSeitenstruktur"))->toBe([
        'intro' => ['Einleitung'],
        'rows' => [
            ['title' => 'Kick-off', 'text' => 'Ziele klären'],
            ['title' => 'Seitenstruktur', 'text' => ''],
        ],
    ]);
});

test('only listed templates go to Gotenberg', function () {
    config(['pdf.driver' => 'dompdf', 'pdf.gotenberg_templates' => 'angebot-dokument, other']);

    expect(PDFService::driverFor('angebot-dokument'))->toBe('gotenberg')
        ->and(PDFService::driverFor('other'))->toBe('gotenberg')
        ->and(PDFService::driverFor('estimate1'))->toBe('dompdf')
        ->and(PDFService::driverFor('Rechnung'))->toBe('dompdf')
        ->and(PDFService::driverFor(null))->toBe('dompdf');

    config(['pdf.gotenberg_templates' => '']);
    expect(PDFService::driverFor('angebot-dokument'))->toBe('dompdf');
});

test('the Gotenberg request carries the footer, margins and inlined fonts', function () {
    config([
        'pdf.connections.gotenberg.host' => 'http://gotenberg:3000',
        'pdf.connections.gotenberg.allowed_private_host' => 'http://gotenberg:3000',
        'pdf.angebot_footer_line' => 'FN 123456 a',
    ]);

    $estimate = documentEstimate(['document' => doc(paragraph('Hallo'), ['type' => 'estimateItems'])]);
    $estimate->getPDFData(); // shares the view data, like a real render

    $options = (new ReflectionMethod($estimate, 'getPdfOptions'))
        ->invoke($estimate, 'app.pdf.estimate.angebot-dokument', $estimate->company);

    $request = (new GotenbergPDFDriver)->buildRequest('app.pdf.estimate.angebot-dokument', $options);
    $body = (string) $request->getBody();

    expect((string) $request->getUri())->toBe('http://gotenberg:3000/forms/chromium/convert/html')
        ->and($body)->toContain('filename="footer.html"')
        ->toContain('FN 123456 a')
        ->toContain('class="pageNumber"')
        ->toContain('name="marginTop"')
        ->toContain('15mm')
        ->toContain('<p>Hallo</p>')
        ->toContain('Design')
        ->toContain('data:font/woff2;base64,')
        ->not->toContain("url('../static");
});

test('templates without a footer view get no Gotenberg options', function () {
    $estimate = documentEstimate(['template_name' => 'estimate1']);

    $options = (new ReflectionMethod($estimate, 'getPdfOptions'))
        ->invoke($estimate, 'app.pdf.estimate.estimate1', $estimate->company);

    expect($options)->toBe([]);
});

test('an estimate without a document still renders the template', function () {
    $estimate = documentEstimate(['notes' => '<p>Alte Notiz</p>', 'document' => null]);

    request()->query->set('preview', '1');
    $html = $estimate->getPDFData()->render();

    expect($html)->toContain('angebot-doc')->toContain('Design')->toContain('Alte Notiz');
});

test('the Bausteine command is a dry run by default and never overwrites', function () {
    $count = fn () => Note::query()->where('company_id', $this->companyId)->where('type', 'EstimateBlock')->count();

    $this->artisan('angebot:bausteine', ['--company' => $this->companyId])->assertSuccessful();
    expect($count())->toBe(0);

    Note::query()->create([
        'company_id' => $this->companyId,
        'type' => 'EstimateBlock',
        'name' => 'Annahme',
        'notes' => '<p>Eigene Fassung</p>',
    ]);

    $this->artisan('angebot:bausteine', ['--company' => $this->companyId, '--apply' => true])->assertSuccessful();
    $this->artisan('angebot:bausteine', ['--company' => $this->companyId, '--apply' => true])->assertSuccessful();

    expect($count())->toBe(count(SeedAngebotBausteine::bausteine()))
        ->and(Note::query()->where('company_id', $this->companyId)->where('name', 'Annahme')->value('notes'))->toBe('<p>Eigene Fassung</p>');
});

test('Bausteine follow the customer text rules', function () {
    foreach (SeedAngebotBausteine::bausteine() as $name => $html) {
        $text = strip_tags($html);

        expect($text)->not->toContain('–', $name)
            ->not->toContain('—', $name)
            ->not->toMatch('/, und /', $name)
            ->not->toMatch('/\b(ganzheitlich|maßgeschneidert|innovativ)/iu', $name);
    }
});

test('every Baustein the default layout uses is created by the command', function () {
    $js = file_get_contents(resource_path('scripts/admin/views/estimates/create/document/defaultDocument.js'));
    preg_match('/DEFAULT_BAUSTEINE = \[(.*?)\]/s', $js, $match);
    preg_match_all("/'([^']+)'/", $match[1] ?? '', $names);

    expect($names[1])->not->toBeEmpty()
        ->each->toBeIn(array_keys(SeedAngebotBausteine::bausteine()));
});

test('a package with only a feature list prints one column', function () {
    $estimate = documentEstimate();
    $estimate->items()->first()->update(['description' => "Website-Paket inkl. 1 Jahr:\n- Website Design\n- Kostenlose Domain"]);

    $html = render($estimate->fresh(), doc(['type' => 'estimateItems']));

    expect($html)
        ->toContain('<td colspan="2">Website Design</td>')
        ->toContain('<td colspan="2">Kostenlose Domain</td>')
        ->toContain('<p>Website-Paket inkl. 1 Jahr:</p>')
        ->not->toContain(__('pdf_angebot_description'));
});
