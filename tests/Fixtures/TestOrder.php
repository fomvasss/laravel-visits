<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Tests\Fixtures;

use Fomvasss\Visits\Concerns\HasVisits;
use Illuminate\Database\Eloquent\Model;

class TestOrder extends Model
{
    use HasVisits;

    protected $table = 'test_orders';

    protected $guarded = ['id'];
}
