<?php

use App\Support\Optics\LensRecommender;

beforeEach(function () {
    $this->rec = new LensRecommender;
});

it('recommends monofocal when there is no addition', function () {
    $out = $this->rec->recommend(['od_sphere' => '-1.00', 'os_sphere' => '-1.25']);
    expect($out['design'])->toBe('Monofocal')
        ->and($out['signals']['has_add'])->toBeFalse();
});

it('recommends progressive when an addition is present', function () {
    $out = $this->rec->recommend(['od_sphere' => '-1.00', 'od_add' => '2.00']);
    expect($out['design'])->toBe('Progresivo')
        ->and($out['signals']['has_add'])->toBeTrue();
});

it('maps max absolute power to a material index', function () {
    expect($this->rec->recommend(['od_sphere' => '-1.00'])['material'])->toBe('Material 1.56');
    expect($this->rec->recommend(['od_sphere' => '-2.50'])['material'])->toBe('Policarbonato');
    expect($this->rec->recommend(['od_sphere' => '-4.50'])['material'])->toBe('Material 1.67');
    expect($this->rec->recommend(['od_sphere' => '-6.50'])['material'])->toBe('Material 1.74');
});

it('adds sphere and cylinder magnitudes for the power signal', function () {
    // |-3.00| + |-1.50| = 4.50 -> 1.67 tier
    expect($this->rec->recommend(['od_sphere' => '-3.00', 'od_cylinder' => '-1.50'])['material'])
        ->toBe('Material 1.67');
});

it('maps prescription filters to a catalog filter', function () {
    expect($this->rec->recommend(['filters' => ['Antirreflejo Blue']])['filter'])->toBe('Blue Cut');
    expect($this->rec->recommend(['filters' => ['Fotocromático']])['filter'])->toBe('Foto Blue Cut');
    expect($this->rec->recommend([])['filter'])->toBe('Sin Filtro');
});

it('recommends digital process for progressive or high power', function () {
    expect($this->rec->recommend(['od_add' => '2.00'])['process'])->toBe('Digital');
    expect($this->rec->recommend(['od_sphere' => '-5.00'])['process'])->toBe('Digital');
    expect($this->rec->recommend(['od_sphere' => '-1.00'])['process'])->toBe('Terminado');
});

it('warns when monofocal chosen but addition present', function () {
    $warnings = $this->rec->warningsFor(['od_add' => '2.00'], ['design' => 'Monofocal']);
    expect($warnings)->toContain('La fórmula tiene adición; suele requerir un lente bifocal o progresivo.');
});

it('warns when multifocal chosen but no addition', function () {
    $warnings = $this->rec->warningsFor(['od_sphere' => '-1.00'], ['design' => 'Progresivo']);
    expect($warnings)->toContain('La fórmula no tiene adición; confirma que el lente deba ser multifocal.');
});

it('warns when a low index is chosen for high power', function () {
    $warnings = $this->rec->warningsFor(['od_sphere' => '-6.00'], ['material' => 'CR-39']);
    expect($warnings)->toContain('Potencia alta: un material de alto índice da un lente más delgado y liviano.');
});

it('returns no warnings for a congruent choice', function () {
    $warnings = $this->rec->warningsFor(
        ['od_sphere' => '-1.00'],
        ['design' => 'Monofocal', 'material' => 'Material 1.56']
    );
    expect($warnings)->toBe([]);
});
