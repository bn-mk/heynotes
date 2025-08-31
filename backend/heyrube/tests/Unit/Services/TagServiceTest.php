<?php

namespace Tests\Unit\Services;

use App\Models\Tag;
use App\Services\TagService;
use Tests\MongoTestCase;

class TagServiceTest extends MongoTestCase
{
    public function test_create_and_list_names(): void
    {
        $service = app(TagService::class);

        $t1 = $service->create('alpha');
        $service->create('alpha'); // duplicate
        $t2 = $service->create('beta');

        $this->assertEquals('alpha', $t1->name);
        $this->assertEquals('beta', $t2->name);
        $this->assertSame(2, Tag::count());

        $names = $service->listNames()->all();
        $this->assertEqualsCanonicalizing(['alpha', 'beta'], $names);
    }
}

