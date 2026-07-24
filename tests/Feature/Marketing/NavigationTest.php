<?php

namespace Tests\Feature\Marketing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('publicRouteProvider')]
    public function test_public_pages_render(string $path, string $component): void
    {
        $this->get($path)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component($component));
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function publicRouteProvider(): array
    {
        return [
            'home' => ['/', 'marketing/Home'],
            'about' => ['/about', 'marketing/About'],
            'services' => ['/services', 'marketing/Services'],
            'pricing' => ['/pricing', 'marketing/Pricing'],
            'faq' => ['/faq', 'marketing/Faq'],
            'contact' => ['/contact', 'marketing/Contact'],
            'directory' => ['/resources/directory', 'marketing/ResourceDirectory'],
            'blog index' => ['/blog', 'marketing/blog/Index'],
        ];
    }

    #[DataProvider('placeholderRouteProvider')]
    public function test_resources_placeholder_pages_render_for_the_shell(string $path): void
    {
        $this->get($path)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('marketing/ComingSoon'));
    }

    /** @return array<string, array{0: string}> */
    public static function placeholderRouteProvider(): array
    {
        return [
            'cattle for sale' => ['/resources/cattle-for-sale'],
        ];
    }

    #[DataProvider('liveCalculatorRouteProvider')]
    public function test_resources_calculators_render_the_live_tools(string $path, string $component): void
    {
        $this->get($path)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component($component));
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function liveCalculatorRouteProvider(): array
    {
        return [
            'ai timing calculator' => ['/resources/ai-timing-calculator', 'public/AiTimingCalculator'],
            'due date calculator' => ['/resources/due-date-calculator', 'public/DueDateCalculator'],
        ];
    }
}
