<?php

use App\Actions\OpenWarrantyClaim;
use App\Enums\LensKind;
use App\Enums\WarrantyClaimStatus;
use App\Enums\WarrantyClaimType;
use App\Models\LensCombination;
use App\Models\LensType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleItemLensConfig;
use App\Models\User;
use App\Models\WarrantyClaim;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
    $category = ProductCategory::factory()->create(['warranty_months' => 6]);
    $this->sale = Sale::factory()->create(['is_delivered' => true, 'delivered_at' => '2026-01-10']);
    $this->item = SaleItem::factory()->create([
        'sale_id' => $this->sale->id,
        'product_id' => Product::factory()->create(['product_category_id' => $category->id])->id,
        'quantity' => 1,
    ]);
});

it('opens a claim within the term', function () {
    $claim = app(OpenWarrantyClaim::class)->handle($this->item, WarrantyClaimType::Warranty, 'Bisagra rota', $this->admin, Carbon::parse('2026-07-09'));

    expect($claim)->status->toBe(WarrantyClaimStatus::Received)->company_id->toBe($this->sale->company_id);
});

it('refuses a claim after the term', function () {
    app(OpenWarrantyClaim::class)->handle($this->item, WarrantyClaimType::Warranty, 'Bisagra rota', $this->admin, Carbon::parse('2026-07-11'));
})->throws(ValidationException::class);

it('extends the term by the days earlier claims were open', function () {
    WarrantyClaim::factory()->create([
        'sale_item_id' => $this->item->id, 'status' => WarrantyClaimStatus::Delivered,
        'received_at' => '2026-03-01', 'delivered_at' => '2026-03-21',
    ]);

    expect(WarrantyClaim::deadlineFor($this->item->fresh(), WarrantyClaimType::Warranty)->toDateString())->toBe('2026-07-30');
});

it('refuses a claim on an undelivered sale and a second open claim on the same line', function () {
    $this->sale->update(['is_delivered' => false]);
    expect(fn () => app(OpenWarrantyClaim::class)->handle($this->item->fresh(), WarrantyClaimType::Warranty, 'x', $this->admin, Carbon::parse('2026-02-01')))
        ->toThrow(ValidationException::class);

    $this->sale->update(['is_delivered' => true, 'delivered_at' => '2026-01-10']);
    app(OpenWarrantyClaim::class)->handle($this->item->fresh(), WarrantyClaimType::Warranty, 'x', $this->admin, Carbon::parse('2026-02-01'));
    expect(fn () => app(OpenWarrantyClaim::class)->handle($this->item->fresh(), WarrantyClaimType::Warranty, 'y', $this->admin, Carbon::parse('2026-02-02')))
        ->toThrow(ValidationException::class);
});

it('accepts adaptation claims only for addition lenses within the adaptation days', function () {
    // A frame line is not a lens: refused.
    expect(fn () => app(OpenWarrantyClaim::class)->handle($this->item, WarrantyClaimType::Adaptation, 'No se adapta', $this->admin, Carbon::parse('2026-01-20')))
        ->toThrow(ValidationException::class);
});

it('accepts a progressive lens adaptation claim within 30 days and refuses it after', function () {
    $this->admin->company->update(['adaptation_warranty_days' => 30]);
    $type = LensType::factory()->create(['kind' => LensKind::Progressive]);
    $combination = LensCombination::factory()->create(['lens_type_id' => $type->id]);
    SaleItemLensConfig::factory()->create(['sale_item_id' => $this->item->id, 'lens_combination_id' => $combination->id]);
    $item = $this->item->fresh();

    $open = fn (string $date) => app(OpenWarrantyClaim::class)->handle($item, WarrantyClaimType::Adaptation, 'No se adapta', $this->admin, Carbon::parse($date));

    expect(fn () => $open('2026-02-10'))->toThrow(ValidationException::class);
    expect($open('2026-02-09'))->type->toBe(WarrantyClaimType::Adaptation);
});

it('moves a claim along the plain steps and refuses skipping to delivered', function () {
    $claim = WarrantyClaim::factory()->create(['sale_item_id' => $this->item->id]);

    $claim->advance(WarrantyClaimStatus::InReview);
    $claim->advance(WarrantyClaimStatus::AtSupplier);
    expect($claim->fresh()->status)->toBe(WarrantyClaimStatus::AtSupplier);

    $claim->advance(WarrantyClaimStatus::Delivered);
})->throws(DomainException::class);
