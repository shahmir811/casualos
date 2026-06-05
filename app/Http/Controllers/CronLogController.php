<?php

namespace App\Http\Controllers;

use App\Models\CronLog;
use Illuminate\Http\Request;

class CronLogController extends Controller
{
    public function index(Request $request)
    {
        $query = CronLog::query();

        if ($request->filled('job_name')) {
            $query->where('job_name', $request->input('job_name'));
        }

        if ($request->input('status') === 'success') {
            $query->where('status', 'success');
        } elseif ($request->input('status') === 'failed') {
            $query->where('status', 'failed');
        }

        if ($request->input('triggered_by') === 'Scheduler') {
            $query->where('triggered_by', 'Scheduler');
        } elseif ($request->input('triggered_by') === 'manual') {
            $query->where('triggered_by', 'like', 'Manual%');
        }

        $logs = $query->latest('ran_at')->paginate(30)->withQueryString();

        return view('system.cron-logs.index', compact('logs'));
    }
}
