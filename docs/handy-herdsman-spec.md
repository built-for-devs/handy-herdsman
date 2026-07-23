# Handy Herdsman — Product Spec

Cattle AI, herd management, and ranch services. Valley Mills, TX.
Owner-operator: Jeff. Built by Tessa (Laravel), foundation issue by Tessa, remaining issues executed by Devin, code-reviewed by Tessa.

This is the spec. It feeds one big foundation issue → then the rest of the issues for Devin. Read "Build order & dependencies" (§10) before slicing issues.

**Guiding principles**
- **Laravel-first, custom-last.** Always check for an official Laravel package before building anything custom. (Cashier for payments, Cashier Checkout, notification channels for SMS, Spatie for permissions, scheduler/queue for reminders.)
- **Config over code.** Prices, fees, service definitions, and service-area rules are editable data, not hardcoded — no deploy to change a price or add a service.
- **Low maintenance, runs unattended.** Jeff is one person; Tessa doesn't babysit it. Every feature weighed against operational cost.
- **MOBILE-FIRST, NOT MOBILE-RESPONSIVE.** Nearly every user — clients *and* Jeff — is on a phone, often outdoors, often with gloves on, often on poor rural signal. Many clients are farmers who don't use computers but do use a smartphone. Design for the phone first and let desktop inherit. **Clean and simple on the surface, robust underneath.** Big tap targets, minimal typing, forms that survive a dropped connection. If a screen is awkward one-handed in a barn, it's wrong.

---

## 1. What this is

A single Laravel app that is three things at once:

1. **A marketing + SEO site** — services, education/blog, local resource directory, cattle-for-sale board. Attracts clients and ranks for Waco-area cattle searches.
2. **A booking + protocol system** — the AI timing calculator, appointment booking, protocol scheduling that respects the CIDR sync timing windows and drive distance.
3. **A light herd CRM/records portal** — clients log their own cattle records; Jeff logs farm-visit records; reminders keep everyone on the breeding cycle. The free records tool is the hook; the data makes Jeff better at his job and keeps clients coming back.

---

## 2. Brand & facts (locked)

- **Brand:** Handy Herdsman (cattle AI + ranch services). Distinct from **Homestead Herds** (the milk business). Shared owners, separate brands.
- **Location:** 1351 High Prairie Road, Valley Mills, TX 76689. All distance math originates here.
- **Service range:** ~15 miles standard. **Over 15 miles → distance fee** (see rates). Further considered case-by-case; some areas declined outright. Range is **mileage-based**, not drive-time.
- **Operators:** Jeff (AI, ranch services, calving, milking, etc.). He and Tessa milk their own 2 cows daily — this is *not* a scheduling constraint; he can shift around it.
- **Scope of work:** anything legal in Texas without a vet license. **Castration is NOT offered** (case-by-case, off-platform). **Hoof trimming NOT offered** (routed to directory). Everything else in §5.2 is offered.

### Rates (from prior content, corrected & confirmed)

| Item | Price |
|---|---|
| Natural Breeding Plan (client recognizes heat, 1 farm call, 1 cow) | $100 |
| Basic Breeding Plan (CIDR sync, 3 farm calls, up to 2 cows) | $300 |
| — additional cow on Basic Plan | +$100/cow |
| Semen receipt fee (per shipment received) | $15 |
| Semen storage | Free year 1 with AI, then $50/yr (up to 10 straws) |
| Distance fee (over 15 mi from farm) | $30 flat per protocol |

**Not our prices — client pays their source, shown as reference only in FAQ:** straws commonly ~$25–50 (standard) up to $50–300 (minis/Highland/popular); shipping commonly ~$150–250. These are ranges for client expectation-setting, never Handy Herdsman line items.

All prices/fees live in editable **rate config** (§7) — never hardcoded.

---

## 3. The AI protocol — the logic that drives scheduling

The non-trivial core. The calculator and the appointment scheduler share this math.

### CIDR 10-day sync protocol (three visits)
- **Visit 1 — Day 0:** insert CIDR + GnRH. Record exact timestamp.
- **Visit 2 — Day 7 (exactly 7 days after V1):** remove CIDR + PG (prostaglandin). Record exact timestamp.
- **Visit 3 — the AI (timed off Visit 2; cow vs. heifer diverges):**
  - **Cows: 60–66 hours after Visit 2**
  - **Heifers: 52–56 hours after Visit 2**
  - Perform AI + final GnRH.

### Why scheduling is hard (not a date picker)
Visit 3 is a **hard 4–6 hour window pinned to Visit 2's actual timestamp** — it can't float. When a client books a sync protocol:
- All three visits schedule up front.
- Visit 3's window must land in Jeff's working hours and fit around his other Visit 3 windows that week.
- Distance from the farm must fit inside the window for out-of-range clients.
- Heifer windows are tighter/earlier than cow windows — a mixed group can't all breed at the same clock time.

**Calculator's job:** given a proposed Visit 1 date/time + animal type, output exact V2 and V3 windows, flag any window outside working hours, and (in booking) validate against existing bookings + distance, offering only viable Visit 1 dates whose downstream windows all work.

### On-call heat (no sync)
Natural Plan clients **text** when their cow is in **standing heat**; Jeff breeds on the AM/PM rule (standing heat AM → breed PM; PM → next AM). Single time-sensitive farm call, handled as an expedited **on-call/text** request, not a three-visit protocol.

---

## 4. Users, teams, and access model

**Auth: Spatie `laravel-permission` in teams mode. Each client = a team (tenant). team_id scopes all their data.** (Verified current best practice for Laravel 13. Do not build a custom permission engine.)

Roles:
- **Staff / Admin (Jeff, Tessa)** — global; see everything. Jeff can **create bookings himself** (someone reaches out via a friend / off-platform) using the same protocol-aware scheduler, and can **add to any client's cattle records** on their behalf. Manage inventory, approve first-time bookings, edit rate/service config and content.
- **Client Owner** — owns their team. Full access to own herd records, protocols, inventory, payments. Invites members.
- **Client Member (spouse)** — invited by owner. Full access to that team's records (report heat, book, log records).
- **Veterinarian (read-only)** — invited by owner. **Read access to that team's cattle profiles only.** No booking, billing, or inventory writes. So a client's vet can see history when consulted.

### Client lifecycle / booking gate (corrected)
- Clients **book immediately** — no pre-approval to make an appointment. They're ready, we don't stop them.
- **First-time appointments are provisional, reviewed by staff after booking** — to catch too-far / no chute or containment / anything that would endanger Jeff. Booking happens; staff confirms or adjusts.
- Once Jeff has worked with them (staff flags "established"), later bookings **self-confirm** subject to timing/distance validation — no manual gate.
- Onboarding still captures the **service agreement + liability waiver** acceptance (§5.6).

---

## 5. Feature areas

### 5.1 Public marketing site

**Nav:** Home · About · Services · Pricing · **Resources ▾** · Blog · Contact

- **Home** — includes prominent **AI Timing Calculator** placement (it's the main lead magnet; the dropdown alone would bury it).
- **About** — Jeff's story: custom homes, fiber, now cattle. "Local and handy," honest about the vet-license line as a trust signal.
- **Services** — rendered from the services table (§5.2 / §6).
- **Pricing** — pulled from rate config, never stale.
- **Resources ▾** (dropdown):
  - AI Timing Calculator (§5.4)
  - **Due Date / Gestation Calculator** (§5.4b)
  - Resource Directory (§5.8) — local vets, nutritionists, hoof trimmers, other AI techs
  - Cattle for Sale (§5.9) — client listings board
- **Blog / education** — top-level (§5.3). The SEO workhorse and the source nurture emails pull from: index, category/pillar pages, individual posts.
- **FAQ** — migrate + update; straw-cost reference ranges live here.
- **Contact** — includes the **text number** for on-call.

**Calculator placement:** lives under Resources for nav tidiness, but also gets prominent homepage placement plus CTAs from the services and blog pages. It's the primary lead magnet and shouldn't depend on a dropdown for discovery.

### 5.2 Services (all system-defined, all bookable — see §6 `services` table)

Every service below is a row in the services table, with a **type** that routes its booking flow: `protocol` (fires the 3-visit sync scheduler), `oncall` (text-now expedited), or `standard` (single-visit date request). Each carries flags: requires-first-time-review, requires-containment, price/fee rule. The public services page + pricing render from this table. Adding a service later = data entry, not a deploy.

**Breeding & repro**
- AI — Natural Plan (`protocol`-lite: 1 farm call, client recognizes heat)
- AI — Basic/Sync Plan (`protocol`: CIDR 10-day sync, 3 farm calls)
- On-call heat breeding (`oncall`, text-now, standing heat)
- Additional cow add-on (per extra head on a plan)
- Heat detection / standing-heat training
- Estrus sync planning for a small herd
- Genetics / breeding-program consult
- Bull recommendation
- Semen sourcing — Jeff places the order (captures farm/bull/contact/pay info)
- Semen intake / receipt ($15/shipment)
- Semen storage (free year 1 w/ AI, $50/yr after)
- **Pregnancy check** — Jeff performs: **blood test**, or **palpation after 4 months** (~120 days). Non-vet-legal in TX.

**Calving** (each a separate bookable visit — not bundled)
- Pre-calving prep (before: body condition, readiness, colostrum plan)
- Newborn-calf care (after: dip navel, ensure colostrum, newborn basics)
- Calving support / assist (scheduled around due date)
- On-call calving emergency (`oncall`, text-now)

**Cattle handling / ranch-hand**
- Work-your-cattle-for-a-day (sort, move, load)
- Chute-side / restraint help for your vet's visit (Jeff handles cattle; vet does vet work)
- Farm / ranch watch while you travel
- Part-time ranch hand

**Non-vet health tasks (all offered — legal in TX without a license)**
- Vaccinations (all owner-administrable vaccines)
- Deworming / parasite management
- Dehorning / disbudding
- Ear tagging / ID setup
- Body condition scoring + nutrition assessment
- Mineral / feed program setup
- Weighing
- Lameness / hoof **assessment** (assessment only — trimming not offered)

**Consult / onboarding**
- New-owner onboarding ("I just got cattle, help")
- Herd-records setup (done-for-you portal setup)

**Milking**
- Milking / relief milking

**Explicitly NOT offered (state on site as trust signals)**
- Castration (case-by-case, off-platform)
- Hoof trimming (→ directory)
- Anything requiring a vet license

### 5.2b Service pricing (ASSUMPTIONS — Tessa/Jeff to adjust)

Anchored to the known numbers: AI Natural = $100 (1 farm call), AI Sync = $300 (3 calls), +$100/cow, $30 distance over 15mi, $15 receipt, storage free-yr-1-then-$50. From those, one farm call ≈ $75–100 of labor. Everything below is derived to be internally consistent with that; **all placeholder, adjust freely.** Prices live in rate config, editable without deploy.

**Pricing models used:**
- **Labor / ranch work:** day rate, half-day rate, or hourly.
- **Per-head procedures:** per-animal rate + a per-visit minimum (so a 1-cow job is still worth the drive).
- **Consults:** flat, some free as lead-gen hooks.
- Distance fee ($30 >15mi) applies on top of any service, per config.

| Service | Model | Assumed price |
|---|---|---|
| **Breeding & repro** | | |
| AI — Natural Plan | flat, 1 call | **$100** (known) |
| AI — Basic/Sync Plan | flat, 3 calls, ≤2 cows | **$300** (known) |
| — additional cow | per head | **+$100** (known) |
| On-call heat breeding | flat farm call | **$100** (= Natural, single timed call) |
| Heat detection / standing-heat training | flat consult/visit | **$75** |
| Estrus sync planning (small herd) | flat consult | **$100** |
| Genetics / breeding-program consult | flat consult | **$100** |
| Bull recommendation | free w/ any AI booking, else | **$50** |
| Semen sourcing (Jeff orders) | handling fee on top of straw cost | **$25/order** |
| Semen receipt | per shipment | **$15** (known) |
| Semen storage | annual | **free yr1, $50/yr** (known) |
| **Pregnancy check** | | |
| Preg check — blood (draw only) | per head + $50 visit min | **$25/head** |
| — lab confirmation add-on (optional) | per head | **+$15** (definitive result) |
| Preg check — palpation (after 4mo) | per head + $50 visit min | **$40/head** |
| **Calving** | | |
| Pre-calving prep | flat visit | **$75** |
| Newborn-calf care | flat visit | **$75** |
| Calving support / assist (scheduled) | flat + hourly if extended | **$150 flat, then $50/hr after 2hr** |
| On-call calving emergency | premium after-hours | **$200 call-out + $75/hr** |
| **Ranch-hand / handling** | | |
| Work-your-cattle-for-a-day | day / half-day | **$250 day / $150 half** |
| Chute-side / restraint for vet visit | hourly, 1hr min | **$60/hr** |
| Farm / ranch watch while traveling | per visit / per day | **$60/visit or $100/day** |
| Part-time ranch hand | hourly | **$40/hr** |
| **Non-vet health tasks** | | |
| Vaccinations | per head + $50 visit min | **$10/head** (+ vaccine cost) |
| Deworming / parasite mgmt | per head + $50 visit min | **$8/head** (+ product) |
| Dehorning / disbudding | per head + $50 visit min | **$35/head** |
| Ear tagging / ID setup | per head + $50 visit min | **$8/head** |
| Body condition + nutrition assessment | flat visit | **$75** |
| Mineral / feed program setup | flat consult | **$75** |
| Weighing | per head + $50 visit min | **$10/head** |
| Lameness / hoof assessment | flat visit | **$60** |
| **Consult / onboarding** | | |
| New-owner onboarding | flat | **$100** |
| Herd-records setup | free (the hook) | **$0** |
| **Milking** | | |
| Milking / relief milking | per session / per day | **$40/session or $70/day** |

Notes baked into these assumptions:
- Anything requiring a farm visit carries a **~$50 visit minimum** so per-head tasks on a tiny herd still cover the trip. Bundle multiple per-head tasks on one visit → one minimum.
- On-call/emergency work is **premium-priced** (nights/weekends, drop-everything).
- Herd-records setup is **free** on purpose — it's the data hook.
- Bull rec is **free when it leads to a booking**, priced standalone otherwise.

### 5.3 Education / blog (SEO + nurture engine)
- Migrate reusable posts from Ghost export (§8).
- Pillars: heat detection, AI protocol, **nutrition (the #1 AI-failure cause)**, health/testing, calving.
- Built for SEO: clean slugs, meta, schema, fast. Feeds the nurture emails (§5.7).

### 5.4 AI Timing Calculator (public + client)
- **Public:** pick Visit 1 date/time + animal type (cow/heifer) → V2 and V3 windows, visit count, plain-English "what each visit is and when." Educates + qualifies leads.
- **Emailable/textable result = instant informal proposal.** Enter contact → schedule + plan pricing sent via Resend (email) / sent.dm (text). Becomes the basis for the booking + payment.
- **Client version (portal):** same math, tied to their actual cattle, feeds a real booking.

### 5.4b Due Date / Gestation Calculator (public + client)

Second public lead-magnet tool, sits alongside the AI timing calculator under Resources.

- **Input:** breeding date + breed (+ cow/heifer).
- **Output:** expected due date, plus the calving-prep milestone dates (dry-off window, −4wk / −2wk / −5d / −2d / −1d).
- **Math:** cattle gestation averages **283 days**, but varies meaningfully by breed. Use a **config-driven breed table** (editable, no deploy):

| Breed | Avg gestation (days) |
|---|---|
| Holstein | 280 |
| Jersey | 279 |
| Ayrshire | 282 |
| Milking Shorthorn | 283 |
| Angus | 283 |
| Guernsey | 286 |
| Brown Swiss | 288 |
| Hereford | 288 |
| Charolais | 286 |
| Simmental | 287 |
| Gelbvieh | 287 |
| Limousin | 289 |
| Brangus | 290 |
| **Default / unknown / crossbreed** | **283** |

- **Heifers calve ~1–2 days earlier than cows** on average — apply a small config-adjustable offset.
- Bull calves tend to gestate ~1–1.5 days longer than heifer calves, but calf sex isn't known in advance — surface this as a **caveat in the UI copy**, not a calculation.
- **Always display as a range/estimate, never a hard date.** Individual variation is real (±5 days is normal). Copy should say "expected around X" and remind them to watch for calving signs.
- **In-app:** when a pregnancy is confirmed, the due date is computed from breeding date + the animal's breed and stored on the record — this is what drives the calving countdown reminders (§5.7).

### 5.5 Booking / appointments
- Choose a **service** (§5.2); flow follows the service **type**.
- **Protocol bookings:** scheduler validates the CIDR windows (§3) against working hours + existing bookings + distance; offers viable Visit 1 dates whose downstream windows all work.
- **On-call bookings:** expedited text path (standing heat now, calving emergency).
- **Standard bookings:** date/time request for the service.
- Out-of-range (>15 mi) → auto-flags distance fee; far/declined areas → blocked or flagged for manual decision.
- **Book immediately.** First-timers' bookings are **provisional pending staff review**; established clients self-confirm subject to validation.
- **Jeff (staff) can create any booking himself** via the same scheduler.
- Confirmed booking → **payment** (§5.6).

**Appointment completion form (Jeff, mobile-first — one per visit):**
Short form Jeff fills at/after each visit. This is the event that drives inventory, records, and accounting.
- Confirm **procedure performed** + **actual completed timestamp** (this timestamp is what V3 recomputes from — see §10b).
- **On AI visits: select which straw/sire was used** → **decrements semen inventory by 1**.
- **Log a wasted/failed straw** if one is lost in the attempt (decrements separately, flagged as waste — keeps counts honest).
- **Body Condition Score (BCS) per animal — REQUIRED on breeding-related visits.** 1–9 scale (1 = emaciated, 9 = obese; **5–6 is the target breeding range**). *Condition is the single biggest driver of AI success — overweight and underweight cows breed poorly. Capturing it every visit is what makes that visible instead of anecdotal.*
- Optional quick condition notes (lameness, temperament, anything Jeff noticed).
- **Supplies consumed** (§5.6b) and **mileage for the trip** (§5.6c) captured here.
- Notes → written to the animal's records automatically.

### 5.6 Payments, agreement, semen inventory

**Payments — Laravel Cashier + Stripe. No Stripe invoicing** (avoids Stripe invoice fees).
- **Payment method captured at booking** (Cashier `setupIntent` / stored payment method), **charged when the booking reaches `confirmed`** — via staff approval for first-timers, or auto-confirm for established clients.
- **Cash-in-person option:** flags the booking as cash, skips the charge, marks amount owed, Jeff settles manually.
- App computes the total from rate config (plan + additional-cow + $30 distance per booking + $15 receipt + storage as applicable).
- No custom Stripe SDK work. Prebuilt Laravel/Cashier path only.

**Service agreement + liability waiver** — accepted at onboarding; covers Jeff's safety and non-vet-liability boundaries (calving/emergency work especially). Small legal review budgeted.

**Semen inventory = custody/storage ledger (we do NOT sell straws).** Two sourcing paths:
- *Client-sourced (most common):* client orders from their breeder/bank and ships to the farm — they just need our **intake info** (shipping address, tank details, timing). Straws must arrive **before the protocol** (booking prerequisite).
- *Jeff-sourced (sometimes):* client asks Jeff to order — system captures **farm/bank, bull info, contact, who to pay**.
- Fields: sire, breed, straw count, **source**, tank/canister/location, owned_by (always client), storage year-1-free-then-$50/yr. Intake logs a shipment ($15 receipt fee).

### 5.6b Supply & consumables inventory (Jeff's own stock)

Separate from client semen custody — this is **Jeff's working stock**, tracked so he isn't caught short mid-season.

- Track: needles, syringes, sleeves/gloves, lubricant, antiseptic, **CIDRs**, GnRH, prostaglandin, prescription meds, ear tags, etc.
- **Consumption is estimated from the appointment completion form** — each service type has a **default supply usage profile** (e.g. a sync protocol consumes 1 CIDR + 2 GnRH doses + 1 PG dose + sleeves/gloves), auto-decremented on completion, with manual adjustment when actual use differs.
- **Low-stock thresholds per item** → alerts Jeff when it's time to reorder.
- **Unit cost per item** stored — this is what feeds cost-of-goods reporting (§5.6c).
- Prescription meds flagged separately (sourcing/expiry matter).
- Deliberately lightweight: rough estimates that stay directionally right beat precise counts nobody maintains.

### 5.6c Cost tracking, profitability & success reporting

The data the business actually runs on. All derived from records already captured — no separate bookkeeping.

**Per-appointment cost capture:**
- **Mileage per appointment** (from the completion form + cached client distance) → for accounting/tax deduction.
- **Supply/drug cost per appointment** (from the supply usage profile × unit costs).
- Revenue (from the booking's charged amount).

**Reporting:**
- **Profit per appointment / per service type / per client** — revenue minus supplies, drugs, and mileage cost.
- **Which services are actually worth doing** (a $75 service that burns $40 of supplies and 40 miles is worth knowing about).
- **AI success rates** — conception rate by: overall, sire, breed, client, cow vs. heifer, protocol type (sync vs. natural heat), season, **and Body Condition Score at time of breeding**. *This is genuinely valuable — it tells Jeff which bulls settle, which clients' nutrition is failing, and what to change.*
- **BCS vs. conception rate** — the headline report. Turns "fat cows don't breed well" from a hunch into evidence Jeff can show a client ("cows at BCS 7+ settled X% vs. Y% at 5–6"). This is the most persuasive nutrition argument he has, and it's the whole reason to capture BCS every visit.
- **BCS trend per animal over time** — is this client's herd improving or sliding?
- **Client value over time** — revenue per client, repeat rate, dormancy.
- **Mileage totals by period** for tax.

Keep the reporting surface simple and mobile-readable — a few clear numbers beat a dashboard nobody opens.

### 5.7 Reminder & nurture engine (recurring-revenue driver)

Laravel scheduler + queued jobs; a daily command queries date-offset windows and dispatches via **sent.dm (SMS)** and **Resend (email)**, both behind Laravel's notification-channel abstraction.

#### Contact & consent model
- **`email`** — REQUIRED. Account identity / login. Laravel starter-kit auth is email-based; email verification, password reset, and team invites all run on it. (Laravel-first: don't fight the framework by making phone the identity.)
- **`phone`** — REQUIRED. Operational contact — Jeff drives to their property and may need a last-minute answer. Required regardless of messaging preferences.
- **`consent_sms`** — SEPARATE explicit opt-in, timestamped. Having their number is not permission to send automated texts. **Jeff calling/texting them as a human ≠ the app auto-texting them.** TCPA also treats *transactional* SMS (your appointment is at 3) differently from *promotional* SMS (breeding season's here, book now) — consent for one isn't consent for the other, so the consent record captures which categories they agreed to.

#### Channel preferences — 4 categories (not one global toggle, not per-message)
~25 message types is too many to toggle individually; one global switch can't express "text me about heat, email me the newsletter." So preferences group into four categories, each with its own channel choice (email / text / both). **At least one channel must stay on per category** — clients can't zero out everything.

| # | Category | What's in it | Default |
|---|---|---|---|
| 1 | **Time-sensitive / act-now** | heat watch (21d), calving countdown, emergency confirmations | **Both** (a missed message costs a calf or a breeding) |
| 2 | **Appointment & booking** | confirmations, reminders, changes, payment | Email (text optional) |
| 3 | **Herd follow-ups** | preg check due, rebreed window, storage renewal, records-based nudges | Email |
| 4 | **Seasonal & content** | nutrition check-ins, blog sends, referral asks, anniversary | **Email only** unless explicitly opted in (TCPA-safe default for promotional) |

- **Staff override:** Jeff can override a client's channel prefs on their record ("never checks email — text him everything"), with a note explaining why. The client's own settings stay intact underneath; the override wins.
- **Quiet hours:** global config (default ~8am–9pm). Non-urgent messages queue to the next allowed window so the 2-days-out calving reminder doesn't fire at 4am. **On-call/emergency confirmations bypass quiet hours** — a 2am calving message goes.

#### The full message catalog

**Around a breeding (per cow bred)** — *cat. 1/3*
- Insem + 21d — watch for return-to-heat; did she settle?
- Insem + 30d — book a pregnancy check.
- Preg check **open** (didn't settle) — rebook AI now, don't lose the season.
- Preg check **bred** — congrats + due date + "we'll remind you as it approaches."
- Insem + 60d, no preg check booked — nudge to schedule.

**Around calving (per bred cow)** — *cat. 1/3*
- Due − 4wk / − 2wk / − 5d / − 2d / − 1d — calving countdown + offer assistance.
- Due date passed, no calving logged — "did she calve? how'd it go?" (care check + data capture)
- Post-calving + ~30d — plan her rebreed; here's the timeline.
- Post-calving + ~45–60d — direct rebreed booking prompt. **This is the core recurring-revenue loop.**

**Seasonal / herd-wide** — *cat. 4*
- Pre-breeding-season (spring/fall) — get on the calendar.
- Pre-winter — nutrition/mineral check-in.
- Annual — vaccination/booster reminder (if last year's was logged).
- Fly/parasite season — deworming nudge.

**Data-driven (from their own logged records)** — *cat. 3*
- No minerals/salt in nutrition regime — "#1 reason AI fails, let's fix it."
- Body condition flagged over/underweight at last visit — follow-up.
- Semen storage nearing 1yr — renews at $50, or want to use these straws?
- Straws unused 6mo+ — ready to breed with these?

**Relationship / retention** — *cat. 4*
- Dormant client (no activity N months) — soft re-engagement tied to a blog piece.
- After any completed visit — "how'd it go?" + review/referral ask.
- New blog post in a pillar they follow — occasional content send.
- Anniversary of first service — year in review.

**Referral / growth** — *cat. 4*
- After successful calving or confirmed pregnancy — referral ask.
- Client lists a cow for sale — "your listing is live" + reshare prompt.

Recipient is **the client** by default; staff optionally notified on the closest calving-window alerts. Nurture emails pull from the blog so content does double duty.

### 5.8 Local resource directory (SEO play)
- Preferred vets, nutritionists, **hoof trimmers**, other AI techs, etc. Helps clients; mainly ranks for "cattle [service] near Waco."
- Lightweight, staff-curated: name, category, area, link.

### 5.9 Cattle-for-sale board
- **Clients only** can list (no public listings). Client toggles an existing herd animal to "for sale," picks which profile fields are shared → light public listing.
- Staff can also post cattle Jeff knows others want to sell.
- Bulletin board, not a marketplace; no transactions.

### 5.10 Dispatch / routing (lightest viable — do last)
- Geocode each client address once at onboarding (cache lat/lng + distance from farm).
- Day/route view of scheduled visits so Jeff isn't crisscrossing.
- Auto-flag out-of-range clients + surface the distance fee at booking.
- **NOT** a territory-day optimization engine at launch — map + distance flag + fee trigger is enough until density justifies more.

---

## 6. Data model (the spine)

- **users** — auth; role via Spatie.
- **teams** — one per client (tenant boundary). Owner user_id.
- **team_invitations** — email, role (member/vet), token, status, **vet_scope (json — client-selected: profile only, or profile + chosen record types)**.
- **clients** — team_id, contact, **status (new/active/inactive — gates self-booking, §10b)**, **email (required, identity)**, **phone (required, operational)**, address, lat/lng, cached_distance_miles, in_range, **consent_sms (timestamped, per-category)**, **channel_prefs (json — 4 categories × email/text/both)**, **staff_channel_override (json + note)**, status (prospect/established/dormant), agreement_signed_at, waiver_signed_at.
- **cattle** — team_id, reg_name, herd_number, dob, breed, **animal_type (heifer/cow/bull/steer)**, **has_calved**, **status (active/inactive)**, a2a2, for_sale, for_sale_shared_fields (json), notes. Animal type drives protocol timing + breeding-service eligibility (§10b).
- **services** — name, description, category, **type (protocol/oncall/standard)**, price/fee rule, requires_first_time_review, requires_containment, active. **Source of truth for services page + pricing + booking flow.**
- **protocols** — cattle_id (or group), service_id, plan_type, visit1_at, visit2_at, visit3_window_start/end (computed by animal type), status.
- **visits** — team_id, cattle_id, protocol_id (nullable), type, scheduled_at, completed_at, staff notes, mileage, fee_applied.
- **health_records** — team_id, cattle_id, type (vaccination/treatment/nutrition/general/dehorning/preg_check/**body_condition**/etc.), payload, added_by, added_role. BCS entries store score (1–9) + date + visit_id so condition can be trended and cross-referenced against conception outcomes.
- **semen_inventory** — team_id, sire, breed, straws_count, source, source_contact, location, owned_by (client), storage_start, storage_free_until, receipt_fee_charged.
- **bookings** — team_id, service_id, cattle refs, proposed_start, computed_windows (json), distance_fee_flag, status (provisional/confirmed/declined), requires_review, is_oncall.
- **payments** — team_id, booking_id, line_items (json), total, **method (card/cash)**, stripe_payment_method_id, charged_at, status. (Cashier-managed.)
- **reminders** — polymorphic (protocol/visit/cattle/client), fire_at, **category (1–4)**, channel (sms/email/both, resolved from prefs + override), template, recipient_role, **quiet_hours_deferred_to**, status.
- **preg_checks** — cattle_id, visit_id, method (blood/palpation), lab_requested (bool), **state (pending/open/bred/recheck)**, result_recorded_at, recorded_by. Final state gates downstream nurture (§10b).
- **media** — polymorphic (cattle/visit), uploaded_by, uploaded_role, file, caption, taken_at. Client + Jeff photo uploads.
- **directory_entries** — category, name, area, url, notes (staff-curated).
- **posts / categories** — blog.
- **supplies** — item, category, unit, on_hand, low_stock_threshold, unit_cost, is_prescription, notes. (Jeff's own stock, §5.6b.)
- **supply_usage_profiles** — service_id → default consumables + quantities; auto-decrements on appointment completion, manually adjustable.
- **visit_completions** — visit_id, completed_at (authoritative timestamp), procedure_confirmed, semen_inventory_id used, straws_used, straws_wasted, supplies_used (json), mileage, notes. **This record drives inventory, cattle records, and COGS.**
- **rate_config** — editable pricing + fee rules (plan prices, per-cow add-on, distance threshold/amount, receipt fee, storage fee, free-year rule).
- **service_area_rules** — mileage thresholds, fee tiers, declined zones.
- **gestation_config** — breed → average gestation days (default 283), heifer offset. Drives the due-date calculator + calving reminders (§5.4b).
- **availability_rules / blackout_dates** — working hours, Sunday rule, max visits/day, buffer, blackout ranges (§10b).

Reminders, dispatch, portal, calculator are **queries against these** — not separate subsystems.

---

## 7. Config, not code (maintenance guardrails)
- **Rate table, service definitions, and service-area rules** = editable admin/config, never hardcoded. No deploy to change a price, add a service, or adjust the range.
- **Protocol timing constants** (V1→V2 = 7d, cow 60–66h, heifer 52–56h, Jeff's working hours) in one config file — one edit if guidance shifts.
- **SMS provider (sent.dm)** behind Laravel's notification channel — swappable via config.
- **Jeff's availability** (working hours, max visits/day) as config the scheduler reads.

---

## 8. Content migration (from Ghost export)
Source: `homestead-herds_ghost_2025-08-02-18-34-25.json` (87 posts).

**Migrate to Handy Herdsman** (cattle/AI education): How to Recognize When Your Cow is in Heat · Feed Your Cow Right: Preparing for AI Success · Understanding Your AI Protocol · Live Bulls vs. Artificial Insemination · Cattle AI Services / Cattle Breeding via AI Services (→ services source) · Ketosis in Milk Cows · Essential Cattle Health Testing Guide for Central Texas · FAQ (AI portions).

**Leave with Homestead Herds:** raw-milk everything, recipes, mini-donkey listings, individual cattle sale posts, herd-share/farm-club.

One-time migration script parses the export, pulls the listed posts (strip Ghost/Koenig markup → clean markdown/HTML), seeds the new blog. Not load-bearing after.

---

## 9. Tech stack
- **Laravel 13**, **Vue starter kit** (Inertia, Composition API, TypeScript, Tailwind, shadcn-vue) — the official "native" Laravel frontend path.
- **Postgres.**
- **Laravel Cloud** hosting (same as Selah).
- **Spatie laravel-permission** (teams mode) — auth/roles/tenancy.
- **Laravel Cashier + Stripe** — stored payment method charged on confirm; no Stripe invoicing; no tax handling.
- **Queue + scheduler** — reminders.
- **sent.dm** — SMS (behind notification channel).
- **Resend** — email (Laravel first-party mail driver); transactional + nurture.
- **Google Geocoding + Distance Matrix** — cached, called at onboarding + booking review only (cost control).

**Laravel-first rule:** before any custom build, confirm no official Laravel package covers it.

---

## 10. Build order & dependencies

**Issue 1 — Foundation (Tessa builds, one big issue):**
Laravel 13 + Vue kit, Postgres, Spatie teams auth, **full data model (§6) + rate/service/area config**, base layouts, deploy pipeline. Blocks everything.

**Then — parallelizable tracks for Devin (any order relative to each other):**
- **A. Marketing site + content migration** (§5.1, 5.2 render, 5.3, 5.8, §8)
- **B. Public calculator** (§5.4) — needs the protocol-timing service class
- **C. Client onboarding + agreement/waiver + team invites** (§4)
- **D. Herd records portal** (§5.5 records, cattle profiles)
- **E. Semen inventory** (§5.6)

**Protocol-timing service class** — the shared §3 math as a tested service. Needed by B and by booking. Build early (can be part of Issue 1 or the first Devin issue).

**Later (depend on the above):**
- **Booking** (§5.5) — needs timing class + onboarding + scheduler config + services table
- **Payments** (§5.6) — needs booking (Cashier Checkout)
- **Reminder/nurture engine** (§5.7) — needs protocols/records producing dates + queue
- **For-sale board** (§5.9) — needs cattle profiles
- **Dispatch view** (§5.10) — needs booking + geocoded clients. Last.

**Slicing rule:** Issue 1 is strictly first. The timing class is the other early must. Everything else is a dependency graph, not a straight line — Devin parallelizes the rest.

---

## 10b. Edge cases & explicit rules (anti-assumption section)

**Written specifically so a coding agent does not invent behavior.** Where the spec is silent, an agent guesses; these are the answers. Nothing here is optional.

### Animal type & protocol eligibility
- **`animal_type` is an enum on `cattle`:** `heifer`, `cow`, `bull`, `steer`. (No `calf` — calf is an age stage, not a type. A young female is a heifer, a young male is a bull. Age comes from `dob` if ever needed.)
- **"Calf" is a COMPUTED DISPLAY LABEL from `dob`, never stored.** Store the fact, derive the label — it stays correct as the animal ages with zero maintenance, unlike a stored type that goes stale. Suggested thresholds (config, adjustable):
  - under 6 months → "calf" (e.g. displays as "heifer calf" / "bull calf")
  - 6–12 months → "weanling / yearling"
  - over 12 months → plain `animal_type` (heifer / cow / bull / steer)
  - UI example: `Bessie — heifer calf, 4mo` while the DB holds only `animal_type: heifer` + `dob`.
  - Sorting/filtering by age stage is likewise computed from `dob`, not a stored column.
- **Protocol timing keys off it:** heifer = 52–56h post-V2; cow = 60–66h post-V2.
- **`bull` and `steer` are invalid for any breeding service** — the system BLOCKS booking a breeding/AI service against them with a clear validation message.
- **Heifer → cow transition is `has_calved`, NOT age.** A heifer becomes a cow when she calves. The system auto-promotes `animal_type` on her first recorded calving event. (Age is wrong: a 3-year-old who has never calved is still physiologically a heifer, and the timing window follows physiology.)
- A cow cannot transition mid-protocol — she can't calve during a 10-day sync she's being bred in. Non-issue, no handling needed.

### Timing math (highest-risk area — a wrong assumption here causes FAILED BREEDINGS, not bugs)
- **Store all timestamps in UTC; compute and display in `America/Chicago`.**
- **Hour offsets (60–66h, 52–56h, 7d) MUST be added as absolute durations, never calendar arithmetic.** A DST boundary must not shift a window. This is the exact bug that silently ruins a breeding.
- **Recommended AI time = midpoint of the window** (cows ≈63h, heifers ≈54h). Display the full window as the acceptable range; schedule to midpoint by default; allow manual adjustment *within* the window only. Midpoint maximizes buffer on both sides if Jeff runs late.
- **Visit 3 is ALWAYS recomputed from Visit 2's actual completed timestamp**, never the planned one. If V2 runs 2 hours late, V3 shifts 2 hours. On V2 completion the system recomputes V3, flags any resulting conflict, and notifies. **This is the common real-world case — it must be automatic.**
- **Mixed cow/heifer group on one farm = two separate V3 windows** (~8h apart) = two visits. Surface this clearly at booking so the client isn't surprised.

### Scheduler & availability
- **Working hours, max visits/day, and inter-appointment buffer are config values** (§7) the scheduler reads — not hardcoded. Jeff sets them; travel time between appointments is non-zero and must be accounted for.
- If **no valid V1 date exists** in the search horizon, the system says so explicitly and offers the next viable dates beyond it — it must not fail silently or return an empty picker.
- **Offline / poor-signal resilience (narrow scope — do NOT over-build):** the **appointment completion form** must persist as a **local draft** (saved as Jeff types) and **sync when connectivity returns**. Rural signal is unreliable and this form is filled in barns and pastures; losing it loses inventory, records, and billing data.
  - **Scope limit:** this applies to the completion form ONLY. **The rest of the app assumes connectivity** — no offline booking, no offline record browsing, no general offline mode, no sync-conflict resolution engine. This is local draft persistence, not offline-first architecture.
- **Sunday rule: no Sunday-morning appointments, ever. Sundays are off by default** — but **on-call emergencies are always allowed** (they bypass availability entirely).
- **Blackout dates:** Jeff can mark days/ranges unavailable (travel, big jobs, personal). The scheduler must not book into them. Emergencies still bypass.
- **When a blackout is added over EXISTING bookings:** the system notifies affected clients, proposes alternative slots that still satisfy protocol timing, and asks them to confirm. **A protocol already past Visit 1 cannot simply be moved** — its V2/V3 windows are physiologically fixed, so those surface to Jeff as conflicts requiring a manual decision rather than auto-rebooking.

### Preg check results (async — lab)
- **Blood preg check is a two-stage event.** Jeff draws blood at the visit; the **lab result is the definitive answer** and arrives later.
- **Optional lab confirmation: +$15/head**, only charged if the client opts for it. Base price is the draw.
- Preg check record has states: **`pending`** (sample taken, awaiting result) → **`open` / `bred` / `recheck`** (final).
- **Jeff records the result** when the lab reports back. Clients do not enter lab results.
- **Downstream nurture only fires on a FINAL result**, never on `pending`:
  - `bred` → due date computed (§5.4b), calving countdown scheduled.
  - `open` → rebreed prompt fires.
  - `recheck` → schedule another check.
- Palpation preg checks (after 4 months) are **immediate/definitive at the visit** — no pending state, no lab fee.

### Rescheduling, failed visits, and staff override
- **Reschedule = create a new appointment.** No special reschedule machinery. **Jeff can edit any booking, visit, or charge directly** — manual override is the escape hatch for every irregular situation.
- **Failed/aborted visit** (cow won't load, no chute, not contained, Jeff arrives and can't work): **still billed at the normal visit rate** ($100 for a standard farm call). Jeff edits the situation as needed. **No automated trip-fee logic** — his judgment, his override.
- Rationale: rather than modeling every irregular case, give Jeff full edit authority and keep the system simple.

### Cattle status
- **Cattle are `active` or `inactive`** (covers sold, deceased, culled, or simply not in the program — no separate states needed).
- **Marking a cow `inactive` STOPS all pending reminders for that animal immediately.** Nothing should keep texting about a cow that's gone.
- Inactive animals stay in records and history — never deleted.

### Client status (gates self-booking)
- **`new`** → first appointment requires staff review (§10b SLA: 24h).
- **`active`** → **promoted automatically after ONE completed visit.** Books freely, no approval.
- **`inactive`** → after **1 year of no activity**, reverts to requiring approval on the next booking.

### Photos & attachments
- **Clients can upload photos** to their cattle profiles — useful when listing an animal for sale (§5.9).
- **Jeff can upload photos at an appointment** via the completion form — e.g. tracking healing after dehorning, documenting condition for BCS comparison, or recording an injury.
- Photos attach to the animal (and optionally to the specific visit) so they're chronological and comparable over time.

### Semen straws
- **Straws do not expire.** Stored in liquid nitrogen, viability is indefinite — no expiry tracking, no shelf-life warnings.

### Multi-animal bookings
- **One booking CAN cover multiple animals** for standard/per-head services (e.g. vaccinating 12 head in one visit) — pricing applies per head with a single visit minimum.
- **For AI/protocol services, mixed cow + heifer on the same booking is BLOCKED** at the timing step: their V3 windows are ~8 hours apart and cannot be performed in one visit. The system explains why and offers to split them into separate bookings/visits.
- Same-type groups (all cows, or all heifers) can share a protocol booking and one V3 window.

### Notification recipients
- **The team OWNER (primary contact) receives all client notifications by default.**
- **Invited members (spouse) receive nothing until they opt in** — they turn on the categories they want in their own preferences.
- Vets never receive automated client notifications.

### Bookings, cancellation, on-call
- **Protocol bookings are cancellable before Visit 1 only.** Once V1 occurs (CIDR inserted), the protocol is locked and the client owes the full protocol. Pre-V1 cancellation is free.
- **First-time booking review SLA: within 24 hours.** Client sees **provisional** status clearly until reviewed.
- **On-call requests (standing heat / calving emergency):** submitted **in the app** (system of record, keeps clients returning), and the app **immediately SMS-alerts Jeff** as the pager. He is alerted by text, responds in the app. *Rationale: a standing-heat window is ~6–12 hours and a calving emergency is minutes-to-hours — notification delivery cannot be the weak link.*

### Money
- **Payment is captured at booking as a stored payment method, and CHARGED when the booking reaches `confirmed`** — whether confirmed via staff approval (first-timers) or auto-confirm (established clients). Implemented with **Cashier's `setupIntent` / stored payment method**, not one-shot Checkout.
- **Cash-in-person is a booking option:** flags the booking as cash, skips the charge, marks amount owed, settled manually by Jeff.
- **Distance fee is PER BOOKING/PROTOCOL, not per visit** — one $30 covers all three protocol visits.
- **Visit minimum (~$50) applies ONCE PER VISIT, not per service.** Multiple per-head tasks bundled into one trip = one minimum.
- **No sales tax handling. Do not implement Stripe Tax or any tax calculation.** Prices are charged as listed. (Business decision — tax obligations, if any, are handled outside the system.)

### Records, permissions, data lifecycle
- **Clients CANNOT edit or delete staff-added records.** Jeff's farm records are his professional record — audit integrity.
- **Jeff CAN edit client-added records**, with changes attributed to him.
- **Nothing hard-deletes. Everything soft-deletes.**
- **Vet read-only scope is CLIENT-CONTROLLED per invitation:** the client chooses whether the vet sees profile only, or profile + selected record types. (Resolves the prior §4 / §5.2 conflict — client decides, not the system.)
- **Client records are kept indefinitely.** Clients can **export** their data at any time. Leaving does not delete anything.
- **After 1 year of account inactivity, a returning client's next booking requires staff approval again** (conditions change — moved, different herd, new containment setup).
- If a cow is sold to another client on the platform, her history does **not** auto-transfer — treat as a new animal for the new owner unless staff explicitly migrates it.

### Content migration
- **Migrated posts publish with NEW dates** (look fresh).
- **Add redirects from the old Homestead Herds URLs** for any migrated post that had traffic, to preserve link equity.
- **Images:** the Ghost export references images on the old site. The migration script must **download and re-host** them in the new app — do not hotlink the old domain.

---

## 11. Settled decisions (all resolved)
1. **Range:** 15 miles, mileage-based; over 15 → $30 distance fee; further case-by-case; some areas declined.
2. **Pricing:** table in §2 confirmed current.
3. **Payments:** Cashier + Stripe Checkout, `checkoutCharge` for dynamic totals; no Stripe invoicing.
4. **Semen:** custody ledger, not sales; two sourcing paths; $15 receipt/shipment; storage free yr 1 then $50/yr; straws arrive before protocol.
5. **SMS:** sent.dm. **Email:** Resend.
6. **Booking gate:** book immediately; first-timers provisional pending staff review; established self-confirm.
7. **Jeff admin:** can create bookings + add to client records.
8. **Vet access:** read-only on cattle profiles.
9. **Services:** all system-defined/bookable via services table; castration + hoof trimming not offered; preg checks (blood/palpation-after-4mo), all vaccinations, dehorning/disbudding all IN.
10. **Milking:** not a scheduling constraint.
11. **Contact model:** email required (identity/login), phone required (operational — Jeff drives out), SMS consent separate + timestamped per category.
12. **Channel prefs:** 4 categories (time-sensitive / booking / herd follow-ups / seasonal-content), each email/text/both, at least one on; cat.1 defaults both, cat.4 email-only by default. Staff override per client. Quiet hours ~8am–9pm, emergencies bypass.
13. **Pricing:** full catalog priced in §5.2b as assumptions off known numbers — adjust freely, lives in rate config.
14. **Mobile-first** is a hard requirement, not a nicety — clients and Jeff are on phones in the field.
15. **Appointment completion form** drives everything downstream: actual timestamp, straw used/wasted, supplies consumed, mileage.
16. **Due-date calculator** added as a second public tool; breed-table-driven, 283-day default.
17. **Availability:** no Sunday mornings, Sundays off by default, emergencies always allowed, blackout dates with client re-book flow.
18. **Multi-animal bookings** allowed; mixed cow/heifer AI blocked (windows ~8h apart).
19. **Notifications:** owner gets all by default; invited members opt in themselves.
20. **Supplies + COGS:** Jeff's stock tracked with low-stock alerts; mileage and drug costs per appointment feed profit + AI success-rate reporting.
21. **Preg checks:** blood = draw + optional **+$15 lab confirmation** (definitive), async `pending` → final state recorded by Jeff; palpation is immediate. Nurture fires only on final results.
22. **Reschedule = new appointment; Jeff has full manual override** on bookings/visits/charges. Failed visits still billed at normal rate.
23. **Cattle active/inactive** (inactive stops reminders). **Clients new → active after one completed visit → inactive after 1 year idle.**
24. **Photos:** clients upload to profiles (for-sale), Jeff uploads at appointments (healing, condition).
25. **Semen straws never expire** — no expiry tracking.
26. **Completion form persists local drafts + syncs** — narrow scope, that form only; rest of app assumes connectivity. Not offline-first architecture.
27. **Body Condition Score (1–9)** captured per animal on breeding visits — required field. Drives the BCS-vs-conception report and out-of-range nutrition nurture. Condition is the biggest lever on AI success.

*(Nothing outstanding — ready to slice into Devin issues.)*
