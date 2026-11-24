<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Item;
use App\Models\Note;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Applies the Ruckzack text catalog (database/ruckzack/katalog.php): item
 * names and descriptions, notes and mail texts. Dry run by default: prints
 * every change with old and new value. --apply writes. Never deletes and never
 * changes prices.
 */
class RuckzackKatalog extends Command
{
    public const ARCHIVE_PREFIX = 'Alt: ';

    protected $signature = 'ruckzack:katalog
        {--company=1 : Company id}
        {--apply : Write the changes instead of only listing them}';

    protected $description = 'Apply the Ruckzack item, note and mail text catalog (dry run unless --apply)';

    protected int $changes = 0;

    protected int $problems = 0;

    public function handle(): int
    {
        $company = Company::query()->find($this->option('company'));

        if (! $company) {
            $this->error('Company not found.');

            return self::FAILURE;
        }

        $catalog = require database_path('ruckzack/katalog.php');
        $apply = (bool) $this->option('apply');

        $this->info($apply ? 'Apply' : 'Dry run (nothing is written)');

        foreach ($catalog['items'] as $spec) {
            $this->item($company->id, $spec, $apply);
        }

        foreach ($catalog['notes'] as $spec) {
            $this->note($company->id, $spec, $apply);
        }

        foreach ($catalog['settings'] as $key => $value) {
            $this->setting($company->id, $key, $value, $apply);
        }

        $this->newLine();
        $this->line("{$this->changes} changes, {$this->problems} problems.".($apply ? '' : ' Run again with --apply to write them.'));

        return $this->problems > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    protected function item(int $companyId, array $spec, bool $apply): void
    {
        $archive = (bool) ($spec['archive'] ?? false);
        $names = $spec['match'];

        if ($archive) {
            $names = array_merge($names, array_map(fn ($name) => self::ARCHIVE_PREFIX.$name, $names));
        } else {
            $names[] = $spec['name'];
        }

        $query = Item::query()->with('unit')->where('company_id', $companyId)->whereIn('name', array_unique($names));
        $items = $query->get()->filter(fn (Item $item) => ! isset($spec['unit'])
            || Str::startsWith((string) $item->unit?->name, $spec['unit']));

        $label = $spec['match'][0].(isset($spec['unit']) ? " ({$spec['unit']})" : '');

        if ($items->count() !== 1) {
            $this->problems++;
            $this->warn(sprintf('  item %s: %d matches, skipped', $label, $items->count()));

            return;
        }

        $item = $items->first();
        $changes = [];

        $name = $archive
            ? (Str::startsWith($item->name, self::ARCHIVE_PREFIX) ? $item->name : self::ARCHIVE_PREFIX.$item->name)
            : $spec['name'];

        if ($item->name !== $name) {
            $changes['name'] = $name;
        }

        if (! $archive && array_key_exists('description', $spec) && $spec['description'] !== null
            && $this->normalize($item->description) !== $this->normalize($spec['description'])) {
            $changes['description'] = $spec['description'];
        }

        $this->report("item #{$item->id} {$item->name}", $changes, [
            'name' => $item->name,
            'description' => $item->description,
        ]);

        if ($apply && $changes) {
            $item->forceFill($changes)->save();
        }
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    protected function note(int $companyId, array $spec, bool $apply): void
    {
        $note = Note::query()
            ->where('company_id', $companyId)
            ->where('type', $spec['type'])
            ->whereIn('name', array_unique(array_merge($spec['match'], [$spec['name']])))
            ->first();

        if (! $note) {
            $this->report("note {$spec['type']} {$spec['name']} (new)", ['notes' => $spec['notes']], ['notes' => '']);

            if ($apply) {
                Note::query()->create([
                    'company_id' => $companyId,
                    'type' => $spec['type'],
                    'name' => $spec['name'],
                    'notes' => $spec['notes'],
                ]);
            }

            return;
        }

        $changes = [];

        if ($note->name !== $spec['name']) {
            $changes['name'] = $spec['name'];
        }

        if ($this->normalize($note->notes) !== $this->normalize($spec['notes'])) {
            $changes['notes'] = $spec['notes'];
        }

        $this->report("note #{$note->id} {$note->type} {$note->name}", $changes, [
            'name' => $note->name,
            'notes' => $note->notes,
        ]);

        if ($apply && $changes) {
            $note->forceFill($changes)->save();
        }
    }

    protected function setting(int $companyId, string $key, string $value, bool $apply): void
    {
        $current = (string) CompanySetting::getSetting($key, $companyId);
        $changes = $this->normalize($current) !== $this->normalize($value) ? [$key => $value] : [];

        $this->report("setting {$key}", $changes, [$key => $current]);

        if ($apply && $changes) {
            CompanySetting::setSettings([$key => $value], $companyId);
        }
    }

    /**
     * @param  array<string, string>  $changes
     * @param  array<string, string|null>  $old
     */
    protected function report(string $label, array $changes, array $old): void
    {
        if (! $changes) {
            $this->line("  {$label}: unchanged");

            return;
        }

        $this->changes += count($changes);
        $this->line("  {$label}:");

        foreach ($changes as $field => $new) {
            $this->line("      {$field}: ".$this->short($old[$field] ?? ''));
            $this->line('        -> '.$this->short($new));
        }
    }

    protected function short(?string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags(str_replace(['<br>', '</p>', "\n"], ' / ', (string) $text))));

        return $text === '' ? '(leer)' : Str::limit($text, 160);
    }

    protected function normalize(?string $text): string
    {
        return trim(str_replace("\r\n", "\n", (string) $text));
    }
}
