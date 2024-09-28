<?php

namespace App\Exports;

use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UsersExport implements FromCollection, WithHeadings,  ShouldAutoSize, WithStyles
{
    use Exportable;

    public function query(): Builder
    {
        return User::query()->select('id', 'first_name', 'last_name', 'phone', 'second_phone', 'districts', 'regions', 'schools', 'telegram_id', 'created_at');
    }

    /**
     * Define the headings for the exported data.
     *
     * @return array
     */
    public function headings(): array
    {
        return [
            '#',
            'Familya',
            'Ism',
            'Telegram raqam',
            'Telefon raqam',
            'Tuman',
            'Viloyat',
            'Maktab',
            'Telegram ID',
            'Ro\'yhatdan o\'tilgan vaqt',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Style settings if needed
        return [];
    }

    public function collection()
    {
        return User::where('step','=',8)->select('id', 'first_name', 'last_name', 'phone', 'second_phone', 'districts', 'regions', 'schools', 'telegram_id', 'created_at')->get();
    }
}

