# Handy Herdsman — Milestone & Issue Plan

**For the coding agent.** This document defines the milestones and the issues within them. Expand each into a full issue (description, acceptance criteria, test notes) and create them in the tracker.

**Read `handy-herdsman-spec.md` first — it is the source of truth.** This plan references spec sections by number; do not re-derive requirements from this document alone. Where this plan and the spec disagree, the spec wins.

## Rules for the agent writing these issues

1. **Every issue must cite its spec section(s).** An issue without a spec reference is underspecified.
2. **§10b (Edge cases & explicit rules) is binding.** It exists specifically to prevent invented behavior. Any issue touching timing, money, permissions, statuses, or notifications must restate the relevant §10b rules in its acceptance criteria.
3. **Do not invent behavior.** If something is genuinely unspecified, write the issue with an explicit `OPEN QUESTION:` block rather than guessing.
4. **Laravel-first.** Before proposing custom code, confirm no official Laravel package covers it (Cashier, Fortify, Spatie, notification channels, scheduler/queue).
5. **Mobile-first is a hard requirement** (spec principles). Every UI issue's acceptance criteria must include mobile behavior — big tap targets, minimal typing, one-handed usability.
6. **Config over code.** Prices, service definitions, timing constants, availability rules, and gestation values are editable data. An issue that hardcodes any of them is wrong.
7. **Tests required** on anything computational: protocol timing, gestation math, fee calculation, inventory decrements, reminder scheduling.
8. **No sales tax logic anywhere** (§10b).

---

## M0 — Foundation (Tessa builds; not for the agent)

Single large issue, built by Tessa before agent work begins. Blocks everything.

- Laravel 13 + Vue starter kit (Inertia, Composition API, TypeScript, Tailwind, shadcn-vue)
- Postgres, Laravel Cloud deploy pipeline
- Spatie `laravel-permission` in **teams mode** (team_id = client), roles: staff/admin, client owner, client member, vet (§4)
- **Full data model migrations** (§6) — every table
- **Config scaffolding** (§7): rate_config, services, service_area_rules, gestation_config, availability_rules/blackout_dates
- Base layouts, mobile-first shell, auth flows

**Agent work starts after M0 merges.**

---

## M1 — Protocol timing engine (BUILD FIRST, blocks much)

The highest-consequence code in the system. A wrong assumption here causes **failed breedings**, not bugs.

### Issue 1.1 — Protocol timing service class
Spec: §3, §10b (Animal type, Timing math)
- V1 → V2 = exactly 7 days; V3 = 60–66h (cow) / 52–56h (heifer) after **V2's actual completed timestamp**
- Recommended time = **window midpoint** (cow ≈63h, heifer ≈54h); full window shown as acceptable range
- **UTC storage, America/Chicago display; offsets added as ABSOLUTE DURATIONS, never calendar arithmetic** (DST must not shift a window)
- Timing constants from config, not literals
- **Heavy test suite required**, including DST boundary crossings both directions

### Issue 1.2 — Animal type rules & eligibility
Spec: §10b (Animal type)
- `animal_type` enum: `heifer`, `cow`, `bull`, `steer` (no `calf`)
- `bull`/`steer` **blocked** from breeding services with clear validation message
- Heifer → cow auto-promotion on first recorded calving (`has_calved`), **not age**
- "Calf"/"weanling" are **computed display labels from `dob`** (thresholds in config), never stored

### Issue 1.3 — V2-late recomputation
Spec: §10b (Timing math)
- On V2 completion, **recompute V3 from actual timestamp**, flag resulting conflicts, notify
- This is the common real-world case and must be automatic

### Issue 1.4 — Gestation / due-date service
Spec: §5.4b
- Config-driven breed table (default 283 days); heifer offset (~1–2 days earlier)
- Always output a **range/estimate**, never a hard date
- Drives calving countdown reminders

---

## M2 — Public marketing site

### Issue 2.1 — Site shell & navigation
Spec: §5.1
- Nav: Home · About · Services · Pricing · **Resources ▾** · Blog · Contact
- Resources dropdown: AI Timing Calculator, Due Date Calculator, Resource Directory, Cattle for Sale
- Mobile-first nav

### Issue 2.2 — Services & pricing pages (rendered from config)
Spec: §5.2, §5.2b, §6 `services`
- Both pages render **from the services table + rate_config** — no hardcoded copy or prices
- Show "not offered" items as trust signals (castration, hoof trimming, vet-licensed work)

### Issue 2.3 — Blog / education engine
Spec: §5.3
- Index, pillar/category pages, posts; SEO fundamentals (slugs, meta, schema, performance)

### Issue 2.4 — Content migration script
Spec: §8, §10b (Content migration)
- Parse Ghost export; migrate **only the listed cattle/AI posts**; leave milk/dairy/donkey/recipe content
- **New publish dates**; **download and re-host images** (do not hotlink old domain); **redirects from old URLs**
- One-time script, not load-bearing after

### Issue 2.5 — Public AI Timing Calculator
Spec: §5.4, depends on 1.1
- Input V1 date/time + animal type → V2/V3 windows, plain-English visit explanation
- **Emailable/textable result = informal proposal** (Resend / sent.dm)
- Prominent homepage placement + CTAs from services/blog (it is the primary lead magnet)

### Issue 2.6 — Public Due Date Calculator
Spec: §5.4b, depends on 1.4

### Issue 2.7 — Resource directory
Spec: §5.8 — staff-curated; SEO-oriented

### Issue 2.8 — FAQ
Spec: §5.1 — includes straw-cost **reference ranges** (explicitly not our prices)

---

## M3 — Clients, teams, onboarding

### Issue 3.1 — Client onboarding + agreement/waiver
Spec: §4, §5.6
- Captures service agreement + liability waiver acceptance (timestamped)
- **Email required** (identity), **phone required** (operational), address → geocoded once

### Issue 3.2 — Client status lifecycle
Spec: §10b (Client status)
- `new` → first booking requires staff review (24h SLA)
- → `active` **automatically after one completed visit** (books freely)
- → `inactive` after **1 year idle** (reverts to approval)

### Issue 3.3 — Team invitations (spouse + vet)
Spec: §4, §10b (Notification recipients)
- Owner invites member (full access) or vet (**read-only, client-selected scope**: profile only, or profile + chosen record types)
- Vets never receive automated notifications

### Issue 3.4 — Communication preferences & consent
Spec: §5.7 (Contact & consent model, 4 categories)
- **`consent_sms` separate and timestamped** — having a phone number ≠ permission to auto-text
- 4 preference categories, each email/text/both, **at least one channel on**; cat.1 defaults both, cat.4 email-only by default
- **Staff override** per client with note; **quiet hours** (~8am–9pm) with **emergency bypass**

---

## M4 — Herd records portal

### Issue 4.1 — Cattle profiles
Spec: §5.5, §10b (Cattle status)
- Full profile; **`status` active/inactive** — marking inactive **stops all pending reminders immediately**
- Nothing hard-deletes; soft-delete only

### Issue 4.2 — Health records
Spec: §5.5, §6, §10b (Records permissions)
- Vaccinations, treatments, nutrition regime, dehorning, preg checks, **body condition**, general
- **Clients CANNOT edit/delete staff-added records**; **Jeff CAN edit client-added records, attributed**

### Issue 4.3 — Photo/media uploads
Spec: §10b (Photos & attachments)
- Client uploads to profiles (for-sale); **Jeff uploads at appointments** (healing progress, condition comparison)
- Attach to animal and optionally the visit; chronological

### Issue 4.4 — Data export
Spec: §10b (Records lifecycle) — clients export anytime; records kept indefinitely

---

## M5 — Semen custody & supplies

### Issue 5.1 — Semen custody ledger
Spec: §5.6, §10b (Semen straws)
- **We do NOT sell straws.** Custody/storage only; `owned_by` always client
- Two sourcing paths: **client-sourced** (needs our intake info; straws must arrive **before protocol**) and **Jeff-sourced** (capture farm/bank, bull, contact, who to pay)
- **$15 receipt fee per shipment**; storage **free year 1 with AI, then $50/yr** (≤10 straws)
- **Straws never expire** — no expiry tracking

### Issue 5.2 — Supply & consumables inventory
Spec: §5.6b
- Jeff's own stock (needles, sleeves, CIDRs, GnRH, PG, meds, tags)
- **Supply usage profiles per service** auto-decrement on completion, manually adjustable
- Low-stock thresholds → alerts; unit costs feed COGS

---

## M6 — Booking & scheduling

### Issue 6.1 — Booking engine core
Spec: §5.5, depends on M1, M3
- Service **type** routes the flow: `protocol` / `oncall` / `standard`
- Book immediately; **first-timers provisional pending staff review (24h SLA)**, status visible to client
- Established clients self-confirm subject to validation

### Issue 6.2 — Protocol scheduling & window validation
Spec: §3, §5.5, §10b, depends on 1.1
- All three visits scheduled up front; validate against working hours, existing bookings, buffer, distance
- **If no valid V1 exists in horizon: say so explicitly** and offer later viable dates — never fail silently or show an empty picker

### Issue 6.3 — Availability, Sunday rule & blackout dates
Spec: §10b (Scheduler & availability)
- Config: working hours, max visits/day, inter-appointment buffer
- **No Sunday mornings ever; Sundays off by default; emergencies always bypass**
- Blackout dates; when added over existing bookings → notify clients, propose alternatives, confirm
- **A protocol past V1 cannot be auto-moved** (windows physiologically fixed) → surfaces to Jeff as a manual decision

### Issue 6.4 — Multi-animal bookings & mixed-group blocking
Spec: §10b (Multi-animal bookings)
- One booking may cover multiple animals for standard/per-head services (per-head pricing, single visit minimum)
- **Mixed cow + heifer on one AI/protocol booking is BLOCKED** (windows ~8h apart); explain and offer to split

### Issue 6.5 — On-call requests (heat / emergency)
Spec: §5.5, §10b (On-call)
- Submitted **in the app** (system of record) and the app **immediately SMS-alerts Jeff** as the pager
- Rationale: standing-heat window ~6–12h, calving emergency minutes-to-hours — notification delivery cannot be the weak link

### Issue 6.6 — Distance fee & service area
Spec: §2, §10b (Money)
- **Distance fee is PER BOOKING/PROTOCOL, not per visit** — one $30 covers all protocol visits
- >15mi flags fee; declined zones blocked/flagged
- Geocode + distance cached at onboarding; Distance Matrix called sparingly (cost control)

### Issue 6.7 — Staff booking & manual override
Spec: §4, §10b (Rescheduling/failed visits)
- Jeff creates bookings himself via the **same protocol-aware scheduler**
- **Jeff can edit any booking, visit, or charge** — this is the escape hatch for all irregular cases
- **Reschedule = new appointment** (no special machinery); **failed/aborted visits still billed** at normal rate

---

## M7 — Appointment completion (keystone — everything downstream depends on it)

### Issue 7.1 — Completion form
Spec: §5.5 (completion form), §10b
- Confirms procedure performed + **actual completed timestamp** (authoritative; V3 recomputes from it)
- **AI visits: select straw/sire used → decrement inventory**; **log wasted straw separately**
- **Body Condition Score (1–9) per animal — REQUIRED on breeding visits** (5–6 target range)
- Supplies consumed, **mileage**, photos, notes → written to animal records
- **Mobile-first, one-handed, gloves-on usable**

### Issue 7.2 — Offline draft persistence (NARROW SCOPE)
Spec: §10b (Offline resilience)
- Completion form **saves local draft as Jeff types; syncs on reconnect**
- **SCOPE LIMIT — do not over-build:** this form ONLY. No offline booking, no offline browsing, no general offline mode, **no sync-conflict resolution engine.** Local draft persistence, not offline-first architecture.

### Issue 7.3 — Preg check async result flow
Spec: §10b (Preg check results)
- Blood: draw at visit → **optional +$15 lab confirmation** (only if client opts in) → **state `pending`** until Jeff records final result
- Final states: `open` / `bred` / `recheck`; **Jeff records results, not clients**
- **Downstream nurture fires ONLY on final results, never `pending`**: `bred` → due date + calving countdown; `open` → rebreed prompt; `recheck` → schedule another
- Palpation (after 4 months) is immediate/definitive, no lab fee

---

## M8 — Payments

### Issue 8.1 — Cashier integration & charge-on-confirm
Spec: §5.6, §10b (Money)
- **Payment method captured at booking** (Cashier `setupIntent`/stored method), **charged when booking reaches `confirmed`** (staff approval or auto-confirm)
- **Cash-in-person option**: flags booking, skips charge, marks owed, Jeff settles manually
- **No Stripe invoicing** (avoids invoice fees); **no tax logic of any kind**
- Laravel Cashier only — no custom Stripe SDK work

### Issue 8.2 — Fee calculation
Spec: §2, §5.2b, §10b (Money)
- Total = plan + additional-cow + **distance fee per booking** + receipt fee + storage, all from rate_config
- **Visit minimum (~$50) applies ONCE PER VISIT, not per service** — bundled per-head tasks share one minimum

---

## M9 — Reminders & nurture engine

### Issue 9.1 — Scheduler/queue infrastructure
Spec: §5.7
- Daily command queries date-offset windows, dispatches via queued jobs
- **Quiet hours** deferral; **emergency bypass**

### Issue 9.2 — Notification channels
Spec: §5.7, §9
- **sent.dm** (SMS) + **Resend** (email) behind Laravel's notification-channel abstraction
- Respect consent, per-category preferences, staff override
- **Owner receives all by default; invited members opt in themselves**

### Issue 9.3 — Transactional reminders
Spec: §5.7
- Insem +21d, +30d, +60d-no-check; calving countdown −4wk/−2wk/−5d/−2d/−1d
- **Stop immediately if the animal is marked inactive**

### Issue 9.4 — Lifecycle & nurture messages
Spec: §5.7 (full catalog)
- Post-calving rebreed loop (**the core recurring-revenue driver**), didn't-settle rebook, seasonal, data-driven (incl. **BCS out of range → nutrition follow-up**), retention, referral
- Nurture pulls from blog content

---

## M10 — Reporting

### Issue 10.1 — Cost & profitability reporting
Spec: §5.6c
- Per-appointment: mileage, supply/drug cost, revenue → profit per appointment / service type / client
- Mileage totals by period (deductions)

### Issue 10.2 — AI success-rate reporting
Spec: §5.6c
- Conception rate by sire, breed, client, cow vs. heifer, protocol type, season, **and BCS at breeding**
- **BCS vs. conception is the headline report** — turns "fat cows don't breed well" into evidence Jeff can show a client
- BCS trend per animal over time
- Keep surface simple and mobile-readable

---

## M11 — Secondary features

### Issue 11.1 — Cattle for sale board
Spec: §5.9 — **clients only**; toggle an existing animal, choose shared fields; staff can also post. Bulletin board, no transactions.

### Issue 11.2 — Dispatch / route view (LAST)
Spec: §5.10
- Day/route view of scheduled visits; out-of-range flags
- **Explicitly NOT a territory optimization engine** — map + distance flag + fee trigger only

---

## Dependency summary

```
M0 Foundation (Tessa)
 └─> M1 Timing engine  ──────────┐
 ├─> M2 Marketing site (2.5, 2.6 need M1)
 ├─> M3 Clients/teams ───────────┤
 ├─> M4 Records portal (needs M3)│
 └─> M5 Semen/supplies           │
                                  v
                            M6 Booking (needs M1+M3+M5)
                                  v
                            M7 Completion (needs M6)
                                  ├─> M8 Payments
                                  ├─> M9 Reminders
                                  └─> M10 Reporting
                            M11 Secondary (M11.2 last)
```

**M2, M3, M4, M5 parallelize** once M0 lands (M1 only gates 2.5/2.6). **M6 is the convergence point.** M7 is the keystone — M8/M9/M10 all consume its data.

---

## Definition of done (applies to every issue)

- Cites its spec section(s); §10b rules restated in acceptance criteria where relevant
- Mobile behavior specified and verified
- Config-driven where the spec requires it (no hardcoded prices, services, timing, availability, gestation values)
- Tests on computational logic (timing, gestation, fees, inventory, reminder scheduling)
- No invented behavior — `OPEN QUESTION:` blocks instead of guesses
- No sales tax logic
