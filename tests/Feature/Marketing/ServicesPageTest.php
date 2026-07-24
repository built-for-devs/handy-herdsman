<?php

namespace Tests\Feature\Marketing;

use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ServicesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_services_render_from_the_database_not_hardcoded(): void
    {
        Service::create([
            'name' => 'Made-Up Test Service',
            'slug' => 'made-up-test-service',
            'description' => 'Only exists in this test.',
            'category' => 'breeding',
            'type' => 'standard',
            'price_rule' => ['model' => 'flat', 'price' => 4242],
            'active' => true,
            'sort_order' => 1,
        ]);

        $this->get('/services')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('marketing/Services')
                ->has('groups', 1)
                ->where('groups.0.services.0.name', 'Made-Up Test Service')
                // Price string is derived from the DB price_rule, proving it is data-driven.
                ->where('groups.0.services.0.price', '$4,242')
                ->has('notOffered')
            );
    }

    public function test_inactive_services_are_hidden(): void
    {
        Service::create([
            'name' => 'Hidden Service',
            'slug' => 'hidden-service',
            'category' => 'health',
            'type' => 'standard',
            'price_rule' => ['model' => 'flat', 'price' => 10],
            'active' => false,
            'sort_order' => 1,
        ]);

        $this->get('/services')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('groups', []));
    }

    public function test_not_offered_trust_signals_are_present(): void
    {
        $this->get('/services')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('notOffered.0.title', 'Castration')
                ->where('notOffered.1.title', 'Hoof trimming')
            );
    }
}
