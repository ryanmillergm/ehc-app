<?php

namespace Tests\Feature\Models;

use App\Models\ApplicationForm;
use App\Models\FormField;
use App\Models\FormFieldPlacement;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FormFieldPlacementDatabaseConstraintTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function database_prevents_duplicate_field_placements_for_same_owner(): void
    {
        $form = ApplicationForm::factory()->create();

        $field = FormField::query()->create([
            'key'   => 'duplicate_test',
            'type'  => 'text',
            'label' => 'Duplicate test',
            'help_text' => null,
            'config' => [],
        ]);

        FormFieldPlacement::query()->create([
            'fieldable_type' => ApplicationForm::class,
            'fieldable_id'   => $form->id,
            'form_field_id'  => $field->id,
            'is_required'    => false,
            'is_active'      => true,
            'sort'           => 1,
        ]);

        $this->expectException(QueryException::class);

        // same unique composite key should throw
        FormFieldPlacement::query()->create([
            'fieldable_type' => ApplicationForm::class,
            'fieldable_id'   => $form->id,
            'form_field_id'  => $field->id,
            'is_required'    => true,
            'is_active'      => true,
            'sort'           => 2,
        ]);
    }
}
