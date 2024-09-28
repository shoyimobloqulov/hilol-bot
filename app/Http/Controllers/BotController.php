<?php

namespace App\Http\Controllers;

use App\Exports\UsersExport;
use App\Jobs\SendPdfJob;
use App\Models\Answer;
use App\Models\Tests;
use App\Models\User;
use App\Models\UserMap;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Image;
use Milly\Laragram\Laragram;
use Milly\Laragram\Types\Video;

class BotController extends Controller
{
    public function index()
    {
        ini_set('memory_limit', '512M');
        set_time_limit(300);
        $users = User::all();
        $pdf = PDF::loadView('users_list', ['users' => $users]);
        return $pdf->download();
    }

    public function adminCheck($chat_id): bool
    {
        $admin_ids = [681971363, 6840537054, 608913545];
        foreach ($admin_ids as $a) {
            if ($a == $chat_id) {
                return true;
            }
        }
        return false;
    }

    public function user_map($telegram_id, $map = "test"): void
    {
        $user = UserMap::where('telegram_id', $telegram_id)
            ->get();

        if (count($user) > 0) {
            UserMap::where('telegram_id', $telegram_id)
                ->update([
                    "map" => $map,
                    "telegram_id" => $telegram_id
                ]);
        } else {
            UserMap::create([
                "map" => $map,
                "telegram_id" => $telegram_id
            ]);
        }
    }

    public function getChatMembersCheck($chat_id = 0): bool
    {
        try {
            $urls = [
                [
                    'url' => "https://t.me/eduhilol",
                    'username' => "@eduhilol"
                ],
                [
                    'url' => "https://t.me/Pmt_Hilol",
                    'username' => "@Pmt_Hilol"
                ]
            ];

            foreach ($urls as $url) {
                $result = Laragram::getChatMember($url['username'], user_id: $chat_id);
                if (array_key_exists('status', $result['result']) && $result['result']['status'] == "left") {
                    return false;
                }
            }
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function generate_unique_random_number(): string
    {
        $unique = false;
        $number = "";

        while (!$unique) {
            $digits = range(0, 9);
            shuffle($digits);

            $selected_digits = array_slice($digits, 0, 6);
            $number = implode('', $selected_digits);

            if (count(array_unique(str_split($number))) == 5) {
                $unique = true;
            }
        }

        return $number;
    }

    public function normalTestCheck($text): array
    {
        $check = true;
        $text = strtolower(trim($text));
        $counter = 0;
        $normat_text = "";
        if (preg_match('/[0-9]/', $text)) {
            $nw = "";
            for ($i = 0; $i < strlen($text); $i++) {
                $x = $text[$i];

                if (ctype_alpha($x)) {
                    $nw .= $x;
                }
            }

            $nwk = "";
            for ($i = 0; $i < strlen($nw); $i++) {
                $nwk .= ($i + 1) . $nw[$i];
            }

            if ($nwk != $text) {
                $check = false;
            }

            if ($check) {
                $counter = strlen($nw);
                $normat_text = $nw;
            }
        } else {
            $counter = strlen($text);
            $normat_text = $text;

        }


        return [$check, $counter, $normat_text];
    }

    private function generatePDF($data): \Barryvdh\DomPDF\PDF
    {
        return PDF::loadView('result', compact('data'));
    }

    private function generateUsersPDF($users): \Barryvdh\DomPDF\PDF
    {
        return PDF::loadView('users_list', compact('users'));
    }

    private function savePDF($pdf, $chat_id): string
    {
        $pdfPath = 'pdf/' . $chat_id . '.pdf';

        if (Storage::disk('public')->exists($pdfPath)) {
            Storage::disk('public')->delete($pdfPath);
        }

        Storage::disk('public')->put($pdfPath, $pdf->output());
        return $pdfPath;
    }

    public function handle(Request $request): void
    {
        try {
            $input = $request->all();
            $chat_id = $input["message"]["from"]["id"] ?? $input["callback_query"]["message"]["chat"]["id"] ?? 0;
            $message = $input['message'] ?? $input["callback_query"]["message"] ?? 0;
            $inline_text = $input["callback_query"]["data"] ?? "";
            $inline_text_split = explode("/", $inline_text);

            $user = User::where('telegram_id', $chat_id)
                ->first();

            $user_map = UserMap::where('telegram_id', $chat_id)
                ->first();

            if (isset($message["new_chat_member"])) {
                Laragram::deleteMessage(
                    $message["chat"]["id"],
                    $message["message_id"],
                );
            }
            if ($message && $message['chat']['type'] != "private") {
                exit(0);
            }
            if ($inline_text == "success_check") {
                if ($this->getChatMembersCheck($input["callback_query"]["message"]["chat"]["id"])) {
                    $this->user_map($chat_id, "Register");
                    $buttons = json_encode(['resize_keyboard' => true, 'keyboard' => [
                        [
                            ['text' => "✅ Javobni tekshirish"],
                            ['text' => "⚙️ Sozlamalar"]
                        ],
                        [
                            ['text' => "👨‍🏫 O’zlashtirish"],
                            ['text' => "👨‍⚕️ Admin"]
                        ]
                    ]]);

                    if ($this->adminCheck($chat_id)) {
                        $buttons = json_encode(['resize_keyboard' => true, 'keyboard' => [
                            [
                                ['text' => "✅ Javobni tekshirish"],
                                ['text' => "⚙️ Sozlamalar"]
                            ],
                            [
                                ['text' => "👨‍🏫 O’zlashtirish"],
                                ['text' => "✍️ Test yaratish"]
                            ],
                            [
                                ['text' => "👨‍⚕️ Admin"],
                                ['text' => "👨‍🏫 Foydalanuvchilar"]
                            ]
                        ]]);
                    }

                    $name = $this->fullname($input["callback_query"]["message"]['from']['first_name'] ?? "", $input["callback_query"]["message"]['from']['last_name'] ?? "");
                    $userlink = $this->userLink($input["callback_query"]["message"]['from']['username'] ?? "");
                    $text = "Assalomu alaykum <a href='$userlink'>$name</a> botimizga xush kelibsiz.";
                    Laragram::sendMessage(
                        $input["callback_query"]["message"]["chat"]["id"],
                        null,
                        $text,
                        parse_mode: "HTML",
                        disable_web_page_preview: true,
                        reply_markup: $buttons
                    );
                } else {
                    $this->getChatMembersPrint($input["callback_query"]["message"]["chat"]["id"]);
                }
            }
            if (count($inline_text_split) == 2) {
                if ($inline_text_split[0] == "Status") {
                    $test_id = $inline_text_split[1];
                    $answers = Answer::where('test_id', $test_id)
                        ->orderBy('correct_answer', 'desc')
                        ->get();
                    $ansx = Answer::where('test_id', $test_id)
                        ->orderBy('correct_answer', 'desc')
                        ->limit(3)
                        ->get();

                    $test = Tests::find($test_id);
                    $ans = count($answers);

                    $text = "✍️ Hisobot!
📌 Test kodi: $test->code
👨 Qatnashchilar soni: $ans ta
🎗 Top qatnashchilar: ";

                    foreach ($ansx as $t) {
                        $user = User::where('telegram_id', '=', $t['telegram_id'])->first();
                        $f = ($t['correct_answer'] * 100) / strlen($test->variant);
                        $text .= $user->first_name . " " . $user->last_name . " " . $t['correct_answer'] . "($f%)\n";
                    }

                    $pdf = $this->generatePDFTests($answers);

                    $pdfPath = $this->savePDF($pdf, $input["callback_query"]["message"]["chat"]["id"]);
                    $fileUrl = asset($pdfPath);

                    Laragram::sendMessage(
                        $input["callback_query"]["message"]["chat"]["id"],
                        null,
                        "Test natijalari malumotlari tayorlanmoqda..."
                    );

                    Laragram::sendDocument(
                        $input["callback_query"]["message"]["chat"]["id"],
                        null,
                        $fileUrl . "?url=" . md5(time()),
                        caption: "$text",
                        parse_mode: "HTML",
                        disable_content_type_detection: false
                    );
                } elseif ($inline_text_split[0] == "Finish") {
                    $test_id = $inline_text_split[1];
                    $test = Tests::find($test_id);
                    $test->status = 1;
                    $test->save();

                    $tests = Answer::where('test_id', $test_id)
                        ->orderBy('correct_answer', 'desc')
                        ->get();

                    $pdf = $this->generatePDFTests($tests);

                    $pdfPath = $this->savePDF($pdf, $input["callback_query"]["message"]["chat"]["id"]);
                    $fileUrl = asset($pdfPath) . "?url=" . md5(time());

                    Laragram::sendMessage(
                        $input["callback_query"]["message"]["chat"]["id"],
                        null,
                        "Test natijalari tayorlanmoqda ..."
                    );

                    Laragram::sendDocument(
                        $input["callback_query"]["message"]["chat"]["id"],
                        null,
                        $fileUrl,
                        caption: "Test yakunlandi, Natijalar.",
                        parse_mode: "HTML",
                        disable_content_type_detection: false
                    );

                }
            }
            if (!$user_map) {
                $user_map = UserMap::create([
                    "telegram_id" => $chat_id,
                    "map" => "started"
                ]);
            }
            //getChatMembersCheck
            if ($this->getChatMembersCheck($message['from']['id'] ?? 0)) {
                if ($this->checkUser($chat_id)) {
                    if ($message && isset($message['text']) && $message['text'] == "/help") {
                        $this->user_map($chat_id, "Help");
                        $text = "<i>👋 Assalomu alaykum, men <b>HILOL Education</b> botiman, vazifam testlar o'tqazish va natijarni monitoring qilishdan iborat.</i>\n\nAdmin: @BarhayotSamatov";
                        Laragram::sendMessage(
                            $chat_id,
                            null,
                            $text,
                            parse_mode: "HTML",
                            disable_web_page_preview: true
                        );
                    } elseif ($message && isset($message['text']) && $message['text'] == "/myinfo") {
                        $this->user_map($chat_id, "My Info");
                        $text = "<b>FIO</b>: $user->first_name $user->last_name\n<b>Ro'yhatga olingan sana: </b> $user->created_at";
                        Laragram::sendMessage(
                            $chat_id,
                            null,
                            $text,
                            parse_mode: "HTML"
                        );
                    } elseif ($message && isset($message['text']) && $message['text'] == "/doc") {
                        Laragram::copyMessage(
                            $chat_id,
                            null,
                            -1001957136946,
                            33
                        );

                        Laragram::copyMessage(
                            $chat_id,
                            null,
                            -1001957136946,
                            34
                        );
                    } elseif ($message && isset($message['text']) && $message['text'] == "/start") {
                        $this->user_map($chat_id, "Start");

                        $buttons = json_encode(['resize_keyboard' => true, 'keyboard' => [
                            [
                                ['text' => "✅ Javobni tekshirish"],
                                ['text' => "⚙️ Sozlamalar"]
                            ],
                            [
                                ['text' => "👨‍🏫 O’zlashtirish"],
                                ['text' => "👨‍⚕️ Admin"],
                            ]
                        ]]);

                        if ($this->adminCheck($chat_id)) {
                            $buttons = json_encode(['resize_keyboard' => true, 'keyboard' => [
                                [
                                    ['text' => "✅ Javobni tekshirish"],
                                    ['text' => "⚙️ Sozlamalar"]
                                ],
                                [
                                    ['text' => "👨‍🏫 O’zlashtirish"],
                                    ['text' => "✍️ Test yaratish"]
                                ],
                                [
                                    ['text' => "👨‍⚕️ Admin"],
                                    ['text' => "👨‍🏫 Foydalanuvchilar"]
                                ]
                            ]]);
                        }

                        $name = $this->fullname($message['from']['first_name'] ?? "", $message['from']['last_name'] ?? "");
                        $userlink = $this->userLink($message['from']['username'] ?? "");
                        $text = "Assalomu alaykum <a href='$userlink'>$name</a> botimizga xush kelibsiz.";
                        Laragram::sendMessage(
                            $chat_id,
                            null,
                            $text,
                            parse_mode: "HTML",
                            disable_web_page_preview: true,
                            reply_markup: $buttons
                        );
                        // https://t.me/PMTONLINE/33
                        // https://t.me/PMTONLINE/33
                        Laragram::copyMessage(
                            $chat_id,
                            null,
                            -1001957136946,
                            33
                        );

                        Laragram::copyMessage(
                            $chat_id,
                            null,
                            -1001957136946,
                            34
                        );
                    } elseif ($message && isset($message['text']) && $message['text'] == "👨‍⚕️ Admin") {
                        $this->user_map($chat_id, "👨‍⚕️ Admin");
                        $text = "<i>👋 Assalomu alaykum, men <b>HILOL Education</b> botiman, vazifam testlar o'tqazish va natijarni monitoring qilishdan iborat.\n\n✅ Botdan foydalanish yo'riqnomasini olish uchun /doc buyrug'ini bering</i>\n\n👨‍⚕ Admin: @BarhayotSamatov";
                        Laragram::sendMessage(
                            $chat_id,
                            null,
                            $text,
                            parse_mode: "HTML",
                            disable_web_page_preview: true
                        );
                    } elseif ($message && isset($message['text']) && $message['text'] == "⚙️ Sozlamalar") {
                        $this->extracted($chat_id);
                    } elseif ($message && isset($message['text']) && $message['text'] == "👨‍🏫 O’zlashtirish") {
                        $this->user_map($chat_id, "👨‍🏫 O’zlashtirish");

                        $answers = Answer::where('telegram_id', $chat_id)
                            ->orderBy('correct_answer', 'desc')
                            ->get();

                        if (count($answers) > 0) {
                            $pdf = $this->generatePDF($answers);

                            $pdfPath = $this->savePDF($pdf, $chat_id);
                            $fileUrl = asset($pdfPath)."?url=".md5(time());

                            Laragram::sendDocument(
                                $chat_id,
                                null,
                                $fileUrl,
                                parse_mode: "HTML",
                                disable_content_type_detection: false
                            );
                        } else {
                            Laragram::sendMessage($chat_id, null, "Hali siz testlarda ishtirok etmagansiz.");
                        }
                    } elseif ($message && isset($message['text']) && $message['text'] == "👨‍🏫 Foydalanuvchilar") {
                        $this->user_map($chat_id, "👨‍🏫 Foydalanuvchilar");

                        $users = User::all();

                        if (count($users) > 0) {
                            $export = new UsersExport();
                            $filePath = $export->store('users.xlsx','public');

                            Laragram::sendMessage(
                                $chat_id,
                                null,
                                "Foydalanuvchilar ro'yhati muofaqiyatli tayorlandi.\n\n✅ https://shoyim.uz/users.xlsx"
                            );
                        } else {
                            Laragram::sendMessage($chat_id, null, "Hali Foydalanuvchilar ro'yhatdan o'tmagan.");
                        }
                    } elseif ($message && isset($message['text']) && $message['text'] == "🎉 Sertifikat tanlash") {
                        $this->user_map($chat_id, "🎉 Sertifikat tanlash");

                        $text = "🔵 Kerakli sertifikatni tanlang.\n🔷 Eslatma. Hozir tanlagan sertifikatingiz siz tuzadigan keyingi testlarda test qatnashchilari uchun beriladi.";
                        $images = [
                            [
                                'type' => 'photo',
                                'media' => "https://telegra.ph/file/e1b456802494124a206cb.png"
                            ],
                            [
                                'type' => 'photo',
                                'media' => "https://telegra.ph/file/44afae3d7ea40a307d0a9.png",
                            ]
                        ];
                        $buttons = json_encode(['resize_keyboard' => true, 'keyboard' => [
                            [
                                ['text' => 1],
                                ['text' => 2],
                            ],
                            [
                                ["text" => "♻️ Orqaga"]
                            ]
                        ]]);

                        $this->sendMediaPhoto($chat_id, $images);

                        Laragram::sendMessage
                        ($chat_id, null, $text, reply_markup: $buttons);
                    } elseif ($message && isset($message['text']) && $message['text'] == "✍️ Ism") {
                        $this->user_map($chat_id, "✍️ Ism");
                        Laragram::sendMessage(
                            $chat_id,
                            null,
                            "Yangi Ismingizni kiriting: ",
                            reply_markup: json_encode([
                                'remove_keyboard' => true
                            ])
                        );
                    } elseif ($message && isset($message['text']) && $user_map->map == "✍️ Ism" && $message['text'] != "♻️ Orqaga" && $message['text'] != "✍️ Familiya") {
                        if ($this->isSurname($message['text'])) {
                            $user->update([
                                'first_name' => $message['text']
                            ]);
                            $buttons = json_encode(['resize_keyboard' => true, 'keyboard' => [
                                [
                                    ['text' => "🎉 Sertifikat tanlash"],
                                ],
                                [
                                    ['text' => "✍️ Ism"],
                                    ['text' => "✍️ Familiya"]
                                ],
                                [
                                    ['text' => "♻️ Orqaga"],
                                ]
                            ]]);

                            Laragram::sendMessage(
                                $chat_id,
                                null,
                                "✅ Ismingiz taxrirlandi.",
                                reply_markup: $buttons,
                            );
                        } else {
                            Laragram::sendMessage(
                                $chat_id,
                                null,
                                "Kechirasiz bu Ismga o'xshamadi qayta urinib ko'ring"
                            );
                        }
                    } elseif ($message && isset($message['text']) && $message['text'] == "✍️ Familiya") {
                        $this->user_map($chat_id, "✍️ Familiya");
                        Laragram::sendMessage(
                            $chat_id,
                            null,
                            "Yangi Familyangizni kiriting: ",
                            reply_markup: json_encode([
                                'remove_keyboard' => true
                            ])
                        );
                    } elseif ($message && isset($message['text']) && $user_map->map == "✍️ Familiya" && $message['text'] != "♻️ Orqaga" && $message['text'] != "✍️ Ism") {
                        if ($this->isSurname($message['text'])) {
                            $user->update([
                                'last_name' => $message['text']
                            ]);
                            $buttons = json_encode(['resize_keyboard' => true, 'keyboard' => [
                                [
                                    ['text' => "🎉 Sertifikat tanlash"],
                                ],
                                [
                                    ['text' => "✍️ Ism"],
                                    ['text' => "✍️ Familiya"]
                                ],
                                [
                                    ['text' => "♻️ Orqaga"],
                                ]
                            ]]);

                            Laragram::sendMessage(
                                $chat_id,
                                null,
                                "✅ Familyangizni taxrirlandi.",
                                reply_markup: $buttons
                            );
                        } else {
                            Laragram::sendMessage(
                                $chat_id,
                                null,
                                "Kechirasiz bu Familyanga o'xshamadi qaytadan kiriting."
                            );
                        }
                    } elseif ($message && isset($message['text']) && $message['text'] == "✍️ Test yaratish") {
                        $this->user_map($chat_id, "✍️ Test yaratish");

                        $buttons = json_encode(['resize_keyboard' => true, 'keyboard' => [
                            [
                                ['text' => "📝 Oddiy test"],
                                ['text' => "📕 Fanli test"],
                            ],
                            [
                                ['text' => "🗂 Maxsus test"],
                                ['text' => "📚 Blok test"]
                            ],
                            [
                                ['text' => "♻️ Orqaga"],
                            ]
                        ]]);

                        Laragram::sendMessage(
                            $chat_id,
                            null,
                            "❕ Kerakli bo'limni tanlang.",
                            reply_markup: $buttons
                        );
                    } elseif ($message && isset($message['text']) && $message['text'] == "📝 Oddiy test") {
                        $this->user_map($chat_id, "📝 Oddiy test create");

                        $buttons = json_encode(['resize_keyboard' => true, 'keyboard' => [
                            [
                                ['text' => "♻️ Orqaga"],
                            ]
                        ]]);

                        $text = "✍️ Test javoblarini yuboring.\nM-n: abcd... yoki 1a2b3c4d...\n<i>❕ Javoblar faqat lotin alifbosida bo'lishi shart.</i>";

                        Laragram::sendMessage(
                            $chat_id,
                            null,
                            $text,
                            parse_mode: "HTML",
                            reply_markup: $buttons
                        );
                    } elseif ($message && isset($message['text']) && $message['text'] == "📕 Fanli test") {
                        $this->user_map($chat_id, "📕 Fanli test");

                        $text = "✍️ Fan nomini yuboring.\nM-n: Matematika";

                        Laragram::sendMessage($chat_id, null, $text, parse_mode: "HTML");
                    } elseif ($message && isset($message['text']) && $message['text'] == "🗂 Maxsus test") {
                        $this->user_map($chat_id, "🗂 Maxsus test");

                        $text = "✍️ Faylni yuboring.\n<i>❗️ Rasm yoki fayl bo'lishi mumkun.</i>";

                        Laragram::sendMessage($chat_id, null, $text, parse_mode: "HTML");
                    } elseif ($message && isset($message['text']) && $message['text'] == "📚 Blok test") {
                        $this->user_map($chat_id, "📚 Blok test");
                        $text = "✍️ Blok test ma'lumotlarini quyidagi ko'rinishda yuboring.\nfan nomi 1/javoblar 1/ball 1\nfan nomi 2/javoblar 2/ball 2\n...\nM-n:\n<code>\nMatematika/acbdabcdba/3.1\nFizika/bacdbcbcbcd/2.1\nOna tili/abcdbadbadbc/1.1</code>\n<i>❗️ Fan nomi 20 ta belgidan oshmasligi shart, ball haqiqiy musbat son bo'lishi shart, javoblar soni 100 dan oshmasligi zarur. Fan va javoblar lotin alifbosida bo'lishi shart.</i>";
                        Laragram::sendMessage(
                            $chat_id,
                            null,
                            $text,
                            parse_mode: "HTML"
                        );
                    } elseif ($message && isset($message['text']) && $user_map->map == "📝 Oddiy test create" && $message['text'] != "♻️ Orqaga" && $message['text'] != "✅ Javobni tekshirish") {
                        $this->user_map($chat_id, "📝 Oddiy test create");
                        $test_check = $this->normalTestCheck($message['text']);
                        if ($test_check[0]) {
                            $number = (rand(100000, 999999) + $chat_id) % 1000000;
                            $test = Tests::create([
                                'code' => $number,
                                'telegram_id' => $chat_id,
                                'variant' => strtolower($message['text'])
                            ]);
                            $user = User::where('telegram_id', $test->telegram_id)
                                ->first();

                            $variant_size = preg_match_all('/[a-zA-Z]/', $message['text']);

                            $text = "✅ Test bazaga qo'shildi.\n👨‍🏫 Muallif: " . $user->first_name . " " . $user->last_name . " \n✍️ Test kodi: $number\n🔹 Savollar: $variant_size ta\n📆 <code>$test->created_at</code>";
                            Laragram::sendMessage($chat_id, null, $text, "HTML");
                        } else {
                            Laragram::sendMessage(
                                $chat_id,
                                null,
                                "<b>Xato urinish. </b>\n❕ Javoblar faqat lotin alifbosida bo'lishi shart.",
                                "HTML"
                            );
                        }
                    } elseif ($message && isset($message['text']) && $message['text'] == "✅ Javobni tekshirish") {
                        $this->user_map($chat_id, "✅ Javobni tekshirish");
                        $buttons = json_encode(['resize_keyboard' => true, 'keyboard' => [
                            [
                                ['text' => "♻️ Orqaga"],
                            ]
                        ]]);
                        Laragram::sendMessage($chat_id, null, "✍️ Test kodini yuboring.", reply_markup: $buttons);
                    } elseif ($message && isset($message['text']) && $message['text'] == "♻️ Orqaga") {
                        if (str_contains($user_map->map, "✅ Javobni tekshirish") or
                            $user_map->map == "📝 Oddiy test create" or
                            $user_map->map == "✍️ Familiya" or $user_map->map == "Oddiy test" or
                            $user_map->map == "✍️ Ism" or $user_map->map == "⚙️ Sozlamalar" or
                            $user_map->map == "✍️ Test yaratish" or $user_map->map == "🎉 Sertifikat tanlash") {
                            $buttons = json_encode(['resize_keyboard' => true, 'keyboard' => [
                                [
                                    ['text' => "✅ Javobni tekshirish"],
                                    ['text' => "⚙️ Sozlamalar"]
                                ],
                                [
                                    ['text' => "👨‍🏫 O’zlashtirish"],
                                    ['text' => "👨‍⚕️ Admin"]
                                ],

                            ]]);
                            if ($this->adminCheck($chat_id)) {
                                $buttons = json_encode(['resize_keyboard' => true, 'keyboard' => [
                                    [
                                        ['text' => "✅ Javobni tekshirish"],
                                        ['text' => "⚙️ Sozlamalar"]
                                    ],
                                    [
                                        ['text' => "👨‍🏫 O’zlashtirish"],
                                        ['text' => "✍️ Test yaratish"]
                                    ],
                                    [
                                        ['text' => "👨‍⚕️ Admin"],
                                        ['text' => "👨‍🏫 Foydalanuvchilar"]
                                    ]
                                ]]);
                            }
                            Laragram::sendMessage($chat_id, null, "Bosh sahifa.", reply_markup: $buttons);
                        } elseif ($user_map->map == "🎉 Sertifikat tanlash") {
                            $this->extracted($chat_id);
                        } elseif ($user_map->map == "📝 Oddiy test create") {
                            $buttons = json_encode(['resize_keyboard' => true, 'keyboard' => [
                                [
                                    ['text' => "📝 Oddiy test"],
                                    ['text' => "📕 Fanli test"],
                                ],
                                [
                                    ['text' => "🗂 Maxsus test"],
                                    ['text' => "📚 Blok test"]
                                ],
                                [
                                    ['text' => "♻️ Orqaga"],
                                ]
                            ]]);

                            Laragram::sendMessage(
                                $chat_id,
                                null,
                                "Bo'limni tanlang.",
                                reply_markup: $buttons
                            );
                        }
                    } elseif ($message && isset($message['text']) && $user_map->map == "✅ Javobni tekshirish") {
                        if (preg_match('/^[0-9]+$/', $message['text'])) {
                            $test = Tests::where('code', $message['text'])
                                ->first();
                            if ($test) {
                                if ($test->status) {
                                    Laragram::sendMessage($chat_id, null, "❗️ Test yakunlangan, javob yuborishda kechikdingiz. Keyingi testlarda faol bo'lishingizni so'raymiz.");
                                } else {
                                    $answer = Answer::where('test_id', $test->id)
                                        ->where('telegram_id', $chat_id)
                                        ->first();
                                    if (isset($message['text'])) {
                                        if (!$answer) {
                                            $buttons = json_encode(['resize_keyboard' => true, 'keyboard' => [
                                                [
                                                    ['text' => "♻️ Orqaga"],
                                                ]
                                            ]]);

                                            $code = $message['text'];
                                            $size = strlen($test->variant ?? "");

                                            $this->user_map($chat_id, "✅ Javobni tekshirish/" . $message['text']);

                                            $test = $this->normalTestCheck($test->variant);
                                            $x = $test[1];

                                            $text = "✍️ $code kodli testda $x ta kalit mavjud. Marhamat o'z javoblaringizni yuboring.\nM-n: abcd yoki 1a2b3c4d";

                                            Laragram::sendMessage($chat_id, null, $text, reply_markup: $buttons);
                                        } else {
                                            $date = $answer->created_at->isoFormat('📆 DD.MM.YYYY ⏰ HH:mm');
                                            $count = intval($this->normalTestCheck($test->variant ?? "")[1]);
                                            $x = intval($answer->correct_answer);
                                            $s = round((100 * $x) / $count);

                                            $text = "🔴 Ushbu testda avval qatnashgansiz.
💻 Test kodi: $test->code
✅ Natija: $answer->correct_answer ta
🎯 Sifat: $s%
$date";
                                            Laragram::sendMessage(
                                                $chat_id,
                                                null,
                                                "Siz aval bu testda qatnashgansiz."
                                            );
                                        }
                                    }
                                }
                            } else {
                                Laragram::sendMessage($chat_id, null, "❗️ Test kodi noto'g'ri, tekshirib qaytadan yuboring.");
                            }
                        } else {
                            Laragram::sendMessage($chat_id, null, "❗️ Test kodi noto'g'ri, tekshirib qaytadan yuboring.");
                        }
                    } elseif ($user_map->map == "🎉 Sertifikat tanlash") {
                        if ($message['text'] == '1' && $message['text'] == '2') {
                            $user->update([
                                'certificate' => $message['text']
                            ]);
                            $url = "https://telegra.ph/file/44afae3d7ea40a307d0a9.png";
                            if ($message['text'] == '2') {
                                $url = "https://telegra.ph/file/e1b456802494124a206cb.png";
                            }
                            $buttons = json_encode(['resize_keyboard' => true, 'keyboard' => [
                                [
                                    ['text' => "🎉 Sertifikat tanlash"],
                                ],
                                [
                                    ['text' => "✍️ Ism"],
                                    ['text' => "✍️ Familiya"]
                                ],
                                [
                                    ['text' => "♻️ Orqaga"],
                                ]
                            ]]);

                            Laragram::sendPhoto(
                                $chat_id,
                                null,
                                $url,
                                "✅ Yaxshi endi ushbu sertifikat siz tuzadigan test qatnashchilariga beriladi.",
                                reply_markup: $buttons
                            );
                        } else {
                            Laragram::sendMessage(
                                $chat_id,
                                null,
                                "Xatolik qaytadan urinib ko'ring."
                            );
                        }
                    } elseif ($message && isset($message['text'])) {
                        $maps_arr = explode("/", $user_map->map);
                        if (count($maps_arr) == 2) {
                            $variant = $message['text'];
                            $code = intval($maps_arr[1]);
                            $userData = $this->normalTestCheck($variant);
                            if ($userData[0]) {
                                $test = Tests::where('code', $code)
                                    ->first();
                                $serverData = $this->normalTestCheck($test->variant);
                                if (!($test->status)) {
                                    if ($test) {
                                        $correct = $userData[1];
                                        $answer = $serverData[1];
                                        if ($correct == $answer) {
                                            $resultData = Answer::where('telegram_id', $chat_id)
                                                ->where('test_id', $test->id)
                                                ->first();

                                            if ($resultData) {
                                                $date = $resultData->created_at->isoFormat('📆 DD.MM.YYYY ⏰ HH:mm');
                                                $count = intval($this->normalTestCheck($test->variant ?? "")[1]);
                                                $x = intval($resultData->correct_answer);
                                                $s = round((100 * $x) / $count);

                                                $text = "🔴 Ushbu testda avval qatnashgansiz.
💻 Test kodi: $test->code
✅ Natija: $resultData->correct_answer ta
🎯 Sifat: $s%
$date";
                                                if ($s >= 90) {
                                                    $date = Carbon::create($resultData->created_at)->format('Y-m-d');

                                                    $this->certificateUser($user, $date, $chat_id, $text);
                                                } else {
                                                    Laragram::sendMessage(
                                                        $chat_id,
                                                        null,
                                                        $text
                                                    );
                                                }
                                            } else {
                                                $result = $this->resultUser($serverData[2], $userData[2]);
                                                $result_text = "";
                                                foreach ($result[0] as $x => $y) {
                                                    $result_text .= $y[0] . "." . $y[2] . " " . $y[1] . "         ";
                                                }

                                                $answer = Answer::create([
                                                    'telegram_id' => $chat_id,
                                                    'correct_answer' => $result[1],
                                                    'test_id' => $test->id,
                                                ]);

                                                $date = $answer->created_at->isoFormat('📆 DD.MM.YYYY ⏰ HH:mm');

                                                $name = $this->fullname($user->first_name ?? "", $user->last_name ?? "");
                                                $cout = intval($this->normalTestCheck($test->variant ?? "")[1]);
                                                $is_correct_answer = $cout - $result[1];

                                                $s = round((100 * $answer->correct_answer) / $cout);

                                                $text = "💡 Natijangiz:
🙎🏻‍♂️ Foydalanuvchi: <b>$name</b>
💻 Test kodi: $code
✅ To'gri javoblar: $result[1] ta
❌ Noto'g'ri javoblar: $is_correct_answer ta
📊 Sifat: $s%
$date

---------------------------
NATIJANGIZNI YAXSHILASH UCHUN @PMTONLINE TELEGRAM KANALIDAGI ONLINE TESTLARGA MUNTAZAM QATNASHIB BORING VA EDU HILOL O’QUV MARKAZINING OFFLINE DARSLARIGA QATNASHING.
";

                                                if ($s >= 90) {
                                                    $date = Carbon::create($answer->created_at)->format('Y-m-d');
                                                    $this->certificateUser($user, $date, $chat_id, $text);
                                                } else {
                                                    Laragram::sendMessage(
                                                        $chat_id,
                                                        null,
                                                        $text,
                                                        "HTML"
                                                    );
                                                }

                                                $admin_text = "$test->code kodli testda  $name qatnashdi!
✅ Natija: $result[1] ta
🎯 Sifat darajasi: $s%
$date";

                                                $buttons = json_encode(['inline_keyboard' => [
                                                    [
                                                        ['text' => '📊 Holat', 'callback_data' => 'Status/' . $test->id],
                                                        ['text' => '⏰ Yakunlash', 'callback_data' => 'Finish/' . $test->id],
                                                    ]
                                                ]]);

                                                Laragram::sendMessage(
                                                    $test->telegram_id,
                                                    null,
                                                    $admin_text,
                                                    parse_mode: "HTML",
                                                    reply_markup: $buttons
                                                );
                                                $user_data = User::where('telegram_id', $test->telegram_id)->first();
                                            }
                                        } else {
                                            Laragram::sendMessage(
                                                $chat_id,
                                                null,
                                                "❗️ Test uchun yuborgan varyandlaringiz noto'g'ri, tekshirib qaytadan yuboring. Yuborish talab qilinadigan javoblar $answer ta."
                                            );
                                        }
                                    } else {
                                        Laragram::sendMessage(
                                            $chat_id,
                                            null,
                                            "❗️ Test kodi noto'g'ri, tekshirib qaytadan yuboring."
                                        );
                                    }
                                } else {
                                    Laragram::sendMessage(
                                        $chat_id,
                                        null,
                                        "️❗️ Test yakunlangan, javob yuborishda kechikdingiz. Keyingi testlarda faol bo'lishingizni so'raymiz."
                                    );
                                }
                            } else {
                                Laragram::sendMessage($chat_id, null, "❗️ Test varyandlarini yuborishdan xatolik, tekshirib qaytadan yuboring.");
                            }
                        }
                    }
                } elseif ($user && !$this->checkUser($chat_id)) {
                    if ($user->step == 1) {
                        if ($this->isSurname($message['text'] ?? "")) {
                            User::where('telegram_id', $chat_id)
                                ->update([
                                    'first_name' => $message['text'] ?? "",
                                    'step' => 2
                                ]);
                            Laragram::sendMessage(
                                $chat_id,
                                null,
                                "✍️ Ismingizni kiriting. \n <i>❗️ Ism faqat lotin alifbosi harflaridan iborat bo'lishi shart.</i>",
                                parse_mode: "HTML",
                                disable_web_page_preview: true
                            );
                        } else {
                            if (isset($message['text']) && $message['text'] == "/start") {
                                Laragram::sendMessage($chat_id, null, "Assalomu alaykum, botdan foydalanish uchun ro'yhatdan o'tishingiz kerak");
                                Laragram::sendMessage(
                                    $chat_id,
                                    null,
                                    "✍️ <b>Familiyangizni kiriting.</b>\n<i>❗️ Familiyangiz faqat lotin harflaridan iborat bo'lishi shart.</i>",
                                    parse_mode: "HTML",
                                    disable_web_page_preview: true
                                );
                            } else {
                                Laragram::sendMessage(
                                    $chat_id,
                                    null,
                                    "<i>❗ Kechirasiz bu Familyaga o'xshamadi qayta urinib ko'ring.</i>",
                                    parse_mode: "HTML",
                                    disable_web_page_preview: true
                                );
                            }

                        }
                    } elseif ($user->step == 2) {
                        if ($this->isSurname($message['text'])) {
                            User::where('telegram_id', $chat_id)
                                ->update([
                                    'last_name' => $message['text'],
                                    'step' => 3
                                ]);

                            $buttons = json_encode(['resize_keyboard' => true, 'keyboard' => [
                                [
                                    ['text' => '📲 Raqamni yuborish', 'request_contact' => true],
                                ]
                            ]]);

                            Laragram::sendMessage(
                                chat_id: $chat_id,
                                text: "✍️ Telegram raqamni yuboring",
                                reply_markup: $buttons
                            );
                        } else {
                            Laragram::sendMessage(
                                $chat_id,
                                null,
                                "<i>❗ Kechirasiz bu Ismga o'xshamadi qayta urinib ko'ring.</i>",
                                parse_mode: "HTML",
                                disable_web_page_preview: true
                            );
                        }
                    } elseif ($user->step == 3) {
                        $phone_number = $message["contact"]["phone_number"] ?? "";
                        if (strlen($phone_number) > 1 && preg_match('/^\+?\d{12}$/', $phone_number)) {
                            $user->update([
                                'phone' => $phone_number,
                                'step' => 4
                            ]);

                            Laragram::sendMessage(
                                $chat_id,
                                null,
                                "✍️ Bog'lanish uchun telefon raqamingizni yozing.\n Masalan: +998991234567",
                                reply_markup: json_encode([
                                    'remove_keyboard' => true
                                ])
                            );
                        } else {
                            Laragram::sendMessage($chat_id, null, "Xatolik, Telegram raqamingizni tugma orqali yuboring.");
                        }
                    } elseif ($user->step == 4) {
                        $second_phone = $message['text'] ?? "";
                        if ($second_phone && strlen($second_phone) > 1 && preg_match('/^\+?\d{12}$/', $second_phone)) {
                            $user->update([
                                'second_phone' => $second_phone,
                                'step' => 5
                            ]);

                            $districts = DB::table('regions')
                                ->get();
                            $keyRows = [];

                            $d_count = count($districts);
                            if ($d_count > 0) {
                                for ($i = 0; $i < $d_count - 1; $i += 2) {
                                    $a = $districts[$i];
                                    $b = $districts[$i + 1];
                                    $keyRows[] = [['text' => $a->name], ['text' => $b->name]];
                                }

                                $buttons = json_encode(['keyboard' => $keyRows]);

                                Laragram::sendMessage(
                                    $chat_id,
                                    null,
                                    "🔁 Viloyatingizni tanglang.",
                                    parse_mode: "HTML",
                                    disable_web_page_preview: true, reply_markup: $buttons
                                );
                            } else {
                                Laragram::sendMessage($chat_id, null, "😑 Xato urinish qilindi!. Qaytadan urinib ko'ring. Viloyatingiz faqat pastdagi tugmani bosish orqali amalga oshirladi.");
                            }
                        } else {
                            Laragram::sendMessage($chat_id, null, "😑 Xato urinish qilindi!. Qaytadan urinib ko'ring. Telefon raqam faqat pastdagi tugmani bosish orqali amalga oshirladi.");
                        }
                    } elseif ($user->step == 5) {
                        $region_text = $message['text'] ?? "";
                        $region = DB::table('regions')
                            ->where('name', $region_text)
                            ->first();

                        if ($region) {
                            $user->update([
                                'regions' => $region_text,
                                'step' => 6
                            ]);

                            $districts = DB::table('districts')
                                ->where('region_id', $region->id ?? 0)
                                ->get();

                            $array_districts = [];

                            $d_count = count($districts);
                            for ($i = 0; $i < $d_count - 1; $i += 2) {
                                $a = $districts[$i];
                                $b = $districts[$i + 1];
                                $array_districts[] = [['text' => $a->name], ['text' => $b->name]];
                            }

                            $buttons = json_encode(['keyboard' => $array_districts]);

                            Laragram::sendMessage(
                                $chat_id,
                                null,
                                "🔁 Tumani tanglang.",
                                parse_mode: "HTML",
                                disable_web_page_preview: true, reply_markup: $buttons
                            );
                        } else {
                            Laragram::sendMessage($chat_id, null, "😑 Xato urinish qilindi!. Bunday viloyat mavjud bo'lmasligi mumkin!. Qaytadan urinib ko'ring.");
                        }
                    } elseif ($user->step == 6) {
                        $district_text = $message['text'] ?? "";
                        $district = DB::table('districts')
                            ->where('name', $district_text)
                            ->first();

                        if ($district) {
                            $user->update([
                                'districts' => $district_text,
                                'step' => 7
                            ]);

                            Laragram::sendMessage(
                                $chat_id,
                                null,
                                "🎒 Maktabingiz nomerini kiriting.",
                                reply_markup: json_encode([
                                    'remove_keyboard' => true
                                ])
                            );
                        } else {
                            Laragram::sendMessage($chat_id, null, "😑 Xato urinish qilindi!. Bunday tuman mavjud bo'lmasligi mumkin!. Qaytadan urinib ko'ring.");
                        }
                    } elseif ($user->step == 7) {
                        $schools_text = $message['text'] ?? "";

                        if ($schools_text && strlen($schools_text) >= 1 && preg_match('/^[0-9]*$/', $schools_text)) {
                            $user->update([
                                'schools' => $schools_text,
                                'step' => 8
                            ]);

                            $buttons = json_encode(['resize_keyboard' => true, 'keyboard' => [
                                [
                                    ['text' => "✅ Javobni tekshirish"],
                                    ['text' => "⚙️ Sozlamalar"]
                                ],
                                [
                                    ['text' => "👨‍🏫 O’zlashtirish"],
                                    ['text' => "👨‍⚕️ Admin"]
                                ]
                            ]]);

                            if ($this->adminCheck($chat_id)) {
                                $buttons = json_encode(['resize_keyboard' => true, 'keyboard' => [
                                    [
                                        ['text' => "✅ Javobni tekshirish"],
                                        ['text' => "⚙️ Sozlamalar"]
                                    ],
                                    [
                                        ['text' => "👨‍🏫 O’zlashtirish"],
                                        ['text' => "✍️ Test yaratish"]
                                    ],
                                    [
                                        ['text' => "👨‍⚕️ Admin"],
                                        ['text' => "👨‍🏫 Foydalanuvchilar"]
                                    ]
                                ]]);
                            }

                            Laragram::sendMessage(
                                $chat_id,
                                null,
                                "👋 Muvaffaqiyatli ro‘yxatdan o‘tdingiz.",
                                reply_markup: $buttons
                            );
                        } else {
                            Laragram::sendMessage(
                                $chat_id,
                                null,
                                "Maktab raqamini kirishdan xatolikga yo'l qo'ydingiz, Qaytadan urinib ko'ring."
                            );
                        }
                    }
                } else {
                    if (!$this->checkUser($chat_id)) {
                        if ($message) {
                            User::create([
                                'telegram_id' => $chat_id,
                                'username' => $message['from']['username'] ?? "",
                                'step' => 1
                            ]);

                            Laragram::sendMessage(
                                $chat_id,
                                null,
                                "✍️ <b>Familiyangizni kiriting.</b>\n<i>❗️ Familiyangiz faqat lotin harflaridan iborat bo'lishi shart.</i>",
                                parse_mode: "HTML",
                                disable_web_page_preview: true,
                                reply_markup: json_encode([
                                    "remove_keyboard" => true
                                ])
                            );
                        } else {
                            Log::error('Xatolik: Array elementi topilmayapdi.');
                        }
                    }
                }
            } else {
                $this->getChatMembersPrint($chat_id);
            }
        } catch (Exception $e) {
            $text = "Xatolik: " . $e->getMessage() . "\nQator: " . $e->getLine() . "\nFayl: " . $e->getFile();
            Laragram::sendMessage(
                6840537054,
                null,
                $text
            );
        }
    }

    /**
     * @param mixed $chat_id
     * @return void
     */
    public function extracted(mixed $chat_id): void
    {
        $this->user_map($chat_id, "⚙️ Sozlamalar");

        $text = "🛠️ Kerakli bo'limni tanlang.";

        $buttons = json_encode(['resize_keyboard' => true, 'keyboard' => [
            [
                ['text' => "🎉 Sertifikat tanlash"],
            ],
            [
                ['text' => "✍️ Ism"],
                ['text' => "✍️ Familiya"]
            ],
            [
                ['text' => "♻️ Orqaga"],
            ]
        ]]);

        Laragram::sendMessage(
            $chat_id,
            null,
            $text,
            parse_mode: "HTML",
            disable_web_page_preview: true,
            reply_markup: $buttons
        );
    }

    private function call($method, $params = [])
    {
        $params = [];
        try {
            $url = "https://api.telegram.org/bot" . env('TELEGRAM_BOT_TOKEN') . "/" . $method;
            $response = Http::post($url, $params);

            return $response->json();
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public function checkUser($chat_id = 0): bool
    {
        $user = User::where('telegram_id', $chat_id)
            ->first();

        /*
            first_name: VARCHAR
            last_name: VARCHAR
            regions:VARCHAR
        */
        return $user &&
            strlen($user->first_name ?? "") > 3 &&
            strlen($user->last_name ?? "") > 3 &&
            strlen($user->phone) > 7 &&
            strlen($user->regions ?? "") > 7 &&
            strlen($user->districts) > 7 &&
            intval($user->step) == 8;
    }

    public function fullname($firs_name = "", $last_name = ""): string
    {
        return $firs_name . " " . $last_name;
    }

    public function userLink($username = ""): string
    {
        return "https://t.me/" . $username;
    }

    public function isSurname($text): bool
    {
        $pattern = "/^([a-zA-Z'\"`]{3,})$/";
        if (preg_match($pattern, $text)) {
            return true;
        } else {
            return false;
        }
    }

    private function getChatMembersPrint($chat_id): void
    {
        $buttons = json_encode(['resize_keyboard' => true, 'inline_keyboard' => [
            [
                ['text' => "Edu Hilol 🔗", 'url' => 'https://t.me/eduhilol'],
            ],
            [
                ['text' => "PMT Hilol 🔗", 'url' => "https://t.me/Pmt_Hilol"]
            ],
            [
                ['text' => "✅ Tasdiqlash", 'callback_data' => "success_check"]
            ],
        ]]);

        Laragram::sendMessage($chat_id, null, "❌ Kechirasiz botimizdan foydalanishdan oldin ushbu kanallarga a'zo bo'lishingiz kerak. Azo bo'lgach ✅ Tasdiqlashni bosing.", reply_markup: $buttons);
    }

    /**
     * @throws GuzzleException
     */
    public function sendMediaPhoto($chat_id, $images): \Psr\Http\Message\StreamInterface
    {
        $client = new Client();

        $response = $client->request('POST', 'https://api.telegram.org/bot6013149717:AAHQakKGRKKCwOzu8tjyhb3BdI7SPmThIo8/sendMediaGroup', [
            'multipart' => [
                [
                    'name' => 'chat_id',
                    'contents' => $chat_id
                ],
                [
                    'name' => 'media',
                    'contents' => json_encode($images)
                ]
            ],
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        return $response->getBody();
    }

    public function checkTextPattern($text): bool
    {
        // Define regex patterns for both formats
        $pattern1 = '/(\d+[a-zA-Z])+/';
        $pattern2 = '/^[a-zA-Z]+$/';

        // Check if the text matches either pattern
        if (preg_match($pattern1, $text) || preg_match($pattern2, $text)) {
            return true;
        } else {
            return false;
        }
    }

    public function resultUser($a, $b): array
    {
        $correct = [];
        $correct_count = 0;

        for ($i = 0; $i < strlen($a); $i++) {
            if ($a[$i] != $b[$i]) {
                $correct[] = [
                    $i + 1,
                    "❌",
                    $a[$i]
                ];
            } else {
                $correct[] = [
                    $i + 1,
                    "✅",
                    $a[$i]
                ];
                $correct_count++;
            }
        }

        return [
            $correct,
            $correct_count
        ];
    }

    public function certificateUser($user, $created_at, $chat_id, $text)
    {
        $imgPath = asset('001.jpg');
        if ($user->certificate == 2) {
            $imgPath = asset('002.jpg');
        }

        $image = imagecreatefromjpeg($imgPath);

        $color = imagecolorallocate($image, 0, 0, 0); // Matn rangi - qora

        $string = $this->fullname($user->first_name, $user->last_name);
        $fontSize = 80;
        $font = public_path("font/timesbold.ttf");
        $x = 1300;
        $y = 700;
        if (file_exists($font)) {
            imagettftext($image, $fontSize, 0, $x, $y, $color, $font, $string);
            imagettftext($image, 70, 0, 200, 2160, $color, $font, $created_at);

            $certificatePath = public_path("certificate/");
            $imageName = $chat_id . '-certificate_image.jpg';
            imagejpeg($image, $certificatePath . $imageName);

            $url = asset("certificate/" . $imageName)."?url=".md5(time());

            Laragram::sendPhoto(
                $chat_id,
                null,
                $url,
                caption: "$text",
                parse_mode: "HTML",
                disable_notification: false
            );
            imagedestroy($image);
        }

        return -1;
    }

    private function generatePDFTests($tests): \Barryvdh\DomPDF\PDF
    {
        return PDF::loadView('user_result', compact('tests'));
    }
}
