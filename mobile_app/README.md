# Padanam LMS – Flutter app

Kerala competitive-exam LMS (PSC, SSC, RRB, KTET, UGC NET, CSEB, ISRO, AAI, RPF, CRPF).
Riverpod 3 · Dio · go_router · feature-first folders · phone / tablet / desktop / web.

## 1. Run

```bash
cd padanam_app
flutter create . --org com.padanam --platforms=android,ios,web,windows,macos,linux
flutter pub get
flutter analyze

# Laravel backend running on your PC (php artisan serve --host=0.0.0.0 --port=8000)
flutter run -d chrome                                            # uses http://localhost:8000/api/v1
flutter run -d emulator-5554                                     # uses http://10.0.2.2:8000/api/v1
flutter run --dart-define=API_BASE_URL=http://192.168.1.20:8000/api/v1   # real phone on same Wi-Fi
```

Runs against the **Laravel API** (`padanam_api.zip`). Local login: seeded student **9876543210**, OTP **123456**
(`APP_DEMO_OTP` in the API `.env`; in production a real OTP goes by WhatsApp or SMS – see Admin → WhatsApp).
A new number goes through the 9-step setup; the same number again goes straight to Home.

Extra build settings:
```bash
flutter run --dart-define=API_BASE_URL=https://api.padanam.app/api/v1 \
            --dart-define=REALTIME_URL=https://rt.padanam.app \
            --dart-define=APP_BUILD=10          # keep equal to the +N in pubspec version
```

### What is connected to the real API
| Area | Screens | API |
|---|---|---|
| Start | splash, onboarding, language, login, OTP (WhatsApp / SMS), 9-step setup, plan ready | `/public/app/config`, `/auth/otp/*`, `/auth/me`, `/public/setup/options`, `/app/profile/setup` |
| Home | greeting, streak, announcements, exam categories, continue learning, new courses, leaderboard, events, promo | `/app/home` |
| Store | Courses tab (my courses + explore), course page, checkout (coupon, Razorpay, PhonePe), payment result, orders & invoices | `/public/courses`, `/app/checkout*`, `/app/orders*` |
| Learning | course tabs & folders, video (YouTube / AWS HLS, resume), PDF, notes (ml/en), links, articles | `/app/my-courses`, `/app/courses/{id}/browse`, `/app/contents/{id}` |
| Tests | test series, instructions, attempt (sectional or single timer, autosave, resume), result, analysis, solutions, report question, performance, OMR upload | `/app/test-series`, `/app/tests/*`, `/app/attempts/*`, `/app/performance` |
| Doubts | my doubts, course doubts, ask with photo | `/app/doubts`, `/app/courses/{id}/doubts` |
| Practice | daily quiz + streak, previous year papers, study plan (week view, tick, add own task) | `/app/daily-quiz`, `/app/test-series?kind=pyq`, `/app/study-plan*` |
| Exams | exam list, exam hub (courses, free tests, exam info, articles) | `/app/exams`, `/app/exams/{slug}` |
| Live | upcoming classes, live class (countdown → YouTube live → recording) | `/app/live-classes`, `/app/live-classes/{id}/join` |
| Other | notifications inbox + settings (incl. class alerts per course), rankings, current affairs, search, profile | `/app/notifications*`, `/app/home`, `/public/articles`, `/app/profile-page` |

| Chat | chats tab, batch groups, direct chats with teachers/support, photos & PDFs, typing, delete, report, mute, invite link join (`/j/{code}`) | `/app/chat/*` REST + Socket.IO (`REALTIME_URL`) |
| Teacher mode | my classes → go live (paste YouTube link) → attendance → end & save recording; answer doubts | `/admin/live-classes?mine=1`, `/app/live-classes/{id}/go-live|end`, `/admin/doubts` |
| Push | FCM token per device, notifications while open, tap → screen, full-screen class alert ring 5 min before class | FCM (Laravel `services.fcm`) |
| Deep links | padanam.app/c/{slug}, /a/{slug}, /j/{code}, /paid/{order} | app_links + `/.well-known/*` served by Laravel |

Removed (no backend yet): flashcards, physical training, standalone notes/lesson screens (notes and lessons now live inside courses).


## 2. Platform settings (after `flutter create .`)

**Android** – `android/app/src/main/AndroidManifest.xml`
```xml
<uses-permission android:name="android.permission.INTERNET"/>
<application android:usesCleartextTraffic="true" ...>   <!-- only for local http -->
```
`android/app/build.gradle`: `minSdk = 23` or higher (razorpay_flutter, pdfrx).
Razorpay: nothing else to add (the SDK brings its own ProGuard rules).

**iOS** – `ios/Runner/Info.plist`
```xml
<key>NSAppTransportSecurity</key><dict><key>NSAllowsArbitraryLoads</key><true/></dict>
<key>NSPhotoLibraryUsageDescription</key><string>Choose a profile photo</string>
<key>NSCameraUsageDescription</key><string>Take a profile photo</string>
```

**macOS** – add to both `DebugProfile.entitlements` and `Release.entitlements`
```xml
<key>com.apple.security.network.client</key><true/>
```

## 3. Folder structure

```
lib/
  main.dart / app.dart
  core/
    config/env.dart            API base URL (dart-define)
    network/                   Dio client, bearer token, 401 → logout, {success,message,data} unwrap
    storage/local_store.dart   token, onboarding flag, language, skipped update
    router/                    go_router, auth redirect, route constants (R), root navigator key
    theme/                     colours, light/dark palette, responsive helpers
    update/                    version check + in-app update banner / sheet / force screen
    utils/                     safe JSON getters, formatting
    widgets/                   shared UI kit, AsyncView (loading / error / retry)
  features/<module>/
    data/          repository + FutureProviders (API calls)
    application/   Notifiers (auth, setup, test attempt)
    presentation/  screens and widgets
```
Modules: splash, onboarding, auth, setup, shell, home, exams, learn, practice, tests,
community, planner, physical, rankings, profile, notifications, search.

## 4. Routes

| Route | Screen |
|---|---|
| /splash, /onboarding, /language | Start |
| /login, /otp, /welcome | OTP login |
| /setup, /setup/plan | 9-step profile setup, plan ready |
| /home, /exams, /tests, /rankings, /profile | Bottom tabs (rail on tablet/desktop) |
| /exam/:id, /exam/:id/info, /exam/:id/paper, /exam/:id/syllabus | Exam hub |
| /lesson/:id, /notes, /flashcards, /current-affairs, /live/:id | Learn |
| /quiz, /pyq, /doubts, /planner, /physical | Practice & tools |
| /test/:id/instructions, /test/:id/attempt | Sectional mock test |
| /attempt/:id/result, /analysis, /solutions, /performance | Result & analysis |
| /notifications, /search | Misc |

## 5. Responsive
- < 600 px: phone, bottom navigation bar
- 600–839 px: tablet, 2-column grids, bottom bar
- ≥ 840 px: navigation rail; ≥ 1200 px extended rail; content width is capped on wide screens.

## 6. In-app update
- `GET /app/version?platform=android&build=10` on start, when the app resumes (max hourly) and every 6 hours.
- `build < latest_build` → banner at the bottom: **Update** / **Later** (Later hides that build).
- `build < min_supported_build` → full-screen force update.
- Android (Play Store installs): Google Play in-app update. Normal updates use the *flexible*
  flow — downloads in the background while the student keeps studying, then "Restart to install".
  Forced updates use the *immediate* flow.
- Sideloaded APK / iOS / desktop: opens the store / download URL from the API.
- Web: reloads the page to pick up the new build.
- To test: change `latest_build` in `AppController@version` on the backend.

## 7. Real data later
Only the Laravel controllers return dummy JSON. Keep the same response shapes and the
Flutter app works unchanged.


## 3. Push notifications & class alert (Firebase)
1. Create a Firebase project → add Android app `com.padanam.app` and iOS app.
2. `dart pub global activate flutterfire_cli && flutterfire configure` (or copy `google-services.json` to `android/app/` and `GoogleService-Info.plist` to `ios/Runner/`).
3. Laravel: put the service-account JSON path in `FIREBASE_CREDENTIALS` (`services.fcm.credentials`).

**Android** `AndroidManifest.xml` (inside `<manifest>`):
```xml
<uses-permission android:name="android.permission.POST_NOTIFICATIONS"/>
<uses-permission android:name="android.permission.USE_FULL_SCREEN_INTENT"/>
<uses-permission android:name="android.permission.VIBRATE"/>
<uses-permission android:name="android.permission.WAKE_LOCK"/>
```
on the main `<activity>`: `android:showWhenLocked="true" android:turnScreenOn="true"` (the class alert opens over the lock screen).
`android/app/build.gradle`: `compileOptions { coreLibraryDesugaringEnabled true }` and
`dependencies { coreLibraryDesugaring 'com.android.tools:desugar_jdk_libs:2.1.4' }` (flutter_local_notifications).

**iOS**: Xcode → Signing & Capabilities → Push Notifications + Background Modes (Remote notifications) + Time Sensitive Notifications. Upload the APNs key in Firebase.
iOS has no full-screen alarm: the class alert arrives as a time-sensitive notification; tapping it opens the ring screen.

## 4. Deep links
**Android** – inside the main `<activity>`:
```xml
<intent-filter android:autoVerify="true">
  <action android:name="android.intent.action.VIEW"/>
  <category android:name="android.intent.category.DEFAULT"/>
  <category android:name="android.intent.category.BROWSABLE"/>
  <data android:scheme="https" android:host="padanam.app" android:pathPrefix="/c/"/>
  <data android:scheme="https" android:host="padanam.app" android:pathPrefix="/a/"/>
  <data android:scheme="https" android:host="padanam.app" android:pathPrefix="/j/"/>
  <data android:scheme="https" android:host="padanam.app" android:pathPrefix="/paid/"/>
</intent-filter>
```
Laravel `.env`: `ANDROID_PACKAGE=com.padanam.app`, `ANDROID_SHA256=<release keystore SHA-256>` → served at `https://padanam.app/.well-known/assetlinks.json`.
**iOS** – Associated Domains: `applinks:padanam.app`; Laravel serves `apple-app-site-association`.

## 5. Chat server
`--dart-define=REALTIME_URL=https://rt.padanam.app` (the Node server from `padanam_realtime.zip`, same JWT secret as Laravel).
