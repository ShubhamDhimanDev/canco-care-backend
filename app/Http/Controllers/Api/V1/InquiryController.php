<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreInquiryRequest;
use App\Models\InquiryType;
use App\Services\InquiryService;
use Illuminate\Http\JsonResponse;

class InquiryController extends Controller
{
    /**
     * The topics for the form's dropdown. Names and slugs only: the staff address each topic is sent
     * to is never exposed.
     */
    public function types(): JsonResponse
    {
        return response()->json([
            'data' => InquiryType::active()->get(['name', 'slug'])->map(fn (InquiryType $type): array => [
                'slug' => $type->slug,
                'name' => $type->name,
            ])->values(),
        ]);
    }

    /**
     * Receives a contact-form submission. A filled honeypot is answered exactly like a real one so a
     * bot learns nothing.
     */
    public function store(StoreInquiryRequest $request, InquiryService $inquiries): JsonResponse
    {
        $inquiry = $inquiries->receive($request->validated(), $request);

        return response()->json(['data' => [
            'reference' => $inquiry->reference,
            'received_at' => $inquiry->created_at?->toIso8601String(),
        ]], 201);
    }
}
