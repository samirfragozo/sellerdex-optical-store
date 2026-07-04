<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $companyId = Auth::user()?->company_id;
        if ($companyId !== null) {
            $builder->where($model->getTable().'.company_id', $companyId);
        }
        // superadmin (company_id = null): sin filtro, ve todo
    }
}
