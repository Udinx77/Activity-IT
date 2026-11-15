<?php

namespace App\Http\Controllers\Act;

use App\Http\Controllers\Controller;
use App\Models\Act\Activity;
use App\Models\Act\Division;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ClosedController extends Controller
{
    /** Gabungan: job Finish (1 shift) + job pending yang akhirnya Closed. */
    public function index(Request $request)
    {
        $from = $this->parse($request->get('from'), \App\Services\Act\RosterService::today()->subDays(90));
        $to   = $this->parse($request->get('to'), \App\Services\Act\RosterService::today());

        $q = Activity::with(['division', 'shift', 'device', 'location', 'supervisor', 'tool', 'technicians'])
            ->whereIn('status', ['finish', 'closed'])
            ->whereBetween('activity_date', [$from->toDateString(), $to->toDateString()]);
        if ($request->filled('division_id')) {
            $q->where('division_id', $request->get('division_id'));
        }
        $rows      = $q->orderByDesc('activity_date')->orderByDesc('id')->get();
        $divisions = Division::where('is_active', 1)->orderBy('id')->get();

        return view('act.closed.index', [
            'rows' => $rows, 'divisions' => $divisions,
            'from' => $from->toDateString(), 'to' => $to->toDateString(),
        ]);
    }

    private function parse($value, $default)
    {
        try {
            return $value ? Carbon::parse($value) : $default;
        } catch (\Exception $e) {
            return $default;
        }
    }
}
