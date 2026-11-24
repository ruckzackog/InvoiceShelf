<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store the visual offer document (Tiptap JSON) next to the estimate.
 *
 * The document is the editable page of the "angebot" template. Positions and
 * totals are not copied into it: the document only holds references to them,
 * so the numbers always come from the estimate items.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->longText('document')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->dropColumn('document');
        });
    }
};
