<?php

namespace App\Http\Controllers;

use App\Models\PageTranslation;
use App\Http\Requests\Pages\StorePageTranslationRequest;
use App\Http\Requests\Pages\UpdatePageTranslationRequest;
use App\Models\Page;

class PageTranslationController extends Controller
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
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Page $page, StorePageTranslationRequest $request)
    {
        $page->addTranslation($request->validated());
    }

    /**
     * Display the specified resource.
     */
    public function show(PageTranslation $pageTranslation)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PageTranslation $pageTranslation)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePageTranslationRequest $request, PageTranslation $pageTranslation)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PageTranslation $pageTranslation)
    {
        //
    }
}
