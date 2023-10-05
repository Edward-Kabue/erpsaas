<?php

namespace App\Events;

use App\Models\{Company, User};
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CompanyGenerated
{
    use Dispatchable;
    use SerializesModels;

    public User $user;

    public Company $company;

    public string $country;

    /**
     * Create a new event instance.
     */
    public function __construct(User $user, Company $company, string $country)
    {
        $this->user = $user;
        $this->company = $company;
        $this->country = $country;
    }
}
