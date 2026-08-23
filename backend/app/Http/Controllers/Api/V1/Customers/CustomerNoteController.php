<?php

namespace App\Http\Controllers\Api\V1\Customers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\StoreCustomerNoteRequest;
use App\Http\Resources\CustomerNoteResource;
use App\Models\Customer;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Staff-internal only - never exposed to the customer themselves (there is
 * no self-service route for this controller at all, unlike addresses) and
 * never reachable by the AI agent (no tool in AgentToolRegistry touches
 * customers). Append-only: notes can be added, never edited or deleted,
 * so the record of "who said what, when" about a customer stays honest.
 */
class CustomerNoteController extends Controller
{
    public function index(Request $request, Customer $customer): JsonResponse
    {
        // Free-text and potentially sensitive (CLAUDE.md §4 "never expose
        // customer information to unauthorized users") - customers.manage
        // only, matching the existing single `customers.notes` field's own
        // visibility rule (see CustomerResource), unlike the more
        // structured, lower-sensitivity tag catalog below.
        abort_unless($request->user()->hasAnyPermission(['customers.manage']), 403);

        // Secondary `id` tiebreaker (bug fix, QA pass): two notes added in
        // the same request-handling second otherwise sort non-
        // deterministically on `created_at` alone - the same class of bug
        // ChatSummarizationService's own secondary `orderBy('id')` already
        // exists to prevent (see ADR 0012).
        $notes = $customer->staffNotes()->with('author')->latest('created_at')->latest('id')->get();

        return CustomerNoteResource::collection($notes)->response();
    }

    public function store(StoreCustomerNoteRequest $request, Customer $customer): JsonResponse
    {
        $note = $customer->staffNotes()->create([
            'author_id' => $request->user()->id,
            'body' => $request->validated('body'),
        ]);

        AuditLogger::log('customer.note_added', $customer, after: ['note_id' => $note->id, 'body' => $note->body]);

        return CustomerNoteResource::make($note->load('author'))->response()->setStatusCode(201);
    }
}
