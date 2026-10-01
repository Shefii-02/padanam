<?php

namespace Database\Seeders;

use App\Core\Support\Code;
use App\Models\Batch;
use App\Models\Content;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\CourseFolder;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamCategory;
use App\Models\Label;
use App\Models\LiveClass;
use App\Models\Note;
use App\Models\Question;
use App\Models\QuestionFolder;
use App\Models\StaffProfile;
use App\Models\Test;
use App\Models\TestSection;
use App\Models\User;
use App\Models\Video;
use App\Modules\Courses\Services\BatchService;
use Illuminate\Database\Seeder;

/**
 * Demo data so every screen has something real to show.
 * Logins:  admin@padanam.app / password   (super admin)
 *          staff@padanam.app / password   (staff)
 *          suresh@padanam.app / password  (teacher)
 *          App: 9876543210 with OTP 123456 (student, enrolled)
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('DemoSeeder skipped in production.');

            return;
        }

        // ---------- people ----------
        $admin = $this->panelUser('Padanam Admin', 'admin@padanam.app', '9000000001', 'super_admin', 'Owner');
        $staff = $this->panelUser('Anjali R', 'staff@padanam.app', '9000000002', 'staff', 'Academic coordinator');
        $teacher = $this->panelUser('Suresh Menon', 'suresh@padanam.app', '9000000003', 'teacher', 'Senior faculty – GK & Kerala', true, ['GK', 'Kerala renaissance']);
        $teacher2 = $this->panelUser('Divya Nair', 'divya@padanam.app', '9000000004', 'teacher', 'Maths & reasoning', true, ['Maths', 'Reasoning']);

        $student = User::firstOrCreate(['phone' => '9876543210'], [
            'name' => 'Arjun Krishnan', 'avatar' => '🧑‍🎓', 'gender' => 'male', 'dob' => '2001-05-14', 'district' => 'Ernakulam',
            'qualification' => 'Degree', 'is_new_user' => false, 'profile_completed_at' => now(), 'referral_code' => Code::make(8), 'last_seen_at' => now(),
        ]);
        $student->syncRoles(['student']);
        $student->profile()->updateOrCreate([], ['target_posts' => ['kerala-psc' => 'LDC'], 'level' => 'Plus Two / Degree', 'aim' => 'Get selected', 'study_hours' => 3, 'study_days' => [true, true, true, true, true, true, false], 'study_slot' => 'Morning']);

        foreach ([['Meera S', 'Thrissur'], ['Rahul P', 'Kozhikode'], ['Fathima N', 'Malappuram'], ['Vishnu K', 'Kollam'], ['Aswathy M', 'Kottayam'], ['Nikhil J', 'Kannur']] as $i => [$name, $district]) {
            $u = User::firstOrCreate(['phone' => '98765432'.str_pad((string) ($i + 11), 2, '0', STR_PAD_LEFT)], [
                'name' => $name, 'district' => $district, 'is_new_user' => false, 'referral_code' => Code::make(8),
                'last_seen_at' => now()->subDays($i * 5),
            ]);
            $u->syncRoles(['student']);
        }

        // ---------- catalog ----------
        $cats = [];
        foreach ([
            ['Kerala PSC', 'kerala-psc', '🏛️', '#0F766E', ['LDC', 'LGS', 'VEO', 'Secretariat Assistant', 'Police Constable']],
            ['SSC', 'ssc', '📋', '#1D4ED8', ['CGL', 'CHSL', 'MTS', 'GD Constable']],
            ['RRB', 'rrb', '🚆', '#B45309', ['NTPC', 'Group D', 'ALP']],
            ['Teaching', 'teaching', '👩‍🏫', '#7C3AED', ['KTET', 'SET', 'UGC NET']],
            ['Defence & Police', 'defence', '🛡️', '#B91C1C', ['CRPF', 'RPF', 'Agniveer']],
        ] as $sort => [$name, $slug, $icon, $color, $exams]) {
            $cat = ExamCategory::updateOrCreate(['slug' => $slug], ['name' => $name, 'icon' => $icon, 'color' => $color, 'sort' => $sort]);
            $cats[$slug] = $cat;
            foreach ($exams as $e) {
                Exam::updateOrCreate(['slug' => \Illuminate\Support\Str::slug($slug.'-'.$e)], [
                    'exam_category_id' => $cat->id, 'name' => $e, 'next_exam_date' => now()->addDays(rand(40, 240))->toDateString(),
                ]);
            }
        }
        $student->interests()->updateOrCreate(['exam_category_id' => $cats['kerala-psc']->id, 'exam_id' => null], ['source' => 'setup', 'target_post' => 'LDC']);

        // ---------- course with 3 batches ----------
        $course = Course::updateOrCreate(['slug' => 'kerala-psc-ldc-2027'], [
            'title' => 'Kerala PSC LDC 2027 – Complete Course',
            'exam_category_id' => $cats['kerala-psc']->id,
            'course_type' => 'live_recorded',
            'language' => 'Malayalam + English',
            'short_description' => 'Live classes, recorded lessons, 60 mock tests and daily quizzes for LDC 2027.',
            'description' => '<p>Everything you need for the LDC exam: GK, Kerala renaissance, Malayalam, English, Maths and current affairs.</p>',
            'what_you_get' => ['300+ recorded classes', 'Daily live class 7 PM', '60 full mock tests', 'PDF notes in Malayalam', 'Doubt support'],
            'intro_video_url' => 'https://youtu.be/dQw4w9WgXcQ',
            'features' => Course::presetFeatures('live_recorded'),
            'status' => 'published', 'published_at' => now(), 'is_featured' => true, 'rating' => 4.7, 'students_count' => 1284,
            'created_by' => $admin->id,
        ]);
        $course->staff()->syncWithoutDetaching([$teacher->id => ['role' => 'teacher'], $teacher2->id => ['role' => 'teacher'], $staff->id => ['role' => 'manager']]);

        $batchSvc = app(BatchService::class);
        $batches = [];
        foreach ([
            ['Default batch', true, 0, 0, 0, 'lifetime', null, null, null],
            ['Morning batch – Jan 2027', false, 399900, 699900, 1, 'fixed_date', null, now()->addMonths(14)->toDateString(), 120],
            ['Evening batch – Jan 2027', false, 349900, 599900, 2, 'days', 365, null, null],
        ] as [$name, $default, $price, $mrp, $sort, $vt, $vd, $vu, $seats]) {
            $b = Batch::where('course_id', $course->id)->where('name', $name)->first() ?? new Batch(['course_id' => $course->id, 'name' => $name, 'code' => Code::make(6, 'LDC')]);
            $b->fill([
                'is_default' => $default, 'price' => $price, 'mrp' => $mrp, 'is_free' => $price === 0, 'sort' => $sort,
                'validity_type' => $vt, 'validity_days' => $vd, 'valid_until' => $vu, 'seat_limit' => $seats,
                'starts_at' => now()->addDays(5)->toDateString(), 'status' => $default ? 'closed' : 'active', 'enrollment_open' => ! $default,
            ])->save();
            $b->staff()->syncWithoutDetaching([$teacher->id => ['role' => 'teacher']]);
            $batchSvc->ensureChatGroup($b);
            $batches[] = $b;
        }

        // ---------- folders + content ----------
        $gk = CourseFolder::firstOrCreate(['course_id' => $course->id, 'title' => 'General Knowledge', 'parent_id' => null], ['sort' => 1]);
        $ren = CourseFolder::firstOrCreate(['course_id' => $course->id, 'title' => 'Kerala Renaissance', 'parent_id' => $gk->id], ['sort' => 1]);
        $maths = CourseFolder::firstOrCreate(['course_id' => $course->id, 'title' => 'Simple Arithmetic', 'parent_id' => null], ['sort' => 2]);

        $videos = [
            [$ren, 'Sree Narayana Guru – life and reforms', 'demo', 'aqz-KE-bpKQ', 1860],
            [$ren, 'Ayyankali and the Villuvandi Samaram', 'premium', 'ScMzIvxBSi4', 2140],
            [$ren, 'Vaikom Satyagraha explained', 'premium', 'kJQP7kiw5Fk', 1990],
            [$maths, 'Percentages in 20 minutes', 'free', '9bZkp7q5f0I', 1210],
            [$maths, 'Ratio & proportion shortcuts', 'premium', 'OPf0YbXqDm0', 1500],
        ];
        foreach ($videos as $i => [$folder, $title, $access, $yt, $dur]) {
            if (Content::where('course_id', $course->id)->where('title', $title)->exists()) {
                continue;
            }
            $v = Video::create(['source' => 'youtube', 'youtube_id' => $yt, 'url' => "https://youtu.be/$yt", 'duration_sec' => $dur, 'thumbnail' => "https://i.ytimg.com/vi/$yt/hqdefault.jpg"]);
            $c = new Content(['course_id' => $course->id, 'folder_id' => $folder->id, 'type' => 'video', 'title' => $title, 'access' => $access, 'sort' => $i + 1, 'created_by' => $teacher->id]);
            $c->contentable()->associate($v)->save();
        }
        if (! Content::where('course_id', $course->id)->where('type', 'note')->exists()) {
            $n = Note::create([
                'title' => ['en' => 'Kerala Renaissance – quick revision', 'ml' => 'കേരള നവോത്ഥാനം – ദ്രുത പുനരവലോകനം'],
                'body' => ['en' => '<h3>Sree Narayana Guru</h3><p>Aruvippuram consecration – 1888.</p>', 'ml' => '<h3>ശ്രീനാരായണഗുരു</h3><p>അരുവിപ്പുറം പ്രതിഷ്ഠ – 1888.</p>'],
                'one_liners' => ['en' => ['SNDP Yogam founded in 1903', 'Vaikom Satyagraha: 1924–25']],
            ]);
            $c = new Content(['course_id' => $course->id, 'folder_id' => $ren->id, 'type' => 'note', 'title' => 'Kerala Renaissance – quick revision', 'access' => 'free', 'sort' => 10]);
            $c->contentable()->associate($n)->save();
        }

        // upcoming live class for the morning batch
        LiveClass::firstOrCreate(['batch_id' => $batches[1]->id, 'title' => 'Live: Kerala Renaissance marathon'], [
            'course_id' => $course->id, 'teacher_id' => $teacher->id, 'folder_id' => $ren->id,
            'starts_at' => now()->addDay()->setTime(19, 0), 'duration_min' => 90, 'source' => 'meet_youtube',
        ]);

        // ---------- question bank + a test ----------
        $qf = QuestionFolder::firstOrCreate(['name' => 'Kerala PSC', 'parent_id' => null]);
        $qfRen = QuestionFolder::firstOrCreate(['name' => 'Renaissance', 'parent_id' => $qf->id]);
        $label = Label::firstOrCreate(['name' => 'LDC 2027'], ['color' => '#0F766E']);
        $pyq = Label::firstOrCreate(['name' => 'PYQ'], ['color' => '#B45309']);

        $bank = [
            ['Who founded the SNDP Yogam?', 'SNDP യോഗം സ്ഥാപിച്ചത് ആര്?', ['Sree Narayana Guru', 'Ayyankali', 'Chattampi Swamikal', 'Mannathu Padmanabhan'], 0],
            ['The Vaikom Satyagraha started in', 'വൈക്കം സത്യാഗ്രഹം ആരംഭിച്ച വർഷം', ['1920', '1924', '1931', '1936'], 1],
            ['Who led the Villuvandi Samaram?', 'വില്ലുവണ്ടി സമരം നയിച്ചത്', ['Ayyankali', 'Pandit Karuppan', 'V.T. Bhattathiripad', 'K. Kelappan'], 0],
            ['Aruvippuram consecration took place in', 'അരുവിപ്പുറം പ്രതിഷ്ഠ നടന്ന വർഷം', ['1888', '1898', '1903', '1910'], 0],
            ['Guruvayur Satyagraha was led by', 'ഗുരുവായൂർ സത്യാഗ്രഹം നയിച്ചത്', ['K. Kelappan', 'A.K. Gopalan', 'T.K. Madhavan', 'C. Kesavan'], 0],
        ];
        $qids = [];
        foreach ($bank as [$en, $ml, $opts, $correct]) {
            $q = Question::firstOrCreate(['hash' => Question::hashFor($en)], ['folder_id' => $qfRen->id, 'type' => 'mcq_single', 'difficulty' => 'moderate', 'subject' => 'GK', 'topic' => 'Kerala Renaissance', 'default_marks' => 1, 'default_negative' => 0.33, 'created_by' => $teacher->id]);
            if (! $q->wasRecentlyCreated) {
                $qids[] = $q->id;

                continue;
            }
            $q->translations()->createMany([['lang' => 'en', 'text' => $en], ['lang' => 'ml', 'text' => $ml]]);
            foreach ($opts as $i => $o) {
                $opt = $q->options()->create(['sort' => $i, 'is_correct' => $i === $correct]);
                $opt->translations()->create(['lang' => 'en', 'text' => $o]);
            }
            $q->labels()->sync([$label->id, $pyq->id]);
            $qids[] = $q->id;
        }

        $test = Test::firstOrCreate(['title' => 'Kerala Renaissance – Mini Mock 1'], [
            'course_id' => $course->id, 'exam_category_id' => $cats['kerala-psc']->id, 'kind' => 'chapter', 'access' => 'free',
            'languages' => ['en', 'ml'], 'duration_min' => 10, 'status' => 'published', 'show_result' => 'instant', 'attempts_allowed' => 3, 'created_by' => $teacher->id,
        ]);
        $sec = TestSection::firstOrCreate(['test_id' => $test->id, 'name' => 'Kerala Renaissance'], ['short_name' => 'KR', 'marks_per_question' => 1, 'negative_per_question' => 0.33]);
        foreach ($qids as $i => $qid) {
            $test->testQuestions()->firstOrCreate(['question_id' => $qid], ['section_id' => $sec->id, 'sort' => $i + 1]);
        }
        $test->refreshTotals();
        if (! Content::where('contentable_type', 'test')->where('contentable_id', $test->id)->exists()) {
            $c = new Content(['course_id' => $course->id, 'folder_id' => $ren->id, 'type' => 'test', 'title' => $test->title, 'access' => 'free', 'sort' => 20]);
            $c->contentable()->associate($test)->save();
        }

        // ---------- daily quiz for today (built from the "LDC 2027" label) ----------
        \App\Models\DailyQuiz::firstOrCreate(['date' => today()->toDateString(), 'exam_category_id' => $cats['kerala-psc']->id], [
            'label_id' => $label->id, 'topic' => 'Kerala Renaissance', 'questions' => 5, 'status' => 'planned',
        ]);

        // ---------- coupons ----------
        $welcome = Coupon::updateOrCreate(['code' => 'WELCOME20'], ['title' => 'New learner 20% off', 'type' => 'percent', 'value' => 20, 'max_discount' => 100000, 'audience' => 'new_users', 'per_user_limit' => 1, 'show_in_app' => true, 'ends_at' => now()->addMonths(3), 'created_by' => $admin->id]);
        $alumni = Coupon::updateOrCreate(['code' => 'OLDSTUDENT'], ['title' => 'Old students – ₹1,000 off', 'type' => 'flat', 'value' => 100000, 'audience' => 'existing_students', 'per_user_limit' => 1, 'created_by' => $admin->id]);
        $alumni->batches()->sync([$batches[1]->id, $batches[2]->id]);
        $alumni->requiredBatches()->sync([$batches[0]->id]);
        $welcome->batches()->sync([]);

        // ---------- enrollment for the demo student ----------
        Enrollment::firstOrCreate(['user_id' => $student->id, 'batch_id' => $batches[1]->id], [
            'course_id' => $course->id, 'source' => 'manual', 'starts_at' => now(), 'expires_at' => $batches[1]->expiryFor(), 'added_by' => $admin->id,
        ]);
        $batches[1]->update(['seats_taken' => $batches[1]->enrollments()->count()]);
        if ($room = $batches[1]->chatRoom()->first()) {
            $room->members()->firstOrCreate(['user_id' => $student->id], ['role' => 'member', 'joined_at' => now()]);
            $room->update(['members_count' => $room->activeMembers()->count()]);
        }
    }

    private function panelUser(string $name, string $email, string $phone, string $role, string $designation, bool $teacher = false, array $subjects = []): User
    {
        $u = User::firstOrCreate(['email' => $email], ['name' => $name, 'phone' => $phone, 'password' => 'password', 'is_new_user' => false, 'profile_completed_at' => now()]);
        $u->syncRoles([$role]);
        StaffProfile::updateOrCreate(['user_id' => $u->id], ['designation' => $designation, 'is_teacher' => $teacher, 'subjects' => $subjects]);

        return $u;
    }
}
