# Task A: CSV Import + Chunked Image Upload - Complete Documentation

## 📋 Table of Contents
1. [Overview](#overview)
2. [Architecture & Design Decisions](#architecture--design-decisions)
3. [File Structure & Explanation](#file-structure--explanation)
4. [Code Flow & How It Works](#code-flow--how-it-works)
5. [API Endpoints](#api-endpoints)
6. [Test Cases](#test-cases)
7. [Interview Q&A Preparation](#interview-qa-preparation)

---

## Overview

This task implements a production-ready CSV import system for products and a chunked/resumable image upload system with automatic variant generation. The solution follows clean architecture principles with clear separation of concerns.

### Key Features
- ✅ CSV import with upsert by SKU (handles 10,000+ rows)
- ✅ Duplicate detection within CSV
- ✅ Invalid row handling (continues processing)
- ✅ Comprehensive counters (total, created, updated, invalid, duplicates)
- ✅ Chunked/resumable image uploads
- ✅ SHA256 checksum validation
- ✅ Automatic image variant generation (256px, 512px, 1024px)
- ✅ Aspect ratio preservation
- ✅ Idempotent operations
- ✅ Concurrency safe

---

## Architecture & Design Decisions

### Why This Architecture?

**1. Separation of Concerns**
- **Controllers**: Handle HTTP requests/responses only
- **Services**: Contain business logic
- **Repositories**: Handle data access
- **Actions**: Single-purpose operations
- **DTOs**: Type-safe data transfer objects

**2. Repository Pattern**
- **Why**: Abstracts database operations, makes testing easier, allows switching databases
- **Benefit**: Can mock repositories in tests, change implementation without affecting services

**3. Service Layer**
- **Why**: Centralizes business logic, reusable across controllers/commands/jobs
- **Benefit**: Single source of truth for business rules

**4. DTOs (Data Transfer Objects)**
- **Why**: Type safety, clear contracts, prevents data corruption
- **Benefit**: IDE autocomplete, catch errors at compile time

**5. Actions Pattern**
- **Why**: Single Responsibility Principle - one action = one operation
- **Benefit**: Easy to test, easy to reuse, easy to understand

---

## File Structure & Explanation

### 📁 Database Migrations

#### `database/migrations/2026_01_06_042528_create_products_table.php`
**Purpose**: Creates the products table
**Why**: Products are the core domain entity, unique by SKU
**Key Fields**:
- `sku` (unique): Business identifier for products
- `name`, `description`, `price`, `quantity`: Product attributes
- `is_active`: Soft delete flag
- Index on `sku` for fast lookups

**Interview Answer**: "I created a products table with SKU as unique identifier because SKU is the business key. I added an index on SKU for performance since we'll be doing frequent lookups during CSV import upsert operations."

#### `database/migrations/2026_01_06_042534_create_uploads_table.php`
**Purpose**: Tracks chunked upload metadata
**Why**: Need to track upload progress, chunks, and status before final file assembly
**Key Fields**:
- `uuid`: Unique identifier for each upload session
- `total_chunks`, `uploaded_chunks`: Progress tracking
- `metadata`: JSON field to track which chunks are uploaded (for resumability)
- `checksum`: SHA256 hash for integrity verification
- `status`: pending, uploading, completed, failed

**Interview Answer**: "The uploads table stores metadata about chunked uploads. I use UUID instead of auto-increment ID because UUIDs are safer for public-facing APIs and prevent enumeration attacks. The metadata JSON field tracks which chunks have been uploaded, enabling resumable uploads."

#### `database/migrations/2026_01_06_042539_create_images_table.php`
**Purpose**: Stores image records and variants
**Why**: Need to track original images and their generated variants
**Key Fields**:
- `variant`: original, 256, 512, 1024
- `is_primary`: Only one primary image per product
- `width`, `height`: Dimensions for display
- Foreign keys to `upload_id` and `product_id`

**Interview Answer**: "I store variants as separate records rather than JSON because it's easier to query, index, and manage. The is_primary flag ensures only one primary image per product, and I use a composite index on product_id and is_primary for fast primary image lookups."

---

### 📁 Models

#### `app/Models/Product.php`
**Purpose**: Product Eloquent model
**Why**: Provides type-safe access to product data
**Key Features**:
- `images()` relationship: One product has many images
- `primaryImage()` relationship: Scoped to primary images only
- Casts: Ensures price is decimal, quantity is integer

**Interview Answer**: "I use Eloquent relationships because they're type-safe and provide a clean API. The primaryImage relationship uses a scope to filter only primary images, making it easy to get the main product image."

#### `app/Models/Upload.php`
**Purpose**: Upload metadata model
**Why**: Tracks chunked upload sessions
**Key Features**:
- Auto-generates UUID in `boot()` method
- `isComplete()` helper method: Checks if upload is finished
- `images()` relationship: Links to final image records

**Interview Answer**: "I use model boot methods to auto-generate UUIDs because it ensures every upload has a unique identifier before being saved. The isComplete() method encapsulates the business rule for when an upload is considered finished."

#### `app/Models/Image.php`
**Purpose**: Image model for storing image records
**Why**: Represents both original and variant images
**Key Features**:
- Relationships to `upload` and `product`
- Variant field distinguishes original from resized versions

**Interview Answer**: "I store variants as separate records because it allows independent management, easier querying, and better performance when loading specific sizes."

---

### 📁 Repositories

#### `app/Repositories/ProductRepository.php`
**Purpose**: Data access layer for products
**Why**: Abstracts database operations, makes testing easier
**Key Methods**:
- `findBySku()`: Find product by SKU
- `upsertBySku()`: Create or update by SKU (used in CSV import)
- `existsBySku()`: Check if SKU exists

**Interview Answer**: "I use repositories to separate data access from business logic. This makes the code testable - I can mock repositories in tests. The upsertBySku method uses Laravel's updateOrCreate which is atomic and handles race conditions."

#### `app/Repositories/ImageRepository.php`
**Purpose**: Data access for images
**Why**: Centralizes image-related database operations
**Key Methods**:
- `setPrimaryImage()`: Atomically sets one image as primary, unsets others
- `findByUploadAndProduct()`: Checks if upload already attached (idempotency)
- `createVariant()`: Creates variant records

**Interview Answer**: "The setPrimaryImage method uses a database transaction to ensure atomicity - it unmarks all other primary images and marks the new one in a single operation. This prevents race conditions where two images could both be primary."

#### `app/Repositories/UploadRepository.php`
**Purpose**: Data access for uploads
**Why**: Manages upload metadata operations
**Key Methods**:
- `findByUuid()`: Find upload by UUID
- `incrementChunks()`: Atomically increment chunk counter

**Interview Answer**: "I use incrementChunks to atomically update the chunk counter. This prevents race conditions when multiple chunks arrive simultaneously."

---

### 📁 Services

#### `app/Services/ProductImportService.php`
**Purpose**: Handles CSV import business logic
**Why**: Centralizes import logic, reusable
**Key Features**:
- Reads CSV line by line (memory efficient for large files)
- Tracks seen SKUs in memory for duplicate detection
- Uses transactions for atomicity
- Continues processing on invalid rows
- Returns comprehensive result summary

**Interview Answer**: "I read CSV line-by-line using fgetcsv() instead of loading entire file into memory. This allows handling files with 10,000+ rows without memory issues. I track duplicates in a PHP array during import rather than querying the database each time for performance."

**Key Design Decisions**:
1. **Transaction wrapping**: Entire import in one transaction - if critical error, rollback all
2. **Duplicate detection**: In-memory array (`$seenSkus`) - faster than DB queries
3. **Invalid row handling**: Mark invalid but continue - requirement says don't stop
4. **Error logging**: Log errors but continue processing

#### `app/Services/ImageUploadService.php`
**Purpose**: Handles chunked uploads and image processing
**Why**: Complex logic needs to be centralized
**Key Features**:
- Chunked upload management
- Checksum validation
- Variant generation with aspect ratio preservation
- Idempotent attachment

**Interview Answer**: "The service handles three main flows: initiating uploads, uploading chunks, and completing uploads. I store chunks temporarily and reassemble them on completion. The checksum validation ensures file integrity - if checksum doesn't match, the upload fails."

**Key Design Decisions**:
1. **Chunk storage**: Store chunks in temp directory, reassemble on completion
2. **Checksum**: SHA256 hash - industry standard, secure
3. **Variant generation**: Uses GD library, preserves aspect ratio
4. **Idempotency**: Check if upload already attached before creating new image

---

### 📁 Actions

#### `app/Actions/ProcessCsvRow.php`
**Purpose**: Processes a single CSV row
**Why**: Single Responsibility Principle - one action, one purpose
**Key Features**:
- Takes CsvRowData DTO
- Returns status: created, updated, duplicate, error
- Uses repository for data access

**Interview Answer**: "I use the Action pattern for single-purpose operations. This makes the code easier to test - I can test ProcessCsvRow independently. It also makes the code more readable - the name clearly states what it does."

---

### 📁 DTOs (Data Transfer Objects)

#### `app/DTOs/CsvImportResult.php`
**Purpose**: Type-safe result object
**Why**: Ensures consistent return structure
**Key Features**:
- Public properties for counters
- `toArray()` method for JSON responses

**Interview Answer**: "DTOs provide type safety and clear contracts. Instead of returning arrays that could have typos, DTOs ensure the structure is always correct. The toArray() method makes it easy to convert to JSON for API responses."

#### `app/DTOs/CsvRowData.php`
**Purpose**: Represents a single CSV row
**Why**: Type-safe data structure
**Key Features**:
- `fromArray()` static factory method
- Type hints for all properties
- Handles optional fields with defaults

**Interview Answer**: "CsvRowData ensures type safety. Instead of passing arrays around, we pass typed objects. The fromArray() method handles data transformation and provides a single place to validate CSV row structure."

---

### 📁 Controllers

#### `app/Http/Controllers/ProductImportController.php`
**Purpose**: HTTP endpoint for CSV import
**Why**: Handles HTTP concerns only
**Key Features**:
- Uses form request for validation
- Calls service (no business logic)
- Returns JSON response

**Interview Answer**: "Controllers should be thin - they handle HTTP concerns only. All business logic is in the service. This makes controllers easy to test and easy to understand."

#### `app/Http/Controllers/ImageUploadController.php`
**Purpose**: HTTP endpoints for image upload flow
**Why**: Handles upload API endpoints
**Key Features**:
- Four endpoints: initiate, chunk, complete, attach
- Each endpoint validates input and calls service
- Returns consistent JSON responses

**Interview Answer**: "I split the upload flow into four endpoints because each has different validation requirements. The initiate endpoint needs file metadata, chunk endpoint needs chunk data, complete needs checksum, and attach needs product ID."

---

### 📁 Form Requests

#### `app/Http/Requests/ImportCsvRequest.php`
**Purpose**: Validates CSV file upload
**Why**: Separates validation from controller logic
**Key Rules**:
- File required, must be CSV, max 10MB

**Interview Answer**: "Form requests encapsulate validation logic. This keeps controllers clean and makes validation reusable. Laravel automatically returns 422 errors if validation fails."

#### `app/Http/Requests/InitiateUploadRequest.php`, `UploadChunkRequest.php`, `CompleteUploadRequest.php`, `AttachImageRequest.php`
**Purpose**: Validate each upload step
**Why**: Different endpoints need different validation
**Key Rules**:
- Initiate: filename, mime_type, total_size, chunk_size
- Chunk: uuid, chunk_index, chunk_data (base64)
- Complete: uuid, checksum (SHA256 hex, 64 chars)
- Attach: upload_uuid, product_id, optional set_as_primary

**Interview Answer**: "Each form request validates specific requirements for that endpoint. The CompleteUploadRequest validates checksum is exactly 64 characters (SHA256 hex). The AttachImageRequest validates product exists using Laravel's exists rule."

---

### 📁 Jobs

#### `app/Jobs/GenerateImageVariants.php`
**Purpose**: Queue job for async variant generation
**Why**: Image processing is CPU-intensive, should be async
**Key Features**:
- Implements ShouldQueue
- Dispatched after image attachment
- Processes variants in background

**Interview Answer**: "I use a queue job for variant generation because image processing is CPU-intensive and can take time. By queuing it, the API responds immediately and variants are generated in the background. This improves user experience."

---

### 📁 Routes

#### `routes/api.php`
**Purpose**: API route definitions
**Why**: Centralized route definitions
**Key Routes**:
- POST `/api/products/import` - CSV import
- POST `/api/uploads/initiate` - Start upload
- POST `/api/uploads/chunk` - Upload chunk
- POST `/api/uploads/complete` - Complete upload
- POST `/api/uploads/attach` - Attach to product

**Interview Answer**: "I use API routes with a prefix for versioning and organization. All routes are RESTful and follow Laravel conventions."

---

## Code Flow & How It Works

### CSV Import Flow

```
1. User uploads CSV file
   ↓
2. ImportCsvRequest validates file (CSV, max 10MB)
   ↓
3. ProductImportController receives request
   ↓
4. Controller calls ProductImportService::import()
   ↓
5. Service opens CSV file, reads header
   ↓
6. Service validates headers (must have SKU, name)
   ↓
7. Service starts database transaction
   ↓
8. For each row:
   a. Validate required fields
   b. Check if SKU seen before (duplicate detection)
   c. Create CsvRowData DTO
   d. Call ProcessCsvRow action
   e. Action calls ProductRepository::upsertBySku()
   f. Repository uses updateOrCreate (atomic upsert)
   g. Update counters
   ↓
9. Commit transaction
   ↓
10. Return CsvImportResult with counters
```

**Interview Answer**: "The CSV import uses a transaction to ensure atomicity. If any critical error occurs, all changes are rolled back. I process rows one-by-one to handle memory efficiently. Duplicate detection happens in-memory for performance."

### Image Upload Flow

```
1. Client calls /api/uploads/initiate
   ↓
2. ImageUploadService::initiateUpload() creates Upload record
   ↓
3. Returns UUID and total_chunks to client
   ↓
4. Client uploads chunks sequentially:
   - POST /api/uploads/chunk with uuid, chunk_index, chunk_data
   - Service stores chunk in temp directory
   - Updates uploaded_chunks counter
   ↓
5. After all chunks uploaded, client calls /api/uploads/complete
   ↓
6. Service reassembles chunks from temp directory
   ↓
7. Calculates SHA256 checksum
   ↓
8. Validates checksum matches expected
   ↓
9. Stores final file, deletes temp chunks
   ↓
10. Updates upload status to 'completed'
   ↓
11. Client calls /api/uploads/attach
   ↓
12. Service checks if already attached (idempotency)
   ↓
13. Creates Image record, sets as primary if requested
   ↓
14. Dispatches GenerateImageVariants job
   ↓
15. Job generates 256px, 512px, 1024px variants
```

**Interview Answer**: "The chunked upload flow allows resumable uploads. If a chunk fails, the client can retry just that chunk. The metadata JSON tracks which chunks are uploaded. On completion, I reassemble chunks and validate checksum before storing the final file."

---

## API Endpoints

### POST `/api/products/import`
**Request**: Multipart form with `file` field
**Response**:
```json
{
  "success": true,
  "data": {
    "total": 100,
    "created": 80,
    "updated": 15,
    "invalid": 3,
    "duplicates": 2
  }
}
```

### POST `/api/uploads/initiate`
**Request**:
```json
{
  "filename": "product.jpg",
  "mime_type": "image/jpeg",
  "total_size": 5000000,
  "chunk_size": 1000000
}
```
**Response**:
```json
{
  "success": true,
  "data": {
    "uuid": "550e8400-e29b-41d4-a716-446655440000",
    "total_chunks": 5
  }
}
```

### POST `/api/uploads/chunk`
**Request**:
```json
{
  "uuid": "550e8400-e29b-41d4-a716-446655440000",
  "chunk_index": 0,
  "chunk_data": "base64encodedchunkdata"
}
```

### POST `/api/uploads/complete`
**Request**:
```json
{
  "uuid": "550e8400-e29b-41d4-a716-446655440000",
  "checksum": "sha256hexstring64chars"
}
```

### POST `/api/uploads/attach`
**Request**:
```json
{
  "upload_uuid": "550e8400-e29b-41d4-a716-446655440000",
  "product_id": 1,
  "set_as_primary": true
}
```

---

## Test Cases

### ProductImportServiceTest (12 tests, all passing ✅)

#### 1. `test_upsert_creates_new_product`
**Purpose**: Verify new products are created
**What it tests**: Basic upsert functionality - creating new records
**Expected**: Product created, counters show 1 created

#### 2. `test_upsert_updates_existing_product`
**Purpose**: Verify existing products are updated
**What it tests**: Upsert updates existing SKU
**Expected**: Product updated, counters show 1 updated

#### 3. `test_import_detects_duplicate_skus_in_csv`
**Purpose**: Verify duplicate detection within CSV
**What it tests**: Same SKU appears twice in CSV
**Expected**: First occurrence created, second marked as duplicate

#### 4. `test_import_marks_invalid_rows_missing_required_fields`
**Purpose**: Verify invalid rows are handled
**What it tests**: Rows missing SKU or name
**Expected**: Invalid rows counted, processing continues

#### 5. `test_import_handles_large_csv`
**Purpose**: Verify performance with large files
**What it tests**: 100 rows (scalable to 10,000+)
**Expected**: All rows processed correctly

#### 6. `test_import_handles_mixed_create_and_update`
**Purpose**: Verify mixed operations
**What it tests**: Some products exist, some are new
**Expected**: Correct counts for created and updated

#### 7. `test_import_handles_empty_csv_file`
**Purpose**: Edge case - empty file
**What it tests**: CSV with only headers
**Expected**: No errors, zero counts

#### 8. `test_import_handles_malformed_rows_with_wrong_column_count`
**Purpose**: Verify malformed row handling
**What it tests**: Rows with wrong number of columns
**Expected**: Malformed rows marked invalid

#### 9. `test_import_handles_special_characters_in_data`
**Purpose**: Verify special character handling
**What it tests**: Quotes, apostrophes in product names
**Expected**: Special characters preserved correctly

#### 10. `test_import_handles_whitespace_trimming`
**Purpose**: Verify whitespace handling
**What it tests**: Leading/trailing spaces in CSV
**Expected**: Whitespace trimmed from values

#### 11. `test_import_rolls_back_on_critical_error`
**Purpose**: Verify transaction rollback
**What it tests**: Transaction integrity
**Expected**: On error, changes are rolled back

#### 12. `test_import_handles_duplicate_skus_across_multiple_rows`
**Purpose**: Verify multiple duplicates
**What it tests**: Same SKU appears 3+ times
**Expected**: First created, others marked duplicate

### ImageUploadServiceTest (12 tests, all passing ✅)

#### 1. `test_generates_image_variants_with_correct_dimensions`
**Purpose**: Verify variant generation works
**What it tests**: Variant generation method exists and works
**Expected**: Variants created with correct dimensions

#### 2. `test_checksum_validation_blocks_invalid_upload`
**Purpose**: Verify checksum validation
**What it tests**: Wrong checksum provided
**Expected**: Exception thrown, upload marked failed

#### 3. `test_attach_to_product_is_idempotent`
**Purpose**: Verify idempotency
**What it tests**: Attaching same upload twice
**Expected**: Same image record returned, no duplicate

#### 4. `test_initiate_upload_creates_upload_record`
**Purpose**: Verify upload initiation
**What it tests**: Upload record creation
**Expected**: UUID generated, metadata stored correctly

#### 5. `test_upload_chunk_increments_counter`
**Purpose**: Verify chunk tracking
**What it tests**: Chunk upload increments counter
**Expected**: uploaded_chunks incremented

#### 6. `test_upload_chunk_is_idempotent`
**Purpose**: Verify chunk idempotency
**What it tests**: Uploading same chunk twice
**Expected**: No corruption, handles gracefully

#### 7. `test_complete_upload_with_valid_checksum`
**Purpose**: Verify successful completion
**What it tests**: Valid checksum provided
**Expected**: Upload marked completed, checksum stored

#### 8. `test_complete_upload_handles_already_completed`
**Purpose**: Verify no-op on re-completion
**What it tests**: Completing already completed upload
**Expected**: Returns without error (idempotent)

#### 9. `test_attach_to_product_creates_image_record`
**Purpose**: Verify image record creation
**What it tests**: Attaching upload to product
**Expected**: Image record created with correct relationships

#### 10. `test_attach_to_product_sets_primary_image`
**Purpose**: Verify primary image logic
**What it tests**: Setting new primary image
**Expected**: Old primary unmarked, new one marked

#### 11. `test_attach_to_product_throws_exception_if_upload_incomplete`
**Purpose**: Verify validation
**What it tests**: Attaching incomplete upload
**Expected**: Exception thrown

#### 12. `test_generate_variants_creates_three_variants`
**Purpose**: Verify variant generation
**What it tests**: All three variants created
**Expected**: 256px, 512px, 1024px variants exist

---

## Interview Q&A Preparation

### Architecture Questions

**Q: Why did you use Repository pattern?**
**A**: "Repositories abstract data access, making code testable and maintainable. I can mock repositories in tests, and if we need to switch databases, only repositories change. It also follows the Dependency Inversion Principle - services depend on abstractions, not concrete implementations."

**Q: Why separate Services from Controllers?**
**A**: "Controllers handle HTTP concerns - request/response, validation. Services contain business logic. This separation means I can reuse services in commands, jobs, or other contexts. It also makes testing easier - I can test business logic without HTTP layer."

**Q: Why use DTOs instead of arrays?**
**A**: "DTOs provide type safety. Arrays can have typos in keys, wrong types, missing fields. DTOs catch these errors at development time. They also serve as documentation - the class definition shows exactly what data is expected."

**Q: Why use Actions pattern?**
**A**: "Actions follow Single Responsibility Principle. Each action does one thing. This makes code easier to test, easier to understand, and easier to reuse. ProcessCsvRow only processes rows - it doesn't handle file reading or error reporting."

### Performance Questions

**Q: How does your solution handle 10,000+ row CSV files?**
**A**: "I read CSV line-by-line using fgetcsv() instead of loading entire file. This keeps memory usage constant regardless of file size. I also use database transactions for atomicity and batch operations. Duplicate detection uses an in-memory array for O(1) lookups instead of database queries."

**Q: How do you prevent memory issues with large images?**
**A**: "Chunked uploads break large files into smaller pieces. Each chunk is processed independently. The final assembly happens only after all chunks are received. Variant generation is queued to run asynchronously, so it doesn't block the API response."

**Q: How do you ensure concurrency safety?**
**A**: "I use database transactions for atomic operations. For chunk counting, I use atomic increment operations. For primary image setting, I use transactions to ensure only one primary image exists. The upsert operation uses updateOrCreate which is atomic at the database level."

### Design Questions

**Q: Why store chunks temporarily instead of appending directly?**
**A**: "Temporary storage allows resumable uploads. If a chunk fails, we can retry just that chunk. It also allows checksum validation before final storage - we can verify integrity before committing. Finally, it prevents partial files if upload is abandoned."

**Q: Why generate variants asynchronously?**
**A**: "Image processing is CPU-intensive and can take seconds. By queuing it, the API responds immediately with the original image. Variants are generated in the background, improving user experience. If variant generation fails, we can retry without affecting the original upload."

**Q: How do you ensure idempotency?**
**A**: "For CSV import, I check if SKU exists before processing. For image attachment, I check if upload is already attached to the product. For chunk uploads, I allow re-uploading chunks safely. For completion, I check if already completed before processing."

### Error Handling Questions

**Q: What happens if CSV import fails halfway?**
**A**: "The entire import is wrapped in a transaction. If a critical error occurs, all changes are rolled back. However, invalid rows don't cause rollback - they're marked invalid and processing continues, as per requirements."

**Q: What happens if checksum validation fails?**
**A**: "The upload status is set to 'failed' and an exception is thrown. The client receives an error response. The chunks remain in temp storage, allowing the client to retry the upload if needed."

**Q: How do you handle duplicate SKUs?**
**A**: "I track seen SKUs in memory during import. If a SKU appears twice in the CSV, the first occurrence is processed normally, subsequent occurrences are marked as duplicates and counted separately. This prevents processing the same SKU multiple times in one import."

### Testing Questions

**Q: What testing strategy did you use?**
**A**: "I wrote unit tests for services, which are the core business logic. I test happy paths, edge cases, and error scenarios. Tests use RefreshDatabase to ensure clean state. I mock external dependencies where appropriate."

**Q: How do you test chunked uploads?**
**A**: "I use Storage::fake() to simulate file storage. I test chunk upload, reassembly, checksum validation, and error scenarios. I verify idempotency by uploading chunks multiple times."

**Q: How do you test CSV import with 10,000 rows?**
**A**: "I test with 100 rows to verify logic, then the same code handles 10,000+. The line-by-line reading approach scales linearly. I verify counters are correct and database operations are efficient."

---

## Key Takeaways for Interview

1. **Clean Architecture**: Clear separation of concerns, testable, maintainable
2. **Performance**: Handles large files efficiently, uses transactions, optimizes queries
3. **Reliability**: Idempotent operations, error handling, checksum validation
4. **Scalability**: Async processing, chunked uploads, efficient memory usage
5. **Best Practices**: SOLID principles, Laravel conventions, type safety

---

## Running Tests

```bash
# Run all Task A tests
php artisan test --filter ProductImportServiceTest
php artisan test --filter ImageUploadServiceTest

# Run specific test
php artisan test --filter test_upsert_creates_new_product
```

**All 24 tests pass successfully! ✅**
