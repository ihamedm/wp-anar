# WP-Anar Plugin Documentation

## Table of Contents
1. [Plugin Overview](#plugin-overview)
2. [Core Systems](#core-systems)
3. [Data Management](#data-management)
4. [User Interface](#user-interface)
5. [File Structure](#file-structure)
6. [Integration Points](#integration-points)

---

## Plugin Overview

The WP-Anar plugin is a comprehensive WordPress dropshipping solution that integrates with the Anar external service to import, manage, and sync products with WooCommerce. The plugin serves as a bridge between the Anar dropshipping platform and WordPress/WooCommerce, enabling users to automatically import their selected products from the Anar dashboard and manage them as WooCommerce products.

### Main Goals
- **Product Import**: Automatically import user-selected products from Anar service dashboard into WooCommerce
- **Data Synchronization**: Keep WooCommerce products synchronized with Anar service data
- **Custom Shipping**: Provide Anar-specific shipping methods for checkout
- **Order Management**: Create orders on Anar system based on WooCommerce orders
- **Reporting & Analytics**: Provide comprehensive reports and system status

### Core Features
- **API Integration**: Fetches product data from Anar external service via API
- **Batch Processing**: Processes products in pages (30 products per page) with cron job system
- **Data Storage**: Saves raw API responses in custom WordPress table (`wp_anar`)
- **Product Creation**: Converts Anar product data to WooCommerce products
- **Image Management**: Downloads and manages product images
- **Sync Strategies**: Multiple synchronization approaches for keeping products updated
- **Custom Shipping**: Anar-specific shipping methods for checkout
- **Order Processing**: Creates orders on Anar system from WooCommerce orders
- **Admin Interface**: Settings pages, reports, and management tools

---

## Core Systems

### 1. Import System

The import system handles the complete process of fetching product data from Anar API and creating WooCommerce products, including data storage, product creation, and error handling.

#### **Core Components:**

##### **Import.php - Main Import Class**
- **Purpose**: Orchestrates the entire import process from API data to WooCommerce products
- **Key Features**:
  - Processes raw API data stored in `wp_anar` table
  - Manages batch processing with cron jobs (30 products per page)
  - Implements job management for progress tracking
  - Handles failed product tracking and retry logic
  - Provides progress monitoring and completion notifications
  - Manages image downloading for products
  - Implements stuck process detection and recovery

##### **ProductManager.php - Product Creation Engine**
- **Purpose**: Handles the creation and management of WooCommerce products from Anar data
- **Key Features**:
  - **Product Serialization**: Converts Anar API data to WooCommerce format
  - **Product Creation**: Creates both simple and variable products
  - **Attribute Management**: Handles product attributes and variations
  - **Product Updates**: Updates existing products with new data
  - **Deprecation Handling**: Manages products removed from Anar
  - **Type Conversion**: Converts simple products to variable when needed

#### **Import Process Flow:**

##### **Phase 1: Data Fetching**
1. **API Data Retrieval**: Fetches product data from Anar API
2. **Raw Data Storage**: Saves API responses in `wp_anar` table (30 products per page)
3. **Data Validation**: Validates API responses and data integrity
4. **Progress Tracking**: Monitors fetch progress and handles errors

##### **Phase 2: Product Creation**
1. **Data Processing**: Processes raw API data through `ProductManager::product_serializer()`
2. **Product Type Detection**: Determines if product should be simple or variable
3. **WooCommerce Product Creation**: Creates products using WooCommerce API
4. **Attribute Setup**: Configures product attributes and variations
5. **Meta Data Storage**: Stores Anar-specific metadata

##### **Phase 3: Product Management**
1. **Image Handling**: Downloads and assigns product images
2. **Category Mapping**: Maps Anar categories to WooCommerce categories
3. **Attribute Mapping**: Maps Anar attributes to WooCommerce attributes
4. **Stock Management**: Sets product stock quantities and status
5. **Price Conversion**: Converts Anar prices to WooCommerce currency

### 2. Synchronization System

The plugin implements three distinct synchronization strategies to keep WooCommerce products updated with Anar service data:

#### **Regular Sync (`Sync.php`)**
- **Purpose**: Scheduled synchronization of all products with recent updates
- **Frequency**: Every 10 minutes via WordPress cron
- **Scope**: Fetches products updated in the last 10 minutes from Anar API
- **Batch Size**: 100 products per page, processes up to 4 minutes execution time
- **Features**:
  - Processes both simple and variable products
  - Updates stock, prices, and metadata
  - Handles product type conversions (simple to variable)
  - Implements locking mechanism to prevent overlapping syncs
  - Provides progress tracking and completion notifications
  - Supports both cron and manual AJAX triggers

#### **Outdated Products Sync (`SyncOutdated.php`)**
- **Purpose**: Efficient strategy for keeping products synchronized with minimal resource usage
- **Frequency**: Every 5 minutes via WordPress cron
- **Scope**: Processes products that haven't been synced in the last 24 hours
- **Batch Size**: 30 products per run (configurable)
- **Capacity**: ~8,640 products per day (30 products × 12 runs/hour × 24 hours)
- **Features**:
  - Optimized SQL queries for better performance
  - Handles deprecated product restoration
  - Simple error handling and authentication checks
  - Predictable execution time and low resource usage
  - Easy to maintain and debug
  - Stops processing on authentication failures

#### **Real-Time Sync (`SyncRealTime.php`)**
- **Purpose**: Immediate synchronization triggered by user interactions
- **Triggers**: Product page visits, cart updates, manual admin actions
- **Scope**: Individual products or cart items
- **Features**:
  - AJAX-based asynchronous updates
  - 10-second cooldown period to prevent excessive API calls
  - Handles simple to variable product conversions
  - Provides admin sync buttons for manual triggers
  - Cart-wide product updates
  - Real-time product ID meta tags for frontend integration
  - Comprehensive error handling and status reporting
  - Authentication failure detection and stopping

### 3. Checkout System (`Checkout.php`)

The checkout system provides a comprehensive solution for handling Anar products during the WooCommerce checkout process, including custom shipping methods, delivery options, and order processing.

#### **Core Features:**

##### **Product Type Detection**
- **Cart Analysis**: Automatically detects standard vs Anar products in cart
- **Session Management**: Stores product type flags for checkout processing
- **Mixed Cart Support**: Handles carts with both standard and Anar products

##### **Custom Shipping Methods**
- **Anar Shipping Display**: Shows custom shipping options for Anar products
- **Location-Based Shipping**: Displays shipping options based on customer location
- **Multi-Warehouse Support**: Handles products from different warehouses
- **Free Shipping Logic**: Implements free shipping conditions based on order value

##### **Delivery Options Management**
- **Dynamic Delivery Options**: Shows available delivery methods based on:
  - Customer location (city/state)
  - Warehouse location
  - Product availability
- **Address Validation**: Validates delivery options when address changes
- **Session Persistence**: Maintains delivery selections across checkout steps
- **Fallback Logic**: Auto-selects first available option if none chosen

### 4. Order Processing System (`Order.php`)

The order processing system handles the complete lifecycle of Anar orders, from creation to tracking and payment management.

#### **Core Features:**

##### **Order Creation & Management**
- **Anar Order Detection**: Identifies orders containing Anar products
- **Order Validation**: Validates required customer information (phone, postcode)
- **API Integration**: Creates orders on Anar system via API
- **Meta Data Storage**: Stores Anar order information in WooCommerce order meta
- **Duplicate Prevention**: Prevents duplicate order creation

##### **Order Data Processing**
- **Product Mapping**: Maps WooCommerce products to Anar variations
- **Address Formatting**: Formats customer address for Anar API
- **Shipping Data**: Processes selected shipping methods and delivery options
- **Order Items**: Converts WooCommerce order items to Anar format

---

## Data Management

### 1. API Integration (`ApiDataHandler.php`)

The API integration system handles all communication with the Anar service, including data fetching, storage, and response processing.

#### **Core Features:**

##### **API Communication**
- **Authentication**: Uses saved Anar token for API requests
- **Request Methods**: Supports both GET and POST requests
- **Error Handling**: Comprehensive error handling with retry logic
- **Timeout Management**: 300-second timeout for API requests
- **Header Management**: Custom headers including WordPress site URL

##### **Data Storage & Retrieval**
- **Database Integration**: Stores API responses in `wp_anar` table
- **Page-based Storage**: Stores responses by page for efficient processing
- **Data Serialization**: Handles serialization/deserialization of API data
- **Response Processing**: Processes and validates API responses

##### **Retry & Error Management**
- **Retry Logic**: Automatic retry with configurable delays
- **Error Detection**: Detects and handles various error conditions
- **Locking Mechanism**: Prevents concurrent API operations
- **Logging**: Comprehensive logging of API operations

### 2. Database Management (`Db.php`)

The database management system provides a robust foundation for storing and managing plugin data, including API responses, job tracking, and system status information.

#### **Core Database Components:**

##### **API Data Storage**
- **Purpose**: Centralized storage for all Anar API responses
- **Features**:
  - **Raw Data Storage**: Stores complete API responses for processing
  - **Page-based Organization**: Organizes data by pages for efficient processing
  - **Processing Status**: Tracks which data has been processed
  - **Data Type Classification**: Categorizes data by type (products, orders, shipments)

##### **Job Management System**
- **Purpose**: Comprehensive tracking of background operations
- **Features**:
  - **Progress Monitoring**: Real-time tracking of job progress
  - **Status Management**: Complete lifecycle management of jobs
  - **Performance Metrics**: Detailed statistics on job execution
  - **Error Tracking**: Comprehensive error logging and retry management
  - **Heartbeat System**: Monitors job activity and detects stuck processes

##### **System Configuration**
- **Purpose**: Stores plugin configuration and system status
- **Features**:
  - **Version Control**: Database schema version management
  - **System Metrics**: Product counts and processing statistics
  - **Error Tracking**: Failed products and system errors
  - **Deprecation Management**: Tracks deprecated products

### 3. Cron Job Management (`CronJobs.php`)

The cron job management system orchestrates all background operations, ensuring efficient scheduling and execution of plugin tasks.

#### **Core Scheduling System:**

##### **Custom Intervals**
- **Purpose**: Provides flexible scheduling intervals for different operations
- **Intervals**:
  - **Every 1 Minute**: Product import processing
  - **Every 2 Minutes**: Quick sync operations
  - **Every 3 Minutes**: Medium-priority tasks
  - **Every 5 Minutes**: Outdated product synchronization
  - **Hourly**: Data updates and notifications
  - **Daily**: Maintenance and cleanup operations

##### **Job Assignment**
- **Product Import**: Handles product creation from API data
- **Data Updates**: Fetches updated information from Anar service
- **Daily Maintenance**: Comprehensive system maintenance tasks
- **Stuck Process Detection**: Monitors and recovers from stuck operations

##### **Performance Optimization**
- **Consolidated Daily Jobs**: Combines multiple daily tasks into single events
- **Conditional Scheduling**: Only schedules jobs when needed
- **Lock Management**: Prevents overlapping operations
- **Resource Management**: Efficient use of system resources

---

## User Interface

### Admin Interface Components

#### **Menu Management**
- **`includes/admin/Menus.php`** - Admin menu management
- **`includes/admin/Tools.php`** - Admin tools functionality
- **`includes/admin/Product_Status_Changer.php`** - Product status management
- **`includes/admin/menu/`** - Admin menu pages and components

#### **Core Components**
- **`includes/core/Activation.php`** - Plugin activation handling
- **`includes/core/Assets.php`** - Asset management
- **`includes/core/ImageDownloader.php`** - Image download functionality
- **`includes/core/Logger.php`** - Logging system
- **`includes/core/SystemStatus.php`** - System status monitoring
- **`includes/core/UsageData.php`** - Usage analytics

#### **Product Management**
- **`includes/product/Edit.php`** - Product editing
- **`includes/product/Front.php`** - Frontend product display
- **`includes/product/Lists.php`** - Product listing
- **`includes/product/PriceSync.php`** - Price synchronization

#### **Wizard System**
- **`includes/wizard/Wizard.php`** - Main wizard class
- **`includes/wizard/ProductManager.php`** - Product management in wizard
- **`includes/wizard/Attributes.php`** - Attribute management
- **`includes/wizard/Category.php`** - Category management

---

## File Structure

### Core Classes and Files

#### **Data Management**
- **`includes/ProductData.php`** - Manages product data operations
- **`includes/AnarProduct.php`** - Core product functionality

#### **Synchronization**
- **`includes/Sync.php`** - Base synchronization functionality
- **`includes/SyncForce.php`** - Force synchronization operations
- **`includes/SyncOutdated.php`** - Handles outdated product synchronization
- **`includes/SyncRealTime.php`** - Real-time synchronization
- **`includes/SyncTools.php`** - Synchronization utilities

#### **Order Management**
- **`includes/Order.php`** - Order processing and management
- **`includes/OrderData.php`** - Order data handling
- **`includes/Orders_List.php`** - Order listing functionality
- **`includes/Payments.php`** - Payment processing

#### **Checkout & Cart**
- **`includes/Checkout.php`** - Checkout process customization
- **`includes/Cart.php`** - Cart functionality

#### **Utilities**
- **`includes/JobManager.php`** - Background job management
- **`includes/Gallery.php`** - Gallery functionality
- **`includes/Notifications.php`** - Notification system
- **`includes/Multi_Seller.php`** - Multi-seller functionality

### Database Structure
- **Custom Table**: `wp_anar` - Stores raw API responses from Anar service
- **WordPress Options**: Various plugin settings and configuration
- **WooCommerce Integration**: Standard WooCommerce product and order tables

### Asset Structure
- **`assets/css/`** - Stylesheets for admin and public interfaces
- **`assets/js/`** - JavaScript files for functionality
- **`assets/images/`** - Plugin images and icons
- **`assets/fonts/`** - Custom fonts (KalamehWeb)

---

## Integration Points

### WordPress Integration
- **Database Abstraction**: Uses WordPress database layer
- **Options System**: Integrates with WordPress options for configuration
- **Cron System**: Supports WordPress cron for background processing
- **Logging System**: Integrates with plugin logging infrastructure

### WooCommerce Integration
- **Product System**: Full integration with WooCommerce product system
- **Order System**: Complete integration with WooCommerce order system
- **Checkout System**: Custom checkout modifications for Anar products
- **Shipping System**: Custom shipping methods for Anar orders

### Anar Service Integration
- **API Communication**: Complete integration with Anar product API
- **Authentication**: Uses Anar token for API authentication
- **Data Synchronization**: Keeps products synchronized with Anar service
- **Order Processing**: Creates orders on Anar system from WooCommerce orders

### Performance Features
- **Optimized Queries**: Efficient database operations
- **Indexed Storage**: Fast data retrieval and updates
- **Memory Management**: Efficient handling of large datasets
- **Resource Optimization**: Minimal impact on system performance

---

This documentation provides a comprehensive overview of the WP-Anar plugin's architecture, functionality, and integration points, making it easier for developers to understand and work with the system.
