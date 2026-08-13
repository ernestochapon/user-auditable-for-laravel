<?php

namespace ErnestoChapon\UserAuditable\Tests\TestModels;

use ErnestoChapon\UserAuditable\Traits\ChangeAuditable;
use ErnestoChapon\UserAuditable\Traits\EventAuditable;
use ErnestoChapon\UserAuditable\Traits\UserAuditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TestModelWithChangeAuditable extends Model
{
    use ChangeAuditable, EventAuditable, SoftDeletes, UserAuditable;

    protected $table = 'test_models_with_change_auditable';

    protected $guarded = [];

    protected $hidden = ['secret'];

    protected array $auditExclude = ['status'];
}
