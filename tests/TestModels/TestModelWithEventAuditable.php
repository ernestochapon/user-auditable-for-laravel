<?php

namespace ErnestoChapon\UserAuditable\Tests\TestModels;

use ErnestoChapon\UserAuditable\Traits\EventAuditable;
use Illuminate\Database\Eloquent\Model;

class TestModelWithEventAuditable extends Model
{
    use EventAuditable;

    protected $table = 'test_models_with_event_auditable';
    protected $guarded = [];
    public $timestamps = true;
}
