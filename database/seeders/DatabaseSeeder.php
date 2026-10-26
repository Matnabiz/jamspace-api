<?php

namespace Database\Seeders;

use App\Models\{Badge, Challenge, Event, Idea, Mission, Service, Technology, User};
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['complete_profile', 'پروفایل خود را کامل کن', 50],
            ['register_technology', 'یک فناوری جدید ثبت کن', 200],
            ['attend_webinar', 'در یک وبینار شرکت کن', 80],
            ['book_meeting', 'یک جلسه با صنعت رزرو کن', 150],
            ['sign_nda', 'فرم NDA را امضا کن', 40],
            ['send_collab', 'یک درخواست همکاری بفرست', 100],
        ] as [$k, $t, $x]) Mission::create(['key' => $k, 'title' => $t, 'xp' => $x]);

        foreach ([
            ['first_technology', 'اولین فناوری ثبت‌شده', '🥇'], ['patent_filed', 'ثبت اختراع', '📜'],
            ['patent_granted', 'گواهی ثبت اختراع', '🏆'], ['first_contract', 'اولین قرارداد صنعتی', '🤝'],
            ['funding', 'جذب سرمایه', '💸'], ['startup', 'ایجاد استارتاپ', '🚀'],
            ['first_license', 'اولین لایسنس', '🗝️'], ['top_monthly', 'برترین فناور ماه', '⭐'],
            ['ecosystem_mentor', 'منتور اکوسیستم', '🧭'],
        ] as [$k, $t, $i]) Badge::create(['key' => $k, 'title' => $t, 'icon' => $i]);

        $admin = User::create(['name' => 'کارشناس ارزیابی', 'email' => 'admin@example.com', 'password' => 'password', 'role' => 'admin']);
        $industry = User::create(['name' => 'مریم احمدی', 'email' => 'industry@example.com', 'password' => 'password',
            'role' => 'industry', 'organization' => 'گروه صنعتی پارس']);
        $res = User::create(['name' => 'دکتر علی رضایی', 'email' => 'researcher@example.com', 'password' => 'password',
            'role' => 'researcher', 'organization' => 'دانشگاه تهران', 'field' => 'مهندسی پزشکی',
            'bio' => 'پژوهشگر مواد زیستی و پانسمان‌های هوشمند.']);
        $res->forceFill(['xp' => 4820])->save();
        $res->badges()->attach(Badge::where('key', 'first_technology')->first()->id);
        $res->missions()->attach(Mission::where('key', 'register_technology')->first()->id);

        $techs = [
            ['T-1042', 'پانسمان هوشمند هیدروژلی ترمیم‌کننده زخم', 6, 'نمونه اولیه پیشرفته', 'contract', 10, 80, 'سلامت'],
            ['T-1039', 'سامانه تشخیص بیماری گیاهی مبتنی بر بینایی ماشین', 4, 'نمونه آزمایشگاهی', 'evaluated', 5, 55, 'کشاورزی'],
            ['T-1051', 'غشای نانوکامپوزیتی تصفیه پساب صنعتی', 3, 'اثبات امکان‌پذیری', 'under_review', 3, 25, 'محیط‌زیست'],
        ];
        foreach ($techs as [$code, $title, $trl, $label, $stage, $step, $prog, $cat]) {
            $t = Technology::create([
                'code' => $code, 'user_id' => $res->id, 'title' => $title, 'summary' => $title . ' — خلاصه فنی و کاربردهای صنعتی.',
                'category' => $cat, 'trl' => $trl, 'trl_label' => $label, 'stage' => $stage, 'roadmap_step' => $step,
                'progress' => $prog, 'is_public' => $stage !== 'under_review',
            ]);
            $t->events()->create(['title' => 'ثبت فناوری', 'happened_at' => now()->subDays(60)]);
            if ($prog >= 55) $t->events()->create(['title' => 'ارزیابی تخصصی', 'note' => "TRL $trl", 'happened_at' => now()->subDays(30)]);
        }

        Idea::create(['user_id' => $res->id, 'title' => 'حسگر پوشیدنی پایش رطوبت زخم', 'description' => 'ایده اولیه.']);
        Idea::create(['user_id' => $res->id, 'title' => 'کود زیستی مبتنی بر پسماند کشاورزی']);

        Challenge::create(['user_id' => $industry->id, 'title' => 'کاهش مصرف آب در خط رنگ‌کاری', 'sector' => 'صنایع تولیدی',
            'description' => 'نیازمند راهکار بازچرخانی پساب با کیفیت قابل استفاده مجدد.', 'budget' => '۵ تا ۱۰ میلیارد ریال',
            'deadline' => now()->addMonths(3)]);

        Event::create(['title' => 'وبینار مالکیت فکری برای پژوهشگران', 'type' => 'webinar', 'starts_at' => now()->addDays(10), 'location' => 'آنلاین']);
        Event::create(['title' => 'روز عرضه فناوری به صنعت', 'type' => 'pitch', 'starts_at' => now()->addDays(30), 'location' => 'پردیس نوآوری', 'capacity' => 80]);

        foreach ([
            ['ارزیابی TRL/MRL', 'ارزیابی تخصصی سطح آمادگی فناوری و بازار.', '📊'],
            ['مالکیت فکری', 'مشاوره و ثبت اختراع، طرح صنعتی و علامت تجاری.', '📜'],
            ['منتورینگ', 'اتصال به مربیان باتجربه صنعت و استارتاپ.', '🧭'],
            ['قرارداد و لایسنس', 'تنظیم و مذاکره قراردادهای انتقال فناوری.', '🤝'],
        ] as [$t, $d, $i]) Service::create(['title' => $t, 'description' => $d, 'icon' => $i]);
    }
}
