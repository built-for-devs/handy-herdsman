<?php

namespace Tests\Feature\Marketing;

use App\Models\Faq;
use App\Models\RateConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FaqPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_faq_renders_active_entries_grouped_by_category(): void
    {
        Faq::factory()->create(['category' => 'general', 'question' => 'Visible?', 'active' => true, 'sort_order' => 1]);
        Faq::factory()->inactive()->create(['question' => 'Hidden?']);

        $this->get('/faq')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('marketing/Faq')
                ->has('faqs', 1)
                ->where('faqs.0.items.0.question', 'Visible?')
            );
    }

    public function test_straw_cost_reference_ranges_are_shown_and_labeled_not_our_prices(): void
    {
        RateConfig::create([
            'key' => 'straw_cost_reference',
            'value' => [
                'standard' => ['min' => 25, 'max' => 50],
                'premium' => ['min' => 50, 'max' => 300],
            ],
            'label' => 'Straw cost reference',
            'group' => 'reference',
        ]);

        $this->get('/faq')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('strawCostReference.standard.min', 25)
                ->where('strawCostReference.premium.max', 300)
                ->where('strawCostDisclaimer', fn (string $text) => str_contains($text, 'NOT Handy Herdsman prices'))
            );
    }
}
