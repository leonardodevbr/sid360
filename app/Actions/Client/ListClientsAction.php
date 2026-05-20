<?php

declare(strict_types=1);

namespace App\Actions\Client;

use App\Models\Client;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class ListClientsAction
{
    /**
     * @return LengthAwarePaginator<Client>|Collection<int, Client>
     */
    public function execute(Request $request): LengthAwarePaginator|Collection
    {
        $query = Client::query()
            ->when($request->filled('search'), function ($q) use ($request): void {
                $search = $request->string('search')->toString();
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('cpf', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            })
            ->orderBy('name');

        if ($request->boolean('all')) {
            return $query->get();
        }

        return $query->paginate(15);
    }
}
