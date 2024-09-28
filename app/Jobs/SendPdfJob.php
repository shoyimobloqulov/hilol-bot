<?php

namespace App\Jobs;

use App\Exports\UsersExport;
use App\Models\User;
use Barryvdh\DomPDF\PDF;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Milly\Laragram\Laragram;

class SendPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    /**
     * Create a new job instance.
     */

    protected $users;
    protected $chat_id;
    public function __construct($users,$chat_id)
    {
        $this->users = $users;
        $this->chat_id = $chat_id;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $export = new UsersExport();
        $export->store('users.xlsx','public');
        $filePath = asset('users.xlsx');
        Laragram::sendDocument(
            $this->chat_id,
            null,
            $filePath,
            "Foydalanuvchilar ro'yhati"
        );
    }
}
