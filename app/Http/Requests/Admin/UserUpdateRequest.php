<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UserUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->whereNull('deleted_at')->ignore($this->route('user'))],
            'password' => ['nullable', 'string', 'min:8'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Proteksi lockout: admin tidak boleh menonaktifkan diri sendiri,
     * dan tidak boleh menonaktifkan admin aktif terakhir.
     *
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->filled('is_active') || $this->boolean('is_active')) {
                    return;
                }

                /** @var User $user */
                $user = $this->route('user');

                if ($this->user()?->is($user)) {
                    $validator->errors()->add('is_active', 'Anda tidak dapat menonaktifkan akun sendiri.');

                    return;
                }

                $activeAdmins = User::query()
                    ->where('is_admin', true)
                    ->where('is_active', true)
                    ->count();

                if ($activeAdmins <= 1) {
                    $validator->errors()->add('is_active', 'Tidak dapat menonaktifkan admin aktif terakhir.');
                }
            },
        ];
    }
}
