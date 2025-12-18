<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;

class ImportSystemParts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes

    public function __construct(public string $path) {}

    public function handle(): void
    {
        DB::disableQueryLog();

        // Resolve the real stored file path safely
        $fullPath = Storage::path($this->path);

        if (! file_exists($fullPath)) {
            // Fail fast if file is missing
            throw new \RuntimeException("Import file not found: {$this->path}");
        }

        $csv = Reader::createFromPath($fullPath, 'r');
        $csv->setHeaderOffset(0);
        $csv->setDelimiter(',');
        $csv->skipEmptyRecords();

        $batchInsert = [];
        $batchSize = 500;

        foreach ($csv->getRecords() as $record) {
            $record = array_change_key_case(
                array_map('trim', $record),
                CASE_LOWER
            );

            $partType = $record['part type'] ?? $record['part_type'] ?? null;
            $manufacturer = $record['manufacturer'] ?? null;
            $modelNumber = $record['model number'] ?? $record['model_number'] ?? null;

            if (! $partType || ! $manufacturer || ! $modelNumber) {
                continue;
            }

            $batchInsert[] = [
                'part_type' => $partType,
                'manufacturer' => $manufacturer,
                'model_number' => $modelNumber,
                'list_price' => (float) ($record['list price'] ?? $record['list_price'] ?? 0),
                'is_active' => ($record['active'] ?? 'Y') === 'Y',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($batchInsert) >= $batchSize) {
                $this->upsert($batchInsert);
                $batchInsert = [];
            }
        }

        if (! empty($batchInsert)) {
            $this->upsert($batchInsert);
        }

        // Clean up stored file after successful import
        Storage::delete($this->path);
    }

    private function upsert(array $rows): void
    {
        DB::table('system_parts')->upsert(
            $rows,
            ['manufacturer', 'model_number'], // unique constraint
            ['part_type', 'list_price', 'is_active', 'updated_at']
        );
    }
}
