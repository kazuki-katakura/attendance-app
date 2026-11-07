<?php

namespace App\Http\Controllers;

use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StampCorrectionRequestController extends Controller
{
    /**
     * 自分の修正申請の一覧を表示する。
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $formattedApplications = $user->applications()
            ->with('attendanceRecord')
            ->latest()
            ->get()
            ->map(fn (Application $application): array => [
                'id' => $application->id,
                'attendance_record_id' => $application->attendance_record_id,
                'approval_status' => $application->approval_status,
                'date' => $application->attendanceRecord->date->format('Y/m/d'),
                'comment' => $application->comment,
                'application_date' => $application->application_date->format('Y/m/d'),
            ]);

        return view('user.user-application-list', [
            'user' => $user,
            'formattedApplications' => $formattedApplications,
        ]);
    }
}
