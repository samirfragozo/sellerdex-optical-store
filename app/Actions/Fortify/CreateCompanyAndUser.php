<?php

namespace App\Actions\Fortify;

use App\Actions\SeedCompanyDefaults;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Spatie\Permission\Models\Role;

class CreateCompanyAndUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /** @param array<string, string> $input */
    public function create(array $input): User
    {
        Validator::make($input, [
            'company_name' => ['required', 'string', 'max:255'],
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        return DB::transaction(function () use ($input): User {
            $company = Company::create([
                'name' => $input['company_name'],
                'is_active' => true,
                'plan' => 'free',
            ]);

            (new SeedCompanyDefaults)->handle($company);

            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
                'company_id' => $company->id,
                'is_active' => true,
            ]);

            $user->assignRole(Role::findOrCreate(User::ROLE_ADMIN));

            return $user;
        });
    }
}
