<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Services\AttendanceCorrectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StampCorrectionRequestController extends Controller
{
    /**
     * 申請一覧を表示する。管理者は全ユーザー分、一般ユーザーは自分の申請のみ。
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return view('admin.admin-application-list', [
                'applications' => Application::with(['user', 'attendanceRecord'])->latest()->get(),
            ]);
        }

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

    /**
     * 修正申請の承認画面を表示する（管理者）。
     */
    public function show(int $attendanceCorrectRequestId): View
    {
        $application = Application::with(['user', 'proposalBreaks'])->findOrFail($attendanceCorrectRequestId);

        return view('admin.admin-application-detail', [
            'application' => $application,
            'user' => $application->user,
        ]);
    }

    /**
     * 修正申請を承認し、勤怠記録に反映する（管理者）。
     */
    public function approve(int $attendanceCorrectRequestId, AttendanceCorrectionService $correctionService): RedirectResponse
    {
        $application = Application::findOrFail($attendanceCorrectRequestId);

        if (! $application->isApproved()) {
            $correctionService->approve($application);
        }

        return redirect("/stamp_correction_request/approve/{$application->id}");
    }
}
