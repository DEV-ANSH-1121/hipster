<?php

namespace App\Services;

use App\Actions\ProcessCsvRow;
use App\DTOs\CsvImportResult;
use App\DTOs\CsvRowData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductImportService
{
    private const REQUIRED_FIELDS = ['sku', 'name'];

    public function __construct(
        private ProcessCsvRow $processCsvRow
    ) {}

    public function import(string $filePath): CsvImportResult
    {
        $result = new CsvImportResult();
        $seenSkus = [];
        $handle = fopen($filePath, 'r');

        if (!$handle) {
            throw new \RuntimeException('Unable to open CSV file');
        }

        // Read header
        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            throw new \RuntimeException('CSV file is empty or invalid');
        }

        $headerMap = array_flip($headers);
        $this->validateHeaders($headerMap);

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle)) !== false) {
                $result->total++;

                if (count($row) !== count($headers)) {
                    $result->invalid++;
                    continue;
                }

                $rowData = array_combine($headers, $row);
                $rowData = array_map('trim', $rowData);

                // Validate required fields
                if (!$this->isValidRow($rowData)) {
                    $result->invalid++;
                    continue;
                }

                $sku = $rowData['sku'];
                $isDuplicate = isset($seenSkus[$sku]);
                $seenSkus[$sku] = true;

                if ($isDuplicate) {
                    $result->duplicates++;
                    continue;
                }

                try {
                    $csvRowData = CsvRowData::fromArray($rowData);
                    $processResult = $this->processCsvRow->execute($csvRowData, false);

                    if ($processResult['status'] === 'created') {
                        $result->created++;
                    } elseif ($processResult['status'] === 'updated') {
                        $result->updated++;
                    } else {
                        $result->invalid++;
                    }
                } catch (\Exception $e) {
                    Log::error('Error processing CSV row', [
                        'row' => $rowData,
                        'error' => $e->getMessage(),
                    ]);
                    $result->invalid++;
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        } finally {
            fclose($handle);
        }

        return $result;
    }

    private function validateHeaders(array $headerMap): void
    {
        foreach (self::REQUIRED_FIELDS as $field) {
            if (!isset($headerMap[$field])) {
                throw new \InvalidArgumentException("Missing required column: {$field}");
            }
        }
    }

    private function isValidRow(array $rowData): bool
    {
        foreach (self::REQUIRED_FIELDS as $field) {
            if (empty($rowData[$field])) {
                return false;
            }
        }
        return true;
    }
}

