# Anar Delivery Options Display System

## Overview

The Anar delivery options display system is responsible for rendering shipping method options for Anar products during the WooCommerce checkout process. This system dynamically shows available delivery options based on the customer's current address and the product's shipment data.

## Core Components

### 1. Main Display Method: `display_anar_products_shipping()`

**Location:** `includes/Checkout.php`

**Hook:** `woocommerce_review_order_before_shipping` (priority 99)

**Purpose:** Displays Anar shipping methods in the checkout review section before standard shipping options.

**Key Features:**
- Address-based delivery option filtering
- PWS plugin integration for city name rendering
- Package grouping by shipment reference
- Dynamic option generation with pricing
- Free shipping condition handling

### 2. Delivery Option Field Generator: `generate_delivery_option_field()`

**Purpose:** Generates custom radio button fields for delivery options with enhanced markup.

**Features:**
- Custom HTML structure for better styling
- Radio button grouping by shipment reference
- Price and estimated time display
- Default selection handling
- Unique ID generation for accessibility

## Display Process Flow

### 1. Address Data Collection

```php
// Get current billing address
$billing_country = WC()->customer->get_billing_country();
$billing_state = WC()->customer->get_billing_state();
$billing_state_name = WC()->countries->states[$billing_country][$billing_state] ?? '';
$billing_city = WC()->customer->get_billing_city();

// Try to get city name from PWS plugin
$billing_city_name = $billing_city; // Default to original value
if ( function_exists( 'PWS' ) && method_exists( PWS(), 'get_city' ) ) {
    $billing_city_name = PWS()->get_city( $billing_city );
}
```

### 2. Cart Item Processing

The system processes each cart item to identify Anar products:

```php
foreach ($cart_items as $cart_item_key => $values) {
    $_product = wc_get_product($values['data']->get_id());
    $product_parent_id = $_product->get_parent_id();

    // Check if product is an Anar product
    $anar_meta = $product_parent_id == 0
        ? get_post_meta($_product->get_id(), self::ANAR_PRODUCT_META, true)
        : get_post_meta($product_parent_id, self::ANAR_PRODUCT_META, true);

    if ($anar_meta) {
        // Process Anar product shipping data
    }
}
```

### 3. Shipment Data Retrieval

For each Anar product, the system retrieves shipment data:

```php
$anar_shipment_data = ProductData::get_anar_product_shipments($product_id);
```

**Shipment Data Structure:**
- `shipmentsReferenceId` - Unique identifier for the shipment reference
- `shipmentsReferenceState` - State where the shipment originates
- `shipmentsReferenceCity` - City where the shipment originates
- `shipments` - Array of available shipment types
- `delivery` - Array of delivery options for each shipment

### 4. Address-Based Filtering

The system determines which delivery options to display based on customer location:

```php
// Determine shipment types to display based on customer location
if ($billing_state_name === $anar_shipment_data['shipmentsReferenceState'] &&
    $billing_city_name === $anar_shipment_data['shipmentsReferenceCity']) {
    $shipment_types_to_display = ['insideShopCity'];
} elseif ($billing_state_name === $anar_shipment_data['shipmentsReferenceState']) {
    $shipment_types_to_display = ['insideShopState'];
} else {
    $shipment_types_to_display = ['otherStates'];
}
```

### 5. Delivery Option Processing

For each valid shipment type, the system processes delivery options:

```php
foreach ($anar_shipment_data['shipments'] as $shipment) {
    if ($shipment->type == 'allCities' && $shipment->active) {
        $shipment_deliveries = $shipment->delivery;
    } elseif (in_array($shipment->type, $shipment_types_to_display) && $shipment->active) {
        $shipment_deliveries = $shipment->delivery;
    }

    foreach ($shipment_deliveries as $delivery) {
        if ($delivery->active) {
            // Process active delivery option
        }
    }
}
```

## Package Grouping System

### Shipment Reference Grouping

Products are grouped by their `shipmentsReferenceId` to create logical shipping packages:

```php
// Group products by shipment reference
if (isset($ship[$shipmentsReferenceId])) {
    $ship[$shipmentsReferenceId]['names'][] = $_product->get_id();
} else {
    $ship[$shipmentsReferenceId] = [
        'delivery' => [],
        'names' => [],
    ];
    $ship[$shipmentsReferenceId]['names'][] = $_product->get_id();
}
```

### Package Display Structure

Each package is displayed with:

1. **Package Header** - Shows package number and item count
2. **Product List** - Visual list of products in the package
3. **Delivery Options** - Radio buttons for shipping methods

## Free Shipping Conditions

### Free Shipping Logic

The system checks for free shipping conditions based on package total:

```php
// Check free shipping condition
if (isset($delivery['freeCondition']) && isset($delivery['freeCondition']['purchasesPrice'])) {
    if ($package_total >= $delivery['freeCondition']['purchasesPrice']) {
        $is_free_shipping = true;
        $delivery['price'] = 0;
        $delivery['estimatedTime'] = 'ارسال رایگان';
    }
}
```

### Free Shipping Display

When free shipping conditions are met:
- Price is set to 0
- Estimated time shows "ارسال رایگان" (Free Shipping)
- Option is marked as free shipping

## HTML Structure Generation

### Package Container

```html
<tr class="anar-shipments-package-row">
    <td colspan="2">
        <div class="anar-shipments-package-content">
            <!-- Package content -->
        </div>
    </td>
</tr>
```

### Package Header

```html
<div class="package-title">
    <div class="icon">
        <!-- SVG truck icon -->
    </div>
    <div class="text">
        <div>مرسوله 1 <span class="chip">2 کالا</span></div>
    </div>
</div>
```

### Product List

```html
<ul class="package-items">
    <li>
        <a class="awca-tooltip-on" href="product-url" title="Product Title">
            <img src="product-image" alt="Product Title">
        </a>
    </li>
</ul>
```

### Delivery Options

```html
<div class="form-row form-row-wide update_totals_on_change">
    <div class="anar-delivery-option selected">
        <input type="radio" class="input-radio" 
               data-input-group="shipment-reference-id" 
               value="delivery-id" 
               name="anar_delivery_option_shipment-reference-id" 
               id="unique-id" checked>
        <label for="unique-id" class="radio">
            <span class="label">Delivery Method Name</span>
            <span class="estimated-time">Estimated Time</span>
            <span class="price">Price</span>
        </label>
    </div>
</div>
```

## CSS Classes and Styling

### Package Classes

- `.anar-shipments-package-row` - Main package row
- `.anar-shipments-package-content` - Package content container
- `.anar-package-items-list` - Product list container
- `.anar-delivery-options-area` - Delivery options container

### Delivery Option Classes

- `.anar-delivery-option` - Individual delivery option
- `.anar-delivery-option.selected` - Selected delivery option
- `.package-title` - Package header
- `.package-items` - Product list
- `.chip` - Item count badge

### Responsive Design

The system includes responsive design considerations:

```php
// Add vertical view class for packages with many items
<div class="anar-shipments-package-content <?php echo count($product_uniques) > 2 ? 'vertical-view' : '';?>">
```

## JavaScript Integration

### Delivery Option Selection

The system works with JavaScript to handle option selection:

```javascript
jQuery('.anar-delivery-option input[type="radio"]').on('change', function() {
    jQuery('.anar-delivery-option').removeClass('selected');
    jQuery(this).closest('.anar-delivery-option').addClass('selected');
});
```

### Form Validation

JavaScript ensures at least one delivery option is selected:

```javascript
function validateRadioSelectionOnOrder() {
    $('#place_order').on('click', function(e) {
        var allChecked = true;
        
        $('input[type="radio"][data-input-group]').each(function() {
            var inputGroup = $(this).data('input-group');
            var radios = $('input[data-input-group="' + inputGroup + '"]');
            
            if (radios.filter(':checked').length === 0) {
                allChecked = false;
                return false;
            }
        });
        
        if (!allChecked) {
            e.preventDefault();
            alert('Please select a delivery option before proceeding.');
        }
    });
}
```

## Session Management

### Delivery Option Storage

Selected delivery options are stored in the WooCommerce session:

```php
// Get chosen delivery option from session
$chosen = WC()->session->get('anar_delivery_option_' . $key);
$chosen = empty($chosen) ? WC()->checkout->get_value('anar_delivery_option_' . $key) : $chosen;

// Set default if none chosen
if (empty($chosen) && !empty($names)) {
    $chosen = key($names); // Get the first key
}
```

### Session Update Handling

The system updates session data when delivery options change:

```php
public function save_checkout_delivery_choice_on_session_better($posted_data) {
    parse_str($posted_data, $output);
    foreach ($output as $key => $value) {
        if (strpos($key, 'anar_delivery_option_') === 0) {
            // Validate and save delivery option
        }
    }
}
```

## Error Handling

### Missing Shipment Data

When products don't have shipment data:

```php
if (empty($anar_shipment_data) || count($anar_shipment_data) === 0) {
    // Remove product from cart
    WC()->cart->remove_cart_item($cart_item_key);
    $product_title = $_product->get_name();
    wc_add_notice(sprintf(__('محصول "%s" به دلیل عدم وجود روش ارسال از سبد خرید شما حذف شد.', 'wp-anar'), $product_title), 'error');
}
```

### Empty Address Handling

When address fields are empty:

```php
if (empty($billing_city) || empty($billing_state_name)) {
    $billing_state_name = 'نامعلوم';
    $billing_city = 'نامعلوم';
}
```

## Performance Considerations

### Optimization Strategies

1. **Conditional Processing** - Only process Anar products
2. **Efficient Grouping** - Group products by shipment reference
3. **Minimal Database Queries** - Use existing cart data
4. **Cached Shipment Data** - Leverage ProductData caching

### Memory Management

- Clean array structures
- Efficient string operations
- Minimal object creation
- Proper variable scoping

## Integration Points

### WooCommerce Integration

- **Checkout Review Section** - Displays before standard shipping
- **Form Field Generation** - Custom radio button fields
- **Session Management** - Integrates with WooCommerce sessions
- **Cart Integration** - Works with existing cart system

### External Plugin Support

- **PWS Plugin** - City name rendering
- **Tooltip Plugins** - Product image tooltips
- **Custom Themes** - Responsive design support

## Troubleshooting

### Common Display Issues

1. **Missing Delivery Options**
   - Check if products have shipment data
   - Verify address is properly set
   - Ensure shipment types match customer location

2. **Incorrect Pricing**
   - Verify free shipping conditions
   - Check currency conversion
   - Validate delivery option data

3. **Styling Issues**
   - Check CSS class availability
   - Verify theme compatibility
   - Ensure proper HTML structure

### Debug Information

Enable debug mode to troubleshoot:

```php
// Check if Anar products are detected
if ($anar_meta) {
    // Log product processing
}

// Check shipment data availability
if ($anar_shipment_data && count($anar_shipment_data) > 0) {
    // Log shipment processing
}
```

## Future Enhancements

### Potential Improvements

1. **Real-time Updates** - AJAX-based option updates
2. **Advanced Filtering** - More sophisticated option filtering
3. **Custom Styling** - Enhanced visual design options
4. **Accessibility** - Improved screen reader support
5. **Mobile Optimization** - Better mobile experience

### API Extensions

- REST API endpoints for delivery options
- Webhook support for external integrations
- Custom validation rules
- Third-party plugin hooks

## Conclusion

The Anar delivery options display system provides a comprehensive solution for showing shipping methods during checkout. It handles address-based filtering, package grouping, free shipping conditions, and integrates seamlessly with WooCommerce's checkout process.

The system's modular design allows for easy customization and future enhancements while maintaining compatibility with various themes and plugins.
