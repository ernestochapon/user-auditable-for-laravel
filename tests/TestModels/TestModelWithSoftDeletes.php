<?php

namespace ErnestoChapon\UserAuditable\Tests\TestModels;

use ErnestoChapon\UserAuditable\Traits\UserAuditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TestModelWithSoftDeletes extends Model
{
    use SoftDeletes, UserAuditable;

    protected $table = 'test_models_with_soft_deletes';
    protected $guarded = [];
}
