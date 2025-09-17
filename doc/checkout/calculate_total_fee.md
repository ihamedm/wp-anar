# Shipping Fee Calculation System

## Overview

The shipping fee calculation system in the Anar plugin handles dynamic shipping cost calculations based on customer address changes during checkout. This system ensures that shipping fees are always calculated using the current address data, preventing the "1 step behind" issue where totals would reflect previous address information.

## Core Components

### 1. Main Calculation Method: `calculate_total_shipping_fee()`

**Location:** `includes/Checkout.php`

**Purpose:** Calculates the total shipping fee for all Anar products in the cart based on selected delivery options.

**Key Features:**
- Address change detection and validation
- PWS plugin integration for city name rendering
- Smart delivery option selection
- Fee calculation and cart integration

**Process Flow:**
1. Get current billing address data
2. Check for address changes and validate delivery selections
3. Process each cart item with Anar products
4. Calculate total shipping fee based on selected options
5. Add fee to WooCommerce cart

### 2. Address Change Detection: `check_and_clear_delivery_selections_on_address_change()`

**Purpose:** Detects when customer address changes and validates existing delivery selections.

**How it works:**
- Compares current address with stored address in session
- If address changed, validates all delivery selections
- Clears invalid selections for the new address
- Updates stored address for future comparisons

### 3. Delivery Option Validation: `validate_delivery_selections_for_current_address()`

**Purpose:** Validates each selected delivery option against the current address.

**Validation Logic:**
- Gets current city and state (with PWS plugin support)
- Checks if each selected delivery option is valid for current address
- Clears invalid selections from session
- Allows system to auto-select correct options

### 4. Smart Option Selection: `get_selected_shipping_option()`

**Purpose:** Gets the selected delivery option with intelligent fallback mechanisms.

**Fallback Chain:**
1. **Session Data** - Check if option is stored in session
2. **POST Data** - Check if option is in current POST request
3. **Auto-Selection** - Automatically select first available option

### 5. Auto-Selection Logic: `get_first_available_delivery_option()`

**Purpose:** Automatically selects the first available delivery option for a shipment reference.

**Selection Criteria:**
- Based on current address (city/state)
- Considers shipment types (insideShopCity, insideShopState, otherStates)
- Only selects active delivery options
- Uses PWS plugin for proper city name comparison

### 6. POST Data Protection: `save_checkout_delivery_choice_on_session_better()`

**Purpose:** Saves delivery choices from POST data while protecting against invalid selections.

**Protection Mechanism:**
- Validates each delivery option against current address before saving
- Only saves valid options to session
- Rejects invalid options to prevent overwriting corrected selections
- Allows system to maintain correct auto-selected options

## Address Handling

### PWS Plugin Integration

The system integrates with the PWS (Persian WooCommerce Shipping) plugin to handle city name rendering:

```php
// Try to get city name from PWS plugin
$current_city_name = $current_city; // Default to original value
if ( function_exists( 'PWS' ) && method_exists( PWS(), 'get_city' ) ) {
    $current_city_name = PWS()->get_city( $current_city );
}
```

**Benefits:**
- Converts city codes to readable city names
- Works with custom dropdown implementations
- Maintains backward compatibility with sites without PWS
- Ensures proper city name comparisons for shipping calculations

### Address Change Detection

The system tracks address changes using session storage:

```php
// Get stored address from session
$stored_city = WC()->session->get('anar_last_city');
$stored_state = WC()->session->get('anar_last_state');

// Compare with current address
if ($stored_city !== $current_city || $stored_state !== $current_state) {
    // Address changed - validate and update selections
}
```

## Delivery Option Validation

### Validation Logic

The system validates delivery options based on shipment types and customer location:

```php
// Determine available shipment types for current address
if ($current_state_name === $shipmentsReferenceState && 
    $current_city_name === $shipmentsReferenceCity) {
    $shipment_types_to_display = ['insideShopCity'];
} elseif ($current_state_name === $shipmentsReferenceState) {
    $shipment_types_to_display = ['insideShopState'];
} else {
    $shipment_types_to_display = ['otherStates'];
}

// Check if selected option is valid
$is_valid = $shipment->type === 'allCities' || 
           in_array($shipment->type, $shipment_types_to_display);
```

### Shipment Types

- **`allCities`** - Available for all addresses
- **`insideShopCity`** - Available only for same city as shop
- **`insideShopState`** - Available for same state as shop
- **`otherStates`** - Available for different states

## Fee Calculation Process

### Step-by-Step Process

1. **Address Validation**
   - Get current billing address
   - Check for address changes
   - Validate existing delivery selections

2. **Option Selection**
   - Get selected delivery option from session
   - Fallback to POST data if needed
   - Auto-select first available option if none selected

3. **Fee Calculation**
   - Process each cart item with Anar products
   - Get shipment data for each product
   - Calculate fee based on selected delivery option
   - Convert price to WooCommerce currency

4. **Cart Integration**
   - Add calculated fee to WooCommerce cart
   - Use appropriate label based on product types
   - Handle standard vs. Anar-only product scenarios

### Fee Labels

- **Mixed Products:** "مجموع حمل نقل سایر محصولات" (Total shipping for other products)
- **Anar Only:** "مجموع حمل نقل" (Total shipping)

## Error Handling

### Graceful Degradation

The system handles various error scenarios gracefully:

- **No Session:** Returns early if WooCommerce session unavailable
- **Empty Cart:** Returns early if no cart items
- **No Shipment Data:** Skips products without shipment data
- **Invalid Options:** Clears invalid selections and auto-selects correct ones
- **PWS Unavailable:** Falls back to original city codes

### Validation Failures

When validation fails:
- Invalid delivery options are cleared from session
- System automatically selects first available option
- Calculation continues with valid options
- No errors are thrown to user

## Performance Considerations

### Optimization Strategies

1. **Session Caching** - Stores address and selections in session
2. **Conditional Validation** - Only validates when address changes
3. **Efficient Lookups** - Uses direct array access for shipment data
4. **Minimal Database Queries** - Leverages existing cart and session data

### Memory Usage

- Minimal memory footprint
- No unnecessary data storage
- Efficient array operations
- Clean session management

## Integration Points

### WooCommerce Hooks

The system integrates with WooCommerce through several hooks:

- **`woocommerce_cart_calculate_fees`** - Main calculation hook
- **`woocommerce_review_order_before_shipping`** - Display hook
- **`woocommerce_checkout_update_order_review`** - Session update hook

### Plugin Dependencies

- **PWS Plugin** - Optional integration for city name rendering
- **WooCommerce** - Core dependency for cart and checkout functionality
- **Anar ProductData** - Internal dependency for shipment data

## Troubleshooting

### Common Issues

1. **"1 Step Behind" Problem**
   - **Cause:** Old delivery selections not cleared on address change
   - **Solution:** Address change detection and validation system

2. **Wrong City Names**
   - **Cause:** PWS plugin not integrated
   - **Solution:** PWS plugin integration for city name rendering

3. **Invalid Delivery Options**
   - **Cause:** POST data overwriting corrected selections
   - **Solution:** POST data validation before saving

4. **Missing Shipping Fees**
   - **Cause:** No valid delivery options selected
   - **Solution:** Auto-selection fallback mechanism

### Debug Information

To debug issues, enable debug logging in WordPress:

```php
// Enable debug mode
define('ANAR_DEBUG', true);

// Check logs in WordPress debug log
// Look for 'calculated_total_shipping_fee' entries
```

## Future Enhancements

### Potential Improvements

1. **Caching** - Cache shipment data for better performance
2. **Real-time Updates** - AJAX-based address change handling
3. **Multiple Addresses** - Support for different shipping addresses
4. **Advanced Validation** - More sophisticated delivery option validation
5. **Analytics** - Track shipping calculation performance

### API Extensions

- Webhook support for external shipping providers
- REST API endpoints for shipping calculations
- Third-party plugin integration hooks
- Custom validation rule system

## Conclusion

The shipping fee calculation system provides a robust, efficient, and user-friendly solution for dynamic shipping cost calculations. It handles address changes gracefully, integrates with external plugins, and maintains data integrity throughout the checkout process.

The system's modular design allows for easy maintenance and future enhancements while ensuring backward compatibility and optimal performance.
