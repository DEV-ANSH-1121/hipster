# Task B: User Discounts Package - Complete Documentation

## 📋 Table of Contents
1. [Overview](#overview)
2. [Package Architecture & Design](#package-architecture--design)
3. [File Structure & Explanation](#file-structure--explanation)
4. [Code Flow & How It Works](#code-flow--how-it-works)
5. [Usage Examples](#usage-examples)
6. [Test Cases](#test-cases)
7. [Interview Q&A Preparation](#interview-qa-preparation)

---

## Overview

This is a production-ready Laravel package for managing user-level discounts with deterministic stacking, usage limits, and comprehensive audit trails. The package follows Laravel package conventions and can be installed via Composer.

### Key Features
- ✅ Assign and revoke discounts to users
- ✅ Eligibility checking (expired, inactive, revoked, usage limits)
- ✅ Deterministic discount stacking (configurable order)
- ✅ Max discount cap enforcement
- ✅ Per-user usage limits
- ✅ Concurrent apply safety (atomic operations)
- ✅ Complete audit trail
- ✅ Event system (DiscountAssigned, DiscountRevoked, DiscountApplied)
- ✅ Configurable (stacking order, max cap, rounding)

---

## Package Architecture & Design

### Why a Package?

**1. Reusability**
- Can be used across multiple Laravel projects
- Installable via Composer
- Versioned independently

**2. Encapsulation**
- All discount logic in one place
- Clear boundaries
- Easy to maintain

**3. Testability**
- Isolated from main application
- Can be tested independently
- Clear dependencies

### Package Structure

```
packages/user-discounts/
├── composer.json              # Package definition
├── src/
│   ├── Models/               # Eloquent models
│   ├── Events/               # Event classes
│   ├── DiscountService.php   # Core service
│   └── UserDiscountsServiceProvider.php
├── database/migrations/       # Package migrations
├── config/                   # Configuration
└── tests/                    # Test suite
```

---

## File Structure & Explanation

### 📁 Package Configuration

#### `packages/user-discounts/composer.json`
**Purpose**: Defines the package for Composer
**Why**: Makes package installable, defines autoloading
**Key Features**:
- PSR-4 autoloading: `Hipster\UserDiscounts\`
- Service provider registration
- Dependencies: Illuminate packages
- Dev dependencies: Orchestra Testbench for testing

**Interview Answer**: "I structured it as a Composer package so it can be reused across projects. The composer.json defines PSR-4 autoloading and registers the service provider so Laravel auto-discovers it."

#### `packages/user-discounts/src/UserDiscountsServiceProvider.php`
**Purpose**: Laravel service provider for the package
**Why**: Registers package with Laravel
**Key Features**:
- Loads migrations from package
- Publishes config file
- Merges default config

**Interview Answer**: "The service provider boot() method loads migrations and publishes config. The register() method merges default config so users can override settings. This follows Laravel package conventions."

#### `packages/user-discounts/config/user-discounts.php`
**Purpose**: Package configuration
**Why**: Makes behavior configurable
**Key Settings**:
- `stacking_order`: 'asc' or 'desc' - order discounts are applied
- `max_discount_cap`: Maximum total discount percentage
- `rounding`: Decimal places for rounding

**Interview Answer**: "I made these configurable because different businesses have different rules. Some want discounts applied smallest-first, others largest-first. The max cap prevents excessive discounts, and rounding ensures consistent currency handling."

---

### 📁 Database Migrations

#### `packages/user-discounts/database/migrations/2026_01_06_000001_create_discounts_table.php`
**Purpose**: Creates discounts master table
**Why**: Stores discount definitions
**Key Fields**:
- `code`: Unique discount code
- `percentage`: Discount percentage (decimal 5,2)
- `is_active`: Enable/disable discount
- `starts_at`, `ends_at`: Validity period
- Index on `is_active`, `starts_at`, `ends_at` for eligibility queries

**Interview Answer**: "The discounts table stores master discount data. I use decimal(5,2) for percentage to allow up to 999.99%. The indexes optimize eligibility queries - we frequently filter by active status and date ranges."

#### `packages/user-discounts/database/migrations/2026_01_06_000002_create_user_discounts_table.php`
**Purpose**: User-Discount pivot table
**Why**: Many-to-many relationship with additional fields
**Key Fields**:
- `user_id`, `discount_id`: Foreign keys
- `usage_count`: How many times applied
- `usage_limit`: Maximum allowed uses (null = unlimited)
- `is_active`: Can be deactivated per-user
- `assigned_at`, `revoked_at`: Timestamps
- Unique constraint on `user_id` + `discount_id`

**Interview Answer**: "I use a pivot table instead of a simple many-to-many because we need additional fields like usage_count and usage_limit. The unique constraint prevents duplicate assignments. The revoked_at timestamp allows soft revocation while maintaining history."

#### `packages/user-discounts/database/migrations/2026_01_06_000003_create_discount_audits_table.php`
**Purpose**: Audit trail for all discount operations
**Why**: Compliance and debugging
**Key Fields**:
- `action`: assigned, revoked, applied
- `original_amount`, `discount_amount`, `final_amount`: Financial tracking
- `metadata`: JSON for additional context (stacking order, percentages)
- Indexes on `user_id`, `discount_id`, `action` for queries

**Interview Answer**: "The audit table provides complete history. Every assignment, revocation, and application is logged. This is crucial for compliance, debugging, and analytics. The metadata JSON stores context like which discounts were stacked together."

---

### 📁 Models

#### `packages/user-discounts/src/Models/Discount.php`
**Purpose**: Discount master model
**Why**: Type-safe access to discount data
**Key Features**:
- `isActive()`: Business logic method - checks dates and status
- `userDiscounts()`: Relationship to user assignments
- `audits()`: Relationship to audit records
- Casts: percentage as decimal, dates as Carbon

**Interview Answer**: "The isActive() method encapsulates the business rule for discount validity. It checks is_active flag, starts_at date (not started yet), and ends_at date (expired). This logic is centralized, so if rules change, we update one place."

#### `packages/user-discounts/src/Models/UserDiscount.php`
**Purpose**: User-Discount pivot model
**Why**: Represents assignment with additional data
**Key Features**:
- `canUse()`: Checks if discount can be used
- `user()`: Relationship to user model
- `discount()`: Relationship to discount model
- Validates: active, not revoked, within usage limit, discount is active

**Interview Answer**: "The canUse() method encapsulates eligibility logic. It checks four conditions: user discount is active, not revoked, within usage limit, and master discount is active. This makes eligibility checking simple and consistent."

#### `packages/user-discounts/src/Models/DiscountAudit.php`
**Purpose**: Audit trail model
**Why**: Tracks all discount operations
**Key Features**:
- Stores action type and financial amounts
- Metadata JSON for context
- Relationships to user and discount

**Interview Answer**: "The audit model provides complete traceability. Every operation is logged with amounts and context. The metadata JSON stores flexible data like stacking order, which discounts were applied together, etc."

---

### 📁 Service

#### `packages/user-discounts/src/DiscountService.php`
**Purpose**: Core business logic for discounts
**Why**: Centralizes all discount operations
**Key Methods**:
- `assign()`: Assign discount to user
- `revoke()`: Revoke discount from user
- `eligibleFor()`: Get eligible discounts for user
- `apply()`: Apply discounts to an amount

**Interview Answer**: "The service contains all business logic. It's stateless and can be used from controllers, commands, or jobs. All methods use transactions for atomicity, ensuring data consistency."

#### Method Details:

**`assign($user, Discount $discount)`**
- Uses `firstOrCreate()` for idempotency
- Fires `DiscountAssigned` event
- Returns UserDiscount model

**Interview Answer**: "The assign method uses firstOrCreate() which is idempotent - calling it twice returns the same record. This prevents duplicate assignments. The event allows other parts of the system to react to assignments."

**`revoke($user, Discount $discount)`**
- Finds active assignment
- Sets `is_active = false`, `revoked_at = now()`
- Fires `DiscountRevoked` event
- Returns boolean (false if not found)

**Interview Answer**: "Revocation is soft - we don't delete the record, we mark it inactive and set revoked_at. This preserves history. The method returns false if discount wasn't assigned, allowing callers to handle gracefully."

**`eligibleFor($user)`**
- Gets all user discounts
- Filters by `canUse()` method
- Returns Collection of eligible UserDiscounts

**Interview Answer**: "Eligibility checking filters out expired, inactive, revoked, and usage-limit-exceeded discounts. I use Eloquent's filter() method for clean code. The result is a Collection that can be further processed."

**`apply($user, float $amount)`**
- Gets eligible discounts
- Sorts by percentage (configurable order)
- Applies discounts up to max cap
- Atomically increments usage counts
- Creates audit records
- Fires events
- Returns result array

**Interview Answer**: "The apply method is the most complex. It handles stacking, respects max cap, and ensures atomicity. I use database transactions and atomic increments to prevent race conditions. Each discount application creates an audit record and fires an event."

---

### 📁 Events

#### `packages/user-discounts/src/Events/DiscountAssigned.php`
**Purpose**: Fired when discount assigned
**Why**: Allows other systems to react
**Use Cases**: Send email, update cache, log to external system

**Interview Answer**: "Events provide loose coupling. When a discount is assigned, listeners can send welcome emails, update caches, or sync to external systems. The event contains the UserDiscount and Discount models for context."

#### `packages/user-discounts/src/Events/DiscountRevoked.php`
**Purpose**: Fired when discount revoked
**Why**: Notify other systems of revocation
**Use Cases**: Send notification, update analytics

**Interview Answer**: "Revocation events allow systems to react - maybe send a notification to the user, update analytics dashboards, or sync to external CRM systems."

#### `packages/user-discounts/src/Events/DiscountApplied.php`
**Purpose**: Fired when discount applied
**Why**: Track applications in real-time
**Use Cases**: Analytics, fraud detection, reporting

**Interview Answer**: "Application events fire for each discount applied. This allows real-time tracking, fraud detection (unusual patterns), and analytics. The event contains the audit record with full financial details."

---

## Code Flow & How It Works

### Assign Discount Flow

```
1. User calls DiscountService::assign($user, $discount)
   ↓
2. Service starts database transaction
   ↓
3. Uses firstOrCreate() to find or create UserDiscount
   - If exists: Returns existing record (idempotent)
   - If new: Creates with is_active=true, assigned_at=now()
   ↓
4. Fires DiscountAssigned event
   ↓
5. Commits transaction
   ↓
6. Returns UserDiscount model
```

**Interview Answer**: "The assign flow is idempotent - calling it multiple times returns the same record. This prevents duplicate assignments. The transaction ensures atomicity, and the event allows other systems to react."

### Apply Discount Flow

```
1. User calls DiscountService::apply($user, $amount)
   ↓
2. Service gets eligible discounts via eligibleFor()
   ↓
3. Filters out expired, inactive, revoked, usage-limit-exceeded
   ↓
4. Sorts discounts by percentage (asc or desc, configurable)
   ↓
5. Starts database transaction
   ↓
6. For each discount (up to max cap):
   a. Calculate discount amount
   b. Check if exceeds max cap
   c. Apply discount
   d. Atomically increment usage_count
   e. Create audit record
   f. Fire DiscountApplied event
   ↓
7. Calculate final amount
   ↓
8. Commit transaction
   ↓
9. Return result array with amounts and applied discounts
```

**Interview Answer**: "The apply flow is deterministic - same inputs always produce same outputs. Sorting ensures consistent order. Atomic increments prevent race conditions. Each discount application is logged and events are fired for real-time tracking."

### Eligibility Check Flow

```
1. Service gets all UserDiscounts for user
   ↓
2. Eager loads discount relationships
   ↓
3. Filters collection:
   - is_active = true
   - revoked_at = null
   - usage_count < usage_limit (if limit exists)
   - discount->isActive() = true
   ↓
4. Returns filtered Collection
```

**Interview Answer**: "Eligibility checking happens in memory after loading from database. I use Eloquent's filter() method for clean code. The canUse() method on UserDiscount encapsulates the logic, making it testable and reusable."

---

## Usage Examples

### Basic Usage

```php
use Hipster\UserDiscounts\DiscountService;
use Hipster\UserDiscounts\Models\Discount;

$service = new DiscountService();

// Create a discount
$discount = Discount::create([
    'code' => 'SAVE10',
    'name' => '10% Off',
    'percentage' => 10.0,
    'is_active' => true,
]);

// Assign to user
$userDiscount = $service->assign($user, $discount);

// Check eligibility
$eligible = $service->eligibleFor($user);

// Apply discounts
$result = $service->apply($user, 100.0);
// Returns: [
//   'original_amount' => 100.0,
//   'discount_amount' => 10.0,
//   'final_amount' => 90.0,
//   'applied_discounts' => [
//     ['discount_id' => 1, 'code' => 'SAVE10', 'percentage' => 10.0, 'amount' => 10.0]
//   ]
// ]

// Revoke discount
$service->revoke($user, $discount);
```

### Stacking Example

```php
// Assign multiple discounts
$discount1 = Discount::create(['code' => 'DISC10', 'percentage' => 10.0]);
$discount2 = Discount::create(['code' => 'DISC20', 'percentage' => 20.0]);

$service->assign($user, $discount1);
$service->assign($user, $discount2);

// Apply - discounts stack
$result = $service->apply($user, 100.0);
// Returns: [
//   'original_amount' => 100.0,
//   'discount_amount' => 30.0,  // 10% + 20%
//   'final_amount' => 70.0,
//   'applied_discounts' => [
//     ['code' => 'DISC10', 'amount' => 10.0],
//     ['code' => 'DISC20', 'amount' => 20.0]
//   ]
// ]
```

### Max Cap Example

```php
// Configure max cap to 25%
config(['user-discounts.max_discount_cap' => 25.0]);

// Assign discounts totaling 50%
$service->assign($user, $discount1); // 20%
$service->assign($user, $discount2); // 30%

// Apply - capped at 25%
$result = $service->apply($user, 100.0);
// Returns: [
//   'discount_amount' => 25.0,  // Capped, not 50%
//   'final_amount' => 75.0
// ]
```

### Usage Limit Example

```php
// Assign discount with usage limit
$discount = Discount::create(['code' => 'LIMITED', 'percentage' => 10.0]);
$userDiscount = $service->assign($user, $discount);
$userDiscount->update(['usage_limit' => 2]);

// First application works
$result1 = $service->apply($user, 100.0); // Works

// Second application works
$result2 = $service->apply($user, 100.0); // Works

// Third application - discount no longer eligible
$result3 = $service->apply($user, 100.0); // No discount applied
```

---

## Test Cases

### DiscountServiceTest (23 tests, all passing ✅)

#### Core Functionality Tests

**1. `test_assign_creates_user_discount`**
- **Purpose**: Verify assignment creates record
- **Tests**: Assignment creates UserDiscount, fires event
- **Expected**: Record created, event dispatched

**2. `test_assign_is_idempotent`**
- **Purpose**: Verify idempotency
- **Tests**: Calling assign twice returns same record
- **Expected**: Same UserDiscount ID returned

**3. `test_revoke_deactivates_user_discount`**
- **Purpose**: Verify revocation works
- **Tests**: Revoke sets is_active=false, revoked_at=now(), fires event
- **Expected**: Discount deactivated, event dispatched

**4. `test_revoke_nonexistent_discount_returns_false`**
- **Purpose**: Edge case - revoking unassigned discount
- **Tests**: Revoking discount that wasn't assigned
- **Expected**: Returns false, no error

#### Eligibility Tests

**5. `test_eligibleFor_excludes_expired_discounts`**
- **Purpose**: Verify expired discounts excluded
- **Tests**: Discount with ends_at in past
- **Expected**: Not in eligible list

**6. `test_eligibleFor_excludes_inactive_discounts`**
- **Purpose**: Verify inactive discounts excluded
- **Tests**: Discount with is_active=false
- **Expected**: Not in eligible list

**7. `test_eligibleFor_excludes_revoked_discounts`**
- **Purpose**: Verify revoked discounts excluded
- **Tests**: UserDiscount with revoked_at set
- **Expected**: Not in eligible list

**8. `test_eligibleFor_enforces_usage_limit`**
- **Purpose**: Verify usage limits enforced
- **Tests**: Discount with usage_count >= usage_limit
- **Expected**: Not in eligible list

**9. `test_apply_handles_discount_not_yet_started`**
- **Purpose**: Verify future discounts excluded
- **Tests**: Discount with starts_at in future
- **Expected**: Not eligible

**10. `test_apply_handles_discount_ended`**
- **Purpose**: Verify ended discounts excluded
- **Tests**: Discount with ends_at in past
- **Expected**: Not eligible

#### Application Tests

**11. `test_apply_calculates_discount_correctly`**
- **Purpose**: Verify basic discount calculation
- **Tests**: 10% discount on 100.0
- **Expected**: 10.0 discount, 90.0 final

**12. `test_apply_stacks_discounts_correctly`**
- **Purpose**: Verify stacking works
- **Tests**: 10% + 20% discounts
- **Expected**: 30% total discount

**13. `test_apply_respects_max_discount_cap`**
- **Purpose**: Verify max cap enforced
- **Tests**: 20% + 30% discounts with 25% cap
- **Expected**: Capped at 25%

**14. `test_apply_stacking_order_asc`**
- **Purpose**: Verify stacking order configurable
- **Tests**: Apply with asc order
- **Expected**: Discounts applied in ascending order

**15. `test_apply_rounds_correctly`**
- **Purpose**: Verify rounding works
- **Tests**: 33.33% discount
- **Expected**: Rounded to 2 decimal places

**16. `test_apply_with_no_eligible_discounts`**
- **Purpose**: Edge case - no discounts
- **Tests**: User with no eligible discounts
- **Expected**: No discount applied, amounts unchanged

**17. `test_apply_respects_usage_limit_during_application`**
- **Purpose**: Verify usage limit checked during apply
- **Tests**: Apply discount with limit=1, apply twice
- **Expected**: First works, second doesn't include discount

**18. `test_apply_handles_zero_amount`**
- **Purpose**: Edge case - zero amount
- **Tests**: Apply discounts to 0.0
- **Expected**: No errors, zero discount

**19. `test_apply_handles_very_large_amount`**
- **Purpose**: Edge case - large numbers
- **Tests**: Apply to 999999.99
- **Expected**: Calculates correctly

#### Usage Tracking Tests

**20. `test_apply_increments_usage_count`**
- **Purpose**: Verify usage tracking
- **Tests**: Apply discount, check usage_count
- **Expected**: Incremented by 1

**21. `test_apply_is_idempotent_with_concurrent_requests`**
- **Purpose**: Verify concurrency safety
- **Tests**: Apply same discount twice rapidly
- **Expected**: Both increment usage correctly

#### Audit Tests

**22. `test_apply_creates_audit_records`**
- **Purpose**: Verify audit trail
- **Tests**: Apply discount, check audit table
- **Expected**: Audit record created with correct amounts

**23. `test_apply_creates_separate_audit_for_each_discount`**
- **Purpose**: Verify separate audits for stacked discounts
- **Tests**: Apply two discounts
- **Expected**: Two audit records created

---

## Interview Q&A Preparation

### Package Design Questions

**Q: Why did you create this as a package instead of part of the main app?**
**A**: "Packages are reusable across projects. This discount system could be used in multiple Laravel applications. Packages also provide clear boundaries and can be versioned independently. It makes the code more maintainable and testable."

**Q: How does your package integrate with Laravel?**
**A**: "I use a Service Provider that Laravel auto-discovers via composer.json. The provider loads migrations, publishes config, and merges default settings. This follows Laravel package conventions, making it easy for users to install and configure."

**Q: Why use migrations in the package?**
**A**: "Package migrations are loaded automatically when the package is installed. Users can publish them if they want to customize, but by default they run automatically. This ensures the database schema is always correct."

### Business Logic Questions

**Q: How do you ensure discount stacking is deterministic?**
**A**: "I sort discounts by percentage in a consistent order (configurable asc/desc). The same discounts always sort the same way, so application order is always the same. This ensures same inputs produce same outputs."

**Q: How do you prevent double-counting usage in concurrent requests?**
**A**: "I use atomic database increments with a WHERE clause checking the current usage_count. This ensures only one request can increment at a time. Database-level atomicity prevents race conditions."

**Q: Why do you check eligibility before applying?**
**A**: "Eligibility checking filters out expired, inactive, revoked, and usage-limit-exceeded discounts. This prevents applying invalid discounts. The check happens once at the start, then discounts are applied in order."

**Q: How does max discount cap work?**
**A**: "As discounts are applied, I track the total percentage. If adding the next discount would exceed the cap, I cap it at the remaining amount. If the cap is already reached, I stop applying discounts."

### Technical Questions

**Q: Why use transactions in the apply method?**
**A**: "Transactions ensure atomicity. Either all discounts are applied and usage counts incremented, or none are. This prevents partial application if an error occurs. It also ensures audit records are created consistently."

**Q: How do you handle rounding?**
**A**: "I round discount amounts and final amounts to the configured decimal places (default 2). This ensures currency amounts are always in the correct format. Rounding happens at each step to prevent accumulation errors."

**Q: Why store audit records separately?**
**A**: "Audit records provide complete history and compliance. Each application creates a record with original amount, discount amount, and final amount. The metadata JSON stores context like which discounts were stacked together."

**Q: How do events help with extensibility?**
**A**: "Events allow other systems to react without modifying the package. Listeners can send emails, update caches, sync to external systems, or trigger analytics. This keeps the package focused while allowing integration."

### Testing Questions

**Q: What testing strategy did you use?**
**A**: "I wrote comprehensive unit tests covering all methods, edge cases, and error scenarios. Tests use RefreshDatabase to ensure clean state. I test idempotency, concurrency, edge cases like zero amounts, and verify events are fired."

**Q: How do you test concurrent operations?**
**A**: "I simulate concurrent applies by calling the method multiple times rapidly. I verify usage counts increment correctly and no double-counting occurs. The atomic increments in the code ensure this works correctly."

**Q: How do you test eligibility filtering?**
**A**: "I create discounts with different states (expired, inactive, revoked, usage limit exceeded) and verify only eligible ones are returned. I test each filter condition independently and together."

### Design Pattern Questions

**Q: Why use a Service class instead of putting logic in models?**
**A**: "Services contain business logic that operates across multiple models. The apply method needs Discount, UserDiscount, and DiscountAudit models. Services also make the code testable - I can test business logic without database."

**Q: Why use Events instead of direct method calls?**
**A**: "Events provide loose coupling. The package doesn't need to know about email systems or analytics. Listeners can be added without modifying the package. This follows the Open/Closed Principle."

**Q: Why use DTOs/arrays for return values?**
**A**: "The apply method returns a structured array with original_amount, discount_amount, final_amount, and applied_discounts. This provides a clear contract. In PHP 8+, I could use a DTO class, but arrays work well and are flexible."

---

## Key Takeaways for Interview

1. **Package Design**: Follows Laravel conventions, reusable, versioned
2. **Business Logic**: Deterministic, idempotent, atomic operations
3. **Performance**: Efficient queries, atomic increments, transaction optimization
4. **Reliability**: Transactions, concurrency safety, comprehensive error handling
5. **Extensibility**: Events, configurable behavior, clear interfaces
6. **Testing**: Comprehensive coverage, edge cases, concurrency tests

---

## Running Tests

```bash
# Run package tests
php artisan test packages/user-discounts/tests/DiscountServiceTest.php

# Run specific test
php artisan test --filter test_apply_stacks_discounts_correctly
```

**All 23 tests pass successfully! ✅**

---

## Installation & Setup

```bash
# Add to composer.json (if publishing)
"repositories": [
    {
        "type": "path",
        "url": "./packages/user-discounts"
    }
],
"require": {
    "hipster/user-discounts": "*"
}

# Install
composer require hipster/user-discounts

# Publish config (optional)
php artisan vendor:publish --tag=user-discounts-config

# Run migrations
php artisan migrate
```

---

## Configuration

```php
// config/user-discounts.php
return [
    'stacking_order' => 'desc',    // 'asc' or 'desc'
    'max_discount_cap' => 50.0,    // Maximum total discount %
    'rounding' => 2,                // Decimal places
];
```

---

## Summary

This package provides a complete, production-ready discount system with:
- ✅ Clean architecture
- ✅ Comprehensive testing
- ✅ Configurable behavior
- ✅ Event system
- ✅ Audit trail
- ✅ Concurrency safety
- ✅ Deterministic stacking

Perfect for e-commerce, SaaS, or any application needing user-level discounts!
