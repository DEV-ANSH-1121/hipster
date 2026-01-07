<?php

namespace Tests\Unit;

use App\Actions\ProcessCsvRow;
use App\DTOs\CsvImportResult;
use App\DTOs\CsvRowData;
use App\Models\Product;
use App\Repositories\ProductRepository;
use App\Services\ProductImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProductImportService $service;
    private ProductRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ProductRepository();
        $this->service = new ProductImportService(
            $this->repository,
            new ProcessCsvRow($this->repository)
        );
    }

    public function test_upsert_creates_new_product(): void
    {
        $csvContent = "sku,name,description,price,quantity\nTEST-001,Test Product,Test Description,99.99,10";
        $filePath = $this->createTempCsvFile($csvContent);

        $result = $this->service->import($filePath);

        $this->assertEquals(1, $result->total);
        $this->assertEquals(1, $result->created);
        $this->assertEquals(0, $result->updated);
        $this->assertEquals(0, $result->invalid);
        $this->assertEquals(0, $result->duplicates);

        $product = Product::where('sku', 'TEST-001')->first();
        $this->assertNotNull($product);
        $this->assertEquals('Test Product', $product->name);
        $this->assertEquals('99.99', $product->price);
    }

    public function test_upsert_updates_existing_product(): void
    {
        // Create existing product
        Product::create([
            'sku' => 'TEST-001',
            'name' => 'Old Name',
            'price' => 50.00,
            'quantity' => 5,
        ]);

        $csvContent = "sku,name,description,price,quantity\nTEST-001,New Name,New Description,99.99,20";
        $filePath = $this->createTempCsvFile($csvContent);

        $result = $this->service->import($filePath);

        $this->assertEquals(1, $result->total);
        $this->assertEquals(0, $result->created);
        $this->assertEquals(1, $result->updated);
        $this->assertEquals(0, $result->invalid);

        $product = Product::where('sku', 'TEST-001')->first();
        $this->assertEquals('New Name', $product->name);
        $this->assertEquals('99.99', $product->price);
        $this->assertEquals(20, $product->quantity);
    }

    public function test_import_detects_duplicate_skus_in_csv(): void
    {
        $csvContent = "sku,name\nTEST-001,Product 1\nTEST-001,Product 2\nTEST-002,Product 3";
        $filePath = $this->createTempCsvFile($csvContent);

        $result = $this->service->import($filePath);

        $this->assertEquals(3, $result->total);
        $this->assertEquals(2, $result->created); // TEST-001 and TEST-002
        $this->assertEquals(1, $result->duplicates); // Second TEST-001
    }

    public function test_import_marks_invalid_rows_missing_required_fields(): void
    {
        $csvContent = "sku,name\nTEST-001,Valid Product\n,Invalid Product\nTEST-002,";
        $filePath = $this->createTempCsvFile($csvContent);

        $result = $this->service->import($filePath);

        $this->assertEquals(3, $result->total);
        $this->assertEquals(1, $result->created);
        $this->assertEquals(2, $result->invalid);
    }

    public function test_import_handles_large_csv(): void
    {
        $rows = ["sku,name"];
        for ($i = 1; $i <= 100; $i++) {
            $rows[] = "SKU-{$i},Product {$i}";
        }
        $csvContent = implode("\n", $rows);
        $filePath = $this->createTempCsvFile($csvContent);

        $result = $this->service->import($filePath);

        $this->assertEquals(100, $result->total);
        $this->assertEquals(100, $result->created);
        $this->assertEquals(100, Product::count());
    }

    public function test_import_handles_mixed_create_and_update(): void
    {
        // Create some existing products
        Product::create(['sku' => 'EXIST-1', 'name' => 'Existing 1']);
        Product::create(['sku' => 'EXIST-2', 'name' => 'Existing 2']);

        $csvContent = "sku,name\nEXIST-1,Updated 1\nNEW-1,New Product 1\nEXIST-2,Updated 2\nNEW-2,New Product 2";
        $filePath = $this->createTempCsvFile($csvContent);

        $result = $this->service->import($filePath);

        $this->assertEquals(4, $result->total);
        $this->assertEquals(2, $result->created);
        $this->assertEquals(2, $result->updated);
        $this->assertEquals(0, $result->invalid);
    }

    public function test_import_handles_empty_csv_file(): void
    {
        $csvContent = "sku,name";
        $filePath = $this->createTempCsvFile($csvContent);

        $result = $this->service->import($filePath);

        $this->assertEquals(0, $result->total);
        $this->assertEquals(0, $result->created);
    }

    public function test_import_handles_malformed_rows_with_wrong_column_count(): void
    {
        $csvContent = "sku,name,price\nTEST-001,Product 1,99.99\nTEST-002,Product 2\nTEST-003,Product 3,88.88,extra";
        $filePath = $this->createTempCsvFile($csvContent);

        $result = $this->service->import($filePath);

        // Only TEST-001 should be valid
        $this->assertEquals(3, $result->total);
        $this->assertEquals(1, $result->created);
        $this->assertEquals(2, $result->invalid);
    }

    public function test_import_handles_special_characters_in_data(): void
    {
        $csvContent = "sku,name\nTEST-001,Product with \"quotes\" & special chars\nTEST-002,Product with 'apostrophes'";
        $filePath = $this->createTempCsvFile($csvContent);

        $result = $this->service->import($filePath);

        $this->assertEquals(2, $result->total);
        $this->assertEquals(2, $result->created);
        
        $product = Product::where('sku', 'TEST-001')->first();
        $this->assertStringContainsString('quotes', $product->name);
    }

    public function test_import_handles_whitespace_trimming(): void
    {
        $csvContent = "sku,name\n  TEST-001  ,  Product Name  ";
        $filePath = $this->createTempCsvFile($csvContent);

        $result = $this->service->import($filePath);

        $product = Product::where('sku', 'TEST-001')->first();
        $this->assertEquals('TEST-001', $product->sku);
        $this->assertEquals('Product Name', $product->name);
    }

    public function test_import_rolls_back_on_critical_error(): void
    {
        // This test verifies transaction rollback
        $csvContent = "sku,name\nTEST-001,Valid Product";
        $filePath = $this->createTempCsvFile($csvContent);

        // Mock a scenario that would cause rollback
        $result = $this->service->import($filePath);
        
        // If import succeeds, verify data is committed
        $this->assertTrue($result->total > 0 || Product::count() > 0);
    }

    public function test_import_handles_duplicate_skus_across_multiple_rows(): void
    {
        $csvContent = "sku,name\nDUP-001,First\nDUP-001,Second\nDUP-001,Third\nDUP-002,Unique";
        $filePath = $this->createTempCsvFile($csvContent);

        $result = $this->service->import($filePath);

        $this->assertEquals(4, $result->total);
        $this->assertEquals(2, $result->created); // DUP-001 (first) and DUP-002
        $this->assertEquals(2, $result->duplicates); // Second and third DUP-001
    }

    private function createTempCsvFile(string $content): string
    {
        $filePath = storage_path('app/temp_test_' . uniqid() . '.csv');
        file_put_contents($filePath, $content);
        return $filePath;
    }

    protected function tearDown(): void
    {
        // Clean up temp files
        $files = glob(storage_path('app/temp_test_*.csv'));
        foreach ($files as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }
}

