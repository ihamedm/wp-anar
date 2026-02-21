# Product Import System

## Overview

The Anar import system imports thousands of products from the Anar API into WooCommerce. It's designed to handle large-scale imports reliably using batch processing, duplicate prevention, and robust error handling.

**Core Principles:**
- Process large datasets without memory issues
- Prevent duplicate products through multi-layer locking
- Handle errors gracefully with automatic recovery
- Provide real-time progress tracking

## System Architecture

### Components

**1. Import Class** (`includes/Import.php`)

The main orchestrator and entry point for the entire import system.

**Responsibilities:**
- **Process Coordination**: Manages the complete import lifecycle from start to finish
- **Batch Management**: Splits product pages into small batches and processes them sequentially
- **Locking & Concurrency**: Implements multi-layer locking (page, batch, row) to prevent race conditions
- **Error Recovery**: Detects stuck processes, manages failed product tracking, and handles retry logic
- **Progress Monitoring**: Updates heartbeat, tracks processing status, and provides progress data
- **Cleanup**: Manages lock cleanup and expired batch lock removal

**Key Behaviors:**
- Runs as a scheduled cron job for long-running imports
- Processes one page at a time, breaking it into 5-product batches
- Sleeps between batches to reduce CPU load
- Automatically recovers from stuck or failed processes

**2. ProductManager Class** (`includes/wizard/ProductManager.php`)

Handles the WooCommerce-specific product creation and data transformation.

**Responsibilities:**
- **Data Transformation**: Converts Anar API product format to WooCommerce product structure
- **Product Creation**: Creates new WooCommerce products using WP/WC APIs
- **Product Updates**: Updates existing products when SKU already exists
- **Variation Handling**: Manages variable products with multiple variants (size, color, etc.)
- **Custom Table Mode**: Optionally inserts products into `wp_anar_products` table first for duplicate detection
- **Duplicate Detection**: Checks for existing products by SKU before creating new ones

**Key Behaviors:**
- Serializes API product data into WooCommerce-compatible format
- Handles both simple products (single variant) and variable products (multiple variants)
- Manages product images, galleries, categories, and attributes
- Uses WooCommerce's native meta fields for product tracking

**3. JobManager Class**

Provides visibility into import progress and manages job lifecycle.

**Responsibilities:**
- **Job Creation**: Initializes new import jobs with total product counts
- **Progress Tracking**: Records how many products are processed, created, updated, or failed
- **Time Estimation**: Calculates estimated completion time based on processing speed
- **Status Management**: Tracks job states (pending, processing, completed, failed)
- **Data Persistence**: Stores job data for UI display and monitoring

**Key Behaviors:**
- Creates a job record when import starts
- Updates progress counters after each batch
- Provides real-time data for admin UI dashboards
- Marks jobs as complete when all pages are processed

### Database Tables

- **`wp_anar`**: Stores legacy API responses (page snapshots used by classic cron import)
- **`wp_anar_products`**: Staging table for Import V2 (one row per SKU, status field, timestamps)
- **`wp_posts`** & **`wp_postmeta`**: WooCommerce products and metadata

## User Flow: From Wizard to Import

### Complete User Journey

The import process begins with a multi-step wizard that guides users through product import configuration:

**Entry Point**: `includes/admin/menu/products-wizard.php`

#### Step 1: Product List (`wizard/products.php`)
- **User Action**: Views list of products available from Anar API
- **What Happens**: 
  - Displays paginated list of products (10 per page)
  - Shows product details: image, name, description, attributes, prices, shipping info
  - If API data is expired, automatically fetches fresh categories and attributes
- **User Action**: Clicks "مرحله بعد" (Next Step) button
- **Technical**: JavaScript handles step navigation via `AnarHandler.move_to_step()`

#### Step 2: Category Mapping (`wizard/categories.php`)
- **User Action**: Maps Anar product categories to WooCommerce product categories
- **What Happens**:
  - Displays all Anar categories from API
  - Shows dropdown of existing WooCommerce product categories
  - User can map each Anar category to a WooCommerce category (optional)
  - If no mapping is done, products use Anar's original categories
- **User Action**: Clicks "مرحله بعد" (Next Step) button
- **Technical**: Form submission via AJAX (`awca_handle_pair_categories_ajax`) saves mappings to `categoryMap` option

#### Step 3: Attribute Mapping (`wizard/attributes.php`)
- **User Action**: Maps Anar product attributes to WooCommerce product attributes
- **What Happens**:
  - Displays all Anar attributes from API
  - Shows dropdown of existing WooCommerce product attributes
  - User can map each Anar attribute to a WooCommerce attribute (optional)
  - If no mapping is done, products use Anar's original attributes
- **User Action**: Clicks "مرحله بعد" (Next Step) button
- **Technical**: Form submission via AJAX (`awca_handle_pair_attributes_ajax`) saves mappings to `attributeMap` option

#### Step 4: Final Import (`wizard/final.php`)
- **User Action**: Clicks "ذخیره نهایی" (Final Save) button
- **What Happens**:
  - JavaScript triggers AJAX call to `awca_import_v2_fetch_products`
  - API pages (30 products) are streamed sequentially
  - Every product is stored as an individual row in `wp_anar_products` with `status = pending`
  - If a previous background job is running, it is cancelled before new data is staged
  - Progress bar shows how many products have been staged so far
- **Technical Flow**:
  1. `AjaxHandlers::fetch_products()` fetches API pages and writes rows via `Import\ProductsRepository`
  2. Table is truncated on page 1 to guarantee clean staging
  3. Job state (if any) is cancelled via `BackgroundImporter::cancel_active_job()` to prevent race conditions
  4. Response returns how many records were staged for real-time UI updates

#### Background Processing (Action Scheduler)
- **Trigger**: User clicks "شروع ساخت محصولات" on Step 5 or re-enters the page with staged data
- **What Happens**:
  - `BackgroundImporter` creates a JobManager record and enqueues the `anar_import_v2_process_batch` Action Scheduler hook
  - Each action processes a small batch from `wp_anar_products` (default 5 SKUs)
  - `ProductCreatorV2` transforms data and creates/updates WooCommerce products
  - Processed rows are deleted from `wp_anar_products`; failed rows are marked `failed`
  - JobManager progress counters are updated after every batch
- **User Experience**: The UI polls job status via AJAX and shows live stats; users can safely leave the page while processing continues

### Technical Import Flow

#### Three-Phase Process (Import V2)

```
1. Data Preparation
   API Response → wp_anar_products (one row per SKU)

2. Processing
   Action Scheduler Batch → ProductCreatorV2 → WooCommerce

3. Completion
   All Rows Deleted → JobManager status completed → UI notified
```

#### Detailed Technical Flow

1. **Data Preparation**: API pages stream into `wp_anar_products`, guaranteeing O(1) access per SKU
2. **Job Scheduling**: BackgroundImporter creates a JobManager record and enqueues Action Scheduler
3. **Batch Claim**: Each scheduled action fetches the next pending batch (default 5 SKUs)
4. **Product Creation**: `ProductCreatorV2` mirrors legacy logic (duplicate detection, variation handling, meta updates)
5. **Progress Update**: JobManager counters (processed/created/existing/failed) are updated after every batch
6. **Cleanup**: Processed SKUs are deleted; failed ones remain with `status = failed` for diagnostics
7. **Completion**: When no pending SKUs remain the job is marked completed and the UI displays final stats

## Import Modes

### Standard Mode (Default)
- Direct WooCommerce product creation
- Uses WordPress post system
- Real-time product creation
- Standard WooCommerce management

### Simple Mode (Custom Table)
- Import V2 now always stages data in `wp_anar_products`
- Duplicate protection leverages the table's primary key (`anar_sku`)
- Background worker deletes rows as soon as WooCommerce creation succeeds
- Failed rows stay in the table for inspection, allowing retries without re-fetching

**Configuration**: Legacy option `anar_conf_feat__import_type` still toggles fallback behaviors, but Import V2 defaults to the staging-table workflow

## Batch Processing Strategy

### Why Batching?

Batch processing solves several critical problems:

- **Memory Management**: Process small chunks to prevent memory exhaustion
- **Error Isolation**: Failed batches don't break entire import
- **Progress Granularity**: Show accurate progress updates
- **CPU Load Management**: Sleep intervals prevent server overload
- **Transaction Safety**: Each batch has its own transaction

### Batch Configuration

- **Batch Size**: 5 products per batch (prevents memory issues)
- **Sleep Interval**: 1 second between batches (reduces CPU load)
- **Max Execution Time**: 4 minutes per cron job (prevents timeouts)

## Duplicate Prevention Strategy

### Multi-Layer Protection

Duplicates are prevented through four layers:

1. **Page-Level Locking**: Prevents concurrent page processing
2. **Batch-Level Locking**: Prevents same batch from being processed twice (race condition protection)
3. **Product-Level Checking**: Uses `_anar_sku` meta to detect existing products
4. **Failed Product Tracking**: Prevents infinite retry loops

### How Batch Locking Works

Each batch gets a unique lock key: `anar_batch_processing_{page}_{batch_index}`

- Before processing: Check if lock exists
- During processing: Set lock with 5-minute timeout
- After processing: Release lock
- Automatic cleanup: Expired locks are removed

This prevents the critical race condition where concurrent cron jobs process the same batch simultaneously.

## Error Handling Strategy

### Failed Product Tracking

- Tracks products that fail to import
- Max 3 retry attempts per product
- Skips permanently failed products
- Clears tracking on successful creation

### Stuck Process Detection

- Monitors process heartbeat (5-minute timeout)
- Detects long-running processes (3-minute timeout)
- Automatically resets stuck processes
- Cleans up expired batch locks

### Transaction Management

- Each product creation wrapped in database transaction
- Automatic rollback on errors
- Prevents partial/corrupted data

## Locking System

### Lock Types

1. **Cron Lock**: Prevents new imports while processing
2. **Row Lock**: Prevents multiple rows being processed simultaneously
3. **Batch Lock**: Prevents duplicate batch processing (most granular)
4. **Heartbeat**: Monitors active processes for stuck detection

All locks use WordPress transients with automatic expiration.

## Performance Optimization

### Memory Management
- Small batch sizes
- Object cleanup after each batch
- Forced garbage collection
- Efficient database queries

### CPU Load Reduction
- Sleep intervals between batches
- Limited products per batch
- Max execution time limits
- Progress monitoring overhead minimization

## Monitoring & Logging

### Log Levels
- **Debug**: Detailed processing information
- **Info**: General progress updates
- **Warning**: Non-critical issues
- **Error**: Critical failures

### Progress Tracking
- Total products count
- Processed products count
- Estimated completion time
- Current processing status
- Real-time job updates

## Key Concepts for Developers

### Why This Architecture?

**Problem**: Importing thousands of products can:
- Exhaust server memory
- Create duplicate products (race conditions)
- Fail without recovery
- Provide no visibility into progress

**Solution**: This system addresses each problem:
- Batch processing prevents memory issues
- Multi-layer locking prevents duplicates
- Error tracking enables retry logic
- Job management provides real-time progress

### Critical Design Decisions

1. **Batch Locking**: The most important feature preventing duplicate products in concurrent environments
2. **Custom Table Mode**: Optional optimization for very large imports (10k+ products)
3. **Failed Product Tracking**: Prevents infinite loops on problematic products
4. **Heartbeat Monitoring**: Enables automatic recovery from stuck processes

### When to Use Each Mode

**Standard Mode**: 
- Most installations
- < 5,000 products
- Standard server resources

**Simple Mode**:
- Very large imports (10k+ products)
- Limited server resources
- Need faster initial data loading

## Code Organization

### User-Facing Files (Wizard Flow)

```
includes/admin/menu/
  ├── products-wizard.php           # Main wizard entry point
  └── wizard/
      ├── products.php              # Step 1: Product list display
      ├── categories.php            # Step 2: Category mapping
      ├── attributes.php            # Step 3: Attribute mapping
      └── final.php                 # Step 4: Import trigger

assets/js/
  ├── products.js                   # Handles wizard navigation & import trigger
  └── general.js                    # Wizard step navigation utilities
```

### Backend Processing Files

```
includes/
  ├── Import.php                    # Main import orchestrator
  ├── wizard/
  │   └── ProductManager.php        # WooCommerce product creation & API fetching
  ├── JobManager.php                # Progress tracking
  ├── core/
  │   └── CronJobs.php              # Cron job scheduling & execution
  └── ApiDataHandler.php            # API communication & data storage
```

### Complete Flow Diagram

```
USER JOURNEY                          BACKEND PROCESSING
─────────────────────────────────────────────────────────────────

[Wizard Entry]
  products-wizard.php
        │
        ├─► Step 1: Product List
        │   wizard/products.php
        │   (View products, click Next)
        │
        ├─► Step 2: Category Mapping
        │   wizard/categories.php
        │   (Map categories, click Next)
        │   └─► AJAX: awca_handle_pair_categories_ajax
        │       └─► Save to categoryMap option
        │
        ├─► Step 3: Attribute Mapping
        │   wizard/attributes.php
        │   (Map attributes, click Next)
        │   └─► AJAX: awca_handle_pair_attributes_ajax
        │       └─► Save to attributeMap option
        │
        └─► Step 4: Final Import
            wizard/final.php
            (Click "ذخیره نهایی")
            │
            └─► JavaScript: products.js
                └─► AJAX: awca_get_products_save_on_db_ajax
                    │
                    └─► ProductManager::fetch_and_save_products_from_api_to_db_ajax()
                        │
                        ├─► Fetch page 1 from API → Store in wp_anar (processed=0)
                        ├─► Fetch page 2 from API → Store in wp_anar (processed=0)
                        ├─► ... (continue for all pages)
                        └─► On last page:
                            ├─► Set awca_product_save_lock = true
                            └─► Import::unlock_create_products_cron()
                                │
                                └─► [BACKGROUND PROCESSING STARTS]
                                    │
                                    └─► Cron: anar_import_products (every 1 minute)
                                        │
                                        └─► CronJobs::create_products_job()
                                            │
                                            └─► Import::process_the_row()
                                                │
                                                ├─► Check locks (cron, row, batch)
                                                ├─► Get unprocessed page from wp_anar
                                                ├─► Split into batches (5 products each)
                                                ├─► Process each batch:
                                                │   ├─► Set batch lock
                                                │   ├─► Create/update WooCommerce products
                                                │   ├─► Update progress (JobManager)
                                                │   └─► Release batch lock
                                                ├─► Mark page as processed
                                                └─► Repeat until all pages done
                                                    │
                                                    └─► Import::complete()
                                                        ├─► Notify Anar API
                                                        ├─► Lock cron (prevent new imports)
                                                        └─► Cleanup & finalize
```

## Summary

The import system is production-ready for large-scale product catalogs. It provides a user-friendly wizard interface that guides users through product import configuration, then automatically processes imports in the background using a robust, multi-layered approach that ensures data integrity while maintaining performance.

### Key Features

1. **User-Friendly Wizard**: Four-step wizard guides users from product selection to import initiation
2. **Background Processing**: Import runs automatically via cron jobs, allowing users to close the page
3. **Multi-Layer Locking**: Prevents duplicate products through page, batch, and row-level locks
4. **Error Recovery**: Automatic detection and recovery from stuck processes
5. **Progress Tracking**: Real-time progress updates via JobManager
6. **Batch Processing**: Efficient memory management through small batch sizes

### Key Innovation

The granular batch locking system (`anar_batch_processing_{page}_{batch_index}`) is the most critical feature, preventing duplicate products in concurrent environments where multiple cron jobs might run simultaneously.

For implementation details, refer to code comments in the respective class files.
