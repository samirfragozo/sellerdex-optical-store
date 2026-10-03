<?php

use App\Enums\FrameSource;
use App\Enums\FrameType;
use App\Models\Company;
use App\Models\Customer;
use App\Models\LensOrder;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SaleItem;
use App\Models\SaleItemLensConfig;
use App\Models\Supplier;
use App\Models\User;
use App\Support\LabOrderMessage;
use App\Support\WhatsApp;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->actingAs(User::factory()->admin()->create());
    $this->lab = Supplier::factory()->laboratory()->create(['name' => 'Lab Central', 'phone' => '300 123 4567', 'email' => 'pedidos@labcentral.test']);
    $patient = Customer::factory()->create(['name' => 'Luis', 'last_name' => 'Pérez', 'id_number' => '123456789', 'phone' => '3109998888']);
    $item = SaleItem::factory()->create(['description' => 'Progresivo Digital Policarbonato — Antirreflejo']);
    SaleItemLensConfig::factory()->create(['sale_item_id' => $item->id, 'patient_id' => $patient->id]);
    $this->order = LensOrder::factory()->create([
        'sale_item_id' => $item->id, 'supplier_id' => $this->lab->id,
        'od_pd' => 31.5, 'os_pd' => 32, 'od_height' => 18, 'os_height' => 18.5,
        'frame_a' => 52, 'frame_b' => 38, 'frame_dbl' => 18, 'frame_type' => FrameType::SemiRimless,
        'frame_source' => FrameSource::CustomerOwn, 'customer_frame_description' => 'Montura dorada', 'customer_frame_condition' => 'Rayón leve',
        'prescription_snapshot' => ['od_sphere' => '-2.25', 'od_cylinder' => '-0.50', 'od_axis' => 180, 'od_add' => '2.00',
            'os_sphere' => '-2.00', 'os_cylinder' => null, 'os_axis' => null, 'os_add' => '2.00'],
    ]);
});

it('prints everything the lab needs', function () {
    $this->get(route('documents.lab-order', $this->order))
        ->assertOk()
        ->assertSee('Luis Pérez')
        ->assertSee('-2.25')
        ->assertSee('+2.00')
        ->assertSee('31.5')
        ->assertSee('18.5')
        ->assertSee('Progresivo Digital Policarbonato — Antirreflejo')
        ->assertSee(FrameType::SemiRimless->label())
        ->assertSee('Montura dorada')
        ->assertSee('Lab Central')
        ->assertDontSee('123456789')
        ->assertDontSee('3109998888');
});

it('downloads the lab order as a PDF', function () {
    $this->get(route('documents.lab-order.pdf', $this->order))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('does not open another company lab order', function () {
    $foreign = LensOrder::factory()->for(Company::factory()->create())->create();

    $this->get(route('documents.lab-order', $foreign->id))->assertNotFound();
});

it('builds a message with the order data and no contact data of the patient', function () {
    $message = LabOrderMessage::for($this->order);

    expect($message)->toContain('Luis Pérez', '-2.25', '180', '+2.00', '31.5', '52', 'Montura dorada')
        ->not->toContain('123456789')
        ->not->toContain('3109998888');
});

it('links to WhatsApp with a Colombian country code and to e-mail', function () {
    expect(WhatsApp::url('300 123 4567', 'Hola'))->toBe('https://wa.me/573001234567?text=Hola')
        ->and(WhatsApp::url('+57 300 123 4567', 'a b'))->toBe('https://wa.me/573001234567?text=a%20b')
        ->and(WhatsApp::url(null, 'x'))->toBeNull()
        ->and(WhatsApp::url('  ', 'x'))->toBeNull()
        ->and(LabOrderMessage::mailtoUrl($this->order))->toStartWith('mailto:pedidos@labcentral.test?subject=');
});

it('has no WhatsApp or e-mail link for a lab without phone or e-mail', function () {
    $this->lab->update(['phone' => null, 'email' => null]);

    expect(WhatsApp::url($this->lab->fresh()->phone, 'x'))->toBeNull()
        ->and(LabOrderMessage::mailtoUrl($this->order->fresh()))->toBeNull();
});

it('describes a sold frame with the frame line of the same armado, not the lens', function () {
    $lens = $this->order->saleItem;
    $lens->update(['group_key' => 'g1']);
    $frameProduct = Product::factory()->create(['product_category_id' => ProductCategory::factory()->create(['key' => 'frame'])->id]);
    SaleItem::factory()->create(['sale_id' => $lens->sale_id, 'group_key' => 'g1', 'product_id' => $frameProduct->id, 'description' => 'Ray-Ban negro']);
    SaleItem::factory()->create(['sale_id' => $lens->sale_id, 'group_key' => 'g2', 'description' => 'Otro']);
    $this->order->update(['frame_source' => FrameSource::Sold]);

    expect($this->order->fresh()->frameDescription())->toBe('Ray-Ban negro');
});
