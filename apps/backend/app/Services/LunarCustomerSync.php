<?php

namespace App\Services;

use App\Models\User;
use Lunar\Models\Customer;

class LunarCustomerSync
{
    public function sync(User $user): Customer
    {
        $customer = $user->customers()->latest()->first()
            ?: Customer::query()->where('account_ref', $user->email)->first()
            ?: Customer::create($this->attributesFor($user));

        $customer->fill($this->attributesFor($user))->save();

        $user->customers()->syncWithoutDetaching([$customer->id]);

        return $customer;
    }

    /**
     * @return array{first_name: string, last_name: string, account_ref: string}
     */
    private function attributesFor(User $user): array
    {
        $parts = preg_split('/\s+/', trim($user->name), 2);

        return [
            'first_name' => $parts[0] ?: 'Customer',
            'last_name' => $parts[1] ?? '',
            'account_ref' => $user->email,
        ];
    }
}
