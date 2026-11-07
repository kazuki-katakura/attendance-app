<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexAttendanceRecordRequest;
use App\Http\Requests\Api\V1\StoreAttendanceRecordRequest;
use App\Http\Requests\Api\V1\UpdateAttendanceRecordRequest;
use App\Http\Resources\AttendanceRecordResource;
use App\Models\AttendanceRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

class AttendanceRecordController extends Controller
{
    private const DEFAULT_PER_PAGE = 20;

    /**
     * 勤怠一覧を取得する（user_id・date・month で絞り込み、ページネーション付き）。
     */
    public function index(IndexAttendanceRecordRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $attendanceRecords = AttendanceRecord::with(['user', 'breaks'])
            ->when($validated['user_id'] ?? null, fn (Builder $query, int|string $userId): Builder => $query->where('user_id', $userId))
            ->when($validated['date'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('date', $date))
            ->when($validated['month'] ?? null, fn (Builder $query, string $month): Builder => $query->inMonth(Carbon::createFromFormat('!Y-m', $month)))
            ->latest('date')
            ->latest('id')
            ->paginate($validated['per_page'] ?? self::DEFAULT_PER_PAGE)
            ->withQueryString();

        return AttendanceRecordResource::collection($attendanceRecords);
    }

    /**
     * 勤怠詳細（ユーザー・休憩・修正申請を含む）を取得する。
     */
    public function show(AttendanceRecord $attendanceRecord): AttendanceRecordResource
    {
        return new AttendanceRecordResource($attendanceRecord->load(['user', 'breaks', 'applications']));
    }

    /**
     * ログインユーザーの勤怠を新規登録する。
     */
    public function store(StoreAttendanceRecordRequest $request): JsonResponse
    {
        $attendanceRecord = $request->user()->attendanceRecords()->create($request->validated());
        $attendanceRecord->recalculateTotals();

        return (new AttendanceRecordResource($attendanceRecord->load(['user', 'breaks'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * 勤怠を更新する（本人または管理者のみ）。送信された項目だけを更新する。
     */
    public function update(UpdateAttendanceRecordRequest $request, AttendanceRecord $attendanceRecord): AttendanceRecordResource
    {
        $this->authorize('update', $attendanceRecord);

        $attendanceRecord->fill($request->validated());
        $attendanceRecord->recalculateTotals();

        return new AttendanceRecordResource($attendanceRecord->load(['user', 'breaks']));
    }

    /**
     * 勤怠を削除する（本人または管理者のみ）。休憩・修正申請は外部キーの CASCADE で削除される。
     */
    public function destroy(AttendanceRecord $attendanceRecord): JsonResponse
    {
        $this->authorize('delete', $attendanceRecord);

        $attendanceRecord->delete();

        return response()->json(null, 204);
    }
}
