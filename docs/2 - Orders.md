# Anar Order Flow Journey

## Overview

This document describes the complete journey of an order through the Anar dropshipping system, from cart to order creation in Anar platform. Understanding this flow is essential for working with the checkout and order management features.

**Key Classes:**
- `Checkout` - Base checkout logic and multi-package handling
- `CheckoutDropShipping` - Frontend shipping display and delivery selection
- `Order` - Admin UI for order display and pre-order modal
- `OrderManager` - Order creation in Anar system via API

---

## The Complete Journey

### 🛒 **Phase 1: Cart Analysis** (Before Checkout)
**Class: `Checkout`**

When customer views cart or begins checkout:

1. **Product Type Detection** (`check_for_cart_products_types()`)
   - Scans cart items
   - Identifies: Anar products vs standard WooCommerce products
   - Checks: Ship-to-stock eligibility
   - Stores results in WC session

2. **Multi-Package Analysis** (`get_cart_packages_info()`)
   - Groups Anar products by `shipmentsReferenceId` (warehouse/origin)
   - Counts number of separate packages
   - Used for: alerts, fees, shipping multipliers

3. **Optional: Multi-Package Features** (when Anar shipping disabled)
   - `display_multiple_packages_alert()` - Shows "shipping from multiple warehouses" notice
   - `add_multiple_package_fee()` - Adds extra fee per package
   - `multiply_shipping_for_multiple_packages()` - Applies multiplier to standard rates

**Result:** System knows what products are in cart and how they'll be grouped for shipment.

---

### 🚚 **Phase 2: Shipping Display** (Checkout Page)
**Class: `CheckoutDropShipping`**

Customer is on checkout page:

1. **Display Anar Shipping Options** (`display_anar_products_shipping()`)
   - Gets customer's billing city and state
   - Checks for address changes, clears invalid selections
   - Fetches available shipping options from parent `Checkout::get_available_shipping_options()`
   - Shows packages with delivery method radio buttons
   - Displays multi-origin notice if multiple packages

2. **Customer Selects Delivery Methods**
   - One selection per package (shipmentsReferenceId)
   - Selections saved to session (`save_checkout_delivery_choice_on_session_better()`)
   - Validated against current address

3. **Calculate Shipping Fee** (`calculate_total_shipping_fee()`)
   - Sums up selected delivery prices
   - Checks free shipping conditions per package
   - Adds as WooCommerce fee to cart totals

4. **Handle Standard Products** (if mixed cart)
   - Shows standard WooCommerce shipping methods
   - For Anar-only carts: creates hidden "free shipping" to satisfy WooCommerce

**Result:** Customer sees delivery options, makes selections, sees total shipping cost.

---

### ✅ **Phase 3: Order Creation** (Place Order)
**Class: `CheckoutDropShipping` (Frontend) & `Checkout` (Base)**

Customer clicks "Place Order":

1. **Checkout Validation** (`checkout_validations()`)
   - Verifies shipping method selected
   - Ensures address is complete

2. **Order Meta Saved** (during `woocommerce_checkout_create_order`)
   - `Checkout::update_order_meta()` - Marks as Anar order (`_is_anar_order`)
   - `CheckoutDropShipping::save_anar_data_on_order()` - Saves delivery selections (`_anar_shipping_data`)

3. **Fallback for Admin/Programmatic Orders**
   - `Checkout::handle_new_order()` - Catches orders created outside frontend
   - Scans order items for Anar products
   - Applies same meta data

**Result:** WooCommerce order created with Anar metadata attached.

---

### 🎯 **Phase 4: Admin Review** (Order Edit Screen)
**Class: `Order`**

Admin opens the order in WooCommerce admin:

1. **Meta Box Display** (`anar_order_meta_box()`)
   - Checks if order contains Anar products
   - Shows meta box in sidebar

2. **Before Anar Order Creation:**
   - `anar_order_meta_box_html()` - Shows "Create in Anar" button
   - `get_packages_shipping_data()` - Displays saved package info and selected delivery methods
   - `canShipToResellerStock()` - Shows if ship-to-stock is available

3. **Pre-Order Modal** (`preorder_modal()`)
   - Injected in admin footer
   - Shows:
     - Customer address (`get_order_address_ajax`)
     - Shipping options for retail orders
     - Stock address form for wholesale orders
     - Order type toggle (retail/wholesale)

4. **Admin Actions:**
   - Click "Create Order in Anar"
   - Select order type (retail = ship to customer, wholesale = ship to stock)
   - For retail: Verify/adjust shipping selections
   - For wholesale: Enter/verify stock address

**Result:** Admin prepares order for Anar system creation.

---

### 🚀 **Phase 5: Anar Order Creation** (API Call)
**Class: `OrderManager`**

Admin clicks "Confirm and Create Order":

1. **AJAX Request** (`handle_ajax_order_creation()`)
   
2. **Validation:**
   - Order hasn't been created in Anar already (check `_anar_order_group_id`)
   - Customer phone not empty
   - Postcode exactly 10 digits
   
3. **Data Collection:**
   - `get_anar_variation_id()` - Extract Anar product IDs from order items
   - `prepare_anar_shipments()` - Format shipment data from order meta
   - `prepare_address_data()` - Route to customer or stock address preparation
   - `format_address_for_anar()` - Clean address for API

4. **API Call:**
   - POST to `https://api.anar360.com/wp/orders/`
   - Payload includes: type, items, address, shipments, externalId

5. **Save Response:**
   - Store `_anar_order_data` - Array of order IDs and numbers
   - Store `_anar_order_group_id` - Main group identifier
   - Add order note with group ID
   - `save_raw_order_data()` - Debug data

**Result:** Order successfully created in Anar system, tracking data saved to WooCommerce.

---

### 📊 **Phase 6: Order Tracking** (After Creation)
**Class: `Order`**

Admin views order after Anar creation:

1. **Live Status Display** (`fetch_order_details_ajax()`)
   - Fetches current status from Anar API
   - Shows for each package:
     - Order number
     - Product items
     - Pricing (items, reseller share, delivery, payable)
     - Status (unpaid, processing, shipped, etc.)
     - Delivery method and estimated time
     - Tracking number
     - Creation date

2. **Payment Handling:**
   - If unpaid: Shows payment link to Anar platform
   - If paid: Shows status tracking link

**Result:** Admin can monitor order status and payment in real-time.

---

## Key Data Flow

```
Customer Cart
    ↓
[Checkout] - Analyze products, group by warehouse
    ↓
[CheckoutDropShipping] - Display shipping options, collect selections
    ↓
WooCommerce Order Created + Anar Meta
    ↓
[Order] - Display admin UI, pre-order modal
    ↓
[OrderManager] - Create in Anar via API
    ↓
[Order] - Display tracking and status
```

---

## Important Meta Keys

| Meta Key | Purpose | Set By |
|----------|---------|--------|
| `_is_anar_order` | Marks order as containing Anar products | Checkout |
| `anar_can_ship_stock` | Order eligible for ship-to-stock | Checkout |
| `_anar_shipping_data` | Customer's delivery selections | CheckoutDropShipping |
| `_anar_order_data` | Anar order IDs and numbers | OrderManager |
| `_anar_order_group_id` | Main Anar group identifier | OrderManager |
| `_anar_raw_create_data` | Debug: API request payload | OrderManager |

---

## Session Data

| Session Key | Purpose | Used By |
|-------------|---------|---------|
| `has_standard_product` | Cart has non-Anar products | Checkout → All |
| `has_anar_product` | Cart has Anar products | Checkout → All |
| `anar_can_ship_stock` | Cart eligible for ship-to-stock | Checkout → Order |
| `anar_delivery_option_{id}` | Selected delivery per package | CheckoutDropShipping |
| `anar_shipment_data` | Raw shipment data from API | CheckoutDropShipping |
| `anar_last_city` | Last known city for change detection | CheckoutDropShipping |
| `anar_last_state` | Last known state for change detection | CheckoutDropShipping |

---

## Order Types

### Retail Order (Ship to Customer)
- Default order type
- Ships to customer's billing address
- Requires delivery method selection
- Uses customer's phone and postcode
- Can have multiple packages from different warehouses

### Wholesale Order (Ship to Stock)
- Products must have `_can_ship_to_stock` meta
- Ships to reseller's stock address in Tehran
- No delivery method selection needed
- Uses stock address from wp_options `_anar_user_stock_address`
- Currently limited to Tehran only

---

## Multi-Package Handling

When cart contains products from different warehouses:

**Without Anar Shipping (standard WooCommerce rates):**
- Option 1: Show alert about multiple origins
- Option 2: Add fixed fee per extra package
- Option 3: Multiply standard shipping rate by package count
- All controlled via admin settings

**With Anar Shipping (dropshipping):**
- Each package shows separately
- Customer selects delivery method per package
- Each package may have different delivery methods/costs
- Total shipping = sum of all package selections

---

## Developer Tips

### Adding New Features

**Need to modify checkout display?**
→ Start with `CheckoutDropShipping::display_anar_products_shipping()`

**Need to change shipping calculations?**
→ Look in `Checkout` base class methods

**Need to modify order creation?**
→ Work with `OrderManager::handle_ajax_order_creation()`

**Need to change admin UI?**
→ Edit `Order` class methods

### Debugging

**Enable debug mode:**
```php
define('ANAR_DEBUG', true);
```

**Check these logs:**
- WooCommerce → Status → Logs → `checkout-*.log`
- Order meta: `_anar_raw_create_data` (visible in meta box when debug on)

**Common issues:**
- Missing shipment data → Check product has `_anar_products` meta
- Address validation fails → Check postcode format (10 digits)
- Delivery options not showing → Verify city/state names match API

---

## Code References

For detailed method documentation, see PHPDoc comments in each class:

- **`includes/Checkout.php`** - Base checkout and multi-package logic
- **`includes/CheckoutDropShipping.php`** - Frontend shipping display
- **`includes/Order.php`** - Admin UI and meta box
- **`includes/OrderManager.php`** - API integration and order creation

Each method includes:
- Purpose and process flow
- Parameter descriptions
- Return value documentation
- Hook information
- Cross-references to related methods

---

## Architecture Patterns

**Singleton Pattern:**
All 4 classes use singleton pattern via `get_instance()`

**Hook-Based Architecture:**
WordPress/WooCommerce hooks manage the flow:
- `woocommerce_before_calculate_totals` - Product type detection
- `woocommerce_review_order_before_shipping` - Display shipping options
- `woocommerce_checkout_create_order` - Save order meta
- `wp_ajax_*` - Admin AJAX handlers

**Inheritance:**
```
Checkout (Parent)
    ↓
CheckoutDropShipping (Child)
```
Child overrides methods for frontend-specific behavior

**Separation of Concerns:**
- `Checkout` - Core logic (reusable)
- `CheckoutDropShipping` - Frontend display
- `Order` - Admin display
- `OrderManager` - API communication

---

## Next Steps for Developers

1. **Read this document** - Understand the big picture ✅
2. **Review class PHPDoc** - See detailed method documentation
3. **Trace a real order** - Create test order and follow the flow
4. **Check session/meta data** - Use browser dev tools and WP debug
5. **Refer to method comments** - Each method explains its role in detail

**Questions?** Check method-level PHPDoc comments for specifics, or review related methods via `@see` tags.

