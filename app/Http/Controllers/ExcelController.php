<?php

namespace App\Http\Controllers;
use App\Exports\UsersExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExcelController extends Controller
{
    /**
     * Export users data to Excel.
     *
     * @return BinaryFileResponse
     */
    public function exportUsers()
    {
        return Excel::download(new UsersExport, 'users.xlsx');
    }
}
