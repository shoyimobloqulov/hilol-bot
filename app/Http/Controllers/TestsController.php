<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TestsController extends Controller
{
    public function image()
    {
        $image = imagecreatefromjpeg(asset('002.jpg'));

        // Rang yaratish (qizil rang)
        $color = imagecolorallocate($image, 0, 0, 0);

        // Shriptni yuklash (TrueType shriftni yuklash uchun to'liq yo'l ko'rsatilishi kerak)
        $font = public_path("font/timesbold.ttf");

        // Matnni yozish
        $text = 'Salom Dunyo!';
        $fontSize = 80; // Shriptning o'lchami
        $x = 1400; // Matnning x koordinatasi
        $y = 480; // Matnning y koordinatasi

        // Matnni rasmga yozish (imagettftext funksiyasi bilan TrueType shrift ishlatish)
        imagettftext($image, $fontSize, 0, $x, $y, $color, $font, $text);

        imagettftext($image, $fontSize, 0, 1150, 2050, $color, $font, "5/22/2024");

        header('Content-Type: image/jpeg');

        // Natijani saqlash
        imagejpeg($image);

        // Xotirani bo'shatish
        imagedestroy($image);
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('tests.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
