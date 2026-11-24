<?php

namespace App\Services\EstimateDocument;

use App\Models\Currency;
use App\Models\Estimate;
use App\Models\Item;
use App\Models\Tax;
use Illuminate\Support\Collection;

/**
 * Turns the Tiptap JSON of an offer document into HTML for the PDF.
 *
 * Only the node and mark types listed here are rendered. Anything else is
 * dropped together with its content, text is always escaped and no attribute
 * is copied through, so the output cannot load images, scripts or styles. The
 * editor (resources/scripts/admin/views/estimates/create/document) uses the
 * same type names and the same CSS classes (resources/css/angebot-document.css).
 *
 * Bound blocks (positions, cost overview, hosting packages) carry no numbers in
 * the document. They are rendered from the estimate and the company items at
 * render time.
 */
class DocumentRenderer
{
    public const MAX_DEPTH = 30;

    protected const ALIGNMENTS = ['left', 'center', 'right', 'justify'];

    /** @var array<string, string>|null */
    protected ?array $fields = null;

    public function __construct(protected Estimate $estimate) {}

    /**
     * @param  array<mixed>|null  $document
     */
    public function render(?array $document): string
    {
        if (! is_array($document) || ($document['type'] ?? null) !== 'doc') {
            return '';
        }

        return $this->children($document, 1);
    }

    /**
     * @param  array<mixed>  $node
     */
    protected function children(array $node, int $depth): string
    {
        $content = $node['content'] ?? [];

        if (! is_array($content)) {
            return '';
        }

        $html = '';

        foreach ($content as $child) {
            if (is_array($child)) {
                $html .= $this->node($child, $depth + 1);
            }
        }

        return $html;
    }

    /**
     * @param  array<mixed>  $node
     */
    protected function node(array $node, int $depth): string
    {
        if ($depth > self::MAX_DEPTH) {
            return '';
        }

        $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
        $inner = fn () => $this->children($node, $depth);

        return match ($node['type'] ?? null) {
            'text' => $this->text($node),
            'paragraph' => '<p'.$this->alignClass($attrs).'>'.$inner().'</p>',
            'heading' => $this->heading($attrs, $inner()),
            'bulletList' => '<ul>'.$inner().'</ul>',
            'orderedList' => '<ol>'.$inner().'</ol>',
            'listItem' => '<li>'.$inner().'</li>',
            'blockquote' => '<blockquote>'.$inner().'</blockquote>',
            'hardBreak' => '<br>',
            'horizontalRule' => '<hr>',
            'table' => '<table class="doc-table"><tbody>'.$inner().'</tbody></table>',
            'tableRow' => '<tr>'.$inner().'</tr>',
            'tableHeader' => '<th'.$this->span($attrs).'>'.$inner().'</th>',
            'tableCell' => '<td'.$this->span($attrs).'>'.$inner().'</td>',
            'pageBreak' => '<div class="page-break"></div>',
            'field' => $this->field($attrs),
            'offerMeta' => $this->offerMeta($attrs),
            'estimateItems' => $this->view('items'),
            'estimateTotals' => $this->view('totals', ['taxes' => $this->taxes()]),
            'hostingPackages' => $this->hostingPackages($attrs),
            default => '',
        };
    }

    /**
     * @param  array<mixed>  $node
     */
    protected function text(array $node): string
    {
        $html = e((string) ($node['text'] ?? ''));
        $marks = is_array($node['marks'] ?? null) ? $node['marks'] : [];

        foreach ($marks as $mark) {
            $html = match (is_array($mark) ? ($mark['type'] ?? null) : null) {
                'bold' => '<strong>'.$html.'</strong>',
                'italic' => '<em>'.$html.'</em>',
                'underline' => '<u>'.$html.'</u>',
                'strike' => '<s>'.$html.'</s>',
                'link' => $this->link($mark['attrs']['href'] ?? null, $html),
                default => $html,
            };
        }

        return $html;
    }

    protected function link(mixed $href, string $html): string
    {
        if (! is_string($href) || ! preg_match('#^(https?://|mailto:)#i', $href)) {
            return $html;
        }

        return '<a href="'.e($href).'">'.$html.'</a>';
    }

    /**
     * @param  array<mixed>  $attrs
     */
    protected function heading(array $attrs, string $inner): string
    {
        $level = (int) ($attrs['level'] ?? 2);
        $level = min(max($level, 1), 3);

        return "<h{$level}".$this->alignClass($attrs).">{$inner}</h{$level}>";
    }

    /**
     * @param  array<mixed>  $attrs
     */
    protected function alignClass(array $attrs): string
    {
        $align = $attrs['textAlign'] ?? null;

        if (! in_array($align, self::ALIGNMENTS, true) || $align === 'left') {
            return '';
        }

        return ' class="align-'.$align.'"';
    }

    /**
     * @param  array<mixed>  $attrs
     */
    protected function span(array $attrs): string
    {
        $html = '';

        foreach (['colspan', 'rowspan'] as $name) {
            $value = (int) ($attrs[$name] ?? 1);

            if ($value > 1 && $value <= 20) {
                $html .= " {$name}=\"{$value}\"";
            }
        }

        return $html;
    }

    /**
     * An inline placeholder such as {PRIMARY_CONTACT_NAME}. Unknown keys render
     * as nothing, like unknown placeholders in notes.
     *
     * @param  array<mixed>  $attrs
     */
    protected function field(array $attrs): string
    {
        $key = $attrs['key'] ?? null;

        if (! is_string($key)) {
            return '';
        }

        return $this->fields()['{'.$key.'}'] ?? '';
    }

    /**
     * Escaped placeholder values: getFieldsArray() escapes its own values, the
     * estimate's extra fields are escaped here.
     *
     * @return array<string, string>
     */
    protected function fields(): array
    {
        if ($this->fields === null) {
            $extra = array_map(fn ($value) => e((string) $value), $this->estimate->getExtraFields());
            $this->fields = array_map('strval', array_merge($this->estimate->getFieldsArray(), $extra));
        }

        return $this->fields;
    }

    /**
     * @param  array<mixed>  $attrs
     */
    protected function hostingPackages(array $attrs): string
    {
        $ids = array_values(array_filter(
            is_array($attrs['itemIds'] ?? null) ? $attrs['itemIds'] : [],
            fn ($id) => is_int($id) || ctype_digit((string) $id)
        ));

        if ($ids === []) {
            return '';
        }

        $items = Item::query()
            ->where('company_id', $this->estimate->company_id)
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (Item $item) => array_search($item->id, array_map('intval', $ids)))
            ->values();

        $recommended = (int) ($attrs['recommendedId'] ?? 0);

        return $this->view('hosting', [
            'packages' => $items->map(fn (Item $item) => [
                'name' => $item->name,
                'lines' => self::descriptionLines($item->description),
                'price' => $item->price,
                'recommended' => $item->id === $recommended,
            ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function view(string $name, array $data = []): string
    {
        return view('app.pdf.estimate.angebot-dokument.'.$name, $data + [
            'estimate' => $this->estimate,
            'currency' => $this->estimate->customer?->currency,
        ])->render();
    }

    /**
     * Number, dates, customer address and the contact person of the offer.
     *
     * @param  array<mixed>  $attrs
     */
    protected function offerMeta(array $attrs): string
    {
        $contact = [];

        foreach (['contactName', 'contactEmail', 'contactPhone'] as $key) {
            $value = $attrs[$key] ?? null;
            $contact[$key] = is_string($value) ? trim(mb_substr($value, 0, 200)) : '';
        }

        return $this->view('meta', [
            'billingAddress' => $this->estimate->getCustomerBillingAddress() ?: '',
            'contact' => $contact,
        ]);
    }

    /**
     * Taxes of the offer, summed per tax type when taxes are set per item
     * (same as Estimate::getPDFData()).
     *
     * @return Collection<int, Tax>
     */
    protected function taxes(): Collection
    {
        if ($this->estimate->tax_per_item !== 'YES') {
            return $this->estimate->taxes;
        }

        $taxes = collect();

        foreach ($this->estimate->items as $item) {
            foreach ($item->taxes as $tax) {
                $found = $taxes->first(fn ($existing) => $existing->tax_type_id == $tax->tax_type_id);

                if ($found) {
                    $found->amount += $tax->amount;
                } else {
                    $taxes->push(clone $tax);
                }
            }
        }

        return $taxes;
    }

    /**
     * Formats cents as "1.200,00 €" in the customer's currency, without the
     * DejaVu font span that format_money_pdf() adds for dompdf.
     */
    public static function money(int|float|null $cents, ?Currency $currency): string
    {
        $amount = ((float) $cents) / 100;

        if (! $currency) {
            return e(number_format($amount, 2, ',', '.').' €');
        }

        $number = number_format(
            $amount,
            (int) $currency->precision,
            (string) $currency->decimal_separator,
            (string) $currency->thousand_separator
        );

        return e($currency->swap_currency_symbol
            ? $number.' '.$currency->symbol
            : $currency->symbol.' '.$number);
    }

    /**
     * Splits an item description into lines, without list dashes.
     *
     * @return list<string>
     */
    public static function descriptionLines(?string $description): array
    {
        $lines = preg_split('/\r\n|\r|\n/', (string) $description) ?: [];
        $lines = array_map(fn ($line) => trim(preg_replace('/^\s*[-•*]\s*/u', '', $line)), $lines);

        return array_values(array_filter($lines, fn ($line) => $line !== ''));
    }

    /**
     * Splits the description of a work package. Lines before the first
     * "Tätigkeit: Beschreibung" line are the intro, every line after that is a
     * row of the activity table (a row without a colon has no description).
     *
     * @return array{intro: list<string>, rows: list<array{title: string, text: string}>}
     */
    public static function workPackage(?string $description): array
    {
        $intro = [];
        $rows = [];

        foreach (self::descriptionLines($description) as $line) {
            $parts = explode(':', $line, 2);

            if (count($parts) === 1 && $rows === []) {
                $intro[] = $line;

                continue;
            }

            $rows[] = ['title' => trim($parts[0]), 'text' => trim($parts[1] ?? '')];
        }

        return ['intro' => $intro, 'rows' => $rows];
    }
}
