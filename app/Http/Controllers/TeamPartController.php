<?php

namespace App\Http\Controllers;

use App\Models\SystemPart;
use App\Models\TeamPart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use League\Csv\Reader;

class TeamPartController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if (! $user->team_id) {
            return response()->json(['message' => 'User not assigned to a team'], 403);
        }

        $query = TeamPart::with('systemPart')
            ->where('team_id', $user->team_id);

        // Filtering
        if ($request->has('part_type')) {
            $query->whereHas('systemPart', function ($q) use ($request) {
                $q->where('part_type', 'like', '%'.$request->part_type.'%');
            });
        }

        if ($request->has('manufacturer')) {
            $query->whereHas('systemPart', function ($q) use ($request) {
                $q->where('manufacturer', 'like', '%'.$request->manufacturer.'%');
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);

        $teamParts = $query->paginate($perPage);

        // Format response with flattened structure
        $teamParts->getCollection()->transform(function ($teamPart) {
            return [
                'id' => $teamPart->id,
                'part_type' => $teamPart->systemPart->part_type,
                'manufacturer' => $teamPart->systemPart->manufacturer,
                'model_number' => $teamPart->systemPart->model_number,
                'list_price' => $teamPart->systemPart->list_price,
                'multiplier' => $teamPart->multiplier,
                'static_price' => $teamPart->static_price,
                'team_price' => $teamPart->team_price,
            ];
        });

        return response()->json($teamParts);
    }

    public function search(Request $request)
    {
        $user = $request->user();

        if (! $user->team_id) {
            return response()->json(['message' => 'User not assigned to a team'], 403);
        }

        $search = $request->get('q', '');

        $query = TeamPart::with('systemPart')
            ->where('team_id', $user->team_id)
            ->whereHas('systemPart', function ($q) use ($search) {
                $q->where('part_type', 'like', '%'.$search.'%')
                    ->orWhere('manufacturer', 'like', '%'.$search.'%')
                    ->orWhere('model_number', 'like', '%'.$search.'%');
            });

        $teamParts = $query->get();

        return response()->json($teamParts->map(function ($teamPart) {
            return [
                'id' => $teamPart->id,
                'part_type' => $teamPart->systemPart->part_type,
                'manufacturer' => $teamPart->systemPart->manufacturer,
                'model_number' => $teamPart->systemPart->model_number,
                'list_price' => $teamPart->systemPart->list_price,
                'multiplier' => $teamPart->multiplier,
                'static_price' => $teamPart->static_price,
                'team_price' => $teamPart->team_price,
            ];
        }));
    }

    public function associate(Request $request)
    {
        $user = $request->user();

        if (! $user->team_id) {
            return response()->json(['message' => 'User not assigned to a team'], 403);
        }

        $validator = Validator::make($request->all(), [
            'system_part_id' => 'required|exists:system_parts,id',
            'multiplier' => 'nullable|numeric|min:0',
            'static_price' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Ensure only multiplier OR static_price is set, not both
        if ($request->has('multiplier') && $request->has('static_price')) {
            if ($request->multiplier !== null && $request->static_price !== null) {
                return response()->json([
                    'message' => 'Cannot set both multiplier and static price. Use multiplier for calculation.',
                ], 422);
            }
        }

        // Check if already associated
        $exists = TeamPart::where('team_id', $user->team_id)
            ->where('system_part_id', $request->system_part_id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Part already associated with this team.',
            ], 422);
        }

        $systemPart = SystemPart::findOrFail($request->system_part_id);

        // If both are provided, use multiplier
        $multiplier = $request->multiplier;
        $staticPrice = $request->static_price;

        if ($multiplier !== null && $staticPrice !== null) {
            $staticPrice = null; // Use multiplier
        }

        $teamPart = TeamPart::create([
            'team_id' => $user->team_id,
            'system_part_id' => $request->system_part_id,
            'multiplier' => $multiplier,
            'static_price' => $staticPrice,
            'team_price' => 0, // Will be calculated in the model boot method
        ]);

        $teamPart->load('systemPart');

        return response()->json($teamPart, 201);
    }

    public function import(Request $request)
    {
        $user = $request->user();

        if (! $user->team_id) {
            return response()->json(['message' => 'User not assigned to a team'], 403);
        }

        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
        ]);

        $file = $request->file('file');
        $csv = Reader::createFromPath($file->getPathname(), 'r');
        $csv->setHeaderOffset(0);

        $records = $csv->getRecords();
        $imported = 0;
        $updated = 0;
        $errors = [];

        foreach ($records as $offset => $record) {
            try {
                $partType = trim($record['Part Type'] ?? '');
                $manufacturer = trim($record['Manufacturer'] ?? '');
                $modelNumber = trim($record['Model Number'] ?? '');
                $listPrice = floatval($record['List Price'] ?? 0);
                $multiplier = isset($record['Multiplier']) && ! empty(trim($record['Multiplier']))
                    ? floatval($record['Multiplier'])
                    : null;
                $staticPrice = isset($record['Static Price']) && ! empty(trim($record['Static Price']))
                    ? floatval($record['Static Price'])
                    : null;

                if (empty($manufacturer) || empty($modelNumber)) {
                    $errors[] = 'Row '.($offset + 2).': Missing manufacturer or model number';

                    continue;
                }

                // Find system part
                $systemPart = SystemPart::where('manufacturer', $manufacturer)
                    ->where('model_number', $modelNumber)
                    ->first();

                if (! $systemPart) {
                    $errors[] = 'Row '.($offset + 2).': System part not found';

                    continue;
                }

                // Use multiplier if both are provided
                if ($multiplier !== null && $staticPrice !== null) {
                    $staticPrice = null;
                }

                // Check if already associated
                $teamPart = TeamPart::where('team_id', $user->team_id)
                    ->where('system_part_id', $systemPart->id)
                    ->first();

                if ($teamPart) {
                    // Update
                    $teamPart->update([
                        'multiplier' => $multiplier,
                        'static_price' => $staticPrice,
                    ]);
                    $updated++;
                } else {
                    // Create
                    TeamPart::create([
                        'team_id' => $user->team_id,
                        'system_part_id' => $systemPart->id,
                        'multiplier' => $multiplier,
                        'static_price' => $staticPrice,
                        'team_price' => 0,
                    ]);
                    $imported++;
                }
            } catch (\Exception $e) {
                $errors[] = 'Row '.($offset + 2).': '.$e->getMessage();
            }
        }

        return response()->json([
            'message' => 'Import completed',
            'imported' => $imported,
            'updated' => $updated,
            'errors' => $errors,
        ]);
    }

    public function destroy(TeamPart $teamPart)
    {
        // Verify the team part belongs to the user's team
        if ($teamPart->team_id !== auth()->user()->team_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $teamPart->delete();

        return response()->json(['message' => 'Team part deleted successfully']);
    }
}
