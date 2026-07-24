<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

/**
 * FAQ entries (§5.1, issue 2.8). The straw-cost reference ranges themselves
 * live in rate_config (straw_cost_reference) and are rendered on the FAQ page
 * with an explicit "not our prices" disclaimer — not duplicated here.
 */
class FaqSeeder extends Seeder
{
    public function run(): void
    {
        $faqs = [
            ['general', 'What area do you serve?', 'We work within about 15 miles of Valley Mills, TX. Farther out is case-by-case, and a distance fee applies over 15 miles — see the Pricing page.', 1],
            ['general', 'Are you a veterinarian?', 'No, and we are honest about that. Jeff does everything that is legal in Texas without a vet license — AI, pregnancy checks by blood or palpation, vaccinations, dehorning, and more. Anything that requires a vet license goes to your vet; Jeff can handle the cattle chute-side.', 2],
            ['breeding', 'What is the difference between the Natural and Sync plans?', 'The Natural Plan is a single timed farm call when you recognize heat. The Basic/Sync Plan uses a CIDR 10-day sync protocol with three farm calls. Both are on the Services and Pricing pages.', 3],
            ['breeding', 'Do you sell semen straws?', 'No. We do not sell straws — we provide custody and storage only, and you always own your straws. Most clients order from their breeder or a semen bank and ship to the farm before the protocol starts.', 4],
            ['breeding', 'What do semen straws typically cost?', 'That depends entirely on your source — see the reference ranges on this page. Those are industry ranges to help you budget and are NOT Handy Herdsman prices.', 5],
            ['billing', 'When am I charged?', 'Your payment method is captured at booking and charged when the booking is confirmed. Cash-in-person is also an option — it flags the booking as cash and Jeff settles it manually.', 6],
        ];

        foreach ($faqs as [$category, $question, $answer, $sort]) {
            Faq::updateOrCreate(
                ['question' => $question],
                ['category' => $category, 'answer' => $answer, 'active' => true, 'sort_order' => $sort],
            );
        }
    }
}
