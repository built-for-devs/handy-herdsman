<?php

declare(strict_types=1);

namespace App\Http\Controllers\Booking;

use App\Enums\AnimalType;
use App\Enums\OnCallKind;
use App\Exceptions\BookingValidationException;
use App\Exceptions\BreedingEligibilityException;
use App\Exceptions\MixedGroupException;
use App\Http\Controllers\Concerns\ResolvesCurrentTeam;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Cattle;
use App\Models\Service;
use App\Services\Booking\BookingRequest;
use App\Services\Booking\BookingService;
use App\Services\Booking\ProtocolScheduler;
use App\Services\Booking\ServiceAreaResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Client booking flow (spec §6.1). Big service-picker cards route by type to
 * the protocol scheduler, the on-call pager, or a standard single-visit date
 * request — all mobile-first with minimal typing.
 */
class BookingController extends Controller
{
    use ResolvesCurrentTeam;

    public function __construct(
        private BookingService $bookings,
        private ProtocolScheduler $scheduler,
        private ServiceAreaResolver $serviceArea,
    ) {}

    public function index(Request $request): Response
    {
        $team = $this->currentTeam($request);

        return Inertia::render('booking/Index', [
            'bookings' => $team->bookings()
                ->with(['service:id,name,type', 'visits'])
                ->latest()
                ->get()
                ->map(fn (Booking $b) => $this->summary($b)),
        ]);
    }

    public function create(Request $request): Response
    {
        $team = $this->currentTeam($request);
        $client = $team->client;

        $area = $client !== null ? $this->serviceArea->resolve($client) : null;

        return Inertia::render('booking/Create', [
            'services' => Service::query()
                ->where('active', true)
                ->orderBy('sort_order')
                ->get(['id', 'name', 'description', 'category', 'type', 'price_rule', 'slug'])
                ->map(fn (Service $s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'description' => $s->description,
                    'category' => $s->category,
                    'type' => $s->type,
                    'price_rule' => $s->price_rule,
                    'breeds' => $s->breedsAnimal(),
                ]),
            'cattle' => $team->cattle()->orderBy('reg_name')->get(['id', 'reg_name', 'herd_number', 'animal_type'])
                ->map(fn (Cattle $c) => [
                    'id' => $c->id,
                    'name' => $c->reg_name ?: ('#'.$c->herd_number),
                    'animal_type' => $c->animal_type instanceof AnimalType ? $c->animal_type->value : (string) $c->animal_type,
                ]),
            'serviceArea' => $area?->toArray(),
            'onCallKinds' => collect(OnCallKind::cases())->map(fn (OnCallKind $k) => [
                'value' => $k->value,
                'label' => $k->label(),
            ]),
        ]);
    }

    /**
     * Viable Visit 1 dates for a protocol service + animal type (spec §6.2).
     * Never returns a silent empty picker — the result always carries a message.
     */
    public function candidates(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'animal_type' => ['required', 'string'],
        ]);

        $type = AnimalType::tryFrom($validated['animal_type']) ?? AnimalType::Cow;
        $result = $this->scheduler->findCandidates($type);

        return response()->json($result->toArray());
    }

    public function store(StoreBookingRequest $request): RedirectResponse
    {
        $team = $this->currentTeam($request);
        $data = $request->validated();
        $service = Service::findOrFail($data['service_id']);

        try {
            $this->bookings->book(BookingRequest::make(
                team: $team,
                service: $service,
                cattleIds: $data['cattle_ids'],
                actor: $request->user(),
                byStaff: false,
                proposedStart: isset($data['proposed_start']) ? CarbonImmutable::parse($data['proposed_start']) : null,
                onCallKind: isset($data['oncall_kind']) ? OnCallKind::from($data['oncall_kind']) : null,
                heatObservedAt: isset($data['heat_observed_at']) ? CarbonImmutable::parse($data['heat_observed_at']) : null,
                isCash: (bool) ($data['is_cash'] ?? false),
            ));
        } catch (MixedGroupException $e) {
            return back()->withErrors(['cattle_ids' => $e->getMessage()])
                ->with('splitGroups', $e->splitGroups);
        } catch (BreedingEligibilityException|BookingValidationException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return to_route('bookings.index')->with('status', 'Your booking has been submitted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Booking $booking): array
    {
        return [
            'id' => $booking->id,
            'service' => $booking->service?->name,
            'type' => $booking->service?->type,
            'status' => $booking->status,
            'status_label' => $booking->bookingStatus()->label(),
            'requires_review' => $booking->requires_review,
            'is_oncall' => $booking->is_oncall,
            'distance_fee_flag' => $booking->distance_fee_flag,
            'proposed_start' => $booking->proposed_start?->toIso8601String(),
            'computed_windows' => $booking->computed_windows,
            'visit_count' => $booking->visits->count(),
        ];
    }
}
