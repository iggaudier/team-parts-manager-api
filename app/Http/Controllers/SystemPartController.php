<?php

namespace App\Http\Controllers;

use App\Jobs\ImportSystemParts;
use App\Models\SystemPart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SystemPartController extends Controller
{
    public function index(Request $request)
    {
        $query = SystemPart::query();

        // Filtering
        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        if ($request->has('part_type')) {
            $query->where('part_type', 'like', '%'.$request->part_type.'%');
        }

        if ($request->has('manufacturer')) {
            $query->where('manufacturer', 'like', '%'.$request->manufacturer.'%');
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('part_type', 'like', '%'.$search.'%')
                    ->orWhere('manufacturer', 'like', '%'.$search.'%')
                    ->orWhere('model_number', 'like', '%'.$search.'%');
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);

        return response()->json($query->paginate($perPage));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'part_type' => 'required|string|max:255',
            'manufacturer' => 'required|string|max:255',
            'model_number' => 'required|string|max:255',
            'list_price' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check for duplicate
        $exists = SystemPart::where('manufacturer', $request->manufacturer)
            ->where('model_number', $request->model_number)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Part with this manufacturer and model number already exists.',
            ], 422);
        }

        $systemPart = SystemPart::create($request->all());

        return response()->json($systemPart, 201);
    }

    public function show(SystemPart $systemPart)
    {
        return response()->json($systemPart);
    }

    public function update(Request $request, SystemPart $systemPart)
    {
        $validator = Validator::make($request->all(), [
            'part_type' => 'sometimes|required|string|max:255',
            'manufacturer' => 'sometimes|required|string|max:255',
            'model_number' => 'sometimes|required|string|max:255',
            'list_price' => 'sometimes|required|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check for duplicate if manufacturer or model_number is being updated
        if ($request->has('manufacturer') || $request->has('model_number')) {
            $manufacturer = $request->get('manufacturer', $systemPart->manufacturer);
            $modelNumber = $request->get('model_number', $systemPart->model_number);

            $exists = SystemPart::where('manufacturer', $manufacturer)
                ->where('model_number', $modelNumber)
                ->where('id', '!=', $systemPart->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => 'Part with this manufacturer and model number already exists.',
                ], 422);
            }
        }

        $systemPart->update($request->all());

        return response()->json($systemPart);
    }

    public function destroy(SystemPart $systemPart)
    {
        $systemPart->delete();

        return response()->json(['message' => 'System part deleted successfully']);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
        ]);

        // Store file permanently
        $path = $request->file('file')->store('imports');

        // Dispatch job with stored path
        ImportSystemParts::dispatch($path);

        return response()->json([
            'message' => 'Import started. You will be notified when it completes.',
        ], 202);
    }

    public function export(Request $request)
    {
        $systemParts = SystemPart::all();

        $filename = 'system-parts-'.date('Y-m-d-His').'.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () use ($systemParts) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Active', 'Part Type', 'Manufacturer', 'Model Number', 'List Price']);

            foreach ($systemParts as $part) {
                fputcsv($file, [
                    $part->is_active ? 'Y' : 'N',
                    $part->part_type,
                    $part->manufacturer,
                    $part->model_number,
                    $part->list_price,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
