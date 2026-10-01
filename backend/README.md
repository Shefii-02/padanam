# Padanam API (Laravel 11, modular)

One MySQL database shared by: this API, the Node realtime server, the React admin and the Flutter app.

## Structure
```
app/Core        ApiResponse, base DTO, BaseRepository, Money, Settings, PermissionCatalog, middleware, providers
app/Models      all Eloquent models (shared by modules)
app/Modules/<Module>/
    routes.php            auto-loaded (routes/api.php)
    Http/Controllers      thin: validate → DTO → Service → Resource
    Http/Requests         validation
    DTOs                  typed input (PATCH sends only changed fields)
    Services              business rules
    Repositories          queries, filters, search, sort, pagination
    Resources             JSON output
    Policies              auto-registered (CoursePolicy → App\Models\Course)
    Console               auto-registered artisan commands
    events.php            auto-loaded event listeners
```
Every response: `{ "success": true, "message": "...", "data": ..., "meta": { "pagination": {...} } }`
Errors: `{ "success": false, "message": "readable text", "errors": {...} }` with 401/403/404/422.

## Modules in this build
| Module | What it does |
|---|---|
| Auth | OTP login (app), email+password (panel), JWT, device + FCM token, `/auth/me` returns roles + permissions |
| Users | Student profile + 9-step setup, admin students list (active/inactive, paid, category, course, batch, expiring filters), student 360, CSV export (audited), staff CRUD with course assignment, block/unblock |
| Roles | Roles CRUD, permission matrix toggle, custom roles |
| Catalog | Category tree (parent/sub), exams |
| Courses | Courses (feature switches, type presets), batches (price, validity, seats, clone with schedule), folders (nested, per-batch, unlock date), content (YouTube/AWS video, PDF, notes ml/en, links, tests, articles, live), staff/teacher access without purchase, store, my courses, tab + folder browsing, lock reasons, progress |
| LiveClasses | Schedule (single or weekly repeat), Google Meet → YouTube go-live, end → recording auto-added to the folder, attendance, 5-min class alert ring |
| Notifications | FCM push with channels (class_alert = alarm), inbox, per-channel preferences, new-content alerts |
| QuestionBank | Nested folders, colour labels (merge duplicates), questions in many languages with images, single/multi/true-false/numeric, bulk move/label/delete, CSV + Word (.docx) import with preview, duplicate check, error report, "add a translation" import |
| Tests | Test builder with sections (own marks, negative marks, time, English-only), hand pick or auto-pick by label/folder/difficulty mix, publish checks, duplicate; exam engine with server clock, sectional timing, resume, auto-submit, language switch; scoring (multi-answer, numeric), rank + percentile on first attempt, section analysis, solutions, leaderboard, per-question accuracy; OMR: printable sheet PDF, answer key, scanner CSV import, key-in, student photo upload |
| Daily | Daily quiz calendar (plan a day or a range from a label, auto-built at 6 AM, streaks), study-plan templates per course/batch → dated tasks for each student + personal tasks |
| Articles | Current affairs / articles with draft, schedule, free or premium |
| Doubts | Students ask (text + photo), assigned teachers answer, student notified |
| Commerce | Checkout quote with offers, Razorpay (app checkout + signature verify), PhonePe (pay page + status check), WhatsApp payment links (auto-creates the student and enrolls on payment), manual admission (cash/bank/UPI/scholarship), idempotent webhooks, refunds (optionally remove access), GST invoices (PDF, share link, WhatsApp), enrollments (seats, renew, extend, move batch, remove), advanced coupons, revenue share (ledger, payouts, balance) |
| Campaigns | Push composer: all installs, all students, course, batch, exam interest, role, chosen people, inactive N days, leads, expiring; preview count, send now or schedule, open tracking, editable templates |
| Leads | Auto-captured from demo videos, offers, course pages, free tests; interest score; assign, status, call/WhatsApp log, CSV export |
| Reports | Dashboard (teachers see no money), revenue by course/batch/month/gateway/coupon with refunds, active/inactive monitoring, performance ranking, CSV exports |
| Chat | Rooms list with unread counts, history, members, uploads, direct chats checked against the admin rules matrix (allow / deny / shared batch / support only), groups & broadcast channels, settings (who can send, media, links, slow mode, member invites, voice), invite links with join modes (open / approval / invite-only), join requests, roles, mute, remove, reports & moderation, internal push endpoint for Node |
| Deep links | `padanam.app/j/{code}` (group invite), `/c/{slug}` (course), `/a/{slug}`, `/paid/{order}` – open the app when installed (assetlinks.json + apple-app-site-association), otherwise a small page with store buttons |
| System | App version check (flexible / immediate update), settings, enrollment expiry, daily stats |

Next modules (same pattern): Commerce (Razorpay, PhonePe, payment links, coupons, invoices, revenue share), Campaigns, Chat REST + Node realtime, Leads, Reports.

## Run
```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret            # copy JWT_SECRET to the Node realtime .env too
# create MySQL database "padanam", then:
php artisan migrate --seed
php artisan storage:link
php artisan serve
php artisan queue:work            # notifications
php artisan schedule:work         # class alerts every minute
```

## Demo logins (local only)
| Who | Login |
|---|---|
| Super admin | admin@padanam.app / password |
| Staff | staff@padanam.app / password |
| Teacher | suresh@padanam.app / password |
| Student (app) | 9876543210, OTP 123456 |

## Quick test
```bash
curl -X POST localhost:8000/api/v1/auth/otp/send -d phone=9876543210
curl -X POST localhost:8000/api/v1/auth/otp/verify -d phone=9876543210 -d otp=123456
curl localhost:8000/api/v1/public/courses
curl -H "Authorization: Bearer <token>" "localhost:8000/api/v1/app/courses/1/browse?tab=classes"
```

## Key endpoints
Public: `GET public/categories`, `GET public/courses`, `GET public/courses/{slug}`, `GET public/app-version?platform=android&build=10`
App: `app/profile`, `app/profile/setup`, `app/my-courses`, `app/courses/{id}/browse?tab=&folder_id=`, `app/contents/{id}`, `app/contents/{id}/progress`, `app/courses/{id}/class-alerts`, `app/live-classes`, `app/live-classes/{id}/join`, `app/notifications`
Admin: `admin/students`, `admin/staff`, `admin/roles`, `admin/permissions`, `admin/categories`, `admin/courses`, `admin/courses/{id}/batches|folders|contents`, `admin/batches/{id}/clone`, `admin/live-classes`, `admin/live-classes/{id}/go-live|end|attendance`, `admin/settings`, `admin/app-versions`

## Payments setup
**Razorpay:** add `RAZORPAY_KEY/SECRET`. Dashboard → Webhooks → URL `https://api.your-domain/api/v1/webhooks/razorpay`, secret = `RAZORPAY_WEBHOOK_SECRET`, events: `payment.captured`, `order.paid`, `payment_link.paid`.
App flow: `POST app/checkout/quote` → `POST app/checkout` (returns Razorpay options) → open Razorpay SDK → `POST app/orders/{order_no}/verify`.
**PhonePe:** add merchant id + salt. `POST app/checkout {gateway: phonepe}` returns `redirect_url`; after returning to the app call `GET app/orders/{order_no}/status`. The server always confirms with PhonePe's status API. (If PhonePe gives you the newer OAuth "v2" credentials, only `PhonePeGateway.php` needs changing.)
**Payment links:** Admin → `POST admin/payment-links` → returns `whatsapp_url` (opens WhatsApp with the message ready). When paid, the student account is created from the phone number, enrolled, added to the batch chat and gets the invoice.
**Coupons:** percent/flat, max discount, minimum, dates, total & per-person limits, audience (everyone / first-time buyers / existing students / chosen people), only for certain batches, "must already own batch X" (old-student offers). Batch coupon policy: allow / deny / only listed coupons.
**Revenue share** is off by default. When turned on it applies to new payments only. Balance = total share − payouts.

## Question import formats
**CSV** (download `GET admin/question-bank/imports/template`):
`question, question_ml, option_a … option_f, option_a_ml …, answer (A / A,C / 12), solution, subject, topic, difficulty, marks, negative, labels (LDC|PYQ)`
Add another language later: tick "match translations" and use the `ref` column = question id.

**Word (.docx)** — type the paper normally:
```
1. Who founded the SNDP Yogam?
ML: SNDP യോഗം സ്ഥാപിച്ചത് ആര്?
A) Sree Narayana Guru || ശ്രീനാരായണഗുരു
B) Ayyankali
C) Chattampi Swamikal
D) Mannathu Padmanabhan
Answer: A
Solution: Founded in 1903.
Subject: GK | Topic: Renaissance | Difficulty: easy | Labels: LDC, PYQ
```
Pictures pasted anywhere are kept. Bold, superscript and subscript are kept.

## Test engine (app)
`POST app/tests/{id}/start` → paper · `POST app/attempts/{uid}/sync` every ~20 s · `POST …/next-section` · `POST …/submit` → result · `GET …/solutions` · `GET app/tests/{id}/leaderboard`.
The server keeps the time: if the app is closed, the attempt resumes with the right remaining time and auto-submits when time runs out.

## Google Meet → YouTube live
Needs a Google Workspace (Individual/Enterprise/Education Plus) or Google One 2 TB+ plan and a YouTube channel with live streaming enabled (first time takes up to 24 h).
In Meet: Activities → Live streaming → YouTube channel (visibility Unlisted). Paste the YouTube link in "Go live". When the class ends, the same video is saved as the recording.

## WhatsApp, OTP logs, course swap, marketing export (update)
```bash
php artisan migrate                                   # adds whatsapp_accounts, whatsapp_messages, otp_logs, course_swaps, marketing_exports
php artisan db:seed --class=RolesAndPermissionsSeeder # adds whatsapp.*, marketing.export, security.view, enrollments.swap
```
- **Admin → WhatsApp**: two API connections saved in the DB (token encrypted):
  - *Share* – payment links, invoice PDF (document message), admission and swap messages.
  - *OTP* – when ON, login OTP goes on WhatsApp (optional SMS fallback); when OFF, SMS.
  - Request body is a JSON template with `{{phone}} {{message}} {{file_url}} {{file_name}} {{caption}} {{otp}} {{token}}`. Presets: Generic, UltraMsg, Meta Cloud API (+ OTP template).
  - The invoice PDF is sent from its public link `/api/v1/public/invoices/{token}` – the API server must be reachable from the internet.
- **Admin → Security & logs**: OTP logs (`/admin/security/otp-logs`, CSV export), login activity, audit log.
- **Admissions → Course swap**: `/admin/swaps/lookup?phone=`, `/admin/enrollments/{id}/swap-quote`, `POST /admin/enrollments/{id}/swap` (modes free / collected / payment_link – link swaps finish automatically on payment).
- **Marketing data**: `/admin/marketing/count` and `/admin/marketing/export?type=students|buyers|non_buyers|expiring|inactive|test_takers|app_installs|leads&format=standard|whatsapp|google_ads|meta_ads`.
- App: `GET /auth/otp/channel` → `{channel: whatsapp|sms}`; `POST /auth/otp/send` also returns `channel`.
