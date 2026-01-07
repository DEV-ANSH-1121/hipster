# Laravel Assessment Solution

This is a complete Laravel solution implementing two tasks:

## Task A: CSV Import + Chunked Image Upload

### Features

- **CSV Import with Upsert**: Import products from CSV files (≥10,000 rows supported)
  - Upsert by SKU (unique identifier)
  - Tracks: total, created, updated, invalid, duplicates
  - Continues processing even if rows are invalid
  - Detects duplicate SKUs within the same CSV

- **Chunked Image Upload**: Drag-and-drop image upload with resumable chunks
  - Supports chunked uploads for large files
  - Resumable uploads (can resume interrupted uploads)
  - Checksum validation (SHA256)
  - Automatic variant generation (256px, 512px, 1024px) preserving aspect ratio
  - Primary image assignment
  - Idempotent attachment (re-attaching same upload = no-op)
  - Concurrency safe

### Architecture

```
app/
  Services/
    ProductImportService.php    # Handles CSV import logic
    ImageUploadService.php      # Handles chunked uploads and variants
  Repositories/
    ProductRepository.php       # Product data access
    ImageRepository.php         # Image data access
    UploadRepository.php        # Upload metadata access
  Actions/
    ProcessCsvRow.php          # Processes individual CSV rows
  DTOs/
    CsvImportResult.php        # Import result summary
    CsvRowData.php             # CSV row data structure
  Models/
    Product.php                # Product model
    Upload.php                 # Upload metadata model
    Image.php                  # Image model
  Jobs/
    GenerateImageVariants.php  # Queue job for variant generation
  Http/
    Controllers/
      ProductImportController.php
      ImageUploadController.php
    Requests/
      ImportCsvRequest.php
      InitiateUploadRequest.php
      UploadChunkRequest.php
      CompleteUploadRequest.php
      AttachImageRequest.php
```

### API Endpoints

- `POST /api/products/import` - Import CSV file
- `POST /api/uploads/initiate` - Start a new upload session
- `POST /api/uploads/chunk` - Upload a chunk
- `POST /api/uploads/complete` - Complete upload with checksum
- `POST /api/uploads/attach` - Attach completed upload to product

### Usage Example

```php
// CSV Import
$file = $request->file('csv');
$result = $productImportService->import($file->getRealPath());
// Returns: ['total' => 100, 'created' => 80, 'updated' => 15, 'invalid' => 3, 'duplicates' => 2]

// Image Upload Flow
// 1. Initiate
$upload = $imageUploadService->initiateUpload('image.jpg', 'image/jpeg', 5000000, 1000000);

// 2. Upload chunks
for ($i = 0; $i < $upload->total_chunks; $i++) {
    $chunk = getChunk($i);
    $imageUploadService->uploadChunk($upload->uuid, $i, base64_encode($chunk));
}

// 3. Complete
$checksum = calculateChecksum($fullFile);
$upload = $imageUploadService->completeUpload($upload->uuid, $checksum);

// 4. Attach to product
$image = $imageUploadService->attachToProduct($upload, $product, true);
```

### Tests

- `tests/Unit/ProductImportServiceTest.php` - Tests CSV upsert logic
- `tests/Unit/ImageUploadServiceTest.php` - Tests image processing

---

## Task B: User Discounts Package

A reusable Laravel package for managing user-level discounts with deterministic stacking.

### Features

- **Discount Management**: Assign and revoke discounts to users
- **Eligibility Checking**: Filters expired, inactive, and revoked discounts
- **Discount Application**: Applies discounts with stacking support
- **Usage Limits**: Enforces per-user usage caps
- **Audit Trail**: Tracks all discount actions
- **Events**: Fires events for assignment, revocation, and application
- **Configurable**: Stacking order, max cap, rounding

### Package Structure

```
packages/user-discounts/
  src/
    Models/
      Discount.php           # Discount model
      UserDiscount.php        # User-Discount pivot
      DiscountAudit.php       # Audit trail
    Events/
      DiscountAssigned.php
      DiscountRevoked.php
      DiscountApplied.php
    DiscountService.php      # Core service
    UserDiscountsServiceProvider.php
  database/migrations/
    create_discounts_table.php
    create_user_discounts_table.php
    create_discount_audits_table.php
  config/
    user-discounts.php        # Package configuration
  tests/
    DiscountServiceTest.php  # Comprehensive test suite
```

### Configuration

```php
// config/user-discounts.php
[
    'stacking_order' => 'desc',    // 'asc' or 'desc'
    'max_discount_cap' => 50.0,    // Maximum total discount %
    'rounding' => 2,                // Decimal places
]
```

### Usage Example

```php
use Hipster\UserDiscounts\DiscountService;
use Hipster\UserDiscounts\Models\Discount;

$service = new DiscountService();

// Assign discount
$discount = Discount::where('code', 'SAVE10')->first();
$service->assign($user, $discount);

// Check eligibility
$eligible = $service->eligibleFor($user);

// Apply discounts
$result = $service->apply($user, 100.0);
// Returns: [
//   'original_amount' => 100.0,
//   'discount_amount' => 30.0,
//   'final_amount' => 70.0,
//   'applied_discounts' => [...]
// ]

// Revoke discount
$service->revoke($user, $discount);
```

### Tests

Comprehensive PHPUnit test suite covering:
- Discount assignment and revocation
- Eligibility filtering (expired, inactive, revoked, usage limits)
- Discount stacking
- Max discount cap enforcement
- Usage count tracking
- Concurrency safety
- Audit trail creation
- Event dispatching

---

## Installation & Setup

1. **Install Dependencies**
   ```bash
   composer install
   ```

2. **Setup Environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Run Migrations**
   ```bash
   php artisan migrate
   ```

4. **Run Tests**
   ```bash
   php artisan test
   ```

## Design Decisions

### Task A

1. **Separation of Concerns**: Controllers only call services, services call repositories
2. **DTOs**: Used for data transfer objects to maintain type safety
3. **Actions**: Single responsibility classes for specific operations
4. **Transactions**: Used for atomic operations (CSV import, image attachment)
5. **Queue Jobs**: Image variant generation is queued for performance
6. **Idempotency**: Re-attaching same upload is a no-op
7. **Concurrency**: Database transactions and atomic increments ensure safety

### Task B

1. **Package Structure**: Follows Laravel package conventions
2. **Service Pattern**: Centralized business logic in DiscountService
3. **Events**: Decoupled event system for extensibility
4. **Audit Trail**: Complete history of all discount operations
5. **Deterministic Stacking**: Configurable order ensures consistent results
6. **Atomic Operations**: Database transactions prevent race conditions
7. **Usage Tracking**: Atomic increments prevent double-counting

## Code Quality

- ✅ Clean separation of concerns
- ✅ SOLID principles followed
- ✅ Laravel conventions respected
- ✅ Production-ready code
- ✅ Comprehensive test coverage
- ✅ Proper error handling
- ✅ Type hints and return types
- ✅ No business logic in controllers
