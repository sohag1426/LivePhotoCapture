<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Str;

class CaptureController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $frameWidth = 640;
        $frameHeight = 480;

        return view('photo-capture-with-faceapi', [
            'width' => $frameWidth,
            'height' => $frameHeight,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'capturedImageData' => 'required|string'
        ]);

        $img = Image::make($request->capturedImageData);

        $filename = Carbon::now()->getTimestamp();
        $image_name =  $filename . '.png';

        $image = 'public/' . $image_name;

        $path = Storage::path($image);
        $r = $img->save($path);

        return redirect()->away('http://127.0.0.1:8000/storage/' . $image_name);
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
