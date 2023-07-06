<?php

namespace App\Http\Controllers;

use App\Exports\EmployeesExport;
use App\Models\Employee;
use App\Models\Position;
use PDF;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use RealRashid\SweetAlert\Facades\Alert;
use Maatwebsite\Excel\Facades\Excel;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $pageTitle = 'Employee List';

        confirmDelete();

        $employees = Employee::all();

        // $employees = Employee::join('positions', 'positions.id', '=', 'employees.position_id')
        // ->select('employees.*', 'positions.name as position_name')->get();

        // $employees = DB::select('
        //     SELECT *, employees.id as employee_id, positions.name as position_name
        //     FROM employees
        //     LEFT JOIN positions on employees.position_id = positions.id
        // ');

        return view('employee.index', compact('pageTitle', 'employees'));
    }

    public function getData(Request $request)
    {
        $employees = Employee::with('position');

        if ($request->ajax()) {
            return datatables()->of($employees)
                ->addIndexColumn()
                ->addColumn('actions', function ($employee) {
                    return view('employee.actions', compact('employee'));
                })
                ->toJson();
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $pageTitle = 'Create Employee';

        $positions = Position::all();

        return view('employee.create', compact('pageTitle', 'positions'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $messages = [
            'required' => ':Attribute harus diisi.',
            'email' => 'Isi :attribute dengan format yang benar',
            'numeric' => 'Isi :attribute dengan angka'
        ];

        $validator = Validator::make($request->all(), [
            'firstName' => 'required',
            'lastName' => 'required',
            'email' => 'required|email',
            'age' => 'required|numeric',
        ], $messages);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // Get File
        $file = $request->file('cv');

        if ($file != null) {
            $originalFilename = $file->getClientOriginalName();
            $encryptedFilename = $file->hashName();

            // Store File
            $file->store('public/files');
        }


        // INSERT QUERY
        $employee = new Employee;
        $employee->firstname = $request->input('firstName');
        $employee->lastname = $request->input('lastName');
        $employee->email = $request->input('email');
        $employee->age = $request->input('age');
        $employee->position_id = $request->input('position');


        if ($file != null) {
            $employee->original_filename = $originalFilename;
            $employee->encrypted_filename = $encryptedFilename;
        }

        $employee->save();

        // DB::table('employees')->insert([
        //     'firstname' => $request->firstName,
        //     'lastname' => $request->lastName,
        //     'email' => $request->email,
        //     'age' => $request->age,
        //     'position_id' => $request->position,
        // ]);

        Alert::success('Added Successfully', 'Employee Data Added Successfully.');

        return redirect()->route('employees.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $pageTitle = 'Employee Detail';

        // Eloquent
        $employee = Employee::find($id);

        // query builder
        // $employee = Employee::find($id)
        // ->join('positions', 'positions.id', '=', 'employees.position_id')
        // ->select('employees.*', 'positions.name as position_name')
        // ->first();

        // RAW SQL QUERY
        // $employee = collect(DB::select('
        // select *, employees.id as employee_id, positions.name as position_name
        // from employees
        // left join positions on employees.position_id = positions.id
        // where employees.id = ?', [$id]))->first();

        return view('employee.show', compact('pageTitle', 'employee'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $pageTitle = 'Employee Edit';

        // ELOQUENT
        $positions = Position::all();
        $employee = Employee::find($id);

        // Query Builder
        // $employee = Employee::leftJoin('positions', 'positions.id', '=', 'employees.position_id')
        //     ->select('employees.*', 'positions.name as position_name', 'positions.id as position_id')
        //     ->where('employees.id', $id)
        //     ->first();

        // $positions = Position::get();

        // dd($employee);
        return view('employee.edit', [
            'pageTitle' => $pageTitle,
            'employee' => $employee,
            'positions' => $positions
        ]);
        // return view('employee.edit', compact('pageTitle', 'employee'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $messages = [
            'required' => ':Attribute harus diisi.',
            'email' => 'Isi :attribute dengan format yang benar',
            'numeric' => 'Isi :attribute dengan angka'
        ];

        $validator = Validator::make($request->all(), [
            'firstName' => 'required',
            'lastName' => 'required',
            'email' => 'required|email',
            'age' => 'required|numeric',
        ], $messages);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // ELOQUENT
        $employee = Employee::find($id);
        $employee->firstname = $request->firstName;
        $employee->lastname = $request->lastName;
        $employee->email = $request->email;
        $employee->age = $request->age;
        $employee->position_id = $request->position;
        $employee->save();

        // Query Builder
        // Employee::where('id', $id)->update([
        //     'firstname' => $request->firstName,
        //     'lastname' => $request->lastName,
        //     'email' => $request->email,
        //     'age' => $request->age,
        //     'position_id' => $request->position,
        // ]);

        Alert::success('Changed Successfully', 'Employee Data Changed Successfully.');

        return redirect()->route('employees.index');
    }

    public function downloadFile($employeeId)
    {
        $employee = Employee::find($employeeId);
        $encryptedFilename = 'public/files/' . $employee->encrypted_filename;
        $downloadFilename = Str::lower($employee->firstname . '_' . $employee->lastname . '_cv.pdf');

        if (Storage::exists($encryptedFilename)) {
            return Storage::download($encryptedFilename, $downloadFilename);
        }
    }

    public function exportExcel()
    {
        return Excel::download(new EmployeesExport, 'employees.xlsx');
    }

    public function exportPdf()
    {
        $employees = Employee::all();

        $pdf = PDF::loadView('employee.export_pdf', compact('employees'));

        return $pdf->download('employees.pdf');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        // ELOQUENT
        Employee::find($id)->delete();

        // QUERY BUILDER
        // Employee::where('id', $id)->delete();

        // DB::table('employees')
        //     ->where('id', $id)
        //     ->delete();

        Alert::success('Deleted Successfully', 'Employee Data Deleted Successfully.');

        return redirect()->route('employees.index');
    }
}
