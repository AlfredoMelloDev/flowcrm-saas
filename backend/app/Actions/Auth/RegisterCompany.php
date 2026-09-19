<?php

namespace App\Actions\Auth;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterCompany
{
    /**
     * @param  array<string, mixed>  $companyData
     * @param  array<string, mixed>  $userData
     */
    public function handle(array $companyData, array $userData): User
    {
        return DB::transaction(function () use ($companyData, $userData) {
            $company = Company::create($companyData);

            return User::create([
                ...$userData,
                'company_id' => $company->id,
                'role' => UserRole::Admin,
            ]);
        });
    }
}
