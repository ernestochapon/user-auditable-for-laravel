<?php

namespace ErnestoChapon\UserAuditable\Tests\TestModels;

use ErnestoChapon\UserAuditable\Traits\ChangeAuditable;
use Illuminate\Database\Eloquent\Model;

class TestModelWithChangeAuditableInclude extends Model
{
    use ChangeAuditable;

    protected $table = 'test_models_with_change_auditable_include';

    protected $guarded = [];

    protected array $auditInclude = ['name'];
}
