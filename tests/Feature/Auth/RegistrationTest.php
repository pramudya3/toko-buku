<?php

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

// ── Kebijakan password production: minimal 6, tanpa syarat kompleks ──

/**
 * Instance kecil utk memanggil trait protected passwordRules().
 */
function passwordRuleTester(): object
{
    return new class
    {
        use PasswordValidationRules;

        public function rules(): array
        {
            return $this->passwordRules();
        }
    };
}

function validatePasswordInProduction(string $password, ?string $confirmation = null): ValidatorContract
{
    // AppServiceProvider membaca app()->isProduction() saat closure dipanggil.
    // Environment tersimpan sebagai container binding 'env' → swap sementara.
    $previousEnv = app()['env'];

    app()->instance('env', 'production');

    try {
        return Validator::make([
            'password' => $password,
            'password_confirmation' => $confirmation ?? $password,
            // passwordRules() = rule utk FIELD password → bungkus key-nya.
        ], ['password' => passwordRuleTester()->rules()]);
    } finally {
        app()->instance('env', $previousEnv);
    }
}

it('accepts a simple 6-character password in production', function (): void {
    $validator = validatePasswordInProduction('abcdef');

    expect($validator->passes())->toBeTrue();
});

it('rejects passwords shorter than 6 characters in production', function (): void {
    $validator = validatePasswordInProduction('abc12');

    expect($validator->fails())->toBeTrue();
});

it('does not require mixed case numbers or symbols in production', function (): void {
    // Semua huruf kecil tanpa angka/simbol — lolos sesuai kebijakan baru.
    $validator = validatePasswordInProduction('rahasia');

    expect($validator->passes())->toBeTrue();
});

it('still requires confirmed password in production', function (): void {
    $validator = validatePasswordInProduction('abcdef', 'fedcba');

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('password'))->toBeTrue();
});
