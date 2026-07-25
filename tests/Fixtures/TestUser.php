<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Tests\Fixtures;

use Fomvasss\Visits\Concerns\HasVisits;
use Illuminate\Foundation\Auth\User as Authenticatable;

class TestUser extends Authenticatable
{
    use HasVisits;

    protected $table = 'test_users';

    protected $guarded = ['id'];

    public function fullName(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }
}
