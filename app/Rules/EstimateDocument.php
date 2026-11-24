<?php

namespace App\Rules;

use App\Services\EstimateDocument\DocumentRenderer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates the Tiptap JSON of an offer document: a "doc" root, a size cap and
 * a nesting cap. Unknown node types are allowed here and dropped when the
 * document is rendered, so an older editor never loses a save.
 */
class EstimateDocument implements ValidationRule
{
    public const MAX_BYTES = 512 * 1024;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        if (($value['type'] ?? null) !== 'doc') {
            $fail('The :attribute must be a document.');

            return;
        }

        $json = json_encode($value);

        if ($json === false || strlen($json) > self::MAX_BYTES) {
            $fail('The :attribute is too large.');

            return;
        }

        if ($this->depth($value) > DocumentRenderer::MAX_DEPTH) {
            $fail('The :attribute is nested too deeply.');
        }
    }

    /**
     * @param  array<mixed>  $node
     */
    protected function depth(array $node, int $level = 1): int
    {
        $max = $level;

        foreach ($node['content'] ?? [] as $child) {
            if (is_array($child)) {
                $max = max($max, $this->depth($child, $level + 1));
            }
        }

        return $max;
    }
}
