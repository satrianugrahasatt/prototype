<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceTime;
use App\Models\AttendanceType;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AttendancesController extends Controller
{
    private $attendances;

    private $attendanceTimes;

    private $attendanceTypes;

    public function __construct()
    {
        $this->middleware('auth');

        $this->attendances = resolve(Attendance::class);
        $this->attendanceTimes = resolve(AttendanceTime::class)->get();
        $this->attendanceTypes = resolve(AttendanceType::class)->get();
    }

    public function index()
    {
        $attendances = $this->attendances->paginate();

        return view('pages.attendances', compact('attendances'));
    }

    public function store(Request $request)
    {
        $inId = $this->getId($this->attendanceTimes, 'IN');
        $outId = $this->getId($this->attendanceTimes, 'OUT');

        $now = Carbon::now('Asia/Jakarta');
        $checkInTime = Carbon::createFromTime(8, 0, 0, 'Asia/Jakarta');
        $checkOutTime = Carbon::createFromTime(16, 0, 0, 'Asia/Jakarta');

        $type = '';
        $time = '';

        if ($request->sick == 1) {
            $type = 'SICK';
            $time = 'OTHER';
        } else {
            $checkForAttendance = Attendance::whereBetween('created_at', [
                Carbon::today('Asia/Jakarta'),
                Carbon::tomorrow('Asia/Jakarta'),
            ])
                ->where('employee_id', auth()->user()->employee->id)
                ->whereIn('attendance_time_id', [$inId, $outId])
                ->latest()
                ->first();

            if ($checkForAttendance === null) {
                // No attendance today, so check-in
                $time = 'IN';
                if ($now > $checkInTime) {
                    return redirect()->route('attendances')->with('status', 'Please wait for checkin time.');
                }
                $type = 'ONTIME';
            } else {
                // Attendance exists, check if it's IN or OUT
                if ($checkForAttendance->attendance_time_id == $inId) {
                    // Has checked in, so check-out
                    $time = 'OUT';
                    if ($now < $checkOutTime) {
                        return redirect()->route('attendances')->with('status', 'Please wait for checkout time.');
                    }
                    $type = $now >= $checkOutTime ? 'ONTIME' : 'OVERTIME';
                } else {
                    // Has checked out, allow new check-in
                    $time = 'IN';
                    $type = $now <= $checkInTime ? 'ONTIME' : 'LATE';
                }
            }
        }

        $this->attendances->create([
            'employee_id' => auth()->user()->employee->id,
            'attendance_time_id' => $this->getId($this->attendanceTimes, $time),
            'attendance_type_id' => $this->getId($this->attendanceTypes, $type),
            'message' => $request->input('message'),
        ]);

        return redirect()->route('attendances')->with('status', 'Attendance recorded successfully.');
    }

    public function print()
    {
        $attendances = Attendance::all();

        return view('pages.attendances_print', compact('attendances'));
    }

    public function getId($array, $type)
    {
        $item = $array->filter(function ($item) use ($type) {
            return $item->name == $type;
        })->first();

        if (! $item) {
            throw new \Exception("Attendance time or type '$type' not found.");
        }

        return $item->id;
    }
}
