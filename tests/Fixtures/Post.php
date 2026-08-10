<?php

declare(strict_types=1);

namespace TheDoctor0\LaravelFactoryGenerator\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use SoftDeletes;

    protected $table = 'posts';

    public function getBodyPreviewAttribute(): string
    {
        return '';
    }

    public function fakeRelation(): ?object
    {
        if ($this->exists) {
            return $this->belongsTo(Country::class);
        }

        return null;
    }
}
